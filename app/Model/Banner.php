<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

class Banner
{
    private const TABLE = 'sys_banner';

    /**
     * 获取启用的banner列表
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public static function getEnabled(int $limit = 10): array
    {
        try {
            $db = Database::get();
            return $db->select(self::TABLE, '*', [
                'status' => 1,  // 使用 status 字段
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC'],
                'LIMIT' => $limit
            ]);
        } catch (\Throwable $e) {
            error_log('获取banner失败: '.$e->getMessage());
            return [];
        }
    }

    /**
     * 分页列表
     */
    public static function getPage(int $page, int $limit): array
    {
        $db = Database::get();
        $offset = ($page-1)*$limit;
        $list = $db->select(self::TABLE, '*', [
            'ORDER'=>['sort'=>'ASC','id'=>'ASC'],
            'LIMIT'=>[$offset,$limit]
        ]);
        $total = $db->count(self::TABLE);
        return ['list'=>$list,'total'=>$total];
    }

    public static function save(array $data): bool
    {
        $db = Database::get();
        $id = (int)($data['id']??0);
        unset($data['id']);
        if($id>0){
            $db->update(self::TABLE,$data,['id'=>$id]);
        }else{
            $db->insert(self::TABLE,$data);
        }
        return true;
    }

    public static function delete(int $id): bool
    {
        Database::get()->delete(self::TABLE,['id'=>$id]);
        return true;
    }

    public static function getById(int $id): ?array
    {
        return Database::get()->get(self::TABLE,'*',['id'=>$id]) ?: null;
    }
}