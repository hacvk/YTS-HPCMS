<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Exception\PathTraversalException;
use App\Http\ResponseContext;
use App\Models\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * 前台文件控制器
 * 负责受控文件下载，并统计下载次数
 */
class FileController extends BaseController
{
    /**
     * 文件下载
     * 路由：GET /file/download/{id}
     *
     * @return void
     */
    public function download(): void
    {
        $id = (int)$this->get('id', 0);
        if ($id <= 0) {
            $this->sendNotFound('无效的文件ID');
            return;
        }

        try {
            $db = Database::get();
            $fileModel = new File($db);
            $file = $fileModel->getById($id);

            if (!$file) {
                $this->sendNotFound('文件不存在');
                return;
            }

            $relativePath = (string)($file['path'] ?? '');
            if ($relativePath === '') {
                $this->sendNotFound('文件路径无效');
                return;
            }

            $publicDir = dirname(__DIR__, 3) . '/public';
            $uploadsDir = $publicDir . '/uploads';

            // 构建物理路径
            $relativePath = ltrim($relativePath, '/');
            $filePath = $publicDir . '/' . $relativePath;

            // 使用 realpath 防御路径遍历
            $realPath = realpath($filePath);
            if ($realPath === false || !is_file($realPath)) {
                $this->sendNotFound('文件不存在');
                return;
            }

            $allowedBaseDir = realpath($uploadsDir);
            if ($allowedBaseDir === false || strpos($realPath, $allowedBaseDir) !== 0) {
                throw new PathTraversalException('路径遍历攻击检测：尝试访问不允许的目录。路径 ' . $relativePath);
            }

            // 更新下载次数（忽略错误）
            try {
                $db->update('sys_files', [
                    'download_count[+]' => 1,
                ], [
                    'id' => $id,
                ]);
            } catch (\Throwable $e) {
                if ($e instanceof \App\Http\ResponseHaltException) {
                    throw $e;
                }
                error_log('[FileController] 更新下载次数失败: ' . $e->getMessage());
            }

            $mimeType = (string)($file['mime_type'] ?? '');
            if ($mimeType === '') {
                $mimeType = 'application/octet-stream';
            }

            $originalName = (string)($file['original_name'] ?? 'download');
            $encodedName = rawurlencode($originalName);

            $response = new BinaryFileResponse($realPath);
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $originalName, $encodedName);
            ResponseContext::set($response);
            return;
        } catch (PathTraversalException $e) {
            error_log('[FileController] 路径遍历攻击检测: ' . $e->getMessage());
            $response = new Response('非法请求', 400);
            ResponseContext::set($response);
            return;
        } catch (\Throwable $e) {
            if ($e instanceof \App\Http\ResponseHaltException) {
                throw $e;
            }
            error_log('[FileController] 文件下载失败: ' . $e->getMessage());
            $response = new Response('文件下载失败', 500);
            ResponseContext::set($response);
            return;
        }
    }

    /**
     * 404 响应封装
     *
     * @param string $message
     * @return void
     */
    private function sendNotFound(string $message = '文件不存在'): void
    {
        $response = new Response($message, 404);
        ResponseContext::set($response);
    }
}
