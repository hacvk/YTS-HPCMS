<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\Security;
use App\Models\Setting;
use App\Services\LogService;

/**
 * 基本设置控制器
 */
class SettingController extends AdminBaseController
{
    public function __construct(
        private readonly Setting $setting
    ) {
        parent::__construct();
    }

    /**
     * 设置页面
     *
     * @return void
     */
    public function index(): void
    {
        try {
            // 获取所有设置
            $settings = $this->setting->getAll();
            
            // 定义设置项分组
            $settingGroups = [
                'basic' => [
                    'title' => '基本信息',
                    'fields' => [
                        'site_name' => ['label' => '网站名称', 'type' => 'text', 'required' => true],
                        'site_title' => ['label' => '网站标题', 'type' => 'text', 'required' => true],
                        'site_keywords' => ['label' => '网站关键字', 'type' => 'text'],
                        'site_description' => ['label' => '网站描述', 'type' => 'textarea'],
                    ]
                ],
                'admin' => [
                    'title' => '后台设置',
                    'fields' => [
                        'admin_path' => ['label' => '后台入口路径', 'type' => 'text', 'help' => '请输入后台入口路径，如：/admin 或 /manager，必须以/开头'],
                    ]
                ],
                'logo' => [
                    'title' => 'Logo设置',
                    'fields' => [
                        'site_logo' => ['label' => '网站Logo', 'type' => 'image'],
                        'site_favicon' => ['label' => '网站图标', 'type' => 'image'],
                    ]
                ],

                'footer' => [
                    'title' => '页脚信息',
                    'fields' => [
                        'site_copyright' => ['label' => '版权信息', 'type' => 'text'],
                        'site_icp' => ['label' => 'ICP备案号', 'type' => 'text'],
                        'site_beian' => ['label' => '公安备案号', 'type' => 'text'],
                        'site_beian_url' => ['label' => '公安备案号查询链接', 'type' => 'text', 'help' => '例如：https://www.beian.gov.cn/portal/registerSystemInfo?recordcode=您的备案号'],
                    ]
                ],
            ];
            
            $this->render('admin/setting/index', [
                'title' => '基本设置',
                'settings' => $settings,
                'setting_groups' => $settingGroups,
            ]);
        } catch (\Exception $e) {
            error_log('加载设置页面失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 保存设置
     *
     * @return void
     */
    public function save(): void
    {
        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }
            
            // 获取所有POST数据（排除csrf_token）
            $rawSettings = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'csrf_token') {
                    $rawSettings[$key] = $value;
                }
            }
            
            // 使用 SettingValidationService 处理数据验证和清理，并应用业务规则
            $processed = \App\Services\SettingValidationService::processBatchSettingsWithBusinessRules($rawSettings);
            
            if (!$processed['success']) {
                LogService::record(
                    $this->getAdminId(),
                    $this->getAdminUsername(),
                    'error',
                    "保存设置失败",
                    "保存系统设置验证失败: " . json_encode($processed['errors'] ?? [], JSON_UNESCAPED_UNICODE)
                );
                
                $errorMessage = '保存失败: ';
                if (isset($processed['errors'])) {
                    $errorMessage .= implode(', ', array_values($processed['errors']));
                } else {
                    $errorMessage .= $processed['message'] ?? '未知错误';
                }
                
                $this->errorJson($errorMessage);
                return;
            }
            
            $settings = $processed['settings'];
            
            // 检查是否有图片设置更新，如果有，需要处理旧图片的数据库记录
            $db = \App\Core\Database::get();
            
            // 检查是否更新了 site_logo 或 site_favicon
            $oldSiteLogo = $this->setting->get('site_logo');
            $oldSiteFavicon = $this->setting->get('site_favicon');
            
            // 批量保存设置
            $result = $this->setting->setBatch($settings);
            
            // 如果设置了新的 logo 或 favicon，且之前有旧的，则删除旧文件和数据库记录
            if ($result) {
                if (isset($settings['site_logo']) && !empty($oldSiteLogo) && $settings['site_logo'] !== $oldSiteLogo) {
                    // 删除旧 logo 文件
                    $relativePath = ltrim($oldSiteLogo, '/');
                    if (preg_match('/^uploads\/images\//', $relativePath)) {
                        \App\Services\FileService::deletePhysicalFile($relativePath);
                        // 同时删除 sys_files 表中的记录
                        $db->delete('sys_files', ['path' => $relativePath]);
                    }
                }
                
                if (isset($settings['site_favicon']) && !empty($oldSiteFavicon) && $settings['site_favicon'] !== $oldSiteFavicon) {
                    // 删除旧 favicon 文件
                    $relativePath = ltrim($oldSiteFavicon, '/');
                    if (preg_match('/^uploads\/images\//', $relativePath)) {
                        \App\Services\FileService::deletePhysicalFile($relativePath);
                        // 同时删除 sys_files 表中的记录
                        $db->delete('sys_files', ['path' => $relativePath]);
                    }
                }
            }
            
            if (!$result) {
                LogService::record(
                    $this->getAdminId(),
                    $this->getAdminUsername(),
                    'error',
                    "保存设置失败",
                    "保存系统设置失败"
                );
                $this->errorJson('保存失败');
                return;
            }
            
            // 记录成功日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'update',
                "更新系统设置",
                "更新基本设置"
            );
            
