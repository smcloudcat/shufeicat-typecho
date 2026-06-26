<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片处理器
 *
 * 基于 GD 库实现：
 *  - 图片压缩（JPEG 质量 / PNG 压缩等级）
 *  - 自动转 WebP
 *  - 文字水印 / 图片水印（位置、透明度可调）
 *
 * 每个处理环节均可独立开关，由调用方传入 $options 数组控制。
 */
class ShufeiImageProcessor
{
    /**
     * 处理图片
     *
     * @param string $localPath 原始图片本地路径
     * @param array  $options   处理选项：
     *     compress: bool        是否压缩
     *     compressQuality: int  压缩质量 1-100
     *     webp: bool            是否转 WebP
     *     watermark: bool       是否加水印
     *     watermarkType: text|image
     *     watermarkText: string
     *     watermarkImage: string  本地水印图片绝对路径
     *     watermarkPosition: tl|tr|bl|br|center|tile
     *     watermarkOpacity: int 0-100
     *     watermarkSize: int     文字字号 or 图片缩放百分比
     *     watermarkColor: string hex 如 #FF0000
     *     watermarkFont: string  TTF 字体文件绝对路径（可选）
     * @return array {
     *     path: string  处理后图片本地路径（可能与原路径不同）
     *     mime: string  处理后 MIME
     *     ext:  string  处理后扩展名
     * }
     */
    public static function process($localPath, array $options)
    {
        // 快速判断是否需要任何处理：若全部关闭则直接返回原图，避免 GD 加载大图的开销
        $wantCompress  = !empty($options['compress']);
        $wantWebp      = !empty($options['webp']);
        $wantWatermark = !empty($options['watermark']);
        if (!$wantCompress && !$wantWebp && !$wantWatermark) {
            return array(
                'path' => $localPath,
                'mime' => self::guessMime($localPath),
                'ext'  => self::guessExt($localPath),
            );
        }

        if (!function_exists('gd_info')) {
            return array(
                'path' => $localPath,
                'mime' => self::guessMime($localPath),
                'ext'  => self::guessExt($localPath),
            );
        }

        $info = @getimagesize($localPath);
        if (!$info || !isset($info[2])) {
            return array(
                'path' => $localPath,
                'mime' => self::guessMime($localPath),
                'ext'  => self::guessExt($localPath),
            );
        }

        $origMime = $info['mime'];
        $wantWebp = !empty($options['webp']);
        // WebP 仅支持来源于 GD 是否编译了 webp 支持
        if ($wantWebp && !self::gdSupports('webp')) {
            $wantWebp = false;
        }

        // 目标格式：转 WebP 时输出 webp；否则保留原格式
        $targetExt = $wantWebp ? 'webp' : self::guessExt($localPath);

        // 如果原格式无法用 GD 处理（如 svg），跳过处理
        $gdMime = self::gdMimeSupported($origMime);
        if (!$gdMime) {
            return array(
                'path' => $localPath,
                'mime' => $origMime,
                'ext'  => self::guessExt($localPath),
            );
        }

        $im = self::imageCreateFromFile($localPath, $origMime);
        if (!$im) {
            return array(
                'path' => $localPath,
                'mime' => $origMime,
                'ext'  => self::guessExt($localPath),
            );
        }

        // 处理 PNG 透明通道：转 WebP 时保持透明
        if ($origMime === 'image/png' || $origMime === 'image/webp') {
            imagesavealpha($im, true);
        }

        // 1) 水印（在压缩前应用，避免水印被压缩失真）
        if (!empty($options['watermark'])) {
            self::applyWatermark($im, $options);
        }

        // 2) 输出
        $quality = isset($options['compressQuality']) ? max(1, min(100, intval($options['compressQuality']))) : 80;
        if (!$wantWebp && !empty($options['compress'])) {
            // 原地压缩：保留扩展名
            $outPath = $localPath;
        } elseif ($wantWebp) {
            // 转 WebP：新路径
            $outPath = preg_replace('/\.[^.]+$/i', '', $localPath) . '.webp';
        } else {
            // 不压缩也不转 webp：直接返回原图
            imagedestroy($im);
            return array(
                'path' => $localPath,
                'mime' => $origMime,
                'ext'  => self::guessExt($localPath),
            );
        }

        $saved = false;
        if ($wantWebp) {
            // imagewebp 不支持 quality 参数（PHP < 7.4），但可以接受质量参数（PHP 7.4+）
            if (PHP_VERSION_ID >= 70400) {
                $saved = @imagewebp($im, $outPath, $quality);
            } else {
                $saved = @imagewebp($im, $outPath);
            }
        } elseif ($targetExt === 'jpg' || $targetExt === 'jpeg') {
            $saved = @imagejpeg($im, $outPath, $quality);
        } elseif ($targetExt === 'png') {
            // PNG 压缩等级 0-9，由 quality 100->0 映射到 0->9，但限制最大为 6（9 太慢且压缩率提升极小）
            $pngLevel = (int)round((100 - $quality) / 100 * 9);
            if ($pngLevel > 6) $pngLevel = 6;
            $saved = @imagepng($im, $outPath, $pngLevel);
        } elseif ($targetExt === 'gif') {
            $saved = @imagegif($im, $outPath);
        }

        imagedestroy($im);

        if (!$saved) {
            // 失败则保留原图
            return array(
                'path' => $localPath,
                'mime' => $origMime,
                'ext'  => self::guessExt($localPath),
            );
        }

        return array(
            'path' => $outPath,
            'mime' => $wantWebp ? 'image/webp' : $origMime,
            'ext'  => $targetExt,
        );
    }

