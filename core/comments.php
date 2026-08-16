<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 评论辅助与验证码验证
 * 从 functions.php 分层迁移
 */

/**
 * 检查当前用户是否已评论指定文章
 *
 * @param int $cid 文章ID
 * @return bool
 */
function shufei_has_commented($cid)
{
    static $cache = array();
    if (isset($cache[$cid])) {
        return $cache[$cid];
    }

    $db = \Typecho\Db::get();
    $hasCommented = false;

    if (\Typecho\Widget::widget('Widget_User')->hasLogin()) {
        $user = \Typecho\Widget::widget('Widget_User');
        $count = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))
            ->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('authorId = ?', $user->uid)
            ->where('status = ?', 'approved'))->num;
        $hasCommented = $count > 0;
    } else {
        $cookieMail = \Typecho\Cookie::get('__typecho_remember_mail');
        if (!empty($cookieMail)) {
            $count = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))
                ->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('mail = ?', $cookieMail)
                ->where('status = ?', 'approved'))->num;
            $hasCommented = $count > 0;
        }
    }

    $cache[$cid] = $hasCommented;
    return $hasCommented;
}

/**
 * 解析文章内容中的 [reply] 短代码
 * 已评论用户可见，未评论用户显示提示
 *
 * @param string $content 文章内容
 * @param int $cid 文章ID
 * @return string
 */
function shufei_parse_reply_content($content, $cid)
{
    if ($content === null) {
        $content = '';
    }
    $hasCommented = shufei_has_commented($cid);

    return preg_replace_callback('/\[reply\](.*?)\[\/reply\]/is', function ($matches) use ($hasCommented, $cid) {
        if ($hasCommented) {
            return '<div class="reply-visible-content">' . $matches[1] . '</div>';
        } else {
            return '<div class="reply-hidden-box">
                <div class="reply-hidden-inner">
                    <div class="reply-hidden-icon">
                        <i class="fa fa-lock"></i>
                    </div>
                    <div class="reply-hidden-title">回复可见</div>
                    <div class="reply-hidden-desc">此处内容需要评论后才可查看，快来参与讨论吧！</div>
                    <a href="#comments" class="reply-hidden-btn">
                        <i class="fa fa-commenting"></i> 去评论
                    </a>
                </div>
            </div>';
        }
    }, $content);
}

/**
 * 核心逻辑钩子：评论安全性校验（包含验证码校验 + AI审核）
 */
