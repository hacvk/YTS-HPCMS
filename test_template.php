<?php
declare(strict_types=1);

// 简单测试Latte模板的PHP标签
$latte = new Latte\Engine();
$latte->setTempDirectory(__DIR__ . '/tmp');
$latte->setLoader(new Latte\Loaders\FileLoader(__DIR__ . '/app/View'));

// 准备测试数据
$params = [
    'title' => '测试文章',
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
    'csrf_token' => 'test_token'
];

try {
    $latte->render('admin/article/edit.latte', $params);
    echo '模板渲染成功！';
} catch (Exception $e) {
    echo '模板渲染失败：' . $e->getMessage() . '\n';
    echo '错误文件：' . $e->getFile() . '\n';
    echo '错误行号：' . $e->getLine() . '\n';
}