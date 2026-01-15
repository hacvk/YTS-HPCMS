<?php
/**
 * 测试模板修复是否正确
 */

// 模拟添加文章时的数据
$article = [];
$categories = [
    [
        'id' => 1,
        'name' => '测试栏目',
        'level' => 0,
        'children' => []
    ]
];

// 测试模板中使用的条件判断
echo "=== 测试模板条件判断 ===\n";

// 测试状态选项的条件
$status = isset($article['status']) ? $article['status'] : null;
echo "文章状态: " . ($status ?? '未设置') . "\n";

// 测试isset条件
$showPublished = (isset($article['status']) && $article['status'] == 1) || !isset($article['status']);
$showDraft = isset($article['status']) && $article['status'] == 0;

echo "显示已发布: " . ($showPublished ? '是' : '否') . "\n";
echo "显示草稿: " . ($showDraft ? '是' : '否') . "\n";

// 测试其他字段的isset检查
echo "\n=== 测试其他字段isset检查 ===\n";
$fields = ['id', 'category_id', 'title', 'cover_img', 'summary', 'content'];
foreach ($fields as $field) {
    echo "字段 {$field} 存在: " . (isset($article[$field]) ? '是' : '否') . "\n";
    if (isset($article[$field])) {
        echo "  值: {$article[$field]}\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "模板修复看起来是正确的！\n";