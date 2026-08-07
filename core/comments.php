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
 * 评论 HTML 白名单过滤
 * 仅允许安全的标签和属性，移除所有事件处理器、javascript: 协议等危险内容
 *
 * @param string $html 原始 HTML
 * @return string 过滤后的安全 HTML
 */
function shufei_sanitize_comment_html($html)
{
    if (empty($html)) return $html;

    // 允许的标签白名单（不含 script/style/iframe/object/embed 等危险标签）
    $allowedTags = '<a><b><strong><i><em><u><s><del><ins><code><pre><blockquote><p><br><hr>'
        . '<ul><ol><li><dl><dt><dd><h1><h2><h3><h4><h5><h6>'
        . '<img><span><div><table><thead><tbody><tr><td><th><sup><sub><mark>'
        . '<details><summary><figure><figcaption><source><video><audio>';

    // 1. 移除不允许的标签（保留其内部文本内容）
    $html = strip_tags($html, $allowedTags);

    // 2. 移除所有 on* 事件属性（onclick/onerror/onload/onmouseover 等）
    $html = preg_replace('#\s+on[a-z]+\s*=\s*(["\']).*?\1#is', '', $html);

    // 3. 移除 javascript: 协议（href="javascript:..."、src="javascript:..."）
    $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript\s*:.*?\2#is', '$1="$2"', $html);

    // 4. 移除 data: 协议（除 img src 外的 data: URI 可能被滥用）
    $html = preg_replace('#(href)\s*=\s*(["\'])\s*data\s*:.*?\2#is', '$1="$2"', $html);

    // 5. 移除 style 属性中的危险内容（expression()、url(javascript:) 等）
    $html = preg_replace('#style\s*=\s*(["\']).*?(expression\s*\(|url\s*\(\s*["\']?\s*javascript\s*:).*?\1#is', '', $html);

    return $html;
}

