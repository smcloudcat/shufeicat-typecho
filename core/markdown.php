<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Markdown / KaTeX / Mark / Task / Blockquote 扩展
 * 从 functions.php 分层迁移
 */

/**
 * 提取数学公式并替换为占位符
 * 在 Markdown 解析前调用，防止 _ 等符号被 Markdown 解析器破坏
 *
 * @param string $text 原始 Markdown 文本
 * @param array &$mathBlocks 用于存储提取的公式 HTML
 * @return string 替换后的文本
 */
function shufei_extract_math_placeholders($text, &$mathBlocks)
{
    if ($text === null) {
        $text = '';
    }

    // 先保护代码块，避免代码块中的 $...$ 等被误提取为公式
    $codeBlocks = array();

    // 保护围栏代码块（支持 3+ 个反引号或波浪号的围栏）
    // 要求起始标记在行首（允许最多3个前导空格），避免匹配缩进代码块中的反引号文本
    // 结束标记也必须在行首（允许前导空格），反引号/波浪号数量必须 >= 起始标记数量
    $text = preg_replace_callback('/^( {0,3})(`{3,})([\s\S]*?)^\1`{3,}/m', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);
    $text = preg_replace_callback('/^( {0,3})(~{3,})([\s\S]*?)^\1~{3,}/m', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);

    // 保护缩进代码块（4+ 空格缩进的连续行）
    // 匹配连续的 4+ 空格缩进行，避免其中的 $ 等被误提取为公式
    $text = preg_replace_callback('/^( {4,}.*(?:\n {4,}.*)*)/m', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);

    // 保护行内代码 `...`
    $text = preg_replace_callback('/`[^`]+`/', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);

    // 处理块级公式 $$...$$（优先处理，避免与行内公式冲突）
    $text = preg_replace_callback('/\$\$([\s\S]+?)\$\$/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="display" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 处理块级公式 \[...\]
    $text = preg_replace_callback('/\\\\\[([\s\S]+?)\\\\\]/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="display" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 处理行内公式 $...$（排除 $$ 和货币金额）
    $text = preg_replace_callback('/(?<!\$)\$(?!\$)([^\$\n]+?)(?<!\$)\$(?!\$)/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="inline" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 处理行内公式 \(...\)
    $text = preg_replace_callback('/\\\\\(([\s\S]+?)\\\\\)/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="inline" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 还原代码块
    foreach ($codeBlocks as $i => $code) {
        $text = str_replace('<!--CODE' . $i . '-->', $code, $text);
    }

    return $text;
}

/**
 * 还原数学公式占位符
 * 在 Markdown 解析后调用，将占位符替换回公式 HTML
 *
 * @param string $content Markdown 解析后的 HTML 内容
 * @param array &$mathBlocks 提取的公式 HTML 数组
 * @return string 还原后的 HTML 内容
 */
function shufei_restore_math_placeholders($content, &$mathBlocks)
{
    if ($content === null) {
        $content = '';
    }
    foreach ($mathBlocks as $i => $mathHtml) {
        $content = str_replace('<!--MATH' . $i . '-->', $mathHtml, $content);
        // Markdown 可能在占位符外面包了 <p> 标签
        $content = str_replace('<p><!--MATH' . $i . '--></p>', $mathHtml, $content);
    }
    return $content;
}

/**
 * KaTeX 内容过滤器
 * 在 Markdown 解析前提取数学公式，防止 Markdown 解析器将 _ 转为 <em> 等标签破坏公式语法
 * 将公式内容提取并包装在占位符中，Markdown 解析后再还原
 */
function shufei_katex_content_filter($content, $widget, $lastResult)
{
    $content = $lastResult ?: $content;
    if ($content === null) {
        $content = '';
    }

    // 保护行内代码中包含反引号序列的模式（如 ` ```mermaid `）
    $icodeBlocks = array();
    if ($widget->isMarkdown) {
        $content = shufei_protect_inline_code_backticks($content, $icodeBlocks);
    }

    $options = \Typecho\Widget::widget('Widget_Options');
    if (empty($options->katexEnabled) || $options->katexEnabled !== 'on') {
        // KaTeX 未开启，直接走默认 Markdown 解析
        $result = $widget->isMarkdown ? $widget->markdown($content) : $widget->autoP($content);

        // 还原行内代码占位符
        $result = shufei_restore_inline_code_backticks($result ?? '', $icodeBlocks);

        return $result ?? '';
    }

    // 用占位符保护数学公式，避免 Markdown 解析器破坏
    $mathBlocks = array();
    $content = shufei_extract_math_placeholders($content, $mathBlocks);

    // 执行 Markdown 解析
    $content = $widget->isMarkdown ? $widget->markdown($content) : $widget->autoP($content);

    // 防止 markdown/autoP 返回 null
    if ($content === null) {
        $content = '';
    }

    // 还原行内代码占位符
    $content = shufei_restore_inline_code_backticks($content, $icodeBlocks);

    // 还原数学公式占位符
    $content = shufei_restore_math_placeholders($content, $mathBlocks);

    return $content;
}

/**
 * 修复 Markdown 在数学公式中产生的副作用
 * 将 Markdown 生成的 HTML 标签还原为原始符号
 */
function shufei_fix_markdown_in_math($text)
{
    // 将 <em> 还原为下划线（Markdown 将 _ 转为 <em>）
    $text = preg_replace('/<em>(.*?)<\/em>/s', '_$1_', $text);
    // 将 <strong> 还原为星号
    $text = preg_replace('/<strong>(.*?)<\/strong>/s', '**$1**', $text);
    // 将 <del> 还原为波浪号
    $text = preg_replace('/<del>(.*?)<\/del>/s', '~~$1~~', $text);
    // 移除 <br> 标签
    $text = preg_replace('/<br\s*\/?>/i', '', $text);
    // 解码 HTML 实体（如 &amp; → &）
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($text);
}

/**
 * 渲染文章内容（模板调用入口）
 * 整合预处理、Markdown 解析、回复可见、扩展处理
 * 优化：对渲染结果做文件缓存，避免每次请求重复 16+ 次正则替换
 *
 * @param Widget\Base\Contents $widget 文章 widget 实例
 * @return string 处理后的 HTML 内容
 */
function shufei_render_post_content($widget)
{
    // 仅对已发布文章/页面启用缓存
    $cid = $widget->cid;
    $rawText = $widget->text;
    // 加入主题文件版本因子（functions.php 修改时间），主题功能改动后旧缓存自动失效
    $themeVersion = filemtime(__FILE__);
    $contentHash = md5($rawText . ($widget->modified ?? $widget->created) . '|tv:' . $themeVersion);
    $cacheKey = 'content_' . $cid . '_' . $contentHash;
    $cacheFile = dirname(__FILE__) . '/cache/' . $cacheKey . '.html';
    $cacheDir = dirname($cacheFile);

    // 尝试读取缓存（缓存有效期 6 小时）
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 21600) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            // 缓存命中后仍需处理回复可见（依赖用户状态）
            return shufei_parse_reply_content($cached, $cid);
        }
    }

    if ($widget->isMarkdown) {
        // 获取原始 Markdown 文本（___text() 已剥离 <!--markdown--> 前缀）
        $rawText = $widget->text;
        // 预处理：修复表格中行内代码内的 | 等问题
        $rawText = shufei_pre_markdown_process($rawText);

        // 保护行内代码中包含反引号序列的模式（如 ` ```mermaid `）
        $icodeBlocks = array();
        $rawText = shufei_protect_inline_code_backticks($rawText, $icodeBlocks);

        // KaTeX 公式保护：在 Markdown 解析前提取数学公式，防止 _ 等被破坏
        $mathBlocks = array();
        $katexEnabled = false;
        $options = \Typecho\Widget::widget('Widget_Options');
        if (!empty($options->katexEnabled) && $options->katexEnabled === 'on') {
            $katexEnabled = true;
            $rawText = shufei_extract_math_placeholders($rawText, $mathBlocks);
        }

        // 手动 Markdown 解析
        $html = \Utils\Markdown::convert($rawText);

        // 还原行内代码占位符
        $html = shufei_restore_inline_code_backticks($html, $icodeBlocks);

        // 还原数学公式占位符
        if ($katexEnabled) {
            $html = shufei_restore_math_placeholders($html, $mathBlocks);
        }
    } else {
        // 非 Markdown 内容，使用默认解析
        $html = $widget->content;
    }

    // 应用 Markdown 扩展（在缓存前完成，避免重复正则处理）
    $html = shufei_apply_markdown_ext($html);

    // 写入缓存（回复可见部分不缓存，因为依赖用户状态）
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, $html);

    // 处理回复可见
    $html = shufei_parse_reply_content($html, $widget->cid);

    return $html;
}

