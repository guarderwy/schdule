<?php
declare (strict_types = 1);

namespace app\backend\controller;

use think\response\Response;

class Index
{
    public function index($params)
    {
        foreach (range(1, 1000) as $num) {
            echo $num . PHP_EOL;
            sleep(1);
        }
        var_dump($params);
        return '您好！这是一个[backend]示例应用';
    }

    /**
     * 生成验证码图片
     * @param int $length 验证码长度，默认4位
     * @return Response
     */
    public function captcha($length = 4)
    {
        // 设置默认长度
        $length = $length > 0 ? (int)$length : 4;
        
        // 生成验证码字符串
        $code = $this->generateCode($length);
        
        // 将验证码存储到session中（如果使用session的话）
        session('captcha', strtolower($code));
        
        // 创建图片
        $width = 130;
        $height = 50;
        $image = imagecreate($width, $height);
        
        // 设置背景色
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        
        // 设置干扰元素颜色
        $gray = imagecolorallocate($image, 118, 151, 199);
        $dark = imagecolorallocate($image, mt_rand(0, 200), mt_rand(0, 120), mt_rand(0, 120));
        
        // 绘制干扰点
        for ($i = 0; $i < 200; $i++) {
            $x = mt_rand(1, $width - 1);
            $y = mt_rand(1, $height - 1);
            $color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagesetpixel($image, $x, $y, $color);
        }
        
        // 绘制干扰线
        for ($i = 0; $i < 3; $i++) {
            $x1 = mt_rand(1, (int)($width / 2));
            $y1 = mt_rand(1, $height);
            $x2 = mt_rand((int)($width / 2), $width);
            $y2 = mt_rand(1, $height);
            imageline($image, $x1, $y1, $x2, $y2, $gray);
        }
        
        // 设置字体
        $font = __DIR__ . '/../../../public/fonts/arial.ttf'; // 尝试使用字体文件
        if (!file_exists($font)) {
            $font = 5; // 使用内置字体
        }
        
        // 绘制验证码字符
        $fontSize = 20;
        $x = 15;
        for ($i = 0; $i < $length; $i++) {
            $textColor = imagecolorallocate($image, mt_rand(0, 150), mt_rand(0, 150), mt_rand(0, 150));
            
            if (is_string($font)) {
                // 使用字体文件
                $y = mt_rand((int)($height / 6), (int)($height / 2));
                imagettftext($image, $fontSize, mt_rand(-30, 30), $x, $y, $textColor, $font, $code[$i]);
            } else {
                // 使用内置字体
                $y = mt_rand((int)($height / 4), (int)($height / 2));
                imagestring($image, $font, $x, $y, $code[$i], $textColor);
            }
            
            $x += ($width - 30) / $length;
        }
        
        // 输出图片
        ob_clean();
        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Content-Type: image/png');
        
        imagepng($image);
        imagedestroy($image);
        
        exit;
    }
    
    /**
     * 生成验证码字符
     * @param int $length 验证码长度
     * @return string
     */
    private function generateCode($length)
    {
        $chars = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        return $code;
    }
}