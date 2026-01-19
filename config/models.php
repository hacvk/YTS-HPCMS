<?php
/**
 * 模型配置文件
 * 定义不同栏目类型对应的扩展字段
 * 
 * 注意：配置键必须与 App\Enums\CategoryType 枚举值匹配
 * 
 * @return array<string, array{name: string, fields: array<string, array{type: string, label: string, options?: array<string, string>}>}>
 */
return [
    // 文章模型 (CategoryType::Article->value)
    'article' => [
        'name' => '文章',
        'fields' => [
            'author' => [
                'type' => 'text',
                'label' => '作者',
            ],
            'source' => [
                'type' => 'text',
                'label' => '来源',
            ],
            'is_channel' => [
                'type' => 'checkbox',
                'label' => '是否频道',
                'help' => '勾选后此栏目将作为频道，不允许直接添加文章，只能添加子分类',
            ],
        ],
    ],
    
    // 单页模型 (CategoryType::Page->value)
    'page' => [
        'name' => '单页',
        'fields' => [
            'is_channel' => [
                'type' => 'checkbox',
                'label' => '是否频道',
                'help' => '勾选后此栏目将作为频道，不允许直接添加文章，只能添加子分类',
            ],
        ],
    ],
    
    // 图文模型 (CategoryType::Pic->value)
    'pic' => [
        'name' => '图文',
        'fields' => [
            'is_channel' => [
                'type' => 'checkbox',
                'label' => '是否频道',
                'help' => '勾选后此栏目将作为频道，不允许直接添加文章，只能添加子分类',
            ],
        ],
    ],

    // 专家模型 (CategoryType::Doctor->value)
    'doctor' => [
        'name' => '专家',
        'fields' => [
            'zhicheng' => [
                'type' => 'text',
                'label' => '职称',
            ],
            'zhiwu' => [
                'type' => 'text',
                'label' => '任职职务',
            ],
            'keshi' => [
                'type' => 'select',
                'label' => '所属科室',
                'lookup_category' => 9,
                'options' => []
            ],
            'shanchang' => [
                'type' => 'textarea',
                'label' => '擅长',
            ],
            'avatar' => [
                'type' => 'image_crop',
                'label' => '专家照片',
                'width' => 150,
                'height' => 210,
                'locked_aspect_ratio' => true,
                'help' => '建议尺寸：150×210像素'
            ],
            'is_channel' => [
                'type' => 'checkbox',
                'label' => '是否频道',
                'help' => '勾选后此栏目将作为频道，不允许直接添加文章，只能添加子分类',
            ],
        ],
    ],
];
