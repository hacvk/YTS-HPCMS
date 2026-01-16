<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "SAPI: " . PHP_SAPI . PHP_EOL;
echo str_repeat("=", 50) . PHP_EOL;

echo "[EXTENSION CHECK]" . PHP_EOL;
echo "gd loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . PHP_EOL;
echo "exif loaded: " . (extension_loaded('exif') ? 'YES' : 'NO') . PHP_EOL;
echo str_repeat("=", 50) . PHP_EOL;

echo "[FUNCTION CHECK]" . PHP_EOL;
echo "function imagewebp exists: " . (function_exists('imagewebp') ? 'YES' : 'NO') . PHP_EOL;
echo "function exif_imagetype exists: " . (function_exists('exif_imagetype') ? 'YES' : 'NO') . PHP_EOL;
echo str_repeat("=", 50) . PHP_EOL;

if (extension_loaded('gd')) {
    echo "[GD INFO]" . PHP_EOL;
    $info = gd_info();
    foreach ($info as $k => $v) {
        echo $k . ": " . (is_bool($v) ? ($v ? 'YES' : 'NO') : (string)$v) . PHP_EOL;
    }

    echo str_repeat("=", 50) . PHP_EOL;
    echo "GD WebP support (gd_info()['WebP Support']): "
        . ((isset($info['WebP Support']) && $info['WebP Support']) ? 'YES' : 'NO')
        . PHP_EOL;
} else {
    echo "GD not loaded, skip gd_info()." . PHP_EOL;
}