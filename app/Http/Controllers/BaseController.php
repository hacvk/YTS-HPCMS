<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\View;

/**
 * 控制器基类
 */
abstract class BaseController
{
    /**
     * View 实例
     *
     * @var View
     */
    protected View $view;

    /**
     * 视图数据
     *
     * @var array<string, mixed>
     */
    protected array $viewData = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->view = new View();
    }

    /**
     * 渲染视图
     *
     * @param string $template 模板名称
     * @param array<string, mixed> $data 模板数据
     * @return void
     */
    protected function render(string $template, array $data = []): void
    {
        $mergedData = array_merge($this->viewData, $data);
        $this->view->render($template, $mergedData);
    }

    /**
     * 渲染视图并返回字符串
     *
     * @param string $template 模板名称
     * @param array<string, mixed> $data 模板数据
     * @return string
     */
    protected function renderToString(string $template, array $data = []): string
    {
        $mergedData = array_merge($this->viewData, $data);
        return $this->view->renderToString($template, $mergedData);
    }

    /**
     * 设置视图数据
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return self
     */
    protected function assign(string $key, mixed $value): self
    {
        $this->viewData[$key] = $value;
        return $this;
    }

    /**
     * 批量设置视图数据
     *
     * @param array<string, mixed> $data 数据数组
     * @return self
     */
    protected function assignAll(array $data): self
    {
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    /**
     * 重定向
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
     * 返回 JSON 响应
     *
     * @param array<string, mixed> $data 数据
     * @param int $code 状态码
     * @return void
     */
    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 返回成功 JSON 响应
     *
     * @param string $message 消息
     * @param mixed $data 数据
     * @param string|null $url 跳转URL
     * @return void
     */
    protected function successJson(string $message, mixed $data = null, ?string $url = null): void
    {
        $response = [
            'code' => 0,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($url !== null) {
            $response['url'] = $url;
        }

        $this->json($response);
    }

    /**
     * 返回错误 JSON 响应
     *
     * @param string $message 错误消息
     * @param int $code 错误码
     * @return void
     */
    protected function errorJson(string $message, int $code = 1): void
    {
        $this->json([
            'code' => $code,
            'message' => $message,
        ]);
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

    /**
     * 判断是否为 POST 请求
     *
     * @return bool
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * 判断是否为 GET 请求
     *
     * @return bool
     */
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * 获取 POST 参数
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * 获取 GET 参数
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * 获取请求参数（GET 或 POST）
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function param(string $key, mixed $default = null): mixed
    {
        return $_REQUEST[$key] ?? $default;
    }
}
