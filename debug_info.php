<?php
// 诊断信息脚本
echo "<h1>服务器环境信息</h1>\n";
echo "<pre>\n";

echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET') . "\n";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'NOT SET') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'NOT SET') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";

echo "</pre>\n";

// 模拟路由解析过程
if (isset($_SERVER['REQUEST_URI'])) {
    echo "<h2>模拟路由解析过程</h2>\n";
    echo "<pre>\n";
    
    $uri = $_SERVER['REQUEST_URI'];
    echo "原始 URI: $uri\n";
    
    // 移除查询字符串
    if (($pos = strpos($uri, '?')) !== false) {
        $uri = substr($uri, 0, $pos);
        echo "移除查询字符串后: $uri\n";
    }
    
    // 移除脚本名称（如 /index.php/xxx）
    $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? '/') . "\n";
    echo "dirname结果: $scriptName\n";
    
    if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
        $originalUri = $uri;
        $uri = substr($uri, strlen($scriptName));
        echo "移除脚本名称后: $originalUri -> $uri\n";
    }
    
    // 标准化：移除开头和结尾的斜杠
    $originalUri = $uri;
    $uri = trim($uri, '/');
    echo "trim处理后: $originalUri -> $uri\n";
    
    // 如果为空，返回根路径
    $finalUri = $uri === '' ? '/' : '/' . $uri;
    echo "最终URI: $finalUri\n";
    
    echo "</pre>\n";
}