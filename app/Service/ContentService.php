<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\DatabaseOperationException;
use App\Core\Exception\RecordNotFoundException;
use App\Enum\ContentStatus;
use App\Model\Content;
use PDOException;

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
     * 保存内容，智能分离标准字段和扩展字段
     *
     * @param array<string, mixed> $data 内容数据
     * @return int 保存成功返回ID
     * @throws DatabaseOperationException 当保存失败时抛出异常
     */
    public function save(array $data): int
    {
        try {
            // 分离标准字段和扩展字段
            $standardFields = [];
            $extendedFields = [];
            
            // 获取模型配置，用于过滤扩展字段
            $modelFields = [];
            if (isset($data['category_id']) && $data['category_id'] > 0) {
                $categoryId = (int)$data['category_id'];
                // 获取栏目信息
                $category = $this->db->get('sys_category', ['type'], ['id' => $categoryId]);
                if ($category && isset($category['type'])) {
                    $categoryType = $category['type'];
                    // 读取模型配置文件
                    $modelsFile = \App\Core\App::getConfigPath('models.php');
                    if (file_exists($modelsFile)) {
                        $models = require $modelsFile;
                        if (isset($models[$categoryType]) && isset($models[$categoryType]['fields'])) {
                            $modelFields = array_keys($models[$categoryType]['fields']);
                        }
                    }
                }
            }
            
            // 标准字段列表
            $tableFields = [
                'id', 'category_id', 'title', 'cover_img', 'summary', 'content', 
                'views', 'status', 'published_at', 'created_at', 'updated_at', 'attributes'
            ];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $tableFields, true)) {
                    // 标准字段直接保存
                    $standardFields[$key] = $value;
                } else {
                    // 扩展字段：只保存模型配置中定义的字段
                    if (empty($modelFields) || in_array($key, $modelFields, true)) {
                        $extendedFields[$key] = $value;
                    }
                }
            }
            
            // 处理attributes字段（表中已有此字段）
            // 注意：将扩展字段作为数组放入 attributes，Model 的 JsonFieldTrait 会自动序列化为 JSON
            if (!empty($extendedFields)) {
                $standardFields['attributes'] = $extendedFields;
            } else {
                // 如果没有扩展字段，清空attributes
                $standardFields['attributes'] = null;
            }
            
            // 设置时间字段
            $now = date('Y-m-d H:i:s');
            if (isset($data['id']) && $data['id'] > 0) {
                // 更新
                $standardFields['updated_at'] = $now;
                $this->contentModel->update((int)$data['id'], $standardFields);
                return (int)$data['id'];
            } else {
                // 添加
                $standardFields['created_at'] = $now;
                $standardFields['updated_at'] = $now;
                
                // 确保必填字段存在
                if (!isset($standardFields['views'])) {
                    $standardFields['views'] = 0;
                }
                if (!isset($standardFields['status'])) {
                    // 使用枚举默认值：已发布
                    $standardFields['status'] = ContentStatus::Published->value;
                } else {
                    // 验证并转换状态值
                    $statusValue = is_int($standardFields['status']) 
                        ? $standardFields['status'] 
                        : (int)$standardFields['status'];
                    $statusEnum = ContentStatus::tryFrom($statusValue);
                    if ($statusEnum === null) {
                        // 如果状态值无效，使用默认值
                        $standardFields['status'] = ContentStatus::Published->value;
                    } else {
                        $standardFields['status'] = $statusEnum->value;
                    }
                }
                
                // 移除 id 字段（如果是新增）
                unset($standardFields['id']);
                
                // 插入新记录
                return $this->contentModel->insert($standardFields);
            }
            
        } catch (\PDOException $e) {
            $errorMsg = $e->getMessage();
            $errorInfo = $e->errorInfo ?? [];
            error_log('保存内容失败 (PDO): ' . $errorMsg);
            error_log('PDO错误信息: ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
            error_log('保存内容堆栈: ' . $e->getTraceAsString());
            // 记录数据信息（不记录完整内容，避免日志过大）
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
            // 记录数据信息（不记录完整内容，避免日志过大）
            $dataForLog = $data;
            if (isset($dataForLog['content'])) {
                $dataForLog['content'] = substr($dataForLog['content'], 0, 100) . '...';
            }
            error_log('保存内容数据: ' . json_encode($dataForLog, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            throw new DatabaseOperationException('保存内容失败: ' . $errorMsg, 0, $e);
        }
    }

    /**
     * 获取单篇文章
     * 注意：Model 的 JsonFieldTrait 已自动处理 JSON 字段反序列化
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
     * 注意：Model 的 JsonFieldTrait 已自动处理 JSON 字段反序列化
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
}
