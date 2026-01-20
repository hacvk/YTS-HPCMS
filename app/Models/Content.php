<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Exception\DatabaseOperationException;
use App\Core\Exception\RecordNotFoundException;
use App\Support\Traits\JsonFieldTrait;
use Medoo\Medoo;

/**
 * 内容模型（数据访问对象）
 * 只负责数据库 CRUD 操作，不包含业务逻辑
 */
class Content
{
    use JsonFieldTrait;

    /**
     * 表名
     */
    public const TABLE = 'sys_content';

    /**
     * JSON 字段列表（attributes 字段存储扩展字段 JSON）
     */
    protected array $jsonFields = ['attributes'];

    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Medoo $db
    ) {
    }

    /**
     * 获取文章列表，关联栏目表
     * 使用 Medoo 的 join 语法，避免原生 SQL 拼接
     *
     * @param int $page 页码
     * @param int $limit 每页条数
     * @param array<string, mixed> $where 查询条件
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function getList(int $page, int $limit, array $where = []): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // 构建 Medoo 查询条件
            // 注意：在 join 查询中，WHERE 条件应该使用表别名
            $conditions = [];
            
            // 处理栏目ID条件（使用别名）
            if (isset($where['cid']) && $where['cid'] > 0) {
                $conditions['content.category_id'] = (int)$where['cid'];
            }
            
            // 处理多个栏目ID条件
            if (isset($where['cids']) && is_array($where['cids']) && !empty($where['cids'])) {
                $conditions['content.category_id'] = $where['cids'];
            }
            
            // 处理关键词搜索条件（使用别名）
            if (isset($where['keyword']) && !empty($where['keyword'])) {
                $conditions['content.title[~]'] = $where['keyword'];
            }
            
            // 使用 Medoo 的 join 方法进行关联查询
            // Medoo join 语法: '[>]table(alias)' => ['left_field' => 'right_field']
            // 注意：join 条件中的字段名不需要表前缀，Medoo 会自动处理
            $list = $this->db->select(
                self::TABLE . '(content)',
                [
                    '[>]sys_category(category)' => ['category_id' => 'id']
                ],
                [
                    'content.id',
                    'content.category_id',
                    'content.title',
                    'content.cover_img',
                    'content.summary',
                    'content.status',
                    'content.views',
                    'content.published_at',
                    'content.is_top',
                    'content.is_image_news',
                    'content.attributes', // 选择 attributes 字段用于 JSON 反序列化
                    'category.name(category_name)'
                ],
                array_merge($conditions, [
                    'ORDER' => ['content.id' => 'DESC'],
                    'LIMIT' => [$offset, $limit]
                ])
            );
            
            // 检查 Medoo 错误
            if ($this->db->error) {
                error_log('Medoo查询错误: ' . json_encode($this->db->error, JSON_UNESCAPED_UNICODE));
                error_log('Medoo错误信息: ' . json_encode($this->db->errorInfo ?? [], JSON_UNESCAPED_UNICODE));
            }
            
            // 批量反序列化 JSON 字段（attributes 字段）
            if (!empty($list) && is_array($list)) {
                $list = $this->deserializeJsonFieldsBatch($list);
            } else {
                $list = [];
            }
            
            // 获取总数（Medoo 的 count 方法不支持 join，需要单独查询主表）
            // 但我们需要应用相同的 WHERE 条件
            $countConditions = [];
            if (isset($where['cid']) && $where['cid'] > 0) {
                $countConditions['category_id'] = (int)$where['cid'];
            }
            
            // 处理多个栏目ID条件的计数
            if (isset($where['cids']) && is_array($where['cids']) && !empty($where['cids'])) {
                $countConditions['category_id'] = $where['cids'];
            }
            if (isset($where['keyword']) && !empty($where['keyword'])) {
                $countConditions['title[~]'] = $where['keyword'];
            }
            $total = $this->db->count(self::TABLE, $countConditions);
            
            return [
                'list' => $list,
                'total' => $total
            ];
            
        } catch (\Exception $e) {
            error_log('获取文章列表失败: ' . $e->getMessage());
            error_log('错误堆栈: ' . $e->getTraceAsString());
            return [
                'list' => [],
                'total' => 0
            ];
        }
    }

    /**
     * 插入新记录
     * 自动处理 JSON 字段序列化
     *
     * @param array<string, mixed> $data 数据
     * @return int 返回ID
     * @throws DatabaseOperationException 当插入失败时抛出异常
     */
    public function insert(array $data): int
    {
        try {
            // 验证栏目是否允许添加文章（如果不是频道）
            if (isset($data['category_id'])) {
                if (!$this->validateCategoryForArticle((int)$data['category_id'])) {
                    throw new DatabaseOperationException('所选栏目已被设为频道，不允许直接添加文章，请添加子分类');
                }
            }
            
            // 自动序列化 JSON 字段
            $data = $this->serializeJsonFields($data);
            
            // 确保新字段存在，防止插入时出错
            if (!isset($data['is_top'])) {
                $data['is_top'] = 0;
            }
            if (!isset($data['is_image_news'])) {
                $data['is_image_news'] = 0;
            }
            
            $result = $this->db->insert(self::TABLE, $data);
            
            // 检查是否有错误
            if ($this->db->error) {
                $errorMsg = 'Medoo插入错误: ' . $this->db->error;
                error_log($errorMsg);
                error_log('Medoo错误信息: ' . json_encode($this->db->errorInfo, JSON_UNESCAPED_UNICODE));
                throw new DatabaseOperationException($errorMsg);
            }
            
            // Medoo 的 insert 返回 PDOStatement，需要获取 lastInsertId
            if ($result) {
                $insertId = (int)$this->db->id();
                if ($insertId > 0) {
                    return $insertId;
                } else {
                    // 即使无法获取ID，也尝试查询最后插入的记录
                    $lastArticle = $this->db->get(self::TABLE, 'id', ['ORDER' => ['id' => 'DESC']]);
                    if ($lastArticle) {
                        return (int)$lastArticle;
                    }
                }
            }
            
            throw new DatabaseOperationException('插入内容失败：无法获取插入ID');
        } catch (DatabaseOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log('插入内容失败: ' . $e->getMessage());
            throw new DatabaseOperationException('插入内容失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 更新记录
     * 自动处理 JSON 字段序列化
     *
     * @param int $id 记录ID
     * @param array<string, mixed> $data 数据
     * @return void
     * @throws DatabaseOperationException 当更新失败时抛出异常
     */
    public function update(int $id, array $data): void
    {
        try {
            // 验证栏目是否允许添加文章（如果不是频道）
            if (isset($data['category_id'])) {
                if (!$this->validateCategoryForArticle((int)$data['category_id'])) {
                    throw new DatabaseOperationException('所选栏目已被设为频道，不允许直接添加文章，请添加子分类');
                }
            }
            
            // 自动序列化 JSON 字段
            $data = $this->serializeJsonFields($data);
            
            // 确保新字段存在，防止更新时出错
            if (!isset($data['is_top'])) {
                $data['is_top'] = 0;
            }
            if (!isset($data['is_image_news'])) {
                $data['is_image_news'] = 0;
            }
            
            $this->db->update(self::TABLE, $data, ['id' => $id]);
            
            // 检查是否有错误
            if ($this->db->error) {
                $errorMsg = 'Medoo更新错误: ' . $this->db->error;
                error_log($errorMsg);
                throw new DatabaseOperationException($errorMsg);
            }
        } catch (DatabaseOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log('更新内容失败: ' . $e->getMessage());
            throw new DatabaseOperationException('更新内容失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 根据ID获取记录
     * 自动处理 JSON 字段反序列化
     *
     * @param int $id 记录ID
     * @return array<string, mixed> 返回记录数组
     * @throws RecordNotFoundException 当记录不存在时抛出异常
     */
    public function getById(int $id): array
    {
        try {
            $article = $this->db->get(
                self::TABLE,
                [
                    'id',
                    'category_id',
                    'title',
                    'cover_img',
                    'summary',
                    'content',
                    'views',
                    'status',
                    'published_at',
                    'created_at',
                    'updated_at',
                    'is_top',
                    'is_image_news',
                    'attributes'
                ],
                ['id' => $id]
            );
            
            if (empty($article)) {
                throw new RecordNotFoundException("内容记录不存在(ID: {$id})");
            }
            
            // 自动反序列化 JSON 字段
            $result = $this->deserializeJsonFields($article);
            if ($result === false) {
                throw new RecordNotFoundException("内容记录不存在(ID: {$id})");
            }
            
            return $result;
        } catch (RecordNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log('获取内容失败: ' . $e->getMessage());
            throw new RecordNotFoundException("获取内容失败: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 根据栏目ID获取内容（用于单页管理）
     * 自动处理 JSON 字段反序列化
     *
     * @param int $categoryId 栏目ID
     * @return array<string, mixed> 返回记录数组
     * @throws RecordNotFoundException 当记录不存在时抛出异常
     */
    public function getByCategoryId(int $categoryId): array
    {
        try {
            $content = $this->db->get(
                self::TABLE,
                [
                    'id',
                    'category_id',
                    'title',
                    'cover_img',
                    'summary',
                    'content',
                    'views',
                    'status',
                    'published_at',
                    'created_at',
                    'updated_at',
                    'is_top',
                    'is_image_news',
                    'attributes'
                ],
                ['category_id' => $categoryId]
            );
            
            if (empty($content)) {
                throw new RecordNotFoundException("栏目内容不存在(Category ID: {$categoryId})");
            }
            
            // 自动反序列化 JSON 字段
            $result = $this->deserializeJsonFields($content);
            if ($result === false) {
                throw new RecordNotFoundException("栏目内容不存在(Category ID: {$categoryId})");
            }
            
            return $result;
        } catch (RecordNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log('根据栏目ID获取内容失败: ' . $e->getMessage());
            throw new RecordNotFoundException("根据栏目ID获取内容失败: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * 验证栏目是否允许添加文章
     * 如果栏目被设为频道，则不允许添加文章
     *
     * @param int $categoryId 栏目ID
     * @return bool 如果允许添加文章返回true，否则返回false
     */
    public function validateCategoryForArticle(int $categoryId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }
        
        // 检查栏目是否为频道
        $category = $this->db->get('sys_category', ['is_channel'], ['id' => $categoryId]);
        
        if ($category) {
            // 如果是频道，不允许添加文章
            return $category['is_channel'] != 1;
        }
        
        // 栏目不存在，默认允许（但这不应该发生）
        return true;
    }
    
    /**
     * 统计文章数量
     *
     * @param array<string, mixed> $where 查询条件
     * @return int
     */
    public function count(array $where = []): int
    {
        try {
            return $this->db->count(self::TABLE, $where);
        } catch (\Exception $e) {
            error_log('统计文章数量失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 删除记录
     *
     * @param int $id 记录ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->delete(self::TABLE, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('删除内容失败: ' . $e->getMessage());
            return false;
        }
    }
}
