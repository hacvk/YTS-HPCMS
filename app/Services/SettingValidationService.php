<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;

/**
 * 设置验证服务类
 * 提供设置数据的验证、清理和批量处理功能
 */
class SettingValidationService
{
    /**
     * 验证设置数据
     *
     * @param array $settings 设置数据
     * @return array{valid: bool, errors?: array<string, string>}
     */
    public static function validateSettings(array $settings): array
    {
        $errors = [];
        
        foreach ($settings as $key => $value) {
            // 验证键名格式（只允许字母、数字、下划线、连字符）
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
                $errors[$key] = '设置键名格式不正确，只能包含字母、数字、下划线和连字符';
                continue;
            }
            
            // 根据键名验证不同的值
            switch ($key) {
                case 'site_name':
                case 'site_title':
                    if (empty(trim($value))) {
                        $errors[$key] = '网站名称和标题不能为空';
                    } elseif (strlen($value) > 100) {
                        $errors[$key] = '网站名称和标题不能超过100个字符';
                    }
                    break;
                    
                case 'site_keywords':
                    if (strlen($value) > 500) {
                        $errors[$key] = '网站关键字不能超过500个字符';
                    }
                    break;
                    
                case 'site_description':
                    if (strlen($value) > 1000) {
                        $errors[$key] = '网站描述不能超过1000个字符';
                    }
                    break;
                    
                case 'site_logo':
                case 'site_favicon':
                    // 验证图片路径格式
                    if (!empty($value) && !preg_match('/^\/uploads\/images\/.+$/i', $value)) {
                        $errors[$key] = '图片路径格式不正确';
                    }
                    break;
                    
                case 'site_copyright':
                    if (strlen($value) > 500) {
                        $errors[$key] = '版权信息不能超过500个字符';
                    }
                    break;
                    
                case 'site_icp':
                    if (strlen($value) > 100) {
                        $errors[$key] = 'ICP备案号不能超过100个字符';
                    }
                    break;
                    
                case 'site_beian':
                    if (strlen($value) > 100) {
                        $errors[$key] = '公安备案号不能超过100个字符';
                    }
                    break;
                    
                case 'site_beian_url':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$key] = '公安备案号查询链接格式不正确';
                    }
                    break;
                    
                case 'admin_path':
                    // 验证admin路径格式：必须以/开头，只能包含字母、数字、下划线和连字符
                    if (!empty($value) && (!preg_match('/^\/[_a-zA-Z0-9-]+$/', $value) || strlen($value) > 50)) {
                        $errors[$key] = '后台入口路径格式不正确，必须以/开头，只能包含字母、数字、下划线和连字符，且不超过50个字符';
                    }
                    break;
                    
                default:
                    // 对于其他设置项，进行通用验证
                    if (strlen($value) > 1000) {
                        $errors[$key] = '设置值不能超过1000个字符';
                    }
                    break;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 清理设置数据
     *
     * @param array $settings 设置数据
     * @return array 清理后的设置数据
     */
    public static function sanitizeSettings(array $settings): array
    {
        $sanitized = [];
        
        foreach ($settings as $key => $value) {
            // 清理键名（只允许字母、数字、下划线、连字符）
            $cleanKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
            
            // 清理值
            $cleanValue = Security::clean($value);
            
            // 特殊处理某些字段
            switch ($cleanKey) {
                case 'site_name':
                case 'site_title':
                case 'site_keywords':
                case 'site_description':
                case 'site_copyright':
                case 'site_icp':
                case 'site_beian':
                case 'site_beian_url':
                case 'admin_path':
                    // 这些字段需要去除首尾空白
                    $cleanValue = trim($cleanValue);
                    break;
            }
            
            $sanitized[$cleanKey] = $cleanValue;
        }
        
        return $sanitized;
    }
    
    /**
     * 处理批量设置更新
     *
     * @param array $settings 设置数据
     * @return array{success: bool, message?: string, errors?: array<string, string>}
     */
    public static function processBatchSettings(array $settings): array
    {
        // 1. 清理数据
        $sanitizedSettings = self::sanitizeSettings($settings);
        
        // 2. 验证数据
        $validation = self::validateSettings($sanitizedSettings);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'message' => '设置验证失败'
            ];
        }
        
        // 3. 应用业务规则
        // 确保网站名称和标题不为空
        if (empty($sanitizedSettings['site_name']) || empty($sanitizedSettings['site_title'])) {
            return [
                'success' => false,
                'errors' => [
                    'site_name' => '网站名称不能为空',
                    'site_title' => '网站标题不能为空'
                ],
                'message' => '网站名称和标题不能为空'
            ];
        }
        
        // 4. 返回处理后的设置
        return [
            'success' => true,
            'settings' => $sanitizedSettings
        ];
    }
    
    /**
     * 处理批量设置更新并应用业务规则
     *
     * @param array $settings 设置数据
     * @return array{success: bool, message?: string, errors?: array<string, string>}
     */
    public static function processBatchSettingsWithBusinessRules(array $settings): array
    {
        // 使用 SettingValidationService 进行初步验证和清理
        $processed = self::processBatchSettings($settings);
        
        if (!$processed['success']) {
            return $processed;
        }
        
        // 获取 SettingService 实例来应用更高级的业务规则
        $settingService = new SettingService(
            new \App\Models\Setting(\App\Core\Database::get()),
            \App\Core\Database::get()
        );
        
        // 应用业务规则
        $finalSettings = $settingService->applyBusinessRules($processed['settings']);
        
        return [
            'success' => true,
            'settings' => $finalSettings
        ];
    }
}