/**
 * 保护行内代码中包含反引号序列的模式
 * 处理 ` ```mermaid ` 这类 HyperDown 无法正确解析的模式
 * 在 Markdown 解析前将整段行内代码替换为占位符，解析后还原为正确的 HTML
 *
 * @param string $text Markdown 原始文本
 * @param array &$codeBlocks 存储替换的 HTML 内容
 * @return string 替换后的文本
 */
function shufei_protect_inline_code_backticks($text, &$codeBlocks)
{
    if (empty($text) || !is_string($text)) {
        return $text ?? '';
    }

    // 先临时保护围栏代码块，避免匹配到代码块内部的相同模式
    $fencedBlocks = array();
    $text = preg_replace_callback('/^( {0,3})(`{3,})([\s\S]*?)^\1`{3,}/m', function ($m) use (&$fencedBlocks) {
        $key = '<!--FENCED' . count($fencedBlocks) . '-->';
        $fencedBlocks[] = $m[0];
        return $key;
    }, $text);
    $text = preg_replace_callback('/^( {0,3})(~{3,})([\s\S]*?)^\1~{3,}/m', function ($m) use (&$fencedBlocks) {
        $key = '<!--FENCED' . count($fencedBlocks) . '-->';
        $fencedBlocks[] = $m[0];
        return $key;
    }, $text);
    // 保护缩进代码块
    $text = preg_replace_callback('/^( {4,}.*(?:\n {4,}.*)*)/m', function ($m) use (&$fencedBlocks) {
        $key = '<!--FENCED' . count($fencedBlocks) . '-->';
        $fencedBlocks[] = $m[0];
        return $key;
    }, $text);

    // 匹配行内代码中内容以反引号序列开头的模式
    // 例如 ` ```mermaid ` → 开头反引号 + 空格 + 反引号序列 + 文字 + 空格 + 结尾反引号
    $text = preg_replace_callback('/`[ \t]*(`+)([^`\n]+?)[ \t]*`/', function ($m) use (&$codeBlocks) {
        $key = '<!--ICODE' . count($codeBlocks) . '-->';
        $innerBackticks = $m[1];
        $rest = $m[2];
        $codeBlocks[] = '<code>' . htmlspecialchars($innerBackticks . $rest) . '</code>';
        return $key;
    }, $text);

    // 还原围栏代码块
    foreach ($fencedBlocks as $i => $fenced) {
        $text = str_replace('<!--FENCED' . $i . '-->', $fenced, $text);
    }

    return $text;
}

