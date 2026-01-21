server {
    listen 80;
    server_name yycms.com www.yycms.com;

    root /var/www/yycms.com/public;
    index index.php index.html;

    # 处理静态资源
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|mjs|pdf|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
    
    # 防止访问敏感文件
    location ~* \.(env|log|sql|bak|config\.php)$ {
        deny all;
    }
}