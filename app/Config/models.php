<?php
/**
 * 模型配置文件
 * 定义不同栏目类型对应的扩展字段
 * 
 * 注意：配置键必须与 App\Enum\CategoryType 枚举值匹配
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
        ],
    ],
    
    // 单页模型 (CategoryType::Page->value)
    'page' => [
        'name' => '单页',
        'fields' => [],
    ],
    
    // 图文模型 (CategoryType::Pic->value)
    'pic' => [
        'name' => '图文',
        'fields' => [],
    ],
    
    // 专家模型 (CategoryType::Doctor->value)
    'doctor' => [
        'name' => '专家',
        'fields' => [
            'zhicheng' => [
                'type' => 'text',
                'label' => '职称',
            ],
            'keshi' => [
                'type' => 'select',
                'label' => '所属科室',
                'lookup_category' => 9, // 特殊标记：表示选项来自 栏目ID=9 的所有文章
                'options' => [] // 默认为空，由控制器动态填充
            ],
            'shanchang' => [
                'type' => 'textarea',
                'label' => '擅长',
            ],
        ],
    ],
];