/**
 * 还原行内代码占位符
 * 在 Markdown 解析后，将占位符替换回正确的 HTML
 *
 * @param string $content Markdown 解析后的 HTML 内容
 * @param array &$codeBlocks 存储的 HTML 内容数组
 * @return string 还原后的 HTML 内容
 */
function shufei_restore_inline_code_backticks($content, &$codeBlocks)
{
    if (empty($codeBlocks)) {
        return $content;
    }
    foreach ($codeBlocks as $i => $html) {
        $content = str_replace('<!--ICODE' . $i . '-->', $html, $content);
        // Markdown 可能在占位符外面包了 <p> 标签
        $content = str_replace('<p><!--ICODE' . $i . '--></p>', $html, $content);
    }
    return $content;
}

/**
 * 预处理 Markdown 文本，修复 HyperDown 解析器的已知问题
 * 1. 表格中行内代码内的 | 会被误当作列分隔符，替换为 &#124;
 * 2. 表格中 \| 转义在 HyperDown 中无效，也需替换
 */
function shufei_pre_markdown_process($text)
{
    if (empty($text) || !is_string($text) || strpos($text, '|') === false) {
        return $text ?? '';
    }

    // 检查是否包含表格（含 | 分隔的行，允许行尾空白）
    if (!preg_match('/^\|.+\|\s*$/m', $text)) {
        return $text;
    }

    // 逐行处理：只处理表格行
    $lines = explode("\n", $text);
    foreach ($lines as &$line) {
        // 跳过非表格行
        if (strpos($line, '|') === false) {
            continue;
        }

        // 1. 保护行内代码中的 | 和 \| 字符
        $line = preg_replace_callback('/`[^`]+`/', function ($m) {
            // 先处理 \| （去掉无意义的反斜杠转义），再处理 |
            $result = str_replace('\\|', '&#124;', $m[0]);
            $result = str_replace('|', '&#124;', $result);
            return $result;
        }, $line);

        // 2. 保护表格行中非代码的 \| 转义（HyperDown 不支持 \| 转义）
        // 只在表格行中处理，避免影响普通段落
        $line = preg_replace('/\\\\\|/', '&#124;', $line);
    }

    return implode("\n", $lines);
}

