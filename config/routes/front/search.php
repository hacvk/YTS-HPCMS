<?php
/**
 * 搜索路由
 */
return [
    'GET /search/' => ['SearchController', 'index'],
    'GET /search' => ['SearchController', 'index'],
    'POST /search/suggestions' => ['SearchController', 'suggestions'],
];