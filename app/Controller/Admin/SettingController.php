<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Core\Security;
use App\Model\Setting;
use App\Service\LogService;

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
                        'site_keywords' => ['label' => '网站关键词', 'type' => 'text'],
                        'site_description' => ['label' => '网站描述', 'type' => 'textarea'],
                    ]
                ],
                'logo' => [
                    'title' => 'Logo设置',
                    'fields' => [
                        'site_logo' => ['label' => '网站Logo', 'type' => 'image'],
                        'site_favicon' => ['label' => '网站图标', 'type' => 'image'],
                    ]
                ],
                'contact' => [
                    'title' => '联系信息',
                    'fields' => [
                        'site_email' => ['label' => '联系邮箱', 'type' => 'email'],
                        'site_phone' => ['label' => '联系电话', 'type' => 'text'],
                        'site_address' => ['label' => '联系地址', 'type' => 'textarea'],
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
        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }
        
        try {
            // 验证CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token验证失败');
                return;
            }
            
            // 获取所有POST数据（排除csrf_token）
            $settings = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'csrf_token') {
                    $settings[$key] = Security::clean($value);
                }
            }
            
            // 批量保存设置
            $result = $this->setting->setBatch($settings);
            
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
}