/**
 * 还原 <code> 和 <pre> 内的 | 占位符
 * 由于 shufei_pre_markdown_process 在预处理阶段将 | 转为 &#124; 避免破坏表格列分隔，
 * Markdown 解析后 &#124; 会被 HTML 实体化为 &amp;#124;，导致在代码块中显示为字面量 &#124;
 * 此函数在最终输出前将 &amp;#124; / &#124; 还原为 |
 */
function shufei_fix_inline_code_pipe($content)
{
    if (empty($content) || !is_string($content) || (strpos($content, '&#124;') === false && strpos($content, '&amp;#124;') === false)) {
        return $content ?? '';
    }

    return preg_replace_callback(
        '/<(code|pre)[^>]*>.*?<\/\1>/si',
        function ($matches) {
            $fixed = str_replace('&amp;#124;', '|', $matches[0]);
            $fixed = str_replace('&#124;', '|', $fixed);
            return $fixed;
        },
        $content
    );
}

/**
 * 直接应用 Markdown 扩展处理（模板调用入口）
 * 绕过钩子系统，直接在模板中处理内容
 *
 * @param string $content 已解析的 HTML 内容
 * @return string 处理后的 HTML 内容
 */
function shufei_apply_markdown_ext($content)
{
    if (empty($content)) {
        return $content;
    }

    // 检查是否开启 Markdown 扩展
    $options = \Typecho\Widget::widget('Widget_Options');
    if (!empty($options->markdownExtEnabled) && $options->markdownExtEnabled === 'off') {
        return $content;
    }

    // 清理可能残留的 <!--markdown--> 标记
    $content = preg_replace('/<p><!--markdown--><\/p>/', '', $content);
    $content = str_replace('<!--markdown-->', '', $content);

    // 1. 处理图片扩展语法（大小、对齐、标题、懒加载）
    $content = shufei_process_images($content);

    // 2. 处理高亮文本 ==text==
    $content = shufei_process_mark($content);

    // 3. 处理任务列表 - [x] / - [ ]
    $content = shufei_process_task_lists($content);

    // 4. 处理 blockquote 中的提示框和折叠区块（统一处理，避免嵌套）
    $content = shufei_process_blockquote_extensions($content);

    // 5. 还原 <code>/<pre> 内的 | 占位符（表格预处理产生）
    $content = shufei_fix_inline_code_pipe($content);

    // 6. 清理残留的空 blockquote 标签
    $content = preg_replace('/<blockquote>\s*<\/blockquote>/s', '', $content);
    // 清理仅包含 admonition/details div 的 blockquote（合并提示框处理后残留）
    $content = shufei_match_outer_blockquotes($content, function ($inner, $fullMatch) {
        $trimmedInner = trim($inner);
        // 如果内部只包含 div 元素（admonition/details），移除 blockquote 包裹
        if (preg_match('/^(<div[^>]*>.*<\/div>)\s*$/s', $trimmedInner) ||
            preg_match('/^(<div[^>]*>.*<\/div>\s*)+$/s', $trimmedInner)) {
            return $inner;
        }
        return $fullMatch;
    });

    // 7. 处理视频短代码 [video]url[/video] 或 [video src="url"]
    $content = shufei_process_video_shortcode($content);

    // 8. 处理音乐短代码 [music]url[/music] 或 [music src="url"]
    $content = shufei_process_music_shortcode($content);

    return $content;
}

