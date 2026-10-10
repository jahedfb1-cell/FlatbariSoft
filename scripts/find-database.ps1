<#
.SYNOPSIS
  Finds every MySQL/MariaDB install on this PC that has the Green Valley
  database, shows which one is running, which one holds the real (newest)
  data, and saves the answer to DATABASE-STATUS.txt.

.DESCRIPTION
  Works even when MySQL is NOT running - it reads the data folders on disk.
  Built after a real incident: C:\xampp and C:\xampp2 both use port 3306;
  the app was pointed at the wrong (old, empty) one while the real data
  sat untouched in the other.

.PARAMETER Start
  If no MySQL is running, start the install that holds the newest data.
  Never stops or kills a running server - if the wrong one is running it
  tells you what to do instead.
#>
param([switch]$Start)

$ErrorActionPreference = 'Continue'
$projectRoot = Split-Path -Parent $PSScriptRoot
$report = New-Object System.Collections.Generic.List[string]

function Say($text) { $report.Add($text); Write-Host $text }

function Get-EnvValue($key, $default) {
    $envFile = Join-Path $projectRoot '.env'
    if (Test-Path $envFile) {
        $m = Select-String -Path $envFile -Pattern "^\s*$key\s*=\s*(.*)$" | Select-Object -First 1
        if ($m) { return $m.Matches[0].Groups[1].Value.Trim().Trim('"') }
    }
    return $default
}

