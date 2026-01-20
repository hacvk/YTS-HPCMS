<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Exception\DatabaseOperationException;
use App\Core\Exception\RecordNotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Security;
use App\Enums\ContentStatus;
use App\Models\Content;

/**
 * 内容服务类
 * 处理内容相关的业务逻辑
 */
class ContentService
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Content $contentModel 内容模型实例
     * @param \Medoo\Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Content $contentModel,
        private readonly \Medoo\Medoo $db
    ) {
    }

    /**
     * 保存内容（包含验证、格式化、业务规则处理）
     *
     * @param array<string, mixed> $data 内容数据
     * @param int|null $adminId 管理员ID（用于日志记录）
     * @param string|null $adminUsername 管理员用户名（用于日志记录）
     * @return int 保存成功返回ID
     * @throws ValidationException 当数据验证失败时抛出
     * @throws DatabaseOperationException 当保存失败时抛出
     */
    public function save(array $data, ?int $adminId = null, ?string $adminUsername = null): int
    {
        $this->validate($data, $adminId, $adminUsername);

        $data = $this->format($data);

        $data = $this->processExtraFields($data);

        $result = $this->performSave($data);

        $this->logOperation($result, $data, $adminId, $adminUsername);

        return $result;
    }

    /**
     * 验证文章数据
     *
     * @param array<string, mixed> $data 待验证的数据
     * @throws ValidationException 验证失败时抛出
     */
    private function validate(array $data, ?int $adminId = null, ?string $adminUsername = null): void
    {
        $title = $data['title'] ?? '';
        if (empty(trim($title))) {
            throw new ValidationException('标题不能为空');
        }

        $categoryId = $data['category_id'] ?? 0;
        if ($categoryId <= 0) {
            throw new ValidationException('请选择栏目');
        }

        $content = $data['content'] ?? '';
        if (empty(trim($content))) {
            LogService::record(
                $adminId ?? 0,
                $adminUsername ?? 'system',
                'warning',
                "文章内容为空: {$title}",
                "保存文章警告：文章内容为空"
            );
        }
    }

    /**
     * 格式化文章数据
     *
     * @param array<string, mixed> $data 待格式化的数据
     * @return array<string, mixed> 格式化后的数据
     */
    private function format(array $data): array
    {
        if (isset($data['title'])) {
            $data['title'] = Security::clean(trim((string)$data['title']));
        }

        if (isset($data['cover_img'])) {
            $data['cover_img'] = Security::clean(trim((string)$data['cover_img']));
        }

        if (isset($data['summary'])) {
            $data['summary'] = Security::clean(trim((string)$data['summary']));
        }

        if (isset($data['published_at'])) {
            $data['published_at'] = $this->formatPublishedAt((string)$data['published_at']);
        } else {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * 格式化发布时间
     *
     * @param string $publishedAt 原始发布时间
     * @return string 格式化后的发布时间
     */
    private function formatPublishedAt(string $publishedAt): string
    {
        if (!empty($publishedAt)) {
            $publishedAt = str_replace('T', ' ', $publishedAt);
            if (strlen($publishedAt) == 16) {
                $publishedAt .= ':00';
            }
            return $publishedAt;
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * 处理扩展字段
     *
     * @param array<string, mixed> $data 待处理的数据
     * @return array<string, mixed> 处理后的数据
     */
    private function processExtraFields(array $data): array
    {
        $excludeFields = [
            'id', 'category_id', 'title', 'cover_img', 'summary',
            'content', 'status', 'published_at', 'is_top', 'is_image_news'
        ];

        foreach ($data as $key => $value) {
            if (!in_array($key, $excludeFields, true) && !is_array($value)) {
                $data[$key] = Security::clean(trim((string)$value));
            }
        }

        return $data;
    }

    /**
     * 执行保存操作
     *
     * @param array<string, mixed> $data 格式化后的数据
     * @return int 保存成功返回ID
     * @throws DatabaseOperationException 保存失败时抛出
     */
    private function performSave(array $data): int
    {
        try {
            return $this->saveInternal($data);
        } catch (\PDOException $e) {
            $errorMsg = $e->getMessage();
            $errorInfo = $e->errorInfo ?? [];
            error_log('保存内容失败 (PDO): ' . $errorMsg);
            error_log('PDO错误信息: ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
            error_log('保存内容堆栈: ' . $e->getTraceAsString());
            $dataForLog = $data;
            if (isset($dataForLog['content'])) {
                $dataForLog['content'] = substr($dataForLog['content'], 0, 100) . '...';
            }
            error_log('保存内容数据: ' . json_encode($dataForLog, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            throw new DatabaseOperationException('保存内容失败 (PDO): ' . $errorMsg, 0, $e);
        } catch (DatabaseOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            error_log('保存内容失败: ' . $errorMsg);
            error_log('保存内容堆栈: ' . $e->getTraceAsString());
            $dataForLog = $data;
            if (isset($dataForLog['content'])) {
                $dataForLog['content'] = substr($dataForLog['content'], 0, 100) . '...';
            }
            error_log('保存内容数据: ' . json_encode($dataForLog, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            throw new DatabaseOperationException('保存内容失败: ' . $errorMsg, 0, $e);
        }
    }

    /**
     * 内部保存方法
     *
     * @param array<string, mixed> $data 数据
     * @return int 文章ID
     */
    private function saveInternal(array $data): int
    {
        $standardFields = [];
        $extendedFields = [];

        $modelFields = [];
        if (isset($data['category_id']) && $data['category_id'] > 0) {
            $categoryId = (int)$data['category_id'];
            $category = $this->db->get('sys_category', ['type'], ['id' => $categoryId]);
            if ($category && isset($category['type'])) {
                $categoryType = $category['type'];
                $modelsFile = \App\Core\App::getConfigPath('models.php');
                if (file_exists($modelsFile)) {
                    $models = require $modelsFile;
                    if (isset($models[$categoryType]) && isset($models[$categoryType]['fields'])) {
                        $modelFields = array_keys($models[$categoryType]['fields']);
                    }
                }
            }
        }

        $tableFields = [
            'id', 'category_id', 'title', 'cover_img', 'summary', 'content',
            'views', 'status', 'published_at', 'created_at', 'updated_at', 'is_top', 'is_image_news', 'attributes'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $tableFields, true)) {
                $standardFields[$key] = $value;
            } else {
                if (empty($modelFields) || in_array($key, $modelFields, true)) {
                    $extendedFields[$key] = $value;
                }
            }
        }

        if (!empty($extendedFields)) {
            $standardFields['attributes'] = $extendedFields;
        } else {
            $standardFields['attributes'] = null;
        }

        $now = date('Y-m-d H:i:s');
        if (isset($data['id']) && $data['id'] > 0) {
            $standardFields['updated_at'] = $now;
            $this->contentModel->update((int)$data['id'], $standardFields);
            return (int)$data['id'];
        } else {
            $standardFields['created_at'] = $now;
            $standardFields['updated_at'] = $now;

            if (!isset($standardFields['views'])) {
                $standardFields['views'] = 0;
            }
            if (!isset($standardFields['status'])) {
                $standardFields['status'] = ContentStatus::Published->value;
            } else {
                $statusValue = is_int($standardFields['status'])
                    ? $standardFields['status']
                    : (int)$standardFields['status'];
                $statusEnum = ContentStatus::tryFrom($statusValue);
                if ($statusEnum === null) {
                    $standardFields['status'] = ContentStatus::Published->value;
                } else {
                    $standardFields['status'] = $statusEnum->value;
                }
            }

            unset($standardFields['id']);

            return $this->contentModel->insert($standardFields);
        }
    }

    /**
     * 记录操作日志
     *
     * @param int $articleId 文章ID
     * @param array<string, mixed> $data 保存的数据
     * @param int|null $adminId 管理员ID
     * @param string|null $adminUsername 管理员用户名
     */
    private function logOperation(int $articleId, array $data, ?int $adminId, ?string $adminUsername): void
    {
        $action = ($data['id'] ?? 0) > 0 ? 'update' : 'create';
        $title = $data['title'] ?? '未知';
        $message = ($data['id'] ?? 0) > 0
            ? "编辑文章: {$title} (ID: {$articleId})"
            : "新建文章: {$title} (ID: {$articleId})";

        LogService::record(
            $adminId ?? 0,
            $adminUsername ?? 'system',
            $action,
            "文章ID: {$articleId}",
            $message
        );
    }

    /**
     * 获取单篇文章
     * 注意：Model 中的 JsonFieldTrait 已自动处理 JSON 字段反序列化
     *
     * @param int $id 文章ID
     * @return array<string, mixed> 返回文章数据
     * @throws RecordNotFoundException 当文章不存在时抛出异常
     */
    public function getOne(int $id): array
    {
        // Model 的 getById 方法已通过 JsonFieldTrait 自动反序列化 attributes 字段
        // attributes 中的扩展字段已自动合并到主数组，attributes 字段本身已被移除
        return $this->contentModel->getById($id);
    }

    /**
     * 根据栏目ID获取内容（用于单页管理）
     * 注意：Model 中的 JsonFieldTrait 已自动处理 JSON 字段反序列化
     *
     * @param int $categoryId 栏目ID
     * @return array<string, mixed> 返回内容数据
     * @throws RecordNotFoundException 当内容不存在时抛出异常
     */
    public function getByCategoryId(int $categoryId): array
    {
        // Model 的 getByCategoryId 方法已通过 JsonFieldTrait 自动反序列化 attributes 字段
        // attributes 中的扩展字段已自动合并到主数组，attributes 字段本身已被移除
        return $this->contentModel->getByCategoryId($categoryId);
    }

    /**
     * 获取上一篇文章
     * 注意：返回的数据已通过 JsonFieldTrait 自动处理 JSON 字段反序列化
     *
     * @param int $currentId 当前文章ID
     * @param int $categoryId 栏目ID
     * @return array<string, mixed>|null 返回上一篇文章数据，不存在则返回 null
     */
    public function getPrevArticle(int $currentId, int $categoryId): ?array
    {
        try {
            $article = $this->db->get(
                'sys_content',
                '*',
                [
                    'AND' => [
                        'id[<]' => $currentId,
                        'category_id' => $categoryId,
                        'status' => 1
                    ],
                    'ORDER' => ['id' => 'DESC']
                ]
            );

            if (!$article) {
                return null;
            }

            // 使用 Model 的方法处理 JSON 字段反序列化
            // 注意：这里直接使用 db->get，需要手动处理 attributes
            if (isset($article['attributes']) && !empty($article['attributes'])) {
                $attributes = is_string($article['attributes'])
                    ? json_decode($article['attributes'], true)
                    : $article['attributes'];

                if (is_array($attributes)) {
                    unset($article['attributes']);
                    $article = array_merge($article, $attributes);
                }
            }

            return $article;
        } catch (\Throwable $e) {
            error_log('获取上一篇文章失败 ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取下一篇文章
     * 注意：返回的数据已通过 JsonFieldTrait 自动处理 JSON 字段反序列化
     *
     * @param int $currentId 当前文章ID
     * @param int $categoryId 栏目ID
     * @return array<string, mixed>|null 返回下一篇文章数据，不存在则返回 null
     */
    public function getNextArticle(int $currentId, int $categoryId): ?array
    {
        try {
            $article = $this->db->get(
                'sys_content',
                '*',
                [
                    'AND' => [
                        'id[>]' => $currentId,
                        'category_id' => $categoryId,
                        'status' => 1
                    ],
                    'ORDER' => ['id' => 'ASC']
                ]
            );

            if (!$article) {
                return null;
            }

            // 使用 Model 的方法处理 JSON 字段反序列化
            // 注意：这里直接使用 db->get，需要手动处理 attributes
            if (isset($article['attributes']) && !empty($article['attributes'])) {
                $attributes = is_string($article['attributes'])
                    ? json_decode($article['attributes'], true)
                    : $article['attributes'];

                if (is_array($attributes)) {
                    unset($article['attributes']);
                    $article = array_merge($article, $attributes);
                }
            }

            return $article;
        } catch (\Throwable $e) {
            error_log('获取下一篇文章失败 ' . $e->getMessage());
            return null;
        }
    }
}
