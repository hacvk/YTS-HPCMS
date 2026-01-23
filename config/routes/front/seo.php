<?php
/**
 * SEO路由
 */
return [
    'GET /seo/sitemap' => ['SeoController', 'sitemap'],
    'GET /seo/sitemap.xml' => ['SeoController', 'sitemap'],
    'GET /robots.txt' => ['SeoController', 'robots'],
    'POST /api/seo/extract-keywords' => ['SeoController', 'extractKeywords'],
];