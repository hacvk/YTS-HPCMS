<?php
define('APP_ROOT', __DIR__);

require_once __DIR__ . '/app/Core/Autoloader.php';
$autoloader = new \App\Core\Autoloader();
$autoloader->register();
$config = require __DIR__ . '/config.php';
$databaseConfig = $config['db'] ?? [];

try {
    $dsn = 'mysql:host=' . ($databaseConfig['host'] ?? 'localhost') . ';dbname=' . ($databaseConfig['database'] ?? 'yycms') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $databaseConfig['username'] ?? 'root', $databaseConfig['password'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['search_blocks', 'search_cache', 'search_logs'];
    foreach ($tables as $table) {
        echo "\nTable: $table\n";
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
}