/**
 * Markdown 扩展内容过滤器（钩子版本）
 * 在 Markdown 解析后处理扩展语法，增强文章内容显示
 *
 * 支持的扩展语法：
 * 1. 图片大小：![alt|300x200](url) 或 ![alt|50%](url)
 * 2. 图片对齐：![alt#center](url)、![alt#left](url)、![alt#right](url)
 * 3. 图片带标题：![这是标题](url) 自动转为 <figure>+<figcaption>
 * 4. 高亮文本：==高亮内容== → <mark>高亮内容</mark>
 * 5. 任务列表：- [x] 已完成 / - [ ] 未完成 → 复选框
 * 6. 提示框：> [!tip] 内容 / > [!warning] 内容 / > [!note] 内容 等
 * 7. 折叠区块：> [details:标题] 内容 → <details><summary>标题</summary>内容</details>
 * 8. 图片懒加载：自动为图片添加 loading="lazy"
 *
 * @param string $content 已解析的 HTML 内容
 * @param object $widget Contents widget 实例
 * @param string $lastResult 上一个钩子的返回值
 * @return string 处理后的 HTML 内容
 */
function shufei_markdown_ext_filter($content, $widget, $lastResult)
{
    $content = $lastResult ?: $content;

    // 仅在文章/页面内容中处理
    if (empty($content)) {
        return $content;
    }

    return shufei_apply_markdown_ext($content);
}

/**
 * 处理高亮文本 ==text== → <mark>text</mark>
 */
function shufei_process_mark($content)
{
    if ($content === null) {
        $content = '';
    }
    // 先保护 <code>、<pre> 内的内容和 HTML 标签本身（含属性值），避免误处理
    $protected = array();
    // 保护 <pre>...</pre> 和 <code>...</code>
    $content = preg_replace_callback('/<(code|pre)[^>]*>.*?<\/\1>/si', function ($m) use (&$protected) {
        $key = '<!--PROTECT' . count($protected) . '-->';
        $protected[] = $m[0];
        return $key;
    }, $content);
    // 保护 HTML 标签（含属性值中的 ==），避免 style="color:==red==" 被误匹配
    $content = preg_replace_callback('/<[a-zA-Z][^>]*>/s', function ($m) use (&$protected) {
        $key = '<!--PROTECT' . count($protected) . '-->';
        $protected[] = $m[0];
        return $key;
    }, $content);

    // 匹配 ==text==，此时已无 HTML 标签干扰
    $content = preg_replace_callback(
        '/==(?!==)(.+?)(?<!<\/)==(?!==)/s',
        function ($matches) {
            return '<mark>' . $matches[1] . '</mark>';
        },
        $content
    );

    // 还原保护的内容
    foreach ($protected as $i => $html) {
        $content = str_replace('<!--PROTECT' . $i . '-->', $html, $content);
    }

    return $content;
}

/**
 * 处理任务列表
 * - [x] 已完成 → <li class="task-list-item"><input type="checkbox" checked disabled>
 * - [ ] 未完成 → <li class="task-list-item"><input type="checkbox" disabled>
 */
function shufei_process_task_lists($content)
{
    if ($content === null) {
        $content = '';
    }
    // 匹配 <li>- [x] 或 <li>[x] 等变体
    $content = preg_replace_callback(
        '/<li>(\s*)\[([ xX])\]\s*/s',
        function ($matches) {
            $checked = strtolower($matches[2]) === 'x';
            $checkbox = '<input type="checkbox" class="task-list-checkbox"' .
                ($checked ? ' checked' : '') .
                ' disabled>';
            return '<li class="task-list-item">' . $matches[1] . $checkbox . ' ';
        },
        $content
    );

    return $content;
}

