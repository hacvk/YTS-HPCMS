<?php
echo "PHP Version: " . phpversion() . "<br>";
echo "Test page loaded successfully!<br>";

// 模拟模板中的条件判断
$article = [];

// 测试状态选择逻辑
$showPublished = (isset($article['status']) && $article['status'] == 1) || !isset($article['status']);
$showDraft = isset($article['status']) && $article['status'] == 0;

echo "<br>=== Template Logic Test ===<br>";
echo "Show Published: " . ($showPublished ? 'Yes' : 'No') . "<br>";
echo "Show Draft: " . ($showDraft ? 'Yes' : 'No') . "<br>";

echo "<br>Test completed successfully!";