# 静态资源说明

由于网络连接问题，CDN 资源可能无法正常加载。请下载以下文件到本地：

## Windows 用户

双击运行项目根目录下的 `download-bootstrap.bat` 脚本，脚本会自动下载并解压文件。

## Linux/Mac 用户

在项目根目录执行：
```bash
chmod +x download-bootstrap.sh
./download-bootstrap.sh
```

## 手动下载

如果脚本无法运行，请手动下载以下文件：

### Bootstrap 5.3.0
下载地址：https://github.com/twbs/bootstrap/releases/download/v5.3.0/bootstrap-5.3.0-dist.zip

解压后复制：
- `bootstrap-5.3.0-dist/css/bootstrap.min.css` → `public/static/css/`
- `bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js` → `public/static/js/`

### Bootstrap Icons 1.11.0
下载地址：https://github.com/twbs/icons/releases/download/v1.11.0/bootstrap-icons-1.11.0.zip

解压后复制：
- `bootstrap-icons-1.11.0/font/bootstrap-icons.css` → `public/static/css/`
- `bootstrap-icons-1.11.0/fonts/` → `public/static/fonts/`

## 国内加速镜像

如果 GitHub 访问缓慢，可以使用国内镜像：

### Gitee 镜像
- Bootstrap: https://gitee.com/mirrors/bootstrap5 (选择 v5.3.0 版本)
- Bootstrap Icons: https://gitee.com/mirrors/bootstrap-icons (选择 v1.11.0 版本)
