<#
.SYNOPSIS
    Download and install Bootstrap 5 + Icons (Smart Version)
#>

$ErrorActionPreference = "Stop"
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
    # 1. Clean up old temp folders first (to avoid conflict)
    if (Test-Path $TempIcons) { Remove-Item $TempIcons -Recurse -Force }
    if (Test-Path $TempBootstrap) { Remove-Item $TempBootstrap -Recurse -Force }

    # 2. Create Directories
    Ensure-Directory $CssPath
    Ensure-Directory $JsPath
    Ensure-Directory $FontsPath

    # ==========================================
    # Process Bootstrap
    # ==========================================
    Write-Host "`n📦 Processing Bootstrap $BootstrapVersion ..." -ForegroundColor Yellow
    
    if (-not (Test-Path $ZipBootstrap)) {
        Write-Host "   Downloading..." -NoNewline
        Invoke-WebRequest -Uri $BootstrapUrl -OutFile $ZipBootstrap
        Write-Host "Done" -ForegroundColor Green
    } else {
        Write-Host "   Using existing zip file." -ForegroundColor DarkGray
    }

    Write-Host "   Extracting..." -NoNewline
    Expand-Archive -Path $ZipBootstrap -DestinationPath $TempBootstrap -Force
    Write-Host "Done" -ForegroundColor Green

    Write-Host "   Copying files..."
    $BsSource = Join-Path $TempBootstrap "bootstrap-$BootstrapVersion-dist"
    Copy-Item -Path "$BsSource\css\bootstrap.min.css" -Destination $CssPath -Force
    Copy-Item -Path "$BsSource\js\bootstrap.bundle.min.js" -Destination $JsPath -Force

    # ==========================================
    # Process Icons (SMART FIX)
    # ==========================================
    Write-Host "`n🎨 Processing Bootstrap Icons $IconsVersion ..." -ForegroundColor Yellow

    if (-not (Test-Path $ZipIcons)) {
        Write-Host "   Downloading..." -NoNewline
        Invoke-WebRequest -Uri $IconsUrl -OutFile $ZipIcons
        Write-Host "Done" -ForegroundColor Green
    } else {
        Write-Host "   Using existing zip file." -ForegroundColor DarkGray
    }

    Write-Host "   Extracting..." -NoNewline
    Expand-Archive -Path $ZipIcons -DestinationPath $TempIcons -Force
    Write-Host "Done" -ForegroundColor Green

    Write-Host "   Searching and Copying files..."
    
    # --- 核心修改：自动搜索文件，而不是写死路径 ---
    
    # 1. 查找并复制 bootstrap-icons.css
    $IconCssFile = Get-ChildItem -Path $TempIcons -Filter "bootstrap-icons.css" -Recurse | Select-Object -First 1
    if ($IconCssFile) {
        Copy-Item -Path $IconCssFile.FullName -Destination $CssPath -Force
        Write-Host "   [OK] Copied bootstrap-icons.css" -ForegroundColor Green
    } else {
        Write-Warning "   [Fail] Could not find bootstrap-icons.css!"
    }

    # 2. 查找并复制 fonts 文件夹 (通常里面包含 .woff/.woff2 文件)
    $FontsFolder = Get-ChildItem -Path $TempIcons -Filter "fonts" -Directory -Recurse | Select-Object -First 1
    if ($FontsFolder) {
        Copy-Item -Path "$($FontsFolder.FullName)\*" -Destination $FontsPath -Recurse -Force
        Write-Host "   [OK] Copied fonts folder contents" -ForegroundColor Green
    } else {
        Write-Warning "   [Fail] Could not find fonts folder!"
    }

    # ==========================================
    # Cleanup
    # ==========================================
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