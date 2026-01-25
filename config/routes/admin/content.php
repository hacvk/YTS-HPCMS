<?php
/**
 * 内容路由（栏目、文章、单页等）
 */
return [
    // 栏目管理
    'GET /admin/category' => ['Admin\\CategoryController', 'index'],
    'GET /admin/category/add' => ['Admin\\CategoryController', 'add'],
    'GET /admin/category/edit' => ['Admin\\CategoryController', 'edit'],
    'POST /admin/category/save' => ['Admin\\CategoryController', 'save'],
    'POST /admin/category/delete' => ['Admin\\CategoryController', 'delete'],
    'GET /admin/category/getModelFields' => ['Admin\\CategoryController', 'getModelFields'],
    'GET /admin/category/tree' => ['Admin\\CategoryController', 'tree'],
    'GET /admin/category/getCategoryOptions' => ['Admin\\CategoryController', 'getCategoryOptions'],
    'GET /admin/category/getContentOptions' => ['Admin\\CategoryController', 'getContentOptions'],
    'GET /admin/category/getAllowCrop' => ['Admin\\CategoryController', 'getAllowCrop'],
    'GET /admin/category/getAllowCropByCategory' => ['Admin\\CategoryController', 'getAllowCropByCategory'],

    // 单页管理
    'GET /admin/page' => ['Admin\\PageController', 'index'],
    'GET /admin/page/add' => ['Admin\\PageController', 'add'],
    'GET /admin/page/edit' => ['Admin\\PageController', 'edit'],
    'POST /admin/page/save' => ['Admin\\PageController', 'save'],
    'POST /admin/page/delete' => ['Admin\\PageController', 'delete'],

    // 文章管理
    'GET /admin/article' => ['Admin\\ArticleController', 'index'],
    'GET /admin/article/add' => ['Admin\\ArticleController', 'add'],
    'GET /admin/article/edit' => ['Admin\\ArticleController', 'edit'],
    'GET /admin/article/edit/' => ['Admin\\ArticleController', 'edit'],
    'GET /admin/article/edit/{id}' => ['Admin\\ArticleController', 'edit'],
    'POST /admin/article/save' => ['Admin\\ArticleController', 'save'],
    'POST /admin/article/delete' => ['Admin\\ArticleController', 'delete'],
    'POST /admin/article/delete/' => ['Admin\\ArticleController', 'delete'],

    // 附件管理
    'GET /admin/attachment' => ['Admin\\AttachmentController', 'index'],
    'POST /admin/attachment/delete' => ['Admin\\AttachmentController', 'delete'],
    'POST /admin/attachment/batchDelete' => ['Admin\\AttachmentController', 'batchDelete'],

    // Banner 管理
    'GET /admin/banner' => ['Admin\\BannerController', 'index'],
    'GET /admin/banner/edit' => ['Admin\\BannerController', 'edit'],
    'POST /admin/banner/save' => ['Admin\\BannerController', 'save'],
    'POST /admin/banner/delete' => ['Admin\\BannerController', 'delete'],

    // 友情链接
    'GET /admin/friendlink' => ['Admin\\FriendLinkController', 'index'],
    'GET /admin/friendlink/edit' => ['Admin\\FriendLinkController', 'edit'],
    'POST /admin/friendlink/save' => ['Admin\\FriendLinkController', 'save'],
    'POST /admin/friendlink/delete' => ['Admin\\FriendLinkController', 'delete'],

    // 文件管理
    'GET /admin/file' => ['Admin\\FileController', 'index'],
    'POST /admin/file/update' => ['Admin\\FileController', 'update'],

    // 文件上传
    'GET /admin/upload' => ['Admin\\UploadController', 'index'],
    'POST /admin/upload/do' => ['Admin\\UploadController', 'upload'],
    'POST /admin/upload/upload' => ['Admin\\UploadController', 'upload'],
    'POST /admin/upload/categoryBanner' => ['Admin\\UploadController', 'categoryBanner'],
    'POST /admin/upload/deleteCoverImage' => ['Admin\\UploadController', 'deleteCoverImage'],
    'POST /admin/upload/crop' => ['Admin\\UploadController', 'crop'],
    'POST /admin/upload/uploadCrop' => ['Admin\\UploadController', 'uploadCrop'],

    // 管理员管理
    'GET /admin/admin' => ['Admin\\AdminController', 'index'],
    'GET /admin/admin/edit' => ['Admin\\AdminController', 'edit'],
    'POST /admin/admin/save' => ['Admin\\AdminController', 'save'],
    'POST /admin/admin/delete' => ['Admin\\AdminController', 'delete'],

    // 角色管理
    'GET /admin/role' => ['Admin\\RoleController', 'index'],
    'GET /admin/role/edit' => ['Admin\\RoleController', 'edit'],
    'POST /admin/role/save' => ['Admin\\RoleController', 'save'],
    'POST /admin/role/delete' => ['Admin\\RoleController', 'delete'],

    // 日志管理
    'GET /admin/log' => ['Admin\\LogController', 'index'],

    // 缓存管理
    'GET /admin/cache' => ['Admin\\CacheController', 'index'],
    'POST /admin/cache/clear-latte' => ['Admin\\CacheController', 'clearLatte'],
    'POST /admin/cache/clear-app' => ['Admin\\CacheController', 'clearApp'],
    'POST /admin/cache/clear-search' => ['Admin\\CacheController', 'clearSearch'],
    'POST /admin/cache/clear-all' => ['Admin\\CacheController', 'clearAll'],
];
