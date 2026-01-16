<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use App\Enum\ContentStatus;

/**
 * 内容模型
 */
class Content
{
    /**
     * 表名
     */
    private const TABLE = 'sys_content';
    
    /**
     * 表字段列表
     */
    private const array TABLE_FIELDS = [
        'id', 'category_id', 'title', 'cover_img', 'summary', 'content', 
        'views', 'status', 'published_at', 'created_at', 'updated_at'
    ];

    /**
     * 获取文章列表，关联栏目表
     *
     * @param int $page 页码
     * @param int $limit 每页条数
     * @param array<string, mixed> $where 查询条件
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function getList(int $page, int $limit, array $where = []): array
    {
        try {
            $db = Database::get();
            
            $offset = ($page - 1) * $limit;
            
            // 构建查询条件
            $whereClause = '';
            $params = [];
            
            if (isset($where['cid']) && $where['cid'] > 0) {
                $whereClause .= ' AND content.category_id = :cid';
                $params[':cid'] = $where['cid'];
            }
            
            if (isset($where['keyword']) && !empty($where['keyword'])) {
                $whereClause .= ' AND content.title LIKE :keyword';
                $params[':keyword'] = '%' . $where['keyword'] . '%';
            }
            
            // 查询文章列表，关联栏目表获取栏目名称
            $sql = "
                SELECT 
                    content.id,
                    content.category_id,
                    content.title,
                    content.cover_img,
                    content.summary,
                    content.status,
                    content.views,
                    content.published_at,
                    category.name AS category_name
                FROM " . self::TABLE . " content
                LEFT JOIN sys_category category ON content.category_id = category.id
                WHERE 1=1 $whereClause
                ORDER BY content.id DESC
                LIMIT $offset, $limit
            ";
            
            $stmt = $db->pdo->prepare($sql);
            $stmt->execute($params);
            $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // 获取总数
            $countSql = "
                SELECT COUNT(*) FROM " . self::TABLE . " content
                WHERE 1=1 $whereClause
            ";
            
            $stmt = $db->pdo->prepare($countSql);
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            
            return [
                'list' => $list,
                'total' => $total
            ];
            
        } catch (\Exception $e) {
            error_log('获取文章列表失败: ' . $e->getMessage());
            return [
                'list' => [],
                'total' => 0
            ];
        }
    }

    /**
     * 保存文章，智能分离标准字段和扩展字段
     *
     * @param array<string, mixed> $data 文章数据
     * @return int|false 保存成功返回ID，失败返回false
     */
    public static function save(array $data): int|false
    {
        try {
            $db = Database::get();
            
            // 分离标准字段和扩展字段
            $standardFields = [];
            $extendedFields = [];
            
            // 获取模型配置，用于过滤扩展字段
            $modelFields = [];
            if (isset($data['category_id']) && $data['category_id'] > 0) {
                $categoryId = (int)$data['category_id'];
                // 获取栏目信息
                $category = $db->get('sys_category', ['type'], ['id' => $categoryId]);
                if ($category && isset($category['type'])) {
                    $categoryType = $category['type'];
                    // 读取模型配置文件
                    $modelsFile = dirname(__DIR__, 2) . '/Config/models.php';
                    if (file_exists($modelsFile)) {
                        $models = require $modelsFile;
                        if (isset($models[$categoryType]) && isset($models[$categoryType]['fields'])) {
                            $modelFields = array_keys($models[$categoryType]['fields']);
                        }
                    }
                }
            }
            
            foreach ($data as $key => $value) {
                if (in_array($key, self::TABLE_FIELDS)) {
                    // 标准字段直接保存
                    $standardFields[$key] = $value;
                } else {
                    // 扩展字段：只保存模型配置中定义的字段
                    if (empty($modelFields) || in_array($key, $modelFields)) {
                        $extendedFields[$key] = $value;
                    }
                }
            }
            
            // 处理attributes字段（表中已有此字段）
            if (!empty($extendedFields)) {
                $standardFields['attributes'] = json_encode($extendedFields, JSON_UNESCAPED_UNICODE);
            } else {
                // 如果没有扩展字段，清空attributes
                $standardFields['attributes'] = null;
            }
            
            // 设置时间字段
            $now = date('Y-m-d H:i:s');
            if (isset($data['id']) && $data['id'] > 0) {
                // 更新
                $standardFields['updated_at'] = $now;
                $db->update(self::TABLE, $standardFields, ['id' => $data['id']]);
                return $data['id'];
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
                
                // 调试：记录要插入的数据
                error_log('准备插入文章数据字段: ' . json_encode(array_keys($standardFields), JSON_UNESCAPED_UNICODE));
                
                $result = $db->insert(self::TABLE, $standardFields);
                
                // 检查是否有错误
                if ($db->error) {
                    error_log('Medoo插入错误: ' . $db->error);
                    error_log('Medoo错误信息: ' . json_encode($db->errorInfo, JSON_UNESCAPED_UNICODE));
                    return false;
                }
                
                // Medoo 的 insert 返回 PDOStatement，需要获取 lastInsertId
                if ($result) {
                    $insertId = (int)$db->id();
                    if ($insertId > 0) {
                        error_log('文章插入成功，ID: ' . $insertId);
                        return $insertId;
                    } else {
                        error_log('警告：插入成功但无法获取ID，尝试查询最后插入的记录');
                        // 即使无法获取ID，也尝试查询最后插入的记录
                        $lastArticle = $db->get(self::TABLE, 'id', ['ORDER' => ['id' => 'DESC']]);
                        if ($lastArticle) {
                            return (int)$lastArticle;
                        }
                    }
                }
                error_log('文章插入失败：insert返回false');
                return false;
            }
            
        } catch (\PDOException $e) {
            $errorMsg = $e->getMessage();
            $errorInfo = $e->errorInfo ?? [];
            error_log('保存文章失败 (PDO): ' . $errorMsg);
            error_log('PDO错误信息: ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
            error_log('保存文章堆栈: ' . $e->getTraceAsString());
            // 记录数据信息（不记录完整内容，避免日志过大）
            $dataForLog = $data;
            if (isset($dataForLog['content'])) {
                $dataForLog['content'] = substr($dataForLog['content'], 0, 100) . '...';
            }
            error_log('保存文章数据: ' . json_encode($dataForLog, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            return false;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            error_log('保存文章失败: ' . $errorMsg);
            error_log('保存文章堆栈: ' . $e->getTraceAsString());
            // 记录数据信息（不记录完整内容，避免日志过大）
            $dataForLog = $data;
            if (isset($dataForLog['content'])) {
                $dataForLog['content'] = substr($dataForLog['content'], 0, 100) . '...';
            }
            error_log('保存文章数据: ' . json_encode($dataForLog, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            return false;
        }
    }

    /**
     * 获取单篇文章，自动解析JSON扩展字段
     *
     * @param int $id 文章ID
     * @return array<string, mixed>|false
     */
    public static function getOne(int $id): array|false
    {
        try {
            $db = Database::get();
            
            $article = $db->get(
                self::TABLE,
                '*',
                ['id' => $id]
            );
            
            if (empty($article)) {
                return false;
            }
            
            // 解析JSON扩展字段并合并到主数组
            if (isset($article['attributes']) && !empty($article['attributes'])) {
                $attributes = json_decode($article['attributes'], true);
                if (is_array($attributes)) {
                    unset($article['attributes']);
                    $article = array_merge($article, $attributes);
                }
            }
            
            return $article;
            
        } catch (\Exception $e) {
            error_log('获取文章详情失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据栏目ID获取内容（用于单页管理）
     *
     * @param int $categoryId 栏目ID
     * @return array<string, mixed>|false
     */
    public static function getByCategoryId(int $categoryId): array|false
    {
        try {
            $db = Database::get();
            
            $content = $db->get(
                self::TABLE,
                '*',
                ['category_id' => $categoryId]
            );
            
            if (empty($content)) {
                return false;
            }
            
            // 解析JSON扩展字段并合并到主数组
            if (isset($content['attributes']) && !empty($content['attributes'])) {
                $attributes = json_decode($content['attributes'], true);
                if (is_array($attributes)) {
                    unset($content['attributes']);
                    $content = array_merge($content, $attributes);
                }
            }
            
            return $content;
            
        } catch (\Exception $e) {
            error_log('根据栏目ID获取内容失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除文章
     *
     * @param int $id 文章ID
     * @return bool
     */
    public static function delete(int $id): bool
    {
        try {
            $db = Database::get();
            $db->delete(self::TABLE, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('删除文章失败: ' . $e->getMessage());
            return false;
        }
    }
}