/**
 * 匹配最外层 blockquote 标签（正确处理嵌套）
 * 使用递归方式匹配，避免非贪婪匹配在嵌套时错误匹配内层结束标签
 *
 * @param string $content HTML 内容
 * @param callable $callback 对每个匹配的 blockquote 调用，参数为 (innerHtml, fullMatch)
 * @return string 处理后的内容
 */
function shufei_match_outer_blockquotes($content, $callback)
{
    $result = '';
    $offset = 0;
    $len = strlen($content);
    $openTagLen = 12;   // strlen('<blockquote>')
    $closeTagLen = 13;  // strlen('</blockquote>')

    while ($offset < $len) {
        // 查找下一个 <blockquote>
        $start = strpos($content, '<blockquote>', $offset);
        if ($start === false) {
            $result .= substr($content, $offset);
            break;
        }

        // 输出 <blockquote> 之前的内容
        $result .= substr($content, $offset, $start - $offset);

        // 使用计数器找到匹配的 </blockquote>
        $depth = 1;
        $searchPos = $start + $openTagLen;
        while ($depth > 0 && $searchPos < $len) {
            $nextOpen = strpos($content, '<blockquote>', $searchPos);
            $nextClose = strpos($content, '</blockquote>', $searchPos);

            if ($nextClose === false) {
                // 没有匹配的关闭标签，原样保留
                $searchPos = $len;
                break;
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $searchPos = $nextOpen + $openTagLen;
            } else {
                $depth--;
                if ($depth === 0) {
                    $end = $nextClose + $closeTagLen;
                    $inner = substr($content, $start + $openTagLen, $nextClose - $start - $openTagLen);
                    $fullMatch = substr($content, $start, $end - $start);
                    $result .= $callback($inner, $fullMatch);
                    $offset = $end;
                    break;
                }
                $searchPos = $nextClose + $closeTagLen;
            }
        }

        // 如果没找到匹配的关闭标签
        if ($depth > 0) {
            $result .= substr($content, $start);
            break;
        }
    }

    return $result;
}

/**
 * 统一处理 blockquote 中的提示框和折叠区块
 * 逐段解析，避免合并 blockquote 时的嵌套问题
 *
 * 支持语法：
 * > [!tip] 标题          → 提示框
 * > [details:标题]       → 折叠区块
 */