function shufei_comment_check($comment, $post) {
    $options = \Typecho\Widget::widget('Widget_Options');
    $captchaType = shufei_get_captcha_type();

    // 1. 验证码校验
    if ($captchaType === 'turnstile') {
        // Turnstile 验证
        $token = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
        if (empty($token)) {
            throw new \Typecho\Widget\Exception(_t('请先完成人机验证'));
        }
        // 调用 Cloudflare Siteverify API 验证 token
        $secretKey = isset($options->turnstileSecretKey) ? $options->turnstileSecretKey : '';
        if (empty($secretKey)) {
            // fail-closed：密钥未配置视为校验失败，避免静默放行
            throw new \Typecho\Widget\Exception(_t('人机验证服务未正确配置，请联系管理员'));
        }
        $verifyResult = shufei_turnstile_verify_curl($secretKey, $token);
        // fail-closed：网络异常、curl 不可用或返回空时拒绝评论
        if ($verifyResult === null) {
            throw new \Typecho\Widget\Exception(_t('人机验证服务暂时不可用，请稍后重试'));
        }
        if (!isset($verifyResult['success']) || !$verifyResult['success']) {
            throw new \Typecho\Widget\Exception(_t('人机验证未通过，请重试'));
        }
    } elseif (shufei_is_geetest_enabled()) {
        // 极验 Geetest v4 验证
        $lotNumber = isset($_POST['geetest_lot_number']) ? trim($_POST['geetest_lot_number']) : '';
        $captchaOutput = isset($_POST['geetest_captcha_output']) ? trim($_POST['geetest_captcha_output']) : '';
        $passToken = isset($_POST['geetest_pass_token']) ? trim($_POST['geetest_pass_token']) : '';
        $genTime = isset($_POST['geetest_gen_time']) ? trim($_POST['geetest_gen_time']) : '';

        if (empty($lotNumber) || empty($captchaOutput) || empty($passToken) || empty($genTime)) {
            throw new \Typecho\Widget\Exception(_t('请先完成人机验证'));
        }

        $captchaId = isset($options->geetestCaptchaId) ? $options->geetestCaptchaId : '';
        $captchaKey = isset($options->geetestCaptchaKey) ? $options->geetestCaptchaKey : '';
        if (empty($captchaId) || empty($captchaKey)) {
            // fail-closed：密钥未配置视为校验失败，避免静默放行
            throw new \Typecho\Widget\Exception(_t('人机验证服务未正确配置，请联系管理员'));
        }

        // sign_token = HMAC-SHA256(captcha_key, lot_number)，输出为小写十六进制
        $signToken = hash_hmac('sha256', $lotNumber, $captchaKey);

        $verifyResult = shufei_geetest_verify($captchaId, $lotNumber, $captchaOutput, $passToken, $genTime, $signToken);
        // fail-closed：网络异常、curl 不可用或返回空时拒绝评论
        if ($verifyResult === null) {
            throw new \Typecho\Widget\Exception(_t('人机验证服务暂时不可用，请稍后重试'));
        }
        if (!isset($verifyResult['result']) || $verifyResult['result'] !== 'success') {
            throw new \Typecho\Widget\Exception(_t('人机验证未通过，请重试'));
        }
    } elseif (shufei_is_catcaptcha_enabled()) {
        // Cat-Captcha 验证
        $ticket = isset($_POST['catcaptcha_ticket']) ? trim($_POST['catcaptcha_ticket']) : '';
        if (empty($ticket)) {
            throw new \Typecho\Widget\Exception(_t('请先完成人机验证'));
        }
        $siteId = shufei_get_catcaptcha_site_id();
        $secretKey = shufei_get_catcaptcha_secret_key();
        if (empty($siteId) || empty($secretKey)) {
            // fail-closed：密钥未配置视为校验失败，避免静默放行
            throw new \Typecho\Widget\Exception(_t('人机验证服务未正确配置，请联系管理员'));
        }
        $verifyResult = shufei_catcaptcha_verify($ticket, $siteId, $secretKey);
        // fail-closed：网络异常、curl 不可用或返回空时拒绝评论
        if ($verifyResult === null) {
            throw new \Typecho\Widget\Exception(_t('人机验证服务暂时不可用，请稍后重试'));
        }
        if (!isset($verifyResult['valid']) || $verifyResult['valid'] !== true) {
            throw new \Typecho\Widget\Exception(_t('人机验证未通过，请重试'));
        }
        // 必须核对 action 与期望一致，防止票据被跨场景复用
        if (isset($verifyResult['action']) && $verifyResult['action'] !== shufei_get_catcaptcha_action()) {
            throw new \Typecho\Widget\Exception(_t('人机验证未通过，请重试'));
        }
    } elseif (shufei_is_captcha_enabled()) {
        // 图片验证码验证
        session_start();
        $captchaInput = isset($_POST['captcha_code']) ? strtolower(trim($_POST['captcha_code'])) : '';
        $captchaSession = isset($_SESSION['shufei_captcha_code']) ? $_SESSION['shufei_captcha_code'] : '';

        // 验证后立即清除，防止重复使用
        unset($_SESSION['shufei_captcha_code']);
        unset($_SESSION['shufei_captcha_time']);

        if (empty($captchaInput)) {
            throw new \Typecho\Widget\Exception(_t('请填写验证码'));
        }
        if (empty($captchaSession)) {
            throw new \Typecho\Widget\Exception(_t('验证码已过期，请刷新验证码后重试'));
        }
        if ($captchaInput !== $captchaSession) {
            throw new \Typecho\Widget\Exception(_t('验证码错误，请重新输入'));
        }
    }

    // 2. AI评论审核
    if (function_exists('processAiModeration')) {
        $comment = processAiModeration($comment);
    }

    return $comment;
}

