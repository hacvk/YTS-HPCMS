<?php
declare(strict_types=1);

namespace App\Models;

use Medoo\Medoo;

/**
 * 设置模型（数据访问对象）
 * 只负责数据库 CRUD 操作，不包含业务逻辑
 */
class Setting
{
    /**
     * 表名
     */
    private const TABLE = 'sys_setting';

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
     * 获取所有设置
     *
     * @return array<string, string>
     */
    public function getAll(): array
    {
        try {
            $settings = $this->db->select(self::TABLE, ['key', 'value']);
            
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting['key']] = $setting['value'];
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('获取设置失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取单个设置值
     *
     * @param string $key 设置键名
     * @param string $default 默认值
     * @return string
     */
    public function get(string $key, string $default = ''): string
    {
        try {
            $value = $this->db->get(self::TABLE, 'value', ['key' => $key]);
            
            return $value ?: $default;
        } catch (\Exception $e) {
            error_log('获取设置失败: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * 设置单个设置值
     *
     * @param string $key 设置键名
     * @param string $value 设置值
     * @return bool
     */
    public function set(string $key, string $value): bool
    {
        try {
            // 检查设置是否存在
            $exists = $this->db->has(self::TABLE, ['key' => $key]);
            
            if ($exists) {
                // 更新
                $this->db->update(self::TABLE, [
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['key' => $key]);
            } else {
                // 插入
                $this->db->insert(self::TABLE, [
                    'key' => $key,
                    'value' => $value,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('保存设置失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量设置
     *
     * @param array<string, string> $settings 设置数组
     * @return bool
     */
    public function setBatch(array $settings): bool
    {
        try {
            foreach ($settings as $key => $value) {
                $exists = $this->db->has(self::TABLE, ['key' => $key]);
                
                if ($exists) {
                    $this->db->update(self::TABLE, [
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['key' => $key]);
                } else {
                    $this->db->insert(self::TABLE, [
                        'key' => $key,
                        'value' => $value,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('批量保存设置失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除设置
     *
     * @param string $key 设置键名
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            $this->db->delete(self::TABLE, ['key' => $key]);
            return true;
        } catch (\Exception $e) {
            error_log('删除设置失败: ' . $e->getMessage());
            return false;
        }
    }
}
