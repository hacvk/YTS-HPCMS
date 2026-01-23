<?php
/**
 * 访客路由
 */
return [
    'GET /admin/visitor/logs' => ['Admin\\VisitorController', 'logs'],
    'GET /admin/visitor/stats' => ['Admin\\VisitorController', 'stats'],
    'GET /admin/visitor/alerts' => ['Admin\\VisitorController', 'alerts'],
    'POST /admin/visitor/alerts/handle' => ['Admin\\VisitorController', 'handleAlert'],
    'GET /admin/visitor/anomaly-report' => ['Admin\\VisitorController', 'anomalyReport'],
];