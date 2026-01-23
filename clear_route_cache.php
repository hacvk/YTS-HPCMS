<?php
// 清除路由缓存脚本
define('APP_ROOT', __DIR__);

// 引入配置文件
require_once __DIR__ . '/config.php';

// 设置自动加载
spl_autoload_register(function ($class) {
    // 将命名空间转换为文件路径
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 初始化容器
$container = new App\Core\Container();

// 绑定数据库服务
$container->bind(App\Core\Database::class, function() {
    return App\Core\Database::get();
});

// 清除路由缓存
$router = new App\Http\Router($container);
$router->clearRouteCache();

echo "路由缓存已清除\n";