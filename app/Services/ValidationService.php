<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * 验证服务类
 * 提供静态方法进行各种数据验证和清理
 */
class ValidationService
{
    private static ?ValidatorInterface $validator = null;

    private static function getValidator(): ValidatorInterface
    {
        if (self::$validator === null) {
            self::$validator = Validation::createValidator();
        }
        return self::$validator;
    }

    private static function validateWithConstraint(mixed $value, mixed $constraint): bool
    {
        $violations = self::getValidator()->validate($value, $constraint);
        return count($violations) === 0;
    }
    /**
     * 验证必填字段
     *
     * @param array $data 待验证的数据数组
     * @param array $fields 必填字段列表
     * @return array|bool 验证成功返回 true，失败返回包含错误信息的数组
     */
    public static function validateRequired(array $data, array $fields): array|bool
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return [
                'status' => false,
                'message' => '缺少必填字段: ' . implode(', ', $missing),
                'fields' => $missing
            ];
        }

        return true;
    }

    /**
     * 验证数值范围
     *
     * @param mixed $value 待验证的值
     * @param float|int|null $min 最小值
     * @param float|int|null $max 最大值
     * @return bool 验证是否通过
     */
    public static function validateNumeric(mixed $value, float|int $min = null, float|int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $num = (float)$value;

        if ($min !== null && $num < $min) {
            return false;
        }

        if ($max !== null && $num > $max) {
            return false;
        }

        return true;
    }

    /**
     * 清理输入数据（防止 XSS）
     *
     * @param mixed $input 输入数据
     * @return mixed 清理后的数据
     */
    public static function sanitizeInput(mixed $input): mixed
    {
        return Security::clean($input);
    }

    /**
     * 验证文件上传
     *
     * @param array $file $_FILES 中的文件项
     * @param array $allowedExtensions 允许的扩展名列表，如 ['jpg', 'png', 'gif']
     * @param int $maxSize 最大字节数，默认 2MB
     * @return array|bool 验证成功返回 true，失败返回错误信息数组
     */
    public static function validateFileUpload(array $file, array $allowedExtensions = [], int $maxSize = 2097152): array|bool
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['status' => false, 'message' => '无效的文件上传参数'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['status' => false, 'message' => '没有文件被上传'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['status' => false, 'message' => '文件超过最大限制'];
            default:
                return ['status' => false, 'message' => '未知的文件上传错误'];
        }

        // 检查文件大小
        if ($file['size'] > $maxSize) {
            return ['status' => false, 'message' => '文件超过允许的大小'];
        }

        // 检查扩展名
        if (!empty($allowedExtensions)) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) {
                return ['status' => false, 'message' => '不支持的文件格式: ' . $ext];
            }
        }

        return true;
    }

    /**
     * 验证URL格式
     *
     * @param string $url 待验证的URL
     * @return bool 是否为有效的URL
     */
    public static function validateUrlFormat(string $url): bool
    {
        return self::validateWithConstraint($url, new Url());
    }

    /**
     * 验证字符串长度
     *
     * @param string $value 待验证的值
     * @param int|null $min 最小长度
     * @param int|null $max 最大长度
     * @return bool
     */
    public static function validateLength(string $value, ?int $min = null, ?int $max = null): bool
    {
        $constraint = new Length([
            'min' => $min,
            'max' => $max,
        ]);

        return self::validateWithConstraint($value, $constraint);
    }

    /**
     * 验证邮箱格式
     *
     * @param string $email 待验证的邮箱
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        return self::validateWithConstraint($email, new Email());
    }

    /**
     * 验证整数
     *
     * @param mixed $value 待验证的值
     * @param int|null $min 最小值
     * @param int|null $max 最大值
     * @return bool
     */
    public static function validateInt(mixed $value, ?int $min = null, ?int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $intVal = (int)$value;
        
        if ($min !== null && $intVal < $min) {
            return false;
        }
        
        if ($max !== null && $intVal > $max) {
            return false;
        }
        
        return true;
    }

    /**
     * 验证手机号格式（中国）
     *
     * @param string $phone 待验证的手机号
     * @return bool
     */
    public static function validatePhone(string $phone): bool
    {
        return self::validateWithConstraint($phone, new Regex('/^1[3-9]\d{9}$/'));
    }

    /**
     * 验证用户名（字母、数字、下划线，3-20位）
     *
     * @param string $username 待验证的用户名
     * @return bool
     */
    public static function validateUsername(string $username): bool
    {
        return self::validateWithConstraint($username, new Regex('/^[a-zA-Z0-9_]{3,20}$/'));
    }

    /**
     * 验证密码强度
     *
     * @param string $password 待验证的密码
     * @param int $level 验证级别（1-3，越高越严格）
     * @return bool
     */
    public static function validatePasswordStrength(string $password, int $level = 2): bool
    {
        $length = strlen($password);
        
        // 基础验证：至少8位
        if ($length < 8) {
            return false;
        }
        
        if ($level >= 2) {
            // 中级验证：包含大小写字母和数字
            if (!preg_match('/[a-z]/', $password) 
                || !preg_match('/[A-Z]/', $password) 
                || !preg_match('/\d/', $password)) {
                return false;
            }
        }
        
        if ($level >= 3) {
            // 高级验证：包含特殊字符
            if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 验证文件名（防止路径遍历）
     *
     * @param string $filename 待验证的文件名
     * @return bool
     */
    public static function validateFilename(string $filename): bool
    {
        // 检查是否包含路径遍历字符
        if (strpos($filename, '..') !== false) {
            return false;
        }
        
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return false;
        }
        
        // 检查是否包含空字符
        if (strpos($filename, "\0") !== false) {
            return false;
        }
        
        // 检查文件名长度
        if (strlen($filename) > 255) {
            return false;
        }
        
        return true;
    }

    /**
     * 验证 HTML 标签（防止 XSS）
     *
     * @param string $html 待验证的 HTML
     * @return bool
     */
    public static function validateHtml(string $html): bool
    {
        // 检查是否包含危险标签
        $dangerousTags = [
            '<script', '<iframe', '<object', '<embed', '<applet',
            '<meta', '<link', '<style', '<form', '<input', '<button'
        ];
        
        $lowerHtml = strtolower($html);
        foreach ($dangerousTags as $tag) {
            if (strpos($lowerHtml, $tag) !== false) {
                return false;
            }
        }
        
        // 检查是否包含危险属性
        $dangerousAttrs = [
            'onload', 'onerror', 'onclick', 'onmouseover', 'onfocus', 'onblur',
            'javascript:', 'vbscript:', 'data:'
        ];
        
        foreach ($dangerousAttrs as $attr) {
            if (strpos($lowerHtml, $attr) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 验证数组键是否存在
     *
     * @param array $data 数据数组
     * @param string $key 键名
     * @param bool $required 是否必须存在
     * @return bool
     */
    public static function validateArrayKey(array $data, string $key, bool $required = true): bool
    {
        $exists = isset($data[$key]) && !empty($data[$key]);
        
        if ($required && !$exists) {
            return false;
        }
        
        return true;
    }

    /**
     * 验证数组值类型
     *
     * @param array $data 数据数组
     * @param string $key 键名
     * @param string $type 期望类型（int, float, string, array, bool）
     * @param bool $required 是否必须存在
     * @return bool
     */
    public static function validateArrayType(array $data, string $key, string $type, bool $required = true): bool
    {
        if (!isset($data[$key])) {
            return !$required;
        }
        
        $value = $data[$key];
        
        return match($type) {
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'bool' => is_bool($value),
            default => false,
        };
    }

    /**
     * 验证并清理输入数据
     *
     * @param array $data 输入数据
     * @param array $rules 验证规则
     * @return array{valid: bool, errors: array<string>, cleaned: array}
     */
    public static function validateAndClean(array $data, array $rules): array
    {
        $errors = [];
        $cleaned = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // 必填验证
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "{$field} 不能为空";
                continue;
            }
            
            // 如果值为空且非必填，跳过
            if (empty($value) && !($rule['required'] ?? false)) {
                $cleaned[$field] = $value;
                continue;
            }
            
            // 类型验证
            if (isset($rule['type'])) {
                $valid = match($rule['type']) {
                    'int' => self::validateInt($value),
                    'float' => self::validateFloat($value),
                    'email' => self::validateEmail($value),
                    'phone' => self::validatePhone($value),
                    'url' => self::validateUrlFormat($value),
                    'username' => self::validateUsername($value),
                    default => true,
                };
                
                if (!$valid) {
                    $errors[$field] = "{$field} 格式不正确";
                    continue;
                }
            }
            
            // 长度验证
            if (isset($rule['min']) || isset($rule['max'])) {
                if (!self::validateLength($value, $rule['min'] ?? null, $rule['max'] ?? null)) {
                    $errors[$field] = "{$field} 长度不符合要求";
                    continue;
                }
            }
            
            // 清理输入
            $cleaned[$field] = self::sanitizeInput($value);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'cleaned' => $cleaned,
        ];
    }

    /**
     * 验证浮点数
     *
     * @param mixed $value 待验证的值
     * @param float|null $min 最小值
     * @param float|null $max 最大值
     * @return bool
     */
    public static function validateFloat(mixed $value, ?float $min = null, ?float $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $floatVal = (float)$value;
        
        if ($min !== null && $floatVal < $min) {
            return false;
        }
        
        if ($max !== null && $floatVal > $max) {
            return false;
        }
        
        return true;
    }
}
