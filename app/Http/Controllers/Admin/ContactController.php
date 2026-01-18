<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\Security;
use App\Models\Setting;
use App\Services\LogService;

/**
 * 联系方式设置控制器
 */
class ContactController extends AdminBaseController
{
    public function __construct(
        private readonly Setting $setting
    ) {
        parent::__construct();
    }

    /**
     * 联系方式设置页面
     *
     * @return void
     */
    public function index(): void
    {
        try {
            // 获取所有设置
            $settings = $this->setting->getAll();
            
            // 定义联系方式设置项
            $contactFields = [
                'contact_email' => ['label' => '联系邮箱', 'type' => 'email'],
                'contact_phone' => ['label' => '服务热线', 'type' => 'text'],
                'contact_report_phone' => ['label' => '举报电话', 'type' => 'text'],
                'contact_online_service' => ['label' => '客服电话', 'type' => 'text'],
                'contact_address' => ['label' => '联系地址', 'type' => 'textarea'],
                'contact_zip' => ['label' => '邮编', 'type' => 'text'],
                'contact_traffic' => ['label' => '乘车方式', 'type' => 'textarea'],
                'contact_org_platform' => ['label' => '机构平台链接', 'type' => 'text'],
                'contact_wechat_qr' => ['label' => '微信二维码', 'type' => 'image'],
                'contact_qr_code' => ['label' => '通用二维码', 'type' => 'image'],
            ];
            
            $this->render('admin/contact/index', [
                'title' => '联系方式设置',
                'settings' => $settings,
                'contact_fields' => $contactFields,
            ]);
        } catch (\Exception $e) {
            error_log('加载联系方式设置页面失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 保存联系方式设置
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
                    "保存联系方式设置失败",
                    "保存联系方式设置失败"
                );
                $this->errorJson('保存失败');
                return;
            }
            
            // 记录成功日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'update',
                "更新联系方式设置",
                "更新联系方式设置"
            );
            
            $this->successJson('保存成功', null, '/admin/contact');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "保存联系方式设置异常",
                "保存联系方式设置异常: {$errorMsg}"
            );
            error_log('保存联系方式设置失败: ' . $errorMsg);
            $this->errorJson('保存失败: ' . $errorMsg);
        }
    }
}