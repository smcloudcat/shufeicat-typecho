<?php
/**
 * 图片验证码生成器
 * 支持纯数字、纯字母、数字+字母三种模式
 * 不依赖外部字体文件，使用 GD 内置字体 + 旋转绘制
 */
session_start();

// 获取验证码类型和长度参数
$captchaType = isset($_GET['type']) ? $_GET['type'] : 'alnum';
$length = isset($_GET['length']) ? intval($_GET['length']) : 4;
$length = max(4, min(6, $length));

// 字符集（去掉容易混淆的字符）
$numberChars = '23456789';
$alphaChars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
$alnumChars = $numberChars . $alphaChars;

switch ($captchaType) {
    case 'number':
        $charSet = $numberChars;
        break;
    case 'alpha':
        $charSet = $alphaChars;
        break;
    case 'alnum':
    default:
        $charSet = $alnumChars;
        break;
}

// 生成验证码
$code = '';
$charLen = strlen($charSet);
for ($i = 0; $i < $length; $i++) {
    $code .= $charSet[random_int(0, $charLen - 1)];
}

// 存入 session
$_SESSION['shufei_captcha_code'] = strtolower($code);
$_SESSION['shufei_captcha_time'] = time();

// 图片参数
$charWidth = 30;
$width = $length * $charWidth + 20;
$height = 44;

// 创建图片
$image = imagecreatetruecolor($width, $height);

// 背景色
$bgColor = imagecolorallocate($image, random_int(235, 250), random_int(235, 250), random_int(235, 250));
imagefill($image, 0, 0, $bgColor);

// 绘制干扰点
for ($i = 0; $i < 100; $i++) {
    $pointColor = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
    imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $pointColor);
}

// 绘制干扰线
for ($i = 0; $i < 4; $i++) {
    $lineColor = imagecolorallocate($image, random_int(130, 200), random_int(130, 200), random_int(130, 200));
    imageline($image, random_int(0, $width / 3), random_int(5, $height - 5), random_int($width * 2 / 3, $width), random_int(5, $height - 5), $lineColor);
}

// 绘制验证码字符（使用内置字体 + 旋转模拟）
$builtInFonts = [5]; // GD 内置最大字体
$font = $builtInFonts[0];

for ($i = 0; $i < $length; $i++) {
    $textColor = imagecolorallocate($image, random_int(20, 100), random_int(20, 100), random_int(20, 100));

    // 每个字符单独绘制到小画布上，然后旋转贴到主画布
    $charW = imagefontwidth($font) + 4;
    $charH = imagefontheight($font) + 4;
    $charImg = imagecreatetruecolor($charW, $charH);

    // 透明背景
    $transparent = imagecolorallocatealpha($charImg, 0, 0, 0, 127);
    imagefill($charImg, 0, 0, $transparent);
    imagecolortransparent($charImg, $transparent);

    // 绘制字符
    $charColor = imagecolorallocate($charImg, random_int(20, 100), random_int(20, 100), random_int(20, 100));
    imagestring($charImg, $font, 2, 2, $code[$i], $charColor);

    // 旋转
    $angle = random_int(-15, 15);
    $rotatedImg = imagerotate($charImg, $angle, $transparent);

    // 贴到主画布
    $destX = 10 + $i * $charWidth + random_int(-2, 2);
    $destY = random_int(4, 12);
    imagecopy($image, $rotatedImg, $destX, $destY, 0, 0, imagesx($rotatedImg), imagesy($rotatedImg));

    imagedestroy($charImg);
    imagedestroy($rotatedImg);
}

// 输出图片
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($image);
imagedestroy($image);
