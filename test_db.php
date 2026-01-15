<?php
// 定义应用根目录
define('APP_ROOT', __DIR__);

// 引入自动加载器
require_once APP_ROOT . '/app/Core/Autoloader.php';

// 注册自动加载器
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 引入配置文件
$config = require APP_ROOT . '/config.php';

// 实例化应用
$app = new App\Core\App($config);

try {
    // 获取数据库实例
    $db = App\Core\Database::get();
    echo '数据库连接成功！\n';
    
    // 测试查询 - 使用Category类的getAll方法
    echo '\n测试Category类的getAll方法：\n';
    $categories = App\Model\Category::getAll();
    
    echo '查询结果数量：' . count($categories) . '\n';
    
    if (count($categories) > 0) {
        echo '\n栏目列表：\n';
        foreach ($categories as $category) {
            echo 'ID: ' . $category['id'] . ', 名称: ' . $category['name'] . ', 父ID: ' . $category['parent_id'] . '\n';
        }
    } else {
        echo '\n查询结果为空！\n';
        
        // 检查表是否存在
        $tables = $db->query("SHOW TABLES LIKE 'sys_category'")->fetchAll();
        echo '\n表是否存在：' . (count($tables) > 0 ? '是' : '否') . '\n';
        
        // 如果表不存在，导入SQL
        if (count($tables) == 0) {
            echo '\n尝试导入SQL文件...\n';
            $sql = file_get_contents(APP_ROOT . '/database/sys_category.sql');
            if ($sql) {
                $db->query($sql);
                echo 'SQL导入成功！\n';
                
                // 重新查询
                $categories = App\Model\Category::getAll();
                echo '重新查询结果数量：' . count($categories) . '\n';
            } else {
                echo '无法读取SQL文件！\n';
            }
        }
    }
    
    // 测试getTree方法
    echo '\n\n测试getTree方法：\n';
    $tree = App\Model\Category::getTree();
    echo '树形结构结果数量：' . count($tree) . '\n';
    
    if (count($tree) > 0) {
        echo '\n树形结构：\n';
        foreach ($tree as $category) {
            echo str_repeat('  ', $category['level']) . '- ' . $category['name'] . ' (ID: ' . $category['id'] . ')\n';
            if (count($category['children']) > 0) {
                foreach ($category['children'] as $child) {
                    echo str_repeat('  ', $child['level']) . '- ' . $child['name'] . ' (ID: ' . $child['id'] . ')\n';
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo '错误：' . $e->getMessage() . '\n';
    echo '错误文件：' . $e->getFile() . '\n';
    echo '错误行号：' . $e->getLine() . '\n';
    echo '错误堆栈：' . $e->getTraceAsString() . '\n';
}