/**
 * 使用 cURL 验证 Turnstile token
 * curl 失败时回退到 file_get_contents
 */
function shufei_turnstile_verify_curl($secretKey, $token) {
    $postData = http_build_query(array(
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
    ));
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // 优先使用 cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);
        if ($response) {
            return json_decode($response, true);
        }
        // 记录 curl 错误，便于排查
        error_log('[ShuFeiCat] Turnstile curl 失败: errno=' . $errno . ' msg=' . $errmsg);
    }

    // 回退到 file_get_contents
    if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $response = @file_get_contents($url, false, $ctx);
        if ($response) {
            return json_decode($response, true);
        }
        error_log('[ShuFeiCat] Turnstile file_get_contents 失败: ' . (isset($http_response_header[0]) ? $http_response_header[0] : 'unknown'));
    }

    return null;
}

/**
 * 使用 cURL 验证极验 Geetest v4 校验参数
 *
 * @param string $captchaId     极验 Captcha ID
 * @param string $lotNumber     验证流水号
 * @param string $captchaOutput 验证输出
 * @param string $passToken     pass_token
 * @param string $genTime       生成时间
 * @param string $signToken     签名 token (HMAC-SHA256)
 * @return array|null 返回极验校验结果数组，失败返回 null
 */
function shufei_geetest_verify($captchaId, $lotNumber, $captchaOutput, $passToken, $genTime, $signToken) {
    $postFields = http_build_query(array(
        'captcha_id' => $captchaId,
        'lot_number' => $lotNumber,
        'captcha_output' => $captchaOutput,
        'pass_token' => $passToken,
        'gen_time' => $genTime,
        'sign_token' => $signToken
    ));
    $url = 'https://gcaptcha4.geetest.com/validate';

    // 优先使用 cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);
        if ($response) {
            return json_decode($response, true);
        }
        error_log('[ShuFeiCat] Geetest curl 失败: errno=' . $errno . ' msg=' . $errmsg);
    }

    // 回退到 file_get_contents
    if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postFields,
                'timeout' => 10,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $response = @file_get_contents($url, false, $ctx);
        if ($response) {
            return json_decode($response, true);
        }
        error_log('[ShuFeiCat] Geetest file_get_contents 失败: ' . (isset($http_response_header[0]) ? $http_response_header[0] : 'unknown'));
    }

    return null;
}

/**
 * 使用 cURL 验证 Cat-Captcha 票据
 *
 * 二次校验协议（HMAC 签名）：
 *   签名内容 = site_id \n timestamp \n nonce \n ticket \n action
 *   请求头：Authorization: Captcha {site_id}:{sig}，X-Timestamp，X-Nonce
 *   请求体：ticket、action 表单字段
 *
 * @param string $ticket    前端验证通过后返回的一次性票据
 * @param string $siteId    站点 ID
 * @param string $secretKey 站点 Secret Key（仅后端持有）
 * @return array|null 返回校验结果数组（含 valid/action/risk 等），失败返回 null
 */
