<#
.SYNOPSIS
  Builds the Green Valley BMS Android (WebView) app and puts it where the website serves it.

.EXAMPLE
  # the real thing, once the site has an address (HTTPS only):
  .\scripts\build-android.ps1 -ServerUrl https://greenvalley.example.com -VersionName 1.0.0 -VersionCode 1

.EXAMPLE
  # a test build for a phone on the same WiFi as this PC (plain http allowed, never ship this one):
  .\scripts\build-android.ps1 -ServerUrl http://192.168.0.116:4000 -AllowHttp

  Output: public\downloads\green-valley-bms.apk  (+ app-version.json)
  Open  <site>/download/app  on the phone to install it.

.NOTES
  - Each NEW release must pass a higher -VersionCode, or phones will refuse to update over the old one.
  - The signing key (green-valley-release.jks) is created once and REUSED. Android only installs an
    update if it is signed with the SAME key - lose the .jks and every phone must uninstall first.
    BACK IT UP somewhere safe, and never commit it.
  - Needs the toolchain in ..\android-toolchain (JDK 17 + Android SDK 34); see ANDROID.md.
#>
param(
    [string]$ServerUrl = '',
    [switch]$AllowHttp,
    [string]$VersionName = '1.0.0',
    [int]$VersionCode = 1,
    [string]$ToolchainDir = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$androidDir = Join-Path $projectRoot 'android-app'
if (-not $ToolchainDir) { $ToolchainDir = Join-Path (Split-Path -Parent $projectRoot) 'android-toolchain' }

# ---- address sanity ---------------------------------------------------------------
if ($ServerUrl) {
    $ServerUrl = $ServerUrl.Trim().TrimEnd('/')
    if ($ServerUrl -notmatch '^https?://[^/\s]+') { throw "ServerUrl must start with https:// (got '$ServerUrl')" }
    if ($ServerUrl -like 'http://*' -and -not $AllowHttp) {
        throw "A live build must use https://. For a WiFi test build add -AllowHttp."
    }
}

# ---- toolchain --------------------------------------------------------------------
$jdk = Get-ChildItem (Join-Path $ToolchainDir 'jdk-extract') -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
$sdk = Join-Path $ToolchainDir 'sdk'
if (-not $jdk -or -not (Test-Path (Join-Path $sdk 'build-tools'))) {
    throw "Android toolchain not found in $ToolchainDir (need jdk-extract\ and sdk\). See ANDROID.md."
}
$env:JAVA_HOME = $jdk.FullName
$env:ANDROID_HOME = $sdk
$env:PATH = "$($jdk.FullName)\bin;$env:PATH"
Set-Content -Path (Join-Path $androidDir 'local.properties') -Value ("sdk.dir=" + ($sdk -replace '\\', '/')) -Encoding ASCII

# ---- signing key: create once, reuse forever ---------------------------------------
$jks = Join-Path $ToolchainDir 'green-valley-release.jks'
$propsFile = Join-Path $androidDir 'keystore.properties'
if (-not (Test-Path $jks)) {
    Write-Host 'Creating the release signing key (one time only)...'
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider
    $bytes = New-Object byte[] 24; $rng.GetBytes($bytes)
    $password = -join ($bytes | ForEach-Object { $chars[$_ % $chars.Length] })
    & keytool -genkeypair -keystore $jks -alias greenvalley -keyalg RSA -keysize 2048 -validity 10000 `
        -storepass $password -keypass $password -dname 'CN=Green Valley BMS, O=Green Valley, C=BD' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'keytool failed' }
    @("storeFile=$($jks -replace '\\', '/')", "storePassword=$password", "keyAlias=greenvalley", "keyPassword=$password") |
        Set-Content -Path $propsFile -Encoding ASCII
    Write-Host "  key:      $jks"
    Write-Host "  password: stored in android-app\keystore.properties (not committed)"
    Write-Host '  >>> BACK UP BOTH FILES. Without them you can never publish an update. <<<'
} elseif (-not (Test-Path $propsFile)) {
    throw "$jks exists but android-app\keystore.properties is missing - restore it (storeFile/storePassword/keyAlias/keyPassword)."
}

# ---- build -----------------------------------------------------------------------------
Push-Location $androidDir
try {
    & .\gradlew.bat assembleRelease --no-daemon `
        "-PserverUrl=$ServerUrl" "-PallowHttp=$($AllowHttp.IsPresent.ToString().ToLower())" `
        "-PappVersionName=$VersionName" "-PappVersionCode=$VersionCode"
    if ($LASTEXITCODE -ne 0) { throw 'Gradle build failed' }
} finally { Pop-Location }

$built = Join-Path $androidDir 'app\build\outputs\apk\release\app-release.apk'
if (-not (Test-Path $built)) { throw "APK not found at $built" }

# ---- verify it is really signed, and says what we think it says ------------------------------
$buildTools = Get-ChildItem (Join-Path $sdk 'build-tools') -Directory | Sort-Object Name -Descending | Select-Object -First 1
& "$($buildTools.FullName)\apksigner.bat" verify --print-certs $built
if ($LASTEXITCODE -ne 0) { throw 'apksigner: the APK signature is NOT valid' }
$badging = & "$($buildTools.FullName)\aapt2.exe" dump badging $built | Select-Object -First 1
Write-Host $badging

# ---- publish into the website ----------------------------------------------------------------
$outDir = Join-Path $projectRoot 'public\downloads'
New-Item -ItemType Directory -Force $outDir | Out-Null
$target = Join-Path $outDir 'green-valley-bms.apk'
Copy-Item $built $target -Force
$sha = (Get-FileHash $target -Algorithm SHA256).Hash.ToLower()
[ordered]@{
    versionName = $VersionName; versionCode = $VersionCode
    builtAt = (Get-Date -Format 'yyyy-MM-dd'); sha256 = $sha
    serverUrl = $ServerUrl; sizeBytes = (Get-Item $target).Length
} | ConvertTo-Json | Set-Content -Path (Join-Path $outDir 'app-version.json') -Encoding ASCII

Write-Host ''
Write-Host "DONE  $target"
Write-Host ("      {0:N2} MB   sha256 {1}" -f ((Get-Item $target).Length / 1MB), $sha)
if ($ServerUrl) { Write-Host "      app opens: $ServerUrl" } else { Write-Host '      app asks for the server address on first launch' }
Write-Host '      phone: open <your site>/download/app'