function Normalize-Path($p) {
    if (-not $p) { return $null }
    return ([System.IO.Path]::GetFullPath(($p -replace '/', '\'))).TrimEnd('\').ToLower()
}

$dbName = Get-EnvValue 'DB_DATABASE' 'green_valley_bms'
$dbPort = Get-EnvValue 'DB_PORT' '3306'

Say "Green Valley BMS - where is the database?  ($(Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))"
Say ('=' * 70)
Say "App is configured for database '$dbName' on port $dbPort (.env)"
Say ''

# ---- 1. Which MySQL servers are running right now? ---------------------------
$running = @()
$procs = Get-CimInstance Win32_Process -Filter "Name='mysqld.exe' OR Name='mariadbd.exe'" -ErrorAction SilentlyContinue
foreach ($p in $procs) {
    $cmd = $p.CommandLine
    $installDir = Split-Path (Split-Path $p.ExecutablePath -Parent) -Parent
    $dataDir = $null
    if ($cmd -match '--datadir=("?)([^"\s]+)\1') { $dataDir = $Matches[2] }
    if (-not $dataDir -and $cmd -match '--defaults-file=("?)([^"\s]+)\1') {
        $ini = $Matches[2] -replace '/', '\'
        if (Test-Path $ini) {
            $line = Select-String -Path $ini -Pattern '^\s*datadir\s*=\s*"?([^"\r\n]+)"?' | Select-Object -First 1
            if ($line) { $dataDir = $line.Matches[0].Groups[1].Value.Trim() }
        }
    }
    if (-not $dataDir) { $dataDir = Join-Path $installDir 'data' }
    $ports = @(Get-NetTCPConnection -OwningProcess $p.ProcessId -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty LocalPort -Unique) -join ','
    $running += [pscustomobject]@{
        Pid = $p.ProcessId; Exe = $p.ExecutablePath; Install = $installDir
        DataDir = Normalize-Path $dataDir; Ports = $ports
    }
}

Say 'RUNNING MySQL servers:'
if ($running.Count -eq 0) { Say '  (none - MySQL is not running)' }
foreach ($r in $running) { Say "  PID $($r.Pid)  port $($r.Ports)  $($r.Exe)"; Say "           data dir: $($r.DataDir)" }
Say ''

# ---- 2. Find every data folder on disk that contains the database -----------------
$namePattern = '^(xampp|wamp|laragon|mysql|mariadb|lamp|uwamp|ampps)'
$roots = @()
foreach ($d in (Get-PSDrive -PSProvider FileSystem | Where-Object { $_.Root -match '^[A-Za-z]:\\$' })) {
    $roots += Get-ChildItem -Path $d.Root -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match $namePattern } | ForEach-Object { $_.FullName }
    foreach ($sub in 'Program Files\MySQL', 'Program Files\MariaDB*', 'Program Files (x86)\MySQL', 'ProgramData\MySQL') {
        $roots += Get-Item -Path (Join-Path $d.Root $sub) -ErrorAction SilentlyContinue | ForEach-Object { $_.FullName }
    }
}
foreach ($r in $running) { $roots += $r.Install }
$roots = $roots | Sort-Object -Unique

$found = @()
foreach ($root in $roots) {
    $dirs = Get-ChildItem -Path $root -Directory -Recurse -Depth 5 -Filter $dbName -ErrorAction SilentlyContinue
    foreach ($dir in $dirs) {
        $isDb = (Test-Path (Join-Path $dir.FullName 'migrations.ibd')) -or (Test-Path (Join-Path $dir.FullName 'db.opt'))
        if (-not $isDb) { continue }
        $dataDir = Normalize-Path $dir.Parent.FullName
        if ($found | Where-Object { $_.DataDir -eq $dataDir }) { continue }
        $files = Get-ChildItem -Path $dir.FullName -File -ErrorAction SilentlyContinue
        $flats = Get-Item (Join-Path $dir.FullName 'flats.ibd') -ErrorAction SilentlyContinue
        $newest = ($files | Measure-Object LastWriteTime -Maximum).Maximum
        # Migrations/seeders/startup touch lookup tables, so "newest file" is noisy. When the
        # BUSINESS tables were last written is the honest sign of which copy people really used.
        $bizTables = 'flats','persons','flat_parties','bills','bill_items','payments','expenses','journal_entries','journal_lines','billing_periods'
        $bizFiles = $files | Where-Object { $_.Extension -eq '.ibd' -and ($bizTables -contains $_.BaseName) }
        $bizNewest = ($bizFiles | Measure-Object LastWriteTime -Maximum).Maximum
        if (-not $bizNewest) { $bizNewest = [datetime]::MinValue }
        $sizeMb = [math]::Round((($files | Measure-Object Length -Sum).Sum) / 1MB, 1)
        $installDir = Split-Path $dir.Parent.FullName -Parent
        $isRunning = [bool]($running | Where-Object { $_.DataDir -eq $dataDir })
        $found += [pscustomobject]@{
            DataDir = $dataDir; DbFolder = $dir.FullName; Install = $installDir
            Newest = $newest; BizNewest = $bizNewest; SizeMb = $sizeMb; FlatsKb = if ($flats) { [math]::Round($flats.Length / 1KB) } else { 0 }
            Tables = @($files | Where-Object { $_.Extension -eq '.ibd' }).Count; Running = $isRunning
        }
    }
}

Say "DATABASES named '$dbName' found on disk:"
if ($found.Count -eq 0) { Say '  (none found - check the folder names/drives, or the database was never created here)' }
$best = $found | Sort-Object BizNewest -Descending | Select-Object -First 1
$i = 0
foreach ($f in ($found | Sort-Object BizNewest -Descending)) {
    $i++
    $tag = @()
    if ($f.Running) { $tag += 'RUNNING' } else { $tag += 'not running' }
    if ($f -eq $best) { $tag += 'business data is newest here = most likely the REAL one' }
    Say "  [$i] $($f.DbFolder)"
    $biz = if ($f.BizNewest -gt [datetime]::MinValue) { $f.BizNewest.ToString('yyyy-MM-dd HH:mm') } else { 'never' }
    Say "      business data last written: $biz   (any file: $($f.Newest.ToString('yyyy-MM-dd HH:mm')))   tables: $($f.Tables)   size: $($f.SizeMb) MB"
    Say "      status: $($tag -join '  |  ')"
}
Say ''

# ---- 3. Verdict ------------------------------------------------------------------------
if ($best) {
    $startCmd = "`"$($best.Install)\bin\mysqld.exe`" --defaults-file=`"$($best.Install)\bin\my.ini`" --standalone"
    Say 'VERDICT'
    Say ('-' * 70)
    Say "Real database : $($best.DbFolder)"
    Say "Install       : $($best.Install)"
    if ($best.Running) {
        Say 'The correct MySQL is RUNNING. Good.'
    } elseif ($running.Count -gt 0) {
        Say 'WARNING: a DIFFERENT MySQL is running - the app will see the wrong (probably empty) data.'
        Say "  1) Stop the running one (XAMPP Control Panel -> Stop, or Task Manager -> mysqld.exe)."
        Say "  2) Start the real one:  $startCmd"
    } else {
        Say 'MySQL is not running. Start the real one with:'
        Say "  $startCmd"
        Say '  (or run:  find-database.bat start)'
        if ($Start) {
            $ini = "$($best.Install)\bin\my.ini"
            Say 'Starting it now...'
            Start-Process -FilePath "$($best.Install)\bin\mysqld.exe" -ArgumentList "--defaults-file=`"$ini`"", '--standalone' -WindowStyle Hidden
            Start-Sleep -Seconds 8
            Say '  started - re-run find-database.bat to confirm.'
        }
    }
    Say ''
}

# ---- 4. What the app itself sees ---------------------------------------------------------
Say 'WHAT THE APP SEES (php artisan db:where)'
Say ('-' * 70)
Push-Location $projectRoot
try {
    $appOut = & php artisan db:where 2>&1 | Out-String
    foreach ($line in ($appOut -split "`r?`n")) { if ($line -ne '') { Say $line } }
} catch {
    Say "  could not run php artisan: $_"
}
Pop-Location

$statusFile = Join-Path $projectRoot 'DATABASE-STATUS.txt'
$report | Set-Content -Path $statusFile -Encoding ASCII
Write-Host ''
Write-Host "Saved to $statusFile"
