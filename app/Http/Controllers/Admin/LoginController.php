<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Core\App;
use App\Core\Security;
use App\Core\View;
use App\Core\Database;
use App\Services\CaptchaService;
use App\Services\SessionService;
use App\Services\ValidationService;

/**
 * 后台登录控制器
 */
class LoginController extends BaseController
{
    public function __construct(
        private readonly \Medoo\Medoo $db,
        private readonly CaptchaService $captchaService,
        private readonly \App\Services\SecurityService $securityService
    ) {
        parent::__construct();
        $this->view = new View();

        // 如果已登录且当前请求不是退出登录，则直接跳转到后台首页
        // 注意：只在访问登录页面时检查，避免在登录处理时干扰
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($uri === '/admin' && SessionService::isLoggedIn()) {
            $this->redirect('/admin/dashboard');
            exit;
        }
    }

    /**
     * View 实例
     *
     * @var View
     */
    protected View $view;

    /**
     * 登录页面
     *
     * @return void
     */
    public function index(): void
    {
        // 生成 CSRF Token
        $csrfToken = $this->securityService->generateCsrfToken();

        $this->view->render('admin/login', [
            'title' => '后台登录',
            'csrf_token' => $csrfToken,
            'system' => App::getConfig('system', []),
        ]);
    }

    /**
     * 生成验证码图片
     * 控制器只负责输出 HTTP Header 和图片内容
     *
     * @return void
     */
    public function captcha(): void
    {
        // 使用 CaptchaService 生成验证码
        $result = $this->captchaService->generate();

        // 保存验证码到 Session（大写方便比较）
        $_SESSION['admin_login_captcha'] = $result['code'];

        // 输出 HTTP Header
        header('Content-Type: ' . $result['mimeType']);
        
        // 输出图片内容
        echo $result['image'];
        exit;
    }

    /**
     * 登录处理
     *
     * @return void
     */
    public function login(): void
    {
        try {
            // 只接受POST 请求
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorJson('请求方法错误');
            }

            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!$this->securityService->checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            // 获取并清理输入
            $username = trim($this->post('username', ''));
            $password = $this->post('password', '');
            $captcha   = strtoupper(trim($this->post('captcha', '')));

            // 验证必填字段
            if (empty($username) || empty($password)) {
                $this->errorJson('用户名和密码不能为空');
            }

            // 验证用户名格式
            if (!ValidationService::validateUsername($username)) {
                $this->errorJson('用户名格式不正确（只能包含字母、数字、下划线，3-20位）');
            }

            // 验证密码强度
            if (!ValidationService::validatePasswordStrength($password, 1)) {
                $this->errorJson('密码长度至少8位');
            }

            // 验证验证码
            $sessionCaptcha = SessionService::get('admin_login_captcha', '');
            if (empty($captcha) || $captcha !== $sessionCaptcha) {
                $this->errorJson('验证码错误');
            }

            // 清理验证码 Session
            SessionService::delete('admin_login_captcha');

            $db = $this->db;

            // 查询管理员
            $admin = $db->get('sys_admin', [
                'id',
                'username',
                'password',
                'name',
                'status',
                'last_login_time',
                'login_count'
            ], [
                'username' => $username
            ]);

            // 检查管理员是否存在
            if (!$admin) {
                // 防止用户名枚举攻击（统一返回错误信息）
                $this->errorJson('用户名或密码错误');
            }

            // 检查账号状态
            if ($admin['status'] != 1) {
                $this->errorJson('账号已被禁用');
            }

            // 验证密码
            if (!$this->securityService->passwordVerify($password, $admin['password'])) {
                $this->errorJson('用户名或密码错误');
            }

            // 检查密码是否需要重新哈希
            if ($this->securityService->passwordNeedsRehash($admin['password'])) {
                $newHash = $this->securityService->passwordHash($password);
                $db->update('sys_admin', [
                    'password' => $newHash
                ], [
                    'id' => $admin['id']
                ]);
            }

            // 更新登录信息
            $currentCount = $admin['login_count'] ?? 0;
            $db->update('sys_admin', [
                'last_login_time' => date('Y-m-d H:i:s'),
                'last_login_ip' => SessionService::getClientIp(),
                'login_count' => $currentCount + 1
            ], [
                'id' => $admin['id']
            ]);

            // 写入 Session（使用 SessionService）
            SessionService::set('admin_id', $admin['id']);
            SessionService::set('admin_username', $admin['username']);
            SessionService::set('admin_name', $admin['name'] ?? $admin['username']);
            SessionService::set('admin_login_time', time());

            // 重新生成 Session ID（防止会话固定攻击）
            SessionService::regenerateId();

            // 记录登录日志
            $this->log('登录成功', 'login', $admin['id']);

            // 返回成功 JSON
            $this->successJson('登录成功', null, '/admin/dashboard');

        } catch (\Exception $e) {
            // 记录详细错误日志
            \App\Services\LogService::error($e, 'login_failed');
            
            // 根据环境返回适当的错误消息
            if (\App\Core\App::isDebug()) {
                $this->errorJson('登录失败：' . $e->getMessage());
            } else {
                $this->errorJson('登录失败，请稍后重试');
            }
        }
    }

    /**
     * 退出登录
     *
     * @return void
     */
    public function logout(): void
    {
        $adminId = SessionService::get('admin_id', 0);

        // 记录退出日志
        $this->log('退出登录', 'logout', $adminId);

        // 安全地销毁 Session
        SessionService::destroy();

        // 跳转到登录页
        $this->redirect('/admin');
    }

    /**
     * 记录日志
     *
     * @param string $message 日志消息
     * @param string $action 操作类型
     * @param int $adminId 管理员ID
     * @return void
     */
    private function log(string $message, string $action = '', int $adminId = 0): void
    {
        try {
            $db = $this->db;
            $db->insert('sys_log', [
                'admin_id' => $adminId,
                'admin_name' => SessionService::get('admin_name', ''),
                'action' => $action,
                'message' => $message,
                'ip' => SessionService::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'create_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            error_log('日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证必填字段
     *
     * @param array $data 数据
     * @param array $fields 字段列表
     *
     * @return void
     */
    private function validateRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->errorJson('缺少必填字段: ' . $field);
            }
        }
    }
}
