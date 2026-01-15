#!/bin/bash
# 下载 Bootstrap 5 和 Bootstrap Icons 到本地

echo "正在下载 Bootstrap 5.3.0..."
curl -L -o bootstrap.zip https://github.com/twbs/bootstrap/releases/download/v5.3.0/bootstrap-5.3.0-dist.zip

echo "正在解压 Bootstrap..."
unzip -o bootstrap.zip -d temp_bootstrap/

echo "复制文件到 static 目录..."
cp temp_bootstrap/bootstrap-5.3.0-dist/css/bootstrap.min.css public/static/css/
cp temp_bootstrap/bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js public/static/js/

echo "正在下载 Bootstrap Icons 1.11.0..."
curl -L -o bootstrap-icons.zip https://github.com/twbs/icons/releases/download/v1.11.0/bootstrap-icons-1.11.0.zip

echo "正在解压 Bootstrap Icons..."
unzip -o bootstrap-icons.zip -d temp_bootstrap_icons/

echo "复制文件到 static 目录..."
cp temp_bootstrap_icons/bootstrap-icons-1.11.0/font/bootstrap-icons.css public/static/css/
cp -r temp_bootstrap_icons/bootstrap-icons-1.11.0/fonts public/static/

echo "清理临时文件..."
rm -rf temp_bootstrap temp_bootstrap_icons bootstrap.zip bootstrap-icons.zip

echo "下载完成！"
