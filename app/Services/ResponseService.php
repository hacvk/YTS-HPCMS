<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 响应服务类
 * 提供统一的响应处理方法
 */
class ResponseService
{
    /**
     * 统一的成功响应
     *
     * @param string $message 消息
     * @param mixed $data 数据
     * @param string|null $redirect 重定向地址
     * @return void
     */
    public static function jsonSuccess(string $message = '操作成功', mixed $data = null, string $redirect = null): void
    {
        $response = [
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];

        if ($redirect !== null) {
            $response['redirect'] = $redirect;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 统一的错误响应
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param mixed $data 数据
     * @return void
     */
    public static function jsonError(string $message = '操作失败', int $code = 1, mixed $data = null): void
    {
        $response = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 重定向成功响应
     *
     * @param string $redirect 重定向地址
     * @param string $message 消息
     * @param mixed $data 数据
     * @return void
     */
    public static function redirectSuccess(string $redirect, string $message = '操作成功', mixed $data = null): void
    {
        $response = [
            'code' => 0,
            'message' => $message,
            'redirect' => $redirect,
            'data' => $data,
            'timestamp' => time()
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 处理WangEditor上传响应
     *
     * @param array{success: bool, url?: string, message?: string}|null $result 上传结果
     * @param bool $success 是否成功
     * @param string $message 错误消息
     * @return void
     */
    public static function handleWangEditorUploadResponse(array $result = null, bool $success = false, string $message = ''): void
    {
        if ($success && $result && isset($result['url'])) {
            // WangEditor 4.x 期望的格式
            $response = [
                'errno' => 0,
                'data' => [$result['url']]  // 图片URL数组
            ];
        } else {
            // 错误响应
            $response = [
                'errno' => 1,
                'message' => $message ?: ($result['message'] ?? '上传失败')
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}