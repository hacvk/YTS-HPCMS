<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Service\CaptchaService;

/**
 * 后台登录控制器
 */
class LoginController extends BaseController
{
    /**
     * View 实例
     *
     * @var View
     */
    protected View $view;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->view = new View();

        // 如果已登录且当前请求不是退出登录，则直接跳转到后台首页
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($this->isLogged() && !str_contains($uri, '/logout')) {
            $this->redirect('/admin/dashboard');
            exit;
        }
    }

    /**
     * 登录页面
     *
     * @return void
     */
    public function index(): void
    {
        // 生成 CSRF Token
        $csrfToken = Security::generateCsrfToken();

        $this->view->render('admin/login', [
            'title' => '后台登录',
            'csrf_token' => $csrfToken,
            'system' => App::getConfig('system', []),
        ]);
    }

    /**
     * 处理登录请求
     *
     * @return void
     */
    /**
     * 生成验证码图片
     * 控制器只负责输出 HTTP Header 和图片内容
     *
     * @return void
     */
    public function captcha(): void
    {
        // 使用 CaptchaService 生成验证码
        $captchaService = new CaptchaService();
        $result = $captchaService->generate();

        // 保存验证码到 Session（大写方便比较）
        $_SESSION['admin_login_captcha'] = $result['code'];

        // 输出 HTTP Header
        header('Content-Type: ' . $result['mimeType']);
        
        // 输出图片内容
        echo $result['image'];
        exit;
    }

    public function login(): void
    {
        // 只接受 POST 请求
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('请求方法错误');
        }

        // 获取输入数据
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';
        $captcha   = strtoupper(trim($_POST['captcha'] ?? ''));

        // 验证验证码
        if (empty($captcha) || ($captcha !== ($_SESSION['admin_login_captcha'] ?? ''))) {
            $this->error('验证码错误');
        }

        // 验证 CSRF Token
        if (!Security::checkCsrfToken($csrfToken)) {
            $this->error('CSRF Token 验证失败');
        }

        // 验证输入
        if (empty($username)) {
            $this->error('请输入用户名');
        }

        if (empty($password)) {
            $this->error('请输入密码');
        }

        try {
            $db = Database::get();

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
                $this->error('用户名或密码错误');
            }

            // 检查账号状态
            if ($admin['status'] != 1) {
                $this->error('账号已被禁用');
            }

            // 验证密码
            if (!Security::passwordVerify($password, $admin['password'])) {
                $this->error('用户名或密码错误');
            }

            // 检查密码是否需要重新哈希
            if (Security::passwordNeedsRehash($admin['password'])) {
                $newHash = Security::passwordHash($password);
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
                'last_login_ip' => $this->getClientIp(),
                'login_count' => $currentCount + 1
            ], [
                'id' => $admin['id']
            ]);

            // 写入 Session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'] ?? $admin['username'];
            $_SESSION['admin_login_time'] = time();

            // 记录登录日志
            $this->log('登录成功', 'login', $admin['id']);

            // 跳转到后台首页
            $this->success('登录成功', '/admin/dashboard');

        } catch (\Exception $e) {
            $this->error('登录失败：' . $e->getMessage());
        }
    }

    /**
     * 退出登录
     *
     * @return void
     */
    public function logout(): void
    {
        $adminId = $_SESSION['admin_id'] ?? 0;

        // 记录退出日志
        $this->log('退出登录', 'logout', $adminId);

        // 清空 Session
        session_unset();
        session_destroy();

        // 跳转到登录页
        $this->redirect('/admin');
    }

    /**
     * 检查是否已登录
     *
     * @return bool
     */
    private function isLogged(): bool
    {
        return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
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
            $db = Database::get();
            $db->insert('sys_log', [
                'admin_id' => $adminId,
                'admin_name' => $_SESSION['admin_name'] ?? '',
                'action' => $action,
                'message' => $message,
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'create_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            error_log('日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取客户端IP
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $ip = '';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * 返回成功响应
     *
     * @param string $message 消息
     * @param string|null $url 跳转URL
     * @return void
     */
    private function success(string $message, ?string $url = null): void
    {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'code' => 0,
                'message' => $message,
                'url' => $url ?? ''
            ], JSON_UNESCAPED_UNICODE);
        } else {
            if ($url) {
                $this->redirect($url);
            } else {
                echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>提示</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
        .box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #28a745; margin-bottom: 20px; }
        p { color: #666; }
        a { color: #007bff; text-decoration: none; margin-top: 20px; display: inline-block; }
    </style>
</head>
<body>
    <div class="box">
        <h2>✓ ' . htmlspecialchars($message) . '</h2>';
                if ($url) {
                    echo '<p>正在跳转...</p>
        <script>setTimeout(function() { window.location.href = "' . htmlspecialchars($url) . '"; }, 1000);</script>';
                }
                echo '</div>
</body>
</html>';
            }
        }
        exit;
    }

    /**
     * 返回错误响应
     *
     * @param string $message 错误消息
     * @return void
     */
    private function error(string $message): void
    {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'code' => 1,
                'message' => $message
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>错误</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
        .box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #dc3545; margin-bottom: 20px; }
        a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <h2>✗ ' . htmlspecialchars($message) . '</h2>
        <p><a href="javascript:history.back()">返回</a></p>
    </div>
</body>
</html>';
        }
        exit;
    }

    /**
     * 页面跳转
     *
     * @param string $url 目标URL
     * @param int $statusCode HTTP状态码
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * 判断是否为 AJAX 请求
     *
     * @return bool
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
