<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Medoo\Medoo;

/**
 * 设置服务类
 * 处理设置相关的业务规则和高级操作
 */
class SettingService
{
    public function __construct(
        private readonly Setting $settingModel,
        private readonly Medoo $db
    ) {
    }

    /**
     * 验证并保存设置
     * 
     * @param array $settings 设置数据
     * @return bool 是否成功
     */
    public function saveSettings(array $settings): bool
    {
        // 应用业务规则
        $validatedSettings = $this->applyBusinessRules($settings);
        
        // 批量保存
        return $this->settingModel->setBatch($validatedSettings);
    }

    /**
     * 应用业务规则
     * 
     * @param array $settings 设置数据
     * @return array 应用规则后的设置数据
     */
    public function applyBusinessRules(array $settings): array
    {
        $result = $settings;
        
        // 规则1: 如果设置了新的logo，可能需要删除旧的logo文件
        if (isset($result['site_logo']) && !empty($result['site_logo'])) {
            // 这里可以添加逻辑来处理旧logo文件的清理
            // 暂时只做简单处理
        }
        
        // 规则2: 确保关键设置项不为空
        if (isset($result['site_name']) && empty(trim($result['site_name']))) {
            unset($result['site_name']); // 或者使用默认值
        }
        
        if (isset($result['site_title']) && empty(trim($result['site_title']))) {
            unset($result['site_title']); // 或者使用默认值
        }
        
        // 规则3: 对某些敏感设置进行额外验证
        if (isset($result['site_icp']) && strlen($result['site_icp']) > 100) {
            $result['site_icp'] = substr($result['site_icp'], 0, 100);
        }
        
        if (isset($result['site_beian']) && strlen($result['site_beian']) > 100) {
            $result['site_beian'] = substr($result['site_beian'], 0, 100);
        }
        
        // 规则4: 确保URL格式正确
        if (isset($result['site_beian_url']) && !empty($result['site_beian_url'])) {
            if (!filter_var($result['site_beian_url'], FILTER_VALIDATE_URL)) {
                unset($result['site_beian_url']); // 无效URL则不保存
            }
        }
        
        return $result;
    }

    /**
     * 获取站点配置
     * 
     * @return array 站点配置
     */
    public function getSiteConfig(): array
    {
        $settings = $this->settingModel->getAll();
        
        return [
            'site_name' => $settings['site_name'] ?? '默认网站名',
            'site_title' => $settings['site_title'] ?? '默认网站标题',
            'site_keywords' => $settings['site_keywords'] ?? '',
            'site_description' => $settings['site_description'] ?? '',
            'site_logo' => $settings['site_logo'] ?? '',
            'site_favicon' => $settings['site_favicon'] ?? '',
            'site_copyright' => $settings['site_copyright'] ?? '',
            'site_icp' => $settings['site_icp'] ?? '',
            'site_beian' => $settings['site_beian'] ?? '',
            'site_beian_url' => $settings['site_beian_url'] ?? ''
        ];
    }

    /**
     * 更新单个设置
     * 
     * @param string $key 设置键
     * @param string $value 设置值
     * @return bool 是否成功
     */
    public function updateSetting(string $key, string $value): bool
    {
        // 应用业务规则到单个设置
        $processedValue = $this->applyRuleToSingleSetting($key, $value);
        
        if ($processedValue === null) {
            return false; // 无效值
        }
        
        return $this->settingModel->set($key, $processedValue);
    }

    /**
     * 应用规则到单个设置
     * 
     * @param string $key 设置键
     * @param string $value 设置值
     * @return string|null 处理后的值，如果无效则返回null
     */
    private function applyRuleToSingleSetting(string $key, string $value): ?string
    {
        switch ($key) {
            case 'site_name':
            case 'site_title':
                if (empty(trim($value))) {
                    return null; // 不能为空
                }
                if (strlen($value) > 100) {
                    return substr($value, 0, 100); // 截断到100字符
                }
                return $value;
                
            case 'site_icp':
            case 'site_beian':
                if (strlen($value) > 100) {
                    return substr($value, 0, 100); // 截断到100字符
                }
                return $value;
                
            case 'site_beian_url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return null; // 无效URL
                }
                return $value;
                
            default:
                // 对于其他设置，限制长度
                if (strlen($value) > 1000) {
                    return substr($value, 0, 1000); // 截断到1000字符
                }
                return $value;
        }
    }
}