    /**
     * 从文件创建 GD 图像资源
     */
    private static function imageCreateFromFile($path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
            case 'image/gif':
                $im = @imagecreatefromgif($path);
                if ($im) {
                    // 转为真彩以支持后续处理
                    $trueColor = imagecreatetruecolor(imagesx($im), imagesy($im));
                    imagealphablending($trueColor, true);
                    imagesavealpha($trueColor, true);
                    $transparent = imagecolorallocatealpha($im, 255, 255, 255, 127);
                    imagefill($trueColor, 0, 0, $transparent);
                    imagecopy($trueColor, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                    imagedestroy($im);
                    return $trueColor;
                }
                return false;
            case 'image/webp':
                return @imagecreatefromwebp($path);
            case 'image/bmp':
                return @imagecreatefrombmp($path);
            default:
                return false;
        }
    }

    /**
     * 检查 GD 是否支持某格式
     */
    private static function gdSupports($format)
    {
        static $info = null;
        if ($info === null) {
            $info = function_exists('gd_info') ? gd_info() : array();
        }
        $key = $format . ' Support';
        return isset($info[$key]) && (strpos(strtolower($info[$key]), 'enabled') !== false);
    }

    /**
     * GD 是否能处理该 MIME
     */
    private static function gdMimeSupported($mime)
    {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
            case 'image/bmp':
                return true;
            case 'image/webp':
                return self::gdSupports('webp');
            default:
                return false;
        }
    }

    /**
     * 应用水印
     */
    private static function applyWatermark($im, array $options)
    {
        $type = isset($options['watermarkType']) ? $options['watermarkType'] : 'text';
        $position = isset($options['watermarkPosition']) ? $options['watermarkPosition'] : 'br';
        $opacity = isset($options['watermarkOpacity']) ? max(0, min(100, intval($options['watermarkOpacity']))) : 50;
        $opacityAlpha = (int)round((100 - $opacity) * 1.27); // 0-127

        if ($type === 'image' && !empty($options['watermarkImage']) && is_file($options['watermarkImage'])) {
            self::applyImageWatermark($im, $options['watermarkImage'], $position, $opacity, $options);
        } else {
            self::applyTextWatermark($im, $options, $position, $opacityAlpha);
        }
    }

    /**
     * 文字水印
     */
    private static function applyTextWatermark($im, array $options, $position, $opacityAlpha)
    {
        $text = isset($options['watermarkText']) ? trim($options['watermarkText']) : '';
        if ($text === '') {
            return;
        }
        $size = isset($options['watermarkSize']) ? max(8, min(72, intval($options['watermarkSize']))) : 16;
        $color = isset($options['watermarkColor']) ? $options['watermarkColor'] : '#FFFFFF';
        $font = !empty($options['watermarkFont']) && is_file($options['watermarkFont']) ? $options['watermarkFont'] : '';

        $rgb = self::hexToRgb($color);
        $textColor = imagecolorallocatealpha($im, $rgb['r'], $rgb['g'], $rgb['b'], $opacityAlpha);

        $iw = imagesx($im);
        $ih = imagesy($im);

        if ($font) {
            // 计算文字外框
            $box = @imagettfbbox($size, 0, $font, $text);
            if ($box) {
                $tw = $box[2] - $box[0];
                $th = $box[1] - $box[7];
            } else {
                $tw = strlen($text) * $size;
                $th = $size;
            }
        } else {
            $tw = imagefontwidth($size + 3) * strlen($text);
            $th = imagefontheight($size + 3);
        }

        $margin = 10;
        list($x, $y) = self::calcPosition($position, $iw, $ih, $tw, $th, $margin);

        // 阴影增强可读性
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, $opacityAlpha);
        if ($font) {
            @imagettftext($im, $size, 0, $x + 1, $y + 1, $shadow, $font, $text);
            @imagettftext($im, $size, 0, $x, $y, $textColor, $font, $text);
        } else {
            imagestring($im, $size + 3, $x + 1, $y + 1, $text, $shadow);
            imagestring($im, $size + 3, $x, $y, $text, $textColor);
        }
    }

    /**
     * 图片水印
     */
    private static function applyImageWatermark($im, $wmPath, $position, $opacity, array $options)
    {
        $info = @getimagesize($wmPath);
        if (!$info) {
            return;
        }
        $wm = self::imageCreateFromFile($wmPath, $info['mime']);
        if (!$wm) {
            return;
        }
        imagealphablending($wm, true);
        imagesavealpha($wm, true);

        $iw = imagesx($im);
        $ih = imagesy($im);
        $ww = imagesx($wm);
        $wh = imagesy($wm);

        // 按百分比缩放水印
        $scale = isset($options['watermarkSize']) ? max(10, min(100, intval($options['watermarkSize']))) : 100;
        if ($scale !== 100) {
            $newW = max(1, (int)($ww * $scale / 100));
            $newH = max(1, (int)($wh * $scale / 100));
            $scaled = imagecreatetruecolor($newW, $newH);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 255, 255, 255, 127));
            imagecopyresampled($scaled, $wm, 0, 0, 0, 0, $newW, $newH, $ww, $wh);
            imagedestroy($wm);
            $wm = $scaled;
            $ww = $newW;
            $wh = $newH;
        }

        $margin = 10;
        list($x, $y) = self::calcPosition($position, $iw, $ih, $ww, $wh, $margin);

        if ($position === 'tile') {
            // 平铺
            for ($ty = 0; $ty < $ih; $ty += $wh + 20) {
                for ($tx = 0; $tx < $iw; $tx += $ww + 20) {
                    self::copyWithAlpha($im, $wm, $tx, $ty, $opacity);
                }
            }
        } else {
            self::copyWithAlpha($im, $wm, $x, $y, $opacity);
        }

        imagedestroy($wm);
    }

    /**
     * 带透明度拷贝
     */
    private static function copyWithAlpha($dst, $src, $x, $y, $opacity)
    {
        if ($opacity >= 100) {
            imagecopy($dst, $src, $x, $y, 0, 0, imagesx($src), imagesy($src));
        } else {
            // imagecopymerge 不支持 alpha 通道，需手动混色
            $cut = imagecreatetruecolor(imagesx($src), imagesy($src));
            imagecopy($cut, $dst, 0, 0, $x, $y, imagesx($src), imagesy($src));
            imagecopy($cut, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
            imagecopymerge($dst, $cut, $x, $y, 0, 0, imagesx($src), imagesy($src), $opacity);
            imagedestroy($cut);
        }
    }

    /**
     * 计算水印位置
     */
    private static function calcPosition($position, $iw, $ih, $w, $h, $margin)
    {
        switch ($position) {
            case 'tl': return array($margin, $margin);
            case 'tr': return array($iw - $w - $margin, $margin);
            case 'bl': return array($margin, $ih - $h - $margin);
            case 'br': return array($iw - $w - $margin, $ih - $h - $margin);
            case 'center': return array((int)(($iw - $w) / 2), (int)(($ih - $h) / 2));
            case 'tile': return array(0, 0);
            default: return array($iw - $w - $margin, $ih - $h - $margin);
        }
    }

    /**
     * hex 转 rgb
     */
    private static function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return array('r' => 255, 'g' => 255, 'b' => 255);
        }
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * 根据文件名猜 MIME
     */
    public static function guessMime($path)
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if ($m) {
                return $m;
            }
        }
        $ext = strtolower(self::guessExt($path));
        $map = array(
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        );
        return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
    }

    /**
     * 根据文件路径猜扩展名
     * 优先通过实际图像类型判断（临时文件常无扩展名），回退到文件名扩展名
     */
    public static function guessExt($path)
    {
        // 1) 优先用 getimagesize 获取真实图像类型（对临时上传文件可靠）
        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            if ($info && isset($info[2])) {
                $map = array(
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_PNG  => 'png',
                    IMAGETYPE_GIF  => 'gif',
                    IMAGETYPE_WEBP => 'webp',
                    IMAGETYPE_BMP  => 'bmp',
                );
                if (isset($map[$info[2]])) {
                    return $map[$info[2]];
                }
            }
        }
        // 2) 回退到 mime_content_type
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            $map = array(
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                'image/bmp'  => 'bmp',
            );
            if (isset($map[$mime])) {
                return $map[$mime];
            }
        }
        // 3) 最后回退到文件名扩展名
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        return $ext;
    }
}
