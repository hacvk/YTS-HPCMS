@echo off
REM 下载 Bootstrap 5 和 Bootstrap Icons 到本地

echo 正在下载 Bootstrap 5.3.0...
powershell -Command "Invoke-WebRequest -Uri 'https://github.com/twbs/bootstrap/releases/download/v5.3.0/bootstrap-5.3.0-dist.zip' -OutFile 'bootstrap.zip'"

echo 正在解压 Bootstrap...
powershell -Command "Expand-Archive -Path 'bootstrap.zip' -DestinationPath 'temp_bootstrap' -Force"

echo 正在复制文件到 static 目录...
copy /Y "temp_bootstrap\bootstrap-5.3.0-dist\css\bootstrap.min.css" "public\static\css\"
copy /Y "temp_bootstrap\bootstrap-5.3.0-dist\js\bootstrap.bundle.min.js" "public\static\js\"

echo 正在下载 Bootstrap Icons 1.11.0...
powershell -Command "Invoke-WebRequest -Uri 'https://github.com/twbs/icons/releases/download/v1.11.0/bootstrap-icons-1.11.0.zip' -OutFile 'bootstrap-icons.zip'"

echo 正在解压 Bootstrap Icons...
powershell -Command "Expand-Archive -Path 'bootstrap-icons.zip' -DestinationPath 'temp_bootstrap_icons' -Force"

echo 正在复制文件到 static 目录...
copy /Y "temp_bootstrap_icons\bootstrap-icons-1.11.0\font\bootstrap-icons.css" "public\static\css\"
xcopy /E /I /Y "temp_bootstrap_icons\bootstrap-icons-1.11.0\fonts" "public\static\fonts"

echo 清理临时文件...
rmdir /S /Q temp_bootstrap
rmdir /S /Q temp_bootstrap_icons
del bootstrap.zip
del bootstrap-icons.zip

echo 下载完成！
pause
