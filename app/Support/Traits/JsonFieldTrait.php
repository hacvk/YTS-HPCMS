<?php
declare(strict_types=1);

namespace App\Support\Traits;

/**
 * JSON 字段处理 Trait
 * 自动处理模型读取/保存时的 JSON 序列化与反序列化
 * 
 * 使用说明�?
 * 使用�?Trait 的类必须定义 protected array $jsonFields 属�?
 * 例如：protected array $jsonFields = ['attributes'];
 * 
 * 注意：Trait 中不定义此属性，避免与使用类的属性定义冲�?
 */
trait JsonFieldTrait
{

    /**
     * 序列�?JSON 字段（保存到数据库前�?
     * 将数�?对象转换�?JSON 字符�?
     *
     * @param array<string, mixed> $data 数据数组
     * @return array<string, mixed> 处理后的数据数组
     */
    protected function serializeJsonFields(array $data): array
    {
        foreach ($this->jsonFields as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                // 如果字段存在且不是字符串，则序列化为 JSON
                if ($data[$field] === null || $data[$field] === '') {
                    $data[$field] = null;
                } else {
                    $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }
        return $data;
    }

    /**
     * 反序列化 JSON 字段（从数据库读取后�?
     * �?JSON 字符串转换为数组/对象
     *
     * @param array<string, mixed>|false $data 数据数组
     * @return array<string, mixed>|false 处理后的数据数组
     */
    protected function deserializeJsonFields(array|false $data): array|false
    {
        if ($data === false) {
            return false;
        }

        foreach ($this->jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && !empty($data[$field])) {
                $decoded = json_decode($data[$field], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // 成功解码且是数组，则替换原字�?
                    unset($data[$field]);
                    // 将解码后的字段合并到主数�?
                    $data = array_merge($data, $decoded);
                } else {
                    // 解码失败或不是数组，保持原�?
                    // 可以选择记录错误日志
                    error_log("JSON 字段 {$field} 解码失败: " . json_last_error_msg());
                }
            } elseif (isset($data[$field]) && ($data[$field] === null || $data[$field] === '')) {
                // 空值或 null，直接移�?
                unset($data[$field]);
            }
        }
        return $data;
    }

    /**
     * 批量反序列化 JSON 字段（用于列表查询）
     *
     * @param array<int, array<string, mixed>> $dataList 数据列表
     * @return array<int, array<string, mixed>> 处理后的数据列表
     */
    protected function deserializeJsonFieldsBatch(array $dataList): array
    {
        $result = [];
        foreach ($dataList as $data) {
            $deserialized = $this->deserializeJsonFields($data);
            if ($deserialized !== false) {
                $result[] = $deserialized;
            }
        }
        return $result;
    }
}