function shufei_catcaptcha_verify($ticket, $siteId, $secretKey) {
    $apiBase = shufei_get_catcaptcha_api_base();
    $action = shufei_get_catcaptcha_action();
    $ts = time();
    $nonce = bin2hex(random_bytes(16));
    $sig = hash_hmac('sha256', "$siteId\n$ts\n$nonce\n$ticket\n$action", $secretKey);
    $postData = http_build_query(array(
        'ticket' => $ticket,
        'action' => $action
    ));
    $url = rtrim($apiBase, '/') . '/api/validate.php';
    $headers = array(
        "Authorization: Captcha $siteId:$sig",
        "X-Timestamp: $ts",
        "X-Nonce: $nonce",
        "Content-Type: application/x-www-form-urlencoded"
    );

    // 优先使用 cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);
        if ($response) {
            return json_decode($response, true);
        }
        error_log('[ShuFeiCat] Cat-Captcha curl 失败: errno=' . $errno . ' msg=' . $errmsg);
    }

    // 回退到 file_get_contents
    if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $postData,
                'timeout' => 10,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $response = @file_get_contents($url, false, $ctx);
        if ($response) {
            return json_decode($response, true);
        }
        error_log('[ShuFeiCat] Cat-Captcha file_get_contents 失败: ' . (isset($http_response_header[0]) ? $http_response_header[0] : 'unknown'));
    }

    return null;
}

/**
 * 解析评论 Markdown 内容
 *
 * @param string $text 评论原始文本
 * @return string 解析后的 HTML
 */
function shufei_parse_comment_markdown($text)
{
    if (empty($text)) {
        return $text;
    }

    $options = \Typecho\Widget::widget('Widget_Options');

    // 先提取数学公式（防止 Markdown 破坏公式语法）
    // 复用已有的公式提取函数，使用 <!--MATH0--> 格式占位符（不会被 Markdown 破坏）
    $mathBlocks = array();
    if (!empty($options->katexEnabled) && $options->katexEnabled === 'on') {
        $text = shufei_extract_math_placeholders($text, $mathBlocks);
    }

    // 使用 Typecho 内置 Markdown 解析器
    $html = \Utils\Markdown::convert($text);

    // 还原数学公式占位符
    if (!empty($mathBlocks)) {
        $html = shufei_restore_math_placeholders($html, $mathBlocks);
    }

    // 应用 Markdown 扩展（高亮、提示框、Mermaid、ECharts、视频、音乐等）
    $html = shufei_apply_markdown_ext($html);

    // 解析评论中的表情代码为图片
    $html = shufei_parse_emoji_code($html, $options);

    // 安全过滤：对评论 HTML 做白名单过滤，防止存储型 XSS
    $html = shufei_sanitize_comment_html($html);

    return $html;
}

/**
 * 解析评论中的文章引用标记 [quote]...[/quote]
 * 将其转换为带"定位到原文"功能的 <blockquote> HTML
 * 不依赖 Markdown 是否开启，统一处理
 *
 * @param string $html 评论 HTML 内容
 * @return string 处理后的 HTML
 */
function shufei_parse_comment_quote($html)
{
    if (empty($html)) return $html;

    // 构造引用块 HTML 的闭包
    $buildQuote = function($content) {
        $content = trim($content);
        if ($content === '') return '';

        // 提取纯文本用于定位（移除所有 HTML 标签）
        $plainText = strip_tags($content);
        // 解码 HTML 实体，得到原始文本
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
        // 截取前 200 字符用于搜索（避免 data 属性过长）
        if (function_exists('mb_strlen') && mb_strlen($plainText) > 200) {
            $plainText = mb_substr($plainText, 0, 200, 'UTF-8');
        } elseif (!function_exists('mb_strlen') && strlen($plainText) > 600) {
            $plainText = substr($plainText, 0, 600);
        }

        // 构造引用块 HTML
        // data-quote-text 存储纯文本，供 JS 在文章中定位
        $html = '<blockquote class="article-quote" data-quote-text="' . htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<span class="quote-content">' . $content . '</span>';
        $html .= '<span class="quote-locate-btn" title="定位到原文" role="button" tabindex="0">';
        $html .= '<i class="fa fa-crosshairs"></i>';
        $html .= '</span>';
        $html .= '</blockquote>';

        return $html;
    };

    // 先处理被 <p> 包围的 [quote]（Markdown 或自动段落模式产生的结构）
    // 避免 <p><blockquote></blockquote></p> 无效嵌套
    $html = preg_replace_callback('/<p>\s*\[quote\](.*?)\[\/quote\]\s*<\/p>/s', function($m) use ($buildQuote) {
        return $buildQuote($m[1]);
    }, $html);

    // 再处理未被 <p> 包围的 [quote]
    $html = preg_replace_callback('/\[quote\](.*?)\[\/quote\]/s', function($m) use ($buildQuote) {
        return $buildQuote($m[1]);
    }, $html);

    return $html;
}

