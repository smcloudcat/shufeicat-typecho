<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片处理与视频/音乐短代码
 * 从 functions.php 分层迁移
 */

/**
 * 处理图片扩展语法
 * - 图片大小：![alt|300x200](url) 或 ![alt|50%](url)
 * - 图片对齐：![alt#center](url)、![alt#left](url)、![alt#right](url)
 * - 图片标题：alt 文本自动作为 figcaption
 * - 图片懒加载：自动添加 loading="lazy"
 */
function shufei_process_images($content)
{
    if ($content === null) {
        $content = '';
    }
    // 匹配 Markdown 生成的 <img> 标签，包括被 <a> 标签包裹的情况
    // HyperDown 生成格式：<img src="URL" alt="ALT" title="TITLE">
    // 链接图片格式：<a href="LINK"><img src="URL" alt="ALT" title="TITLE"></a>
    $content = preg_replace_callback(
        '/(<a\s+href="[^"]*"[^>]*>)?\s*(<img\s+src="([^"]+)"\s+alt="([^"]*)"(?:\s+title="([^"]*)")?\s*>)\s*(<\/a>)?/s',
        function ($matches) {
            $hasLink = !empty($matches[1]);
            $openLink = $hasLink ? $matches[1] : '';
            $closeLink = !empty($matches[5]) ? $matches[5] : '';
            $src = $matches[3];
            $alt = $matches[4];
            $title = isset($matches[5]) && !$closeLink ? $matches[5] : (isset($matches[6]) ? '' : '');
            // 修正：title 在 matches[5]（当无链接时）或 matches[5] 是 </a>
            // 重新从 img 标签中提取 title
            $imgTag = $matches[2];
            $titleVal = '';
            if (preg_match('/title="([^"]*)"/', $imgTag, $titleMatch)) {
                $titleVal = $titleMatch[1];
            }

            $width = '';
            $height = '';
            $align = '';
            $caption = '';
            $cleanAlt = $alt;

            // 解析 alt 中的扩展语法
            // 格式：alt文本|宽x高#对齐
            // 例如：图片描述|300x200#center

            // 提取对齐方式 #left / #center / #right
            if (preg_match('/#(left|center|right)$/i', $cleanAlt, $alignMatch)) {
                $align = strtolower($alignMatch[1]);
                $cleanAlt = preg_replace('/#(left|center|right)$/i', '', $cleanAlt);
            }

            // 提取尺寸 |300x200 或 |50% 或 |300 或 |x200
            if (preg_match('/\|(\d*%?)(?:x(\d*%?))?$/i', $cleanAlt, $sizeMatch)) {
                if (!empty($sizeMatch[1])) {
                    $width = $sizeMatch[1];
                }
                if (!empty($sizeMatch[2])) {
                    $height = $sizeMatch[2];
                }
                $cleanAlt = preg_replace('/\|\d*%?(?:x\d*%?)?$/i', '', $cleanAlt);
            }

            // 构建 img 属性
            $imgAttrs = 'src="' . htmlspecialchars($src) . '"';
            $imgAttrs .= ' alt="' . htmlspecialchars($cleanAlt) . '"';
            if (!empty($titleVal) && $titleVal !== $cleanAlt) {
                $imgAttrs .= ' title="' . htmlspecialchars($titleVal) . '"';
            }
            // 收集百分比样式，合并到同一个 style 属性中
            $styleParts = array();
            if (!empty($width)) {
                if (substr($width, -1) === '%') {
                    $styleParts[] = 'width:' . intval($width) . '%';
                } else {
                    $imgAttrs .= ' width="' . intval($width) . '"';
                }
            }
            if (!empty($height)) {
                if (substr($height, -1) === '%') {
                    $styleParts[] = 'height:' . intval($height) . '%';
                } else {
                    $imgAttrs .= ' height="' . intval($height) . '"';
                }
            }
            if (!empty($styleParts)) {
                $imgAttrs .= ' style="' . implode(';', $styleParts) . '"';
            }
            // 懒加载
            $imgAttrs .= ' loading="lazy"';

            $newImgTag = '<img ' . $imgAttrs . '>';

            // 如果有对齐方式或 alt 文本（作为标题），包裹在 figure 中
            if (!empty($align) || !empty($cleanAlt)) {
                $figureClass = 'post-figure';
                if (!empty($align)) {
                    $figureClass .= ' post-figure-' . $align;
                }
                $html = '<figure class="' . $figureClass . '">';
                // 如果图片在链接内，将链接保留在 figure 内部
                if ($hasLink) {
                    $html .= $openLink . $newImgTag . $closeLink;
                } else {
                    $html .= $newImgTag;
                }
                if (!empty($cleanAlt)) {
                    $html .= '<figcaption>' . htmlspecialchars($cleanAlt) . '</figcaption>';
                }
                $html .= '</figure>';
                return $html;
            }

            // 无需 figure 包裹，只替换 img 标签属性
            if ($hasLink) {
                return $openLink . $newImgTag . $closeLink;
            }
            return $newImgTag;
        },
        $content
    );

    return $content;
}

