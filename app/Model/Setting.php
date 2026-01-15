<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * 设置模型
 */
class Setting
{
    /**
     * 表名
     */
    private const TABLE = 'sys_setting';

    /**
     * 获取所有设置
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        try {
            $db = Database::get();
            
            $settings = $db->select(self::TABLE, ['key', 'value']);
            
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
    public static function get(string $key, string $default = ''): string
    {
        try {
            $db = Database::get();
            
            $value = $db->get(self::TABLE, 'value', ['key' => $key]);
            
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
    public static function set(string $key, string $value): bool
    {
        try {
            $db = Database::get();
            
            // 检查设置是否存在
            $exists = $db->has(self::TABLE, ['key' => $key]);
            
            if ($exists) {
                // 更新
                $db->update(self::TABLE, [
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['key' => $key]);
            } else {
                // 插入
                $db->insert(self::TABLE, [
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
    public static function setBatch(array $settings): bool
    {
        try {
            $db = Database::get();
            
            foreach ($settings as $key => $value) {
                $exists = $db->has(self::TABLE, ['key' => $key]);
                
                if ($exists) {
                    $db->update(self::TABLE, [
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['key' => $key]);
                } else {
                    $db->insert(self::TABLE, [
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
    public static function delete(string $key): bool
    {
        try {
            $db = Database::get();
            $db->delete(self::TABLE, ['key' => $key]);
            return true;
        } catch (\Exception $e) {
            error_log('删除设置失败: ' . $e->getMessage());
            return false;
        }
    }
}
