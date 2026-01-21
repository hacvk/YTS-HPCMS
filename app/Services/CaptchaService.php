<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 验证码服务类
 * 生成验证码图片（支持 GD 和 SVG 两种方式）
 */
class CaptchaService
{
    /**
     * 验证码字符集（排除易混淆字符：O, I, 1等）
     */
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * 验证码长度
     */
    private const LENGTH = 5;

    /**
     * 图片宽度
     */
    private const WIDTH = 120;

    /**
     * 图片高度
     */
    private const HEIGHT = 40;

    /**
     * 生成验证码
     *
     * @return array{code: string, image: string, mimeType: string}
     *         返回 ['code' => 验证码字符串, 'image' => 图片二进制数据, 'mimeType' => MIME类型]
     */
    public function generate(): array
    {
        // 生成验证码字符串
        $code = $this->generateCode();

        // 如果 GD 库不可用，生成 SVG
        if (!function_exists('imagecreatetruecolor')) {
            return [
                'code' => $code,
                'image' => $this->generateSvg($code),
                'mimeType' => 'image/svg+xml'
            ];
        }

        // 生成 PNG 图片
        return [
            'code' => $code,
            'image' => $this->generatePng($code),
            'mimeType' => 'image/png'
        ];
    }

    /**
     * 生成验证码字符串
     *
     * @return string
     */
    private function generateCode(): string
    {
        $code = '';
        $charsLength = strlen(self::CHARS);
        
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, $charsLength - 1)];
        }

        return strtoupper($code);
    }

    /**
     * 生成 PNG 图片（使用 GD 库）
     *
     * @param string $code 验证码字符串
     * @return string 图片二进制数据
     */
    private function generatePng(string $code): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // 颜色
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 50, 50, 50);

        // 填充背景
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $bgColor);

        // 添加干扰线
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate(
                $image,
                random_int(100, 200),
                random_int(100, 200),
                random_int(100, 200)
            );
            imageline(
                $image,
                random_int(0, self::WIDTH),
                random_int(0, self::HEIGHT),
                random_int(0, self::WIDTH),
                random_int(0, self::HEIGHT),
                $lineColor
            );
        }

        // 写入验证码
        $fontSize = 5; // 内置字体大小 (1-5)
        $x = 10;
        $y = (int)((self::HEIGHT - imagefontheight($fontSize)) / 2);
        imagestring($image, $fontSize, $x, $y, $code, $textColor);

        // 输出到缓冲区
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return $imageData;
    }

    /**
     * 生成 SVG 图片（当 GD 库不可用时使用）
     *
     * @param string $code 验证码字符串
     * @return string SVG XML 字符串
     */
    private function generateSvg(string $code): string
    {
        $chars = str_split($code);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::WIDTH . '" height="' . self::HEIGHT . '" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '">';
        $svg .= '<rect width="' . self::WIDTH . '" height="' . self::HEIGHT . '" fill="#ffffff"/>';
        
        $x = 10;
        foreach ($chars as $ch) {
            $color = sprintf('#%06x', random_int(0, 0xffffff));
            $svg .= '<text x="' . $x . '" y="28" font-size="24" fill="' . htmlspecialchars($color) . '" font-family="Arial,Helvetica,sans-serif">' . htmlspecialchars($ch) . '</text>';
            $x += 20;
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }
}
