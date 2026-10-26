<#
.SYNOPSIS
  Runs Green Valley BMS so a PHONE on the same WiFi can reach it (for testing the Android app).

  Normal dev servers listen on 127.0.0.1 only - invisible to a phone. This starts them on every
  network interface instead, and prints the addresses to type on the phone.

  One-time, as Administrator (Windows Firewall blocks the phone otherwise):
    netsh advfirewall firewall add rule name="GreenValley dev" dir=in action=allow protocol=TCP localport=4000,8100 profile=private
#>
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$ip = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notmatch '^(127|169\.254)\.' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Select-Object -First 1).IPAddress
if (-not $ip) { throw 'No WiFi/LAN address found - is this PC connected to the same network as the phone?' }

foreach ($port in 4000, 8100) {
    if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
        throw "Port $port is already in use. Stop the running dev server first (it is probably bound to 127.0.0.1 only)."
    }
}

Start-Process powershell -WorkingDirectory $root -ArgumentList '-NoExit', '-Command', 'php artisan serve --host=0.0.0.0 --port=8100'
Start-Process powershell -WorkingDirectory (Join-Path $root 'frontend') -ArgumentList '-NoExit', '-Command', 'npm run dev -- --host 0.0.0.0'

Write-Host ''
Write-Host "On the phone (same WiFi):"
Write-Host "  download the app : http://${ip}:8100/download/app"
Write-Host "  or just use web  : http://${ip}:4000"
Write-Host ''
Write-Host 'Build the matching app first:  scripts\build-android.ps1 -ServerUrl http://' -NoNewline
Write-Host "${ip}:4000 -AllowHttp"
