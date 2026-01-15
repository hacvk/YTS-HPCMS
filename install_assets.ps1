<#
.SYNOPSIS
    Download and install Bootstrap 5 + Icons (English Version)
#>

$ErrorActionPreference = "Stop"
# Force TLS 1.2 for GitHub
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# Configuration
$BootstrapVersion = "5.3.0"
$IconsVersion = "1.11.0"

$BootstrapUrl = "https://github.com/twbs/bootstrap/releases/download/v$BootstrapVersion/bootstrap-$BootstrapVersion-dist.zip"
$IconsUrl = "https://github.com/twbs/icons/releases/download/v$IconsVersion/bootstrap-icons-$IconsVersion.zip"

$ScriptRoot = $PSScriptRoot
$PublicPath = Join-Path $ScriptRoot "public\static"
$CssPath = Join-Path $PublicPath "css"
$JsPath = Join-Path $PublicPath "js"
$FontsPath = Join-Path $PublicPath "fonts"

$TempBootstrap = Join-Path $ScriptRoot "temp_bootstrap"
$TempIcons = Join-Path $ScriptRoot "temp_icons"
$ZipBootstrap = Join-Path $ScriptRoot "bootstrap.zip"
$ZipIcons = Join-Path $ScriptRoot "bootstrap-icons.zip"

function Ensure-Directory($Path) {
    if (-not (Test-Path -Path $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
        Write-Host "Created dir: $Path" -ForegroundColor DarkGray
    }
}

Clear-Host
Write-Host "🚀 Starting download process..." -ForegroundColor Cyan

try {
    # Create Directories
    Ensure-Directory $CssPath
    Ensure-Directory $JsPath
    Ensure-Directory $FontsPath

    # --- Process Bootstrap ---
    Write-Host "`n📦 Processing Bootstrap $BootstrapVersion ..." -ForegroundColor Yellow
    
    Write-Host "   Downloading..." -NoNewline
    Invoke-WebRequest -Uri $BootstrapUrl -OutFile $ZipBootstrap
    Write-Host "Done" -ForegroundColor Green

    Write-Host "   Extracting..." -NoNewline
    Expand-Archive -Path $ZipBootstrap -DestinationPath $TempBootstrap -Force
    Write-Host "Done" -ForegroundColor Green

    Write-Host "   Copying files..."
    $BsSource = Join-Path $TempBootstrap "bootstrap-$BootstrapVersion-dist"
    Copy-Item -Path "$BsSource\css\bootstrap.min.css" -Destination $CssPath -Force
    Copy-Item -Path "$BsSource\js\bootstrap.bundle.min.js" -Destination $JsPath -Force

    # --- Process Icons ---
    Write-Host "`n🎨 Processing Bootstrap Icons $IconsVersion ..." -ForegroundColor Yellow

    Write-Host "   Downloading..." -NoNewline
    Invoke-WebRequest -Uri $IconsUrl -OutFile $ZipIcons
    Write-Host "Done" -ForegroundColor Green

    Write-Host "   Extracting..." -NoNewline
    Expand-Archive -Path $ZipIcons -DestinationPath $TempIcons -Force
    Write-Host "Done" -ForegroundColor Green

    Write-Host "   Copying files..."
    $IconsSource = Join-Path $TempIcons "bootstrap-icons-$IconsVersion"
    Copy-Item -Path "$IconsSource\font\bootstrap-icons.css" -Destination $CssPath -Force
    Copy-Item -Path "$IconsSource\font\fonts\*" -Destination $FontsPath -Recurse -Force

    # --- Cleanup ---
    Write-Host "`n🧹 Cleaning up temp files..." -ForegroundColor Magenta
    Remove-Item -Path $TempBootstrap -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -Path $TempIcons -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -Path $ZipBootstrap -Force -ErrorAction SilentlyContinue
    Remove-Item -Path $ZipIcons -Force -ErrorAction SilentlyContinue

    Write-Host "`n✅ Success! All files are in public\static" -ForegroundColor Green

}
catch {
    Write-Host "`n❌ Error: $_" -ForegroundColor Red
}

Write-Host "`nPress Enter to exit..."
Read-Host