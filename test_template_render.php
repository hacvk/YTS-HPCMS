<?php
/**
 * 测试模板渲染是否正确
 */

// 定义应用根目录
define('APP_ROOT', __DIR__);

// 引入自动加载器
require_once APP_ROOT . '/app/Core/Autoloader.php';

// 注册自动加载器
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 模拟模板参数
$params = [
    'title' => '添加文章',
    'action' => '/admin/article/save',
    'article' => [],
    'categories' => [
        [
            'id' => 1,
            'name' => '测试栏目',
            'level' => 0,
            'children' => []
        ]
    ],
    'csrf_token' => 'test_csrf_token_123456'
];

// 模拟View类的模板渲染
$view = new App\Core\View();

// 测试模板渲染
ob_start();
$view->render('admin/article/edit', $params);
$output = ob_get_clean();

// 检查输出中是否包含错误
if (strpos($output, '{') !== false && strpos($output, '}') !== false) {
    echo "模板渲染可能存在问题，输出中包含未处理的Latte标签\n\n";
} else {
    echo "模板渲染成功，没有未处理的Latte标签\n\n";
}

// 检查CSRF Token是否正确输出
echo "=== 检查CSRF Token输出 ===\n";
$csrfTokenPattern = '/name="csrf_token".*value="([^"]+)"/';
if (preg_match($csrfTokenPattern, $output, $matches)) {
    echo "CSRF Token正确输出: {$matches[1]}\n";
} else {
    echo "CSRF Token未找到\n";
}

// 检查JavaScript中的CSRF Token获取方式
echo "\n=== 检查JavaScript中的CSRF Token获取方式 ===\n";
$jsPattern = '/const csrfTokenElement = document\.getElementById\(\'csrf_token\'\);/';
if (preg_match($jsPattern, $output)) {
    echo "JavaScript中使用DOM获取CSRF Token: 成功\n";
} else {
    echo "JavaScript中CSRF Token获取方式不正确\n";
}

// 输出部分内容以便检查
echo "\n=== 输出模板前1000个字符 ===\n";
echo substr($output, 0, 1000) . "\n...\n";

echo "\n=== 测试完成 ===\n";