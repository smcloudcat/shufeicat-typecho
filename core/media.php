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


/* ==================== 文章图片滑块 Markdown 增强语法 ==================== */

/**
 * 清洗单个图片地址
 *
 * 仅允许 http/https、协议相对（//）、以及站内相对路径；
 * javascript: / data: / vbscript: 等一律丢弃（防 XSS）。
 *
 * @param string $url 原始地址
 * @return string 清洗后的地址，非法返回空串
 */
function shufei_sanitize_gallery_url($url)
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    // 协议相对
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    // 相对路径：形如 /a/b.jpg、./a.jpg、a.jpg（不含协议头）
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
        return $url;
    }
    return '';
}

/**
 * 渲染图片滑块 HTML
 *
 * @param array $urls    图片地址数组（已按出现顺序）
 * @param bool  $noslide true=不轮播（网格平铺），false=轮播
 * @return string HTML，无有效图片时返回空串
 */
function shufei_render_post_gallery_html($urls, $noslide = false)
{
    $clean = array();
    foreach ((array)$urls as $u) {
        $v = shufei_sanitize_gallery_url($u);
        if ($v !== '') {
            $clean[] = $v;
        }
    }
    $count = count($clean);
    if ($count < 1) {
        return '';
    }

    if ($noslide) {
        $html = '<div class="post-gallery post-gallery-noslide" data-count="' . $count . '">';
        foreach ($clean as $i => $u) {
            $html .= '<div class="post-gallery-item"><img src="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8')
                . '" alt="图片 ' . ($i + 1) . '" loading="lazy" decoding="async"></div>';
        }
        $html .= '</div>';
        return $html;
    }

    $multi = ($count > 1);
    $html = '<div class="post-gallery post-gallery-slide" data-slide="' . ($multi ? '1' : '0')
        . '" data-count="' . $count . '">';
    $html .= '<div class="post-gallery-viewport"><div class="post-gallery-track">';
    foreach ($clean as $i => $u) {
        $html .= '<div class="post-gallery-item"><img src="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8')
            . '" alt="图片 ' . ($i + 1) . '" loading="lazy" decoding="async"></div>';
    }
    $html .= '</div>';

    if ($multi) {
        $html .= '<button type="button" class="post-gallery-arrow prev" aria-label="上一张">'
            . '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
        $html .= '<button type="button" class="post-gallery-arrow next" aria-label="下一张">'
            . '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
        $html .= '<div class="post-gallery-footer"><div class="post-gallery-dots">';
        for ($i = 0; $i < $count; $i++) {
            $html .= '<button type="button" class="post-gallery-dot' . ($i === 0 ? ' active' : '')
                . '" aria-label="第 ' . ($i + 1) . ' 张"></button>';
        }
        $html .= '</div><span class="post-gallery-counter">1 / ' . $count . '</span></div>';
    }

    $html .= '</div></div>';
    return $html;
}

/**
 * 保护图片滑块短代码 [gallery:...]（Markdown 解析前调用）
 *
 * HyperDown 会把裸 URL 自动转成 <a href="url">url</a>，导致 [gallery:url1|url2]
 * 里的 URL 被包上 HTML 标签而无法作为图片地址使用（video/music 短代码也踩过）。
 * 解析前整段替换为纯字母数字占位符，解析后原样还原，交给
 * shufei_process_post_gallery() 在扩展阶段渲染。
 *
 * @param string $text Markdown 原始文本
 * @param array &$blocks 存储占位符 → 原始短代码文本
 * @return string 替换后的文本
 */
function shufei_protect_gallery_shortcodes($text, &$blocks)
{
    $blocks = array();
    if (!is_string($text) || stripos($text, '[gallery:') === false) {
        return $text;
    }
    return preg_replace_callback('/\[gallery:[^\]]*\]/i', function ($m) use (&$blocks) {
        $key = '%%SHUFEIGALLERY' . count($blocks) . '%%';
        $blocks[$key] = $m[0];
        return $key;
    }, $text);
}

/**
 * 还原图片滑块短代码占位符（Markdown 解析后调用）
 *
 * @param string $html 已解析的 HTML
 * @param array $blocks shufei_protect_gallery_shortcodes 产生的占位映射
 * @return string 还原后的 HTML
 */
function shufei_restore_gallery_shortcodes($html, $blocks)
{
    if (empty($blocks) || !is_string($html)) {
        return $html;
    }
    foreach ($blocks as $key => $original) {
        $html = str_replace($key, $original, $html);
    }
    return $html;
}

/**
 * 处理图库短代码（Markdown 增强语法）
 *
 * 语法：
 *   [gallery:url1|url2|url3]            轮播（默认）
 *   [gallery:noslide:url1|url2|url3]    不轮播（网格平铺）
 *
 * 独占一行时替换整个 <p>；行内出现时也渲染为块级滑块（浏览器自动闭合段落）。
 * 地址用 | 分隔（URL 中几乎不出现竖线），支持 http/https/协议相对/相对路径。
 *
 * @param string $content 已解析的 HTML
 * @return string 处理后的 HTML
 */
function shufei_process_post_gallery($content)
{
    if (!is_string($content) || stripos($content, '[gallery:') === false) {
        return $content;
    }

    // 保护 <pre>/<code> 内的示例文本：语法文档里会写 [gallery:图1|图2] 作为示例，
    // 若不保护会被当真实语法渲染（渲染成 src="图1" 的破图）
    $protected = array();
    $content = preg_replace_callback('/<(code|pre)[^>]*>.*?<\/\1>/si', function ($m) use (&$protected) {
        $key = '<!--GALLERYPROTECT' . count($protected) . '-->';
        $protected[] = $m[0];
        return $key;
    }, $content);

    $render = function ($inner) {
        $inner = trim($inner);
        $noslide = false;
        if (stripos($inner, 'noslide:') === 0) {
            $noslide = true;
            $inner = substr($inner, 8);
        }
        $parts = preg_split('/\s*\|\s*/', $inner);
        // 兜底：解析器可能已把裸 URL 自动转成 <a href="url">url</a>，提取真实地址
        $parts = array_map(function ($p) {
            $p = trim($p);
            if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $p, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            }
            return html_entity_decode(strip_tags($p), ENT_QUOTES, 'UTF-8');
        }, $parts);
        return shufei_render_post_gallery_html($parts, $noslide);
    };

    // 1. 独占整段：<p>[gallery:...]</p> → 替换整个段落
    $content = preg_replace_callback(
        '/<p>\s*\[gallery:([^\]]*)\]\s*<\/p>/i',
        function ($m) use ($render) {
            return $render($m[1]);
        },
        $content
    );

    // 2. 兜底：剩余的裸标记（行内或未包 <p> 的情况）
    $content = preg_replace_callback(
        '/\[gallery:([^\]]*)\]/i',
        function ($m) use ($render) {
            return $render($m[1]);
        },
        $content
    );

    // 3. 清理无效标记留下的空段落（如写了空的 [gallery:]）
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);

    // 4. 还原被保护的代码块内容
    foreach ($protected as $i => $html) {
        $content = str_replace('<!--GALLERYPROTECT' . $i . '-->', $html, $content);
    }

    return $content;
}
