<?php
declare(strict_types=1);

namespace App\Models;

use Medoo\Medoo;

class FriendLink
{
    private const TABLE = 'sys_friend_link';

    public function __construct(
        private readonly Medoo $db
    ) {
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function getPage(int $page, int $limit, array $where = []): array
    {
        try {
            $offset = ($page - 1) * $limit;

            $cond = $where;
            $cond['ORDER'] = ['sort' => 'ASC', 'id' => 'ASC'];
            $cond['LIMIT'] = [$offset, $limit];

            $list = $this->db->select(self::TABLE, '*', $cond);
            $total = $this->db->count(self::TABLE, $where);

            return [
                'list' => $list ?: [],
                'total' => (int)$total,
            ];
        } catch (\Throwable $e) {
            error_log('获取友情链接列表失败: ' . $e->getMessage());
            return ['list' => [], 'total' => 0];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            return $this->db->get(self::TABLE, '*', ['id' => $id]) ?: null;
        } catch (\Throwable $e) {
            error_log('获取友情链接失败: ' . $e->getMessage());
            return null;
        }
    }

    public function insert(array $data): int|false
    {
        try {
            $this->db->insert(self::TABLE, $data);
            $id = (int)$this->db->id();
            return $id > 0 ? $id : false;
        } catch (\Throwable $e) {
            error_log('插入友情链接失败: ' . $e->getMessage());
            return false;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $this->db->update(self::TABLE, $data, ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            error_log('更新友情链接失败: ' . $e->getMessage());
            return false;
        }
    }

    public function save(array $data): bool
    {
        $id = (int)($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            return $this->update($id, $data);
        }

        return $this->insert($data) !== false;
    }

    public function delete(int $id): bool
    {
        try {
            $this->db->delete(self::TABLE, ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            error_log('删除友情链接失败: ' . $e->getMessage());
            return false;
        }
    }
}