/**
 * 处理视频短代码
 * 支持格式：
 * [video]url[/video]
 * [video src="url"]
 * [video src="url" poster="封面图url"]
 * [video src="url" autoplay="true"]
 *
 * @param string $content HTML 内容
 * @return string 处理后的 HTML 内容
 */
function shufei_process_video_shortcode($content)
{
    if ($content === null) {
        $content = '';
    }

    // 处理 [video]url[/video] 格式
    // Markdown 可能已将 URL 转为 <a href="url">url</a>，需要从中提取纯 URL
    $content = preg_replace_callback(
        '/\[video\](.*?)\[\/video\]/is',
        function ($matches) {
            $raw = trim($matches[1]);
            if (empty($raw)) return '';
            // 从 <a> 标签中提取 href
            $url = $raw;
            if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $raw, $m)) {
                $url = trim($m[1]);
            }
            if (empty($url)) return '';
            return '<div class="post-video-wrap"><div class="post-video-container"><video class="post-video-player" controls preload="metadata" playsinline><source src="' . htmlspecialchars($url) . '" type="video/mp4">您的浏览器不支持视频播放</video></div></div>';
        },
        $content
    );

    // 处理 [video src="url" ...] 格式
    $content = preg_replace_callback(
        '/\[video\s+([^]]*)\]/is',
        function ($matches) {
            $attrs = $matches[1];
            $src = '';
            $poster = '';
            $autoplay = false;

            // 提取 src 属性
            if (preg_match('/src=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $src = trim($m[1]);
            }
            // 提取 poster 属性
            if (preg_match('/poster=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $poster = trim($m[1]);
            }
            // 提取 autoplay 属性
            if (preg_match('/autoplay=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $autoplay = strtolower(trim($m[1])) === 'true';
            }

            if (empty($src)) return '';

            $posterAttr = !empty($poster) ? ' poster="' . htmlspecialchars($poster) . '"' : '';
            $autoplayAttr = $autoplay ? ' autoplay' : '';

            return '<div class="post-video-wrap"><div class="post-video-container"><video class="post-video-player" controls preload="metadata" playsinline' . $posterAttr . $autoplayAttr . '><source src="' . htmlspecialchars($src) . '" type="video/mp4">您的浏览器不支持视频播放</video></div></div>';
        },
        $content
    );

    return $content;
}

/**
 * 处理音乐短代码
 * 支持格式：
 * [music]url[/music]
 * [music src="url"]
 * [music src="url" title="歌曲名" artist="艺术家"]
 * [music src="url" cover="封面图url"]
 *
 * @param string $content HTML 内容
 * @return string 处理后的 HTML 内容
 */
function shufei_process_music_shortcode($content)
{
    if ($content === null) {
        $content = '';
    }

    // 处理 [music]url[/music] 格式
    // Markdown 可能已将 URL 转为 <a href="url">url</a>，需要从中提取纯 URL
    $content = preg_replace_callback(
        '/\[music\](.*?)\[\/music\]/is',
        function ($matches) {
            $raw = trim($matches[1]);
            if (empty($raw)) return '';
            // 从 <a> 标签中提取 href
            $url = $raw;
            if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $raw, $m)) {
                $url = trim($m[1]);
            }
            if (empty($url)) return '';
            return '<div class="post-music-wrap"><div class="post-music-player"><div class="music-player-inner"><div class="music-disc"><div class="music-disc-inner"></div></div><div class="music-info"><div class="music-title">音乐播放器</div><div class="music-artist">未知艺术家</div></div><audio class="music-audio" controls preload="metadata"><source src="' . htmlspecialchars($url) . '" type="audio/mpeg">您的浏览器不支持音频播放</audio></div></div></div>';
        },
        $content
    );

    // 处理 [music src="url" ...] 格式
    $content = preg_replace_callback(
        '/\[music\s+([^]]*)\]/is',
        function ($matches) {
            $attrs = $matches[1];
            $src = '';
            $title = '音乐播放器';
            $artist = '未知艺术家';
            $cover = '';

            if (preg_match('/src=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $src = trim($m[1]);
            }
            if (preg_match('/title=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $title = trim($m[1]);
            }
            if (preg_match('/artist=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $artist = trim($m[1]);
            }
            if (preg_match('/cover=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $cover = trim($m[1]);
            }

            if (empty($src)) return '';

            $coverHtml = '';
            if (!empty($cover)) {
                $coverHtml = '<div class="music-cover" style="background-image:url(' . htmlspecialchars($cover) . ')"></div>';
            }

            return '<div class="post-music-wrap"><div class="post-music-player"><div class="music-player-inner">' . $coverHtml . '<div class="music-disc"><div class="music-disc-inner"></div></div><div class="music-info"><div class="music-title">' . htmlspecialchars($title) . '</div><div class="music-artist">' . htmlspecialchars($artist) . '</div></div><audio class="music-audio" controls preload="metadata"><source src="' . htmlspecialchars($src) . '" type="audio/mpeg">您的浏览器不支持音频播放</audio></div></div></div>';
        },
        $content
    );

    return $content;
}

