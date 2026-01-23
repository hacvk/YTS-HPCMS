<?php
/**
 * 设置路由
 */
return [
    // 网站设置
    'GET /admin/setting' => ['Admin\\SettingController', 'index'],
    'POST /admin/setting/save' => ['Admin\\SettingController', 'save'],
    'POST /admin/setting/delete-image' => ['Admin\\SettingController', 'deleteImage'],

    // 联系我们
    'GET /admin/contact' => ['Admin\\ContactController', 'index'],
    'POST /admin/contact/save' => ['Admin\\ContactController', 'save'],
];