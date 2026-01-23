<?php
/**
 * 系统路由
 */
return [
    'GET /admin/system/about' => ['Admin\\SystemController', 'about'],
    'GET /admin/system/logs' => ['Admin\\SystemController', 'logs'],
    'POST /admin/system/clean-logs' => ['Admin\\SystemController', 'cleanLogs'],
];