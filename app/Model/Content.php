<?php
declare(strict_types=1);

namespace App\Model;

use Medoo\Medoo;

/**
 * 内容模型（数据访问对象）
 * 只负责数据库的 CRUD 操作，不包含业务逻辑
 */
class Content
{
    /**
     * 表名
     */
    private const TABLE = 'sys_content';

    /**
     * 数据库实例
     */
    private Medoo $db;

    /**
     * 构造函数
     *
     * @param Medoo $db 数据库实例
     */
    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    /**
     * 获取文章列表，关联栏目表
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
            
            $stmt = $this->db->pdo->prepare($sql);
            $stmt->execute($params);
            $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // 获取总数
            $countSql = "
                SELECT COUNT(*) FROM " . self::TABLE . " content
                WHERE 1=1 $whereClause
            ";
            
            $stmt = $this->db->pdo->prepare($countSql);
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
     * 插入新记录
     *
     * @param array<string, mixed> $data 数据
     * @return int|false 返回ID，失败返回false
     */
    public function insert(array $data): int|false
    {
        try {
            $result = $this->db->insert(self::TABLE, $data);
            
            // 检查是否有错误
            if ($this->db->error) {
                error_log('Medoo插入错误: ' . $this->db->error);
                error_log('Medoo错误信息: ' . json_encode($this->db->errorInfo, JSON_UNESCAPED_UNICODE));
                return false;
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
            return false;
        } catch (\Exception $e) {
            error_log('插入内容失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新记录
     *
     * @param int $id 记录ID
     * @param array<string, mixed> $data 数据
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            $this->db->update(self::TABLE, $data, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('更新内容失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据ID获取记录
     *
     * @param int $id 记录ID
     * @return array<string, mixed>|false
     */
    public function getById(int $id): array|false
    {
        try {
            $article = $this->db->get(
                self::TABLE,
                '*',
                ['id' => $id]
            );
            
            return $article ?: false;
        } catch (\Exception $e) {
            error_log('获取内容失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据栏目ID获取内容（用于单页管理）
     *
     * @param int $categoryId 栏目ID
     * @return array<string, mixed>|false
     */
    public function getByCategoryId(int $categoryId): array|false
    {
        try {
            $content = $this->db->get(
                self::TABLE,
                '*',
                ['category_id' => $categoryId]
            );
            
            return $content ?: false;
        } catch (\Exception $e) {
            error_log('根据栏目ID获取内容失败: ' . $e->getMessage());
            return false;
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
