# wangeditor 下载和安装脚本
# 功能：从 GitHub 下载 wangeditor 最新版本并解压到 /public/static/wangeditor 目录

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "wangeditor 下载和安装脚本" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 设置变量
$targetDir = Join-Path $PSScriptRoot "public\static\wangeditor"
$tempZip = Join-Path $env:TEMP "wangeditor-latest.zip"
$wangeditorVersion = "4.7.13"  # 可以修改为最新版本号（当前最新稳定版）
$githubRepo = "wangeditor-team/wangeditor"
$cdnUrl = "https://cdn.jsdelivr.net/npm/wangeditor@$wangeditorVersion/dist/wangEditor.min.js"

# 检查目标目录是否存在，如果存在则删除
if (Test-Path $targetDir) {
    Write-Host "检测到已存在的 wangeditor 目录，正在删除..." -ForegroundColor Yellow
    Remove-Item -Path $targetDir -Recurse -Force
    Write-Host "已删除旧目录" -ForegroundColor Green
}

# 创建目标目录
Write-Host "创建目标目录: $targetDir" -ForegroundColor Yellow
New-Item -ItemType Directory -Path $targetDir -Force | Out-Null

# 方法1：尝试从 CDN 下载（推荐，因为更快）
Write-Host ""
Write-Host "方法1: 尝试从 CDN 下载 wangeditor..." -ForegroundColor Yellow
try {
    $cdnDir = Join-Path $targetDir "cdn"
    New-Item -ItemType Directory -Path $cdnDir -Force | Out-Null
    
    # 下载主文件
    $mainFile = Join-Path $cdnDir "wangEditor.min.js"
    Write-Host "正在下载: wangEditor.min.js" -ForegroundColor Cyan
    Invoke-WebRequest -Uri $cdnUrl -OutFile $mainFile -UseBasicParsing
    Write-Host "下载成功: wangEditor.min.js" -ForegroundColor Green
    
    # 创建说明文件
    $readmeLines = @()
    $readmeLines += "# wangeditor 文件说明"
    $readmeLines += ""
    $readmeLines += "本目录包含从 CDN 下载的 wangeditor 文件。"
    $readmeLines += ""
    $readmeLines += "## 文件说明"
    $readmeLines += "- wangEditor.min.js: wangeditor 主文件（压缩版）"
    $readmeLines += ""
    $readmeLines += "## 使用方法"
    $readmeLines += "在 HTML 中引入："
    $readmeLines += "``````html"
    $readmeLines += "<script src=`"/public/static/wangeditor/cdn/wangEditor.min.js`"></script>"
    $readmeLines += "``````"
    $readmeLines += ""
    $readmeLines += "## 版本信息"
    $readmeLines += "- 版本: $wangeditorVersion"
    $readmeLines += "- 下载时间: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    $readmeLines += "- 来源: https://cdn.jsdelivr.net/npm/wangeditor@$wangeditorVersion/dist/"
    
    $readmeContent = $readmeLines -join "`r`n"
    $readmeFile = Join-Path $cdnDir "README.md"
    
    # 使用 UTF-8 BOM 编码保存
    $utf8WithBom = New-Object System.Text.UTF8Encoding $true
    [System.IO.File]::WriteAllLines($readmeFile, $readmeContent, $utf8WithBom)
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "wangeditor 下载完成！" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "文件位置: $targetDir" -ForegroundColor Cyan
    Write-Host "主文件: $mainFile" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "在模板中使用:" -ForegroundColor Yellow
    Write-Host '<script src="/public/static/wangeditor/cdn/wangEditor.min.js"></script>' -ForegroundColor White
    Write-Host ""
    
    exit 0
} catch {
    Write-Host "CDN 下载失败: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "尝试方法2: 从 GitHub 下载完整源码..." -ForegroundColor Yellow
}

# 方法2：从 GitHub 下载完整源码（备用方案）
Write-Host ""
Write-Host "方法2: 从 GitHub 下载 wangeditor 源码..." -ForegroundColor Yellow

try {
    # GitHub API 获取最新 release
    $apiUrl = "https://api.github.com/repos/$githubRepo/releases/latest"
    Write-Host "正在获取最新版本信息..." -ForegroundColor Cyan
    
    $releaseInfo = Invoke-RestMethod -Uri $apiUrl -UseBasicParsing
    $zipUrl = $releaseInfo.zipball_url
    $tagName = $releaseInfo.tag_name
    
    Write-Host "最新版本: $tagName" -ForegroundColor Green
    Write-Host "下载地址: $zipUrl" -ForegroundColor Cyan
    
    # 下载 ZIP 文件
    Write-Host "正在下载 ZIP 文件..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri $zipUrl -OutFile $tempZip -UseBasicParsing
    
    # 解压 ZIP 文件
    Write-Host "正在解压文件..." -ForegroundColor Yellow
    $extractDir = Join-Path $env:TEMP "wangeditor-extract"
    if (Test-Path $extractDir) {
        Remove-Item -Path $extractDir -Recurse -Force
    }
    Expand-Archive -Path $tempZip -DestinationPath $extractDir -Force
    
    # 查找 dist 目录
    $distDirs = Get-ChildItem -Path $extractDir -Recurse -Directory -Filter "dist" | Select-Object -First 1
    if ($distDirs) {
        $distPath = $distDirs.FullName
        Write-Host "找到 dist 目录: $distPath" -ForegroundColor Green
        
        # 复制 dist 目录内容到目标目录
        Copy-Item -Path "$distPath\*" -Destination $targetDir -Recurse -Force
        Write-Host "文件复制完成" -ForegroundColor Green
    } else {
        # 如果没有 dist 目录，复制整个解压的目录
        $sourceDir = Get-ChildItem -Path $extractDir -Directory | Select-Object -First 1
        if ($sourceDir) {
            Copy-Item -Path "$($sourceDir.FullName)\*" -Destination $targetDir -Recurse -Force
            Write-Host "文件复制完成（完整源码）" -ForegroundColor Green
        } else {
            throw "无法找到解压后的目录"
        }
    }
    
    # 清理临时文件
    Remove-Item -Path $tempZip -Force -ErrorAction SilentlyContinue
    Remove-Item -Path $extractDir -Recurse -Force -ErrorAction SilentlyContinue
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "wangeditor 下载完成！" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "文件位置: $targetDir" -ForegroundColor Cyan
    Write-Host "版本: $tagName" -ForegroundColor Cyan
    Write-Host ""
    
} catch {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "下载失败！" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "错误信息: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "建议:" -ForegroundColor Yellow
    Write-Host "1. 检查网络连接" -ForegroundColor White
    Write-Host "2. 检查防火墙设置" -ForegroundColor White
    Write-Host "3. 手动下载: https://github.com/$githubRepo/releases" -ForegroundColor White
    Write-Host ""
    
    # 清理可能存在的临时文件
    if (Test-Path $tempZip) {
        Remove-Item -Path $tempZip -Force -ErrorAction SilentlyContinue
    }
    
    exit 1
}

Write-Host "脚本执行完成！" -ForegroundColor Green