function shufei_process_blockquote_extensions($content)
{
    if ($content === null) {
        $content = '';
    }
    $content = shufei_match_outer_blockquotes($content, function ($inner, $fullMatch) {
            // 检查是否包含扩展标记（支持 <p> 包裹和直接文本两种情况）
            if (!preg_match('/(<p>)?\[!(tip|note|info|warning|danger)\]|(<p>)?\[details:/i', $inner)) {
                return $fullMatch; // 无标记，原样返回
            }

            // 先保护 <pre> 块，避免段落拆分时破坏代码块
            $preBlocks = array();
            $inner = preg_replace_callback('/<pre[^>]*>.*?<\/pre>/si', function ($m) use (&$preBlocks) {
                $key = '<!--PRE' . count($preBlocks) . '-->';
                $preBlocks[] = $m[0];
                return $key;
            }, $inner);

            // 按段落拆分（保留分隔符）
            $parts = preg_split('/(<p>.*?<\/p>)/si', $inner, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            $result = '';
            $currentBlock = null; // 当前正在构建的块

            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (empty($trimmed)) continue;

                // 检查是否为提示框标记段落（<p> 包裹）
                if (preg_match('/^<p>\[!(tip|note|info|warning|danger)\](.*?)<\/p>$/si', $trimmed, $m)) {
                    // 先输出之前的块
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $type = strtolower($m[1]);
                    $titleAndBody = trim($m[2]);
                    $title = '';
                    $body = '';
                    if (preg_match('/^(.*?)(?:<br\s*\/?>)(.*)$/s', $titleAndBody, $titleParts)) {
                        $title = trim($titleParts[1]);
                        $body = trim($titleParts[2]);
                    } else {
                        $title = $titleAndBody;
                    }

                    $currentBlock = array('type' => 'admonition', 'subtype' => $type, 'title' => $title, 'body' => $body);
                }
                // 检查是否为折叠区块标记段落（<p> 包裹）
                elseif (preg_match('/^<p>\[details:(.*?)\](.*?)<\/p>$/si', $trimmed, $m)) {
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $summary = trim($m[1]);
                    $inlineBody = trim($m[2]);
                    $body = '';
                    if (preg_match('/^(?:<br\s*\/?>)?(.*)$/s', $inlineBody, $bodyMatch)) {
                        $body = trim($bodyMatch[1]);
                    }

                    $currentBlock = array('type' => 'details', 'summary' => $summary, 'body' => $body);
                }
                // 检查无 <p> 包裹的提示框标记（HyperDown 有时不生成 <p> 标签）
                elseif (preg_match('/^\[!(tip|note|info|warning|danger)\](.*)$/si', $trimmed, $m)) {
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $type = strtolower($m[1]);
                    $titleAndBody = trim($m[2]);
                    $title = '';
                    $body = '';
                    // 无 <p> 包裹时用 <br> 分割标题和内容
                    if (preg_match('/^(.*?)(?:<br\s*\/?>)(.*)$/s', $titleAndBody, $titleParts)) {
                        $title = trim($titleParts[1]);
                        $body = trim($titleParts[2]);
                    } else {
                        $title = $titleAndBody;
                    }

                    $currentBlock = array('type' => 'admonition', 'subtype' => $type, 'title' => $title, 'body' => $body);
                }
                // 检查无 <p> 包裹的折叠区块标记
                elseif (preg_match('/^\[details:(.*?)\](.*)$/si', $trimmed, $m)) {
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $summary = trim($m[1]);
                    $inlineBody = trim($m[2]);
                    $body = '';
                    if (preg_match('/^(?:<br\s*\/?>)?(.*)$/s', $inlineBody, $bodyMatch)) {
                        $body = trim($bodyMatch[1]);
                    }

                    $currentBlock = array('type' => 'details', 'summary' => $summary, 'body' => $body);
                }
                // 内容段落：归属当前块，或作为独立内容
                elseif ($currentBlock !== null) {
                    $currentBlock['body'] .= (empty($currentBlock['body']) ? '' : ' ') . $trimmed;
                } else {
                    $result .= $part;
                }
            }

            // 输出最后一个块
            if ($currentBlock !== null) {
                $result .= shufei_build_ext_block_html($currentBlock);
            }

            // 还原 <pre> 块
            foreach ($preBlocks as $i => $preHtml) {
                $result = str_replace('<!--PRE' . $i . '-->', $preHtml, $result);
            }

            return $result;
        });

    return $content;
}

/**
 * 构建扩展块的 HTML（提示框或折叠区块）
 */
function shufei_build_ext_block_html($block)
{
    if ($block['type'] === 'admonition') {
        $type = $block['subtype'];
        $title = $block['title'];
        $body = $block['body'];

        $typeNames = array(
            'tip'    => '提示',
            'note'   => '备注',
            'info'   => '信息',
            'warning' => '警告',
            'danger' => '危险'
        );
        $displayTitle = !empty($title) ? $title : (isset($typeNames[$type]) ? $typeNames[$type] : ucfirst($type));

        $html = '<div class="admonition admonition-' . $type . '">';
        $html .= '<div class="admonition-title">' . htmlspecialchars($displayTitle) . '</div>';
        if (!empty($body)) {
            $html .= '<div class="admonition-content">' . $body . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    if ($block['type'] === 'details') {
        $summary = $block['summary'];
        $body = $block['body'];

        $html = '<details class="post-details">';
        $html .= '<summary>' . htmlspecialchars($summary) . '</summary>';
        $html .= '<div class="details-content">' . $body . '</div>';
        $html .= '</details>';
        return $html;
    }

    return '';
}

