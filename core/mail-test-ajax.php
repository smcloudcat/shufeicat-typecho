<?php
/**
 * 邮件功能测试 AJAX 接口
 *
 * 使用已保存的评论邮件 SMTP 配置发送一封测试邮件，
 * 用于验证邮件功能是否正常。
 *
 * 请求方式：POST
 * 参数：
 *   - to: 测试接收邮箱
 * 返回格式：JSON
 *   - success: bool
 *   - message: string
 *
 * 鉴权方式与 update-ajax.php / style-ajax.php 一致：
 * 仅允许已登录的 administrator / editor。
 */

header('Content-Type: application/json; charset=utf-8');

// 全局 fatal error handler
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array('success' => false, 'message' => 'PHP致命错误: ' . $err['message']));
    }
});

// 载入 Typecho 配置
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        echo json_encode(array('success' => false, 'message' => '系统配置文件缺失'));
        exit;
    }
}

// 手动定义 __TYPECHO_ROOT_URL__ 以让 Cookie 前缀与后台一致
if (!defined('__TYPECHO_ROOT_URL__')) {
    if (defined('__TYPECHO_SITE_URL__')) {
        define('__TYPECHO_ROOT_URL__', __TYPECHO_SITE_URL__);
    } else {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isSecure ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $basePath = '';
        if ($scriptName && defined('__TYPECHO_ROOT_DIR__')) {
            $relPath = str_replace('\\', '/', substr(__FILE__, strlen(__TYPECHO_ROOT_DIR__)));
            if ($relPath && substr($scriptName, -strlen($relPath)) === $relPath) {
                $basePath = substr($scriptName, 0, -strlen($relPath));
            }
        }
        define('__TYPECHO_ROOT_URL__', rtrim($protocol . '://' . $host . $basePath, '/'));
    }
}

try {
    $options = \Widget\Options::alloc();
    \Typecho\Cookie::setPrefix($options->rootUrl);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '系统初始化失败: ' . $e->getMessage()));
    exit;
}

// 鉴权：仅管理员/编辑可用
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录'));
        exit;
    }
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        echo json_encode(array('success' => false, 'message' => '权限不足'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// 参数校验
$to = isset($_POST['to']) ? trim((string)$_POST['to']) : '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array('success' => false, 'message' => '请填写有效的测试接收邮箱'));
    exit;
}

// 读取已保存的 SMTP 配置
$host = isset($options->commentMailHost) ? trim($options->commentMailHost) : '';
$account = isset($options->commentMailAccount) ? trim($options->commentMailAccount) : '';
$password = isset($options->commentMailPassword) ? (string)$options->commentMailPassword : '';
$port = isset($options->commentMailPort) ? trim($options->commentMailPort) : '';
$fromName = isset($options->commentMailFromName) ? $options->commentMailFromName : '';
$smtpSecure = isset($options->commentMailSMTPSecure) ? $options->commentMailSMTPSecure : 'ssl';
// 兼容历史拼写错误 'tsl'，统一修正为 'tls'
if ($smtpSecure === 'tsl') {
    $smtpSecure = 'tls';
}

if ($host === '' || $account === '' || $password === '') {
    echo json_encode(array('success' => false, 'message' => 'SMTP 配置不完整，请先在下方填写并保存「邮箱服务器地址」「发件人邮箱」和「邮箱授权码」'));
    exit;
}

// 端口为空时按加密方式推断
if ($port === '' || !is_numeric($port)) {
    $port = ($smtpSecure === 'ssl') ? 465 : 587;
} else {
    $port = (int)$port;
}

// 包含本地 PHPMailer 7.1.1 文件（命名空间方式）
require_once dirname(__FILE__) . '/phpmailer/Exception.php';
require_once dirname(__FILE__) . '/phpmailer/OAuthTokenProvider.php';
require_once dirname(__FILE__) . '/phpmailer/OAuth.php';
require_once dirname(__FILE__) . '/phpmailer/SMTP.php';
require_once dirname(__FILE__) . '/phpmailer/POP3.php';
require_once dirname(__FILE__) . '/phpmailer/DSNConfigurator.php';
require_once dirname(__FILE__) . '/phpmailer/PHPMailer.php';

// 构造测试邮件内容
$siteName = isset($options->title) ? $options->title : '本站';
$siteUrl = isset($options->siteUrl) ? $options->siteUrl : '';
$now = date('Y-m-d H:i:s');
$secureLabel = ($smtpSecure === 'tls') ? 'TLS' : 'SSL';

$body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邮件功能测试</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fa;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#f5f7fa;">
        <tr>
            <td align="center" style="padding:30px 15px;">
                <table width="600" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background-color:#467B96;padding:25px 30px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">邮件功能测试</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <div style="line-height:1.8;color:#333333;font-size:15px;">
                                这是一封由 <strong>{siteName}</strong> 发送的测试邮件。<br><br>
                                如果您收到了这封邮件，说明主题的邮件通知功能配置正确，可以正常发送邮件。
                            </div>
                            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:20px;background-color:#f8f9fa;border-radius:6px;">
                                <tr>
                                    <td style="padding:16px 20px;font-size:13px;color:#666666;line-height:1.8;">
                                        发送时间：{time}<br>
                                        发件服务器：{host}:{port}（{secure}）<br>
                                        站点地址：<a href="{siteUrl}" style="color:#467B96;text-decoration:none;">{siteUrl}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#fafafa;padding:20px 30px;text-align:center;border-top:1px solid #eeeeee;">
                            <p style="margin:0;color:#999999;font-size:12px;line-height:1.6;">
                                此邮件由 {siteName} 邮件测试功能自动发送，请勿直接回复
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

$body = strtr($body, array(
    '{siteName}' => $siteName,
    '{time}'     => $now,
    '{host}'     => $host,
    '{port}'     => $port,
    '{secure}'   => $secureLabel,
    '{siteUrl}'  => $siteUrl,
));

$subject = '【测试邮件】' . $siteName . ' 邮件功能测试';

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->CharSet = 'UTF-8';
    $mail->Host = $host;
    $mail->Port = $port;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Username = $account;
    $mail->Password = $password;
    $mail->setFrom($account, $fromName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->addAddress($to);
    $mail->send();

    echo json_encode(array(
        'success' => true,
        'message' => '测试邮件发送成功，请检查 ' . $to . ' 的收件箱（注意垃圾邮件文件夹）'
    ));
} catch (\Throwable $e) {
    $errMsg = $e->getMessage();
    // 补充 SMTP 调试信息，便于定位问题
    if (isset($mail) && method_exists($mail, 'getSMTPInstance')) {
        try {
            $smtpInfo = $mail->getSMTPInstance()->getError();
            if (!empty($smtpInfo['error'])) {
                $errMsg .= ' | SMTP: ' . $smtpInfo['error'];
            }
        } catch (\Throwable $ignored) {
        }
    }
    echo json_encode(array('success' => false, 'message' => '发送失败：' . $errMsg));
}