            $this->successJson('保存成功', null, '/admin/setting');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "保存设置异常",
                "保存系统设置异常: {$errorMsg}"
            );
            error_log('保存设置失败: ' . $errorMsg);
            $this->errorJson('保存失败: ' . $errorMsg);
        }
    }
    
    /**
     * 删除图片
     *
     * @return void
     */
    public function deleteImage(): void
    {
        try {
            error_log('deleteImage方法开始执行');
            error_log(date('Y-m-d H:i:s') . ' - deleteImage方法开始执行');
            
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                error_log('CSRF验证失败');
                return;
            }
            
            $fieldName = $this->post('field_name', '');
            error_log('收到的字段名: ' . $fieldName);
            
            // 验证字段名是否合法
            $allowedFields = ['site_logo', 'site_favicon'];
            if (!in_array($fieldName, $allowedFields, true)) {
                error_log('字段名不合法: ' . $fieldName);
                $this->errorJson('不允许删除此字段的图片');
                return;
            }
            
            // 获取当前设置值
            $currentValue = $this->setting->get($fieldName);
            error_log('当前设置值: ' . $currentValue);
            
            if (!empty($currentValue)) {
                // 尝试删除物理文件
                $relativePath = ltrim($currentValue, '/');
                error_log('准备删除物理文件路径: ' . $relativePath);
                
                if (preg_match('/^uploads\/images\//', $relativePath)) {
                    // 使用FileService删除物理文件
                    $result = \App\Services\FileService::deletePhysicalFile($relativePath);
                    error_log('物理文件删除结果: ' . ($result ? '成功' : '失败') . ', 路径: ' . $relativePath);
                    if (!$result) {
                        error_log('删除物理文件失败: ' . $relativePath);
                    }
                } else {
                    error_log('文件路径不符合预期格式，不执行物理删除: ' . $relativePath);
                }
                
                // 同时删除 sys_files 表中的记录
                try {
                    $db = \App\Core\Database::get();
                    $db->delete('sys_files', ['path' => $relativePath]);
                    error_log('从 sys_files 表中删除记录: ' . $relativePath);
                } catch (\Exception $e) {
                    error_log('删除 sys_files 表记录失败: ' . $e->getMessage());
                    // 继续执行，不中断流程
                }
            }
            
            // 清空设置值
            $result = $this->setting->set($fieldName, '');
            error_log('数据库更新结果: ' . ($result ? '成功' : '失败'));
            if (!$result) {
                error_log('删除设置失败');
                $this->errorJson('删除设置失败');
                return;
            }
            
            // 记录日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "删除设置图片",
                "删除{$fieldName}图片"
            );
            
            error_log('deleteImage方法执行成功');
            $this->successJson('图片删除成功');
        } catch (\Exception $e) {
            error_log('删除图片失败: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->errorJson('删除图片失败: ' . $e->getMessage());
        }
    }
}