/**
 * 评论 HTML 白名单过滤（基于 DOM 解析器）
 * 仅允许安全的标签和属性，移除所有事件处理器、javascript:/vbscript:/data: 等危险协议与危险 style。
 *
 * 与旧版正则消毒不同：DOM 解析器会先解码 HTML 实体，因此
 * java&#10;script:、&#106;avascript:、on&#9;click 等绕过写法无法生效。
 *
 * @param string $html 原始 HTML
 * @return string 过滤后的安全 HTML
 */
function shufei_sanitize_comment_html($html)
{
    if (empty($html)) return $html;

    // 允许的标签白名单（不含 script/style/iframe/object/embed/svg 等危险标签）
    $allowedTags = array(
        'a', 'b', 'strong', 'i', 'em', 'u', 's', 'del', 'ins', 'code', 'pre',
        'blockquote', 'p', 'br', 'hr', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'span', 'div', 'table',
        'thead', 'tbody', 'tr', 'td', 'th', 'sup', 'sub', 'mark', 'details',
        'summary', 'figure', 'figcaption', 'source', 'video', 'audio'
    );

    // 允许的属性白名单（'*' 为所有标签通用；data-* 始终允许，用于 KaTeX 数学公式等）
    $allowedAttrs = array(
        '*' => array('class', 'title', 'style'),
        'a' => array('href', 'title', 'rel', 'target'),
        'img' => array('src', 'alt', 'title', 'width', 'height', 'loading'),
        'video' => array('src', 'poster', 'controls', 'preload', 'playsinline', 'autoplay', 'width', 'height'),
        'audio' => array('src', 'controls', 'preload'),
        'source' => array('src', 'type'),
        'td' => array('colspan', 'rowspan'),
        'th' => array('colspan', 'rowspan'),
        'ol' => array('start'),
    );

    // URI 属性：只允许安全协议（img 的 src 额外允许 data:image/）
    $uriAttrs = array('href', 'src', 'poster');
    $safeSchemes = array('http', 'https', 'mailto');

    // DOM 扩展不可用时的保守回退：保留白名单标签但剥离所有属性（无属性即无 javascript:/on* 风险）
    if (!class_exists('DOMDocument')) {
        $html = strip_tags($html, '<' . implode('><', $allowedTags) . '>');
        $html = preg_replace('#<([a-zA-Z][a-zA-Z0-9]*)(\s+[^>]*)>#', '<$1>', $html);
        return $html;
    }

    $prev = libxml_use_internal_errors(true);
    $dom = new \DOMDocument('1.0', 'UTF-8');
    // 通过 meta 声明 UTF-8，避免 loadHTML 按 ISO-8859-1 解析导致中文乱码
    $wrapped = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><div id="shufei-sanitize-root">' . $html . '</div>';
    $dom->loadHTML($wrapped);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xp = new \DOMXPath($dom);
    $root = $xp->query('//*[@id="shufei-sanitize-root"]')->item(0);
    if (!$root) {
        return '';
    }

    // 递归清理：不允许的标签先净化其子树再解包；允许的标签清理属性
    $process = function ($node) use (&$process, $allowedTags, $allowedAttrs, $uriAttrs, $safeSchemes) {
        $children = array();
        for ($c = $node->firstChild; $c; $c = $c->nextSibling) {
            $children[] = $c;
        }

        foreach ($children as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                // 仅保留文本节点；移除注释、处理指令等
                if ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
                    $node->removeChild($child);
                }
                continue;
            }

            $name = strtolower($child->nodeName);

            if (!in_array($name, $allowedTags, true)) {
                // 不允许的标签：先递归净化其子树，再解包（用子节点替换自身，保留内部文本）
                $process($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // 清理属性
            $attrNames = array();
            foreach ($child->attributes as $attr) {
                $attrNames[] = $attr->name;
            }
            foreach ($attrNames as $attrName) {
                $attrLower = strtolower($attrName);
                $tagAllows = isset($allowedAttrs[$name]) ? $allowedAttrs[$name] : array();
                $globalAllows = $allowedAttrs['*'];
                $isDataAttr = (strpos($attrLower, 'data-') === 0);

                if (!$isDataAttr && !in_array($attrLower, $globalAllows, true) && !in_array($attrLower, $tagAllows, true)) {
                    $child->removeAttribute($attrName);
                    continue;
                }

                // URI 属性协议校验
                if (in_array($attrLower, $uriAttrs, true)) {
                    $value = $child->getAttribute($attrName);
                    if (!shufei_is_safe_uri($value, $safeSchemes, $name, $attrLower)) {
                        $child->removeAttribute($attrName);
                    }
                }

                // style 属性净化
                if ($attrLower === 'style') {
                    $value = $child->getAttribute('style');
                    $clean = shufei_sanitize_style_value($value);
                    if ($clean === '') {
                        $child->removeAttribute('style');
                    }
                }
            }

            $process($child);
        }
    };

    $process($root);

    // 提取 root 内部 HTML
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

/**
 * 校验 URI 属性值是否安全（防止 javascript:/vbscript:/data: 等协议）
 * DOM 已解码 HTML 实体，此处再剥离控制字符与空白后判断协议
 *
 * @param string $value 属性值
 * @param array  $safeSchemes 允许的协议
 * @param string $tag 所在标签名
 * @param string $attr 属性名
 * @return bool
 */
function shufei_is_safe_uri($value, $safeSchemes, $tag, $attr)
{
    $check = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $value));
    if ($check === '') return true; // 空值无风险
    if (!preg_match('#^([a-z][a-z0-9+.\-]*):#', $check, $m)) {
        return true; // 无协议（相对路径 / #锚点 / ?查询）
    }
    $scheme = $m[1];
    if (in_array($scheme, $safeSchemes, true)) return true;
    // 仅图片 src 允许 data:image/（base64 内联图）
    if ($scheme === 'data' && $tag === 'img' && $attr === 'src' && strpos($check, 'data:image/') === 0) return true;
    return false;
}

/**
 * 净化 style 属性值：阻止 expression/url(javascript:)/@import/behavior 等危险 CSS
 *
 * @param string $style
 * @return string 安全则原样返回，危险则返回空字符串
 */
function shufei_sanitize_style_value($style)
{
    if (trim($style) === '') return '';
    $lower = strtolower($style);
    $blocked = array('expression', 'javascript:', 'vbscript:', 'data:', '@import', '@media', 'behavior', '-moz-binding', '<', '>', '\\', '/*', '*/');
    foreach ($blocked as $b) {
        if (strpos($lower, $b) !== false) return '';
    }
    // 校验 url(...) 内容协议，仅允许 http/https（用于音乐封面背景图等）
    if (preg_match_all('/url\s*\(\s*(["\']?)(.*?)\1\s*\)/is', $style, $m)) {
        foreach ($m[2] as $u) {
            $check = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $u));
            if (preg_match('#^([a-z][a-z0-9+.\-]*):#', $check, $sm)) {
                if (!in_array($sm[1], array('http', 'https'), true)) return '';
            }
        }
    }
    return $style;
}

