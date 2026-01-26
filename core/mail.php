<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/* 邮件通知 */
\Typecho\Plugin::factory('Widget_Feedback')->finishComment = array('ShuFeiCat_Email', 'send');

class ShuFeiCat_Email
{
    public static function send($comment)
    {
        // 使用正确的选项名
        $options = \Typecho\Widget::widget('Widget_Options');
        
        // 包含本地PHPMailer文件
        require_once(dirname(__FILE__) . '/phpmailer.php');
        require_once(dirname(__FILE__) . '/smtp.php');
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPSecure = $options->commentMailSMTPSecure ? $options->commentMailSMTPSecure : 'ssl';
            $mail->Host = $options->commentMailHost;
            $mail->Port = $options->commentMailPort;
            $mail->FromName = $options->commentMailFromName;
            $mail->Username = $options->commentMailAccount;
            $mail->From = $options->commentMailAccount;
            $mail->Password = $options->commentMailPassword;
            $mail->isHTML(true);
            
            $text = $comment->text;
            // 处理画图模式
            $text = preg_replace('/\{!\{([^\"]*)\}!\}/', '<img style="max-width: 100%;vertical-align: middle;" src="$1"/>', $text);
            
            // 获取邮件样式设置（使用默认值）
            $mailStyle = 'simple';
            $mailBgColor = '#f8f9fa';
            $mailAccentColor = '#3498db';
            $mailTextColor = '#333333';
            
            // 根据选择的样式生成不同的邮件模板
            if ($mailStyle === 'modern') {
                $html = self::getModernStyle($mailBgColor, $mailAccentColor, $mailTextColor);
            } elseif ($mailStyle === 'elegant') {
                $html = self::getElegantStyle($mailBgColor, $mailAccentColor, $mailTextColor);
            } elseif ($mailStyle === 'cute') {
                $html = self::getCuteStyle($mailBgColor, $mailAccentColor, $mailTextColor);
            } else {
                $html = self::getSimpleStyle($mailBgColor, $mailAccentColor, $mailTextColor);
            }
            
            /* 如果是博主发的评论 */
            if ($comment->authorId == $comment->ownerId) {
                /* 发表的评论是回复别人 */
                if ($comment->parent != 0) {
                    $db = \Typecho\Db::get();
                    $parentInfo = $db->fetchRow($db->select('mail')->from('table.comments')->where('coid = ?', $comment->parent));
                    $parentMail = $parentInfo['mail'];
                    /* 被回复的人不是自己时，发送邮件 */
                    if ($parentMail != $comment->mail) {
                        $mail->Body = strtr(
                            $html,
                            array(
                                "{title}" => '您在 [' . $comment->title . '] 的评论有了新的回复！',
                                "{subtitle}" => '博主：[ ' . $comment->author . ' ] 在《 <a style="color: ' . $mailAccentColor . ';text-decoration: none;" href="' . substr($comment->permalink, 0, strrpos($comment->permalink, "#")) . '" target="_blank">' . $comment->title . '</a> 》上回复了您:',
                                "{content}" => $text,
                                "{siteName}" => $options->title,
                                "{permalink}" => substr($comment->permalink, 0, strrpos($comment->permalink, "#")),
                            )
                        );
                        $mail->addAddress($parentMail);
                        $mail->Subject = '您在 [' . $comment->title . '] 的评论有了新的回复！';
                        $mail->send();
                    }
                }
            } else {
                /* 如果是直接发表的评论，不是回复别人，那么发送邮件给博主 */
                if ($comment->parent == 0) {
                    $db = \Typecho\Db::get();
                    $authoInfo = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $comment->ownerId));
                    $authorMail = $authoInfo['mail'];
                    if ($authorMail) {
                        $mail->Body = strtr(
                            $html,
                            array(
                                "{title}" => '您的文章 [' . $comment->title . '] 收到一条新的评论！',
                                "{subtitle}" => $comment->author . ' [' . $comment->ip . '] 在您的《 <a style="color: ' . $mailAccentColor . ';text-decoration: none;" href="' . substr($comment->permalink, 0, strrpos($comment->permalink, "#")) . '" target="_blank">' . $comment->title . '</a> 》上发表评论:',
                                "{content}" => $text,
                                "{siteName}" => $options->title,
                                "{permalink}" => substr($comment->permalink, 0, strrpos($comment->permalink, "#")),
                            )
                        );
                        $mail->addAddress($authorMail);
                        $mail->Subject = '您的文章 [' . $comment->title . '] 收到一条新的评论！';
                        $mail->send();
                    }
                } else {
                    /* 如果发表的评论是回复别人 */
                    $db = \Typecho\Db::get();
                    $parentInfo = $db->fetchRow($db->select('mail')->from('table.comments')->where('coid = ?', $comment->parent));
                    $parentMail = $parentInfo['mail'];
                    /* 被回复的人不是自己时，发送邮件 */
                    if ($parentMail != $comment->mail) {
                        $mail->Body = strtr(
                            $html,
                            array(
                                "{title}" => '您在 [' . $comment->title . '] 的评论有了新的回复！',
                                "{subtitle}" => $comment->author . ' 在《 <a style="color: ' . $mailAccentColor . ';text-decoration: none;" href="' . substr($comment->permalink, 0, strrpos($comment->permalink, "#")) . '" target="_blank">' . $comment->title . '</a> 》上回复了您:',
                                "{content}" => $text,
                                "{siteName}" => $options->title,
                                "{permalink}" => substr($comment->permalink, 0, strrpos($comment->permalink, "#")),
                            )
                        );
                        $mail->addAddress($parentMail);
                        $mail->Subject = '您在 [' . $comment->title . '] 的评论有了新的回复！';
                        $mail->send();
                    }
                }
            }
        } catch (\Exception $e) {
            // 记录错误但不中断流程
            error_log('邮件发送失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 简约风格
     */
    private static function getSimpleStyle($bgColor, $accentColor, $textColor)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>邮件通知</title>
        </head>
        <body style="margin:0;padding:0;background-color:' . $bgColor . ';font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:' . $bgColor . ';">
                <tr>
                    <td align="center" style="padding:30px 15px;">
                        <table width="600" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                            <!-- 头部 -->
                            <tr>
                                <td style="background-color:' . $accentColor . ';padding:25px 30px;text-align:center;">
                                    <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">{title}</h1>
                                </td>
                            </tr>
                            <!-- 内容区域 -->
                            <tr>
                                <td style="padding:30px;">
                                    <div style="margin-bottom:20px;line-height:1.8;color:' . $textColor . ';font-size:15px;">
                                        {subtitle}
                                    </div>
                                    <div style="background-color:' . $bgColor . ';padding:20px;border-radius:8px;line-height:1.8;color:' . $textColor . ';font-size:14px;margin-bottom:20px;">
                                        {content}
                                    </div>
                                    <div style="border-top:1px solid #eeeeee;padding-top:20px;margin-top:20px;">
                                        <a href="{permalink}" style="display:inline-block;padding:12px 24px;background-color:' . $accentColor . ';color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;">查看原文</a>
                                    </div>
                                </td>
                            </tr>
                            <!-- 底部 -->
                            <tr>
                                <td style="background-color:#fafafa;padding:20px 30px;text-align:center;border-top:1px solid #eeeeee;">
                                    <p style="margin:0;color:#999999;font-size:12px;line-height:1.6;">
                                        此邮件由 {siteName} 自动发送，请勿直接回复<br>
                                        如果您不想再收到此类邮件，请忽略此邮件
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
    
    /**
     * 现代风格
     */
    private static function getModernStyle($bgColor, $accentColor, $textColor)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>邮件通知</title>
        </head>
        <body style="margin:0;padding:0;background:linear-gradient(135deg,' . $bgColor . ' 0%,#e8e8e8 100%);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="padding:40px 20px;">
                        <table width="600" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
                            <!-- 装饰头部 -->
                            <tr>
                                <td style="background:linear-gradient(135deg,' . $accentColor . ' 0%,' . self::adjustColor($accentColor, -20) . ' 100%);padding:40px 30px;text-align:center;">
                                    <div style="width:60px;height:60px;background-color:rgba(255,255,255,0.2);border-radius:50%;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;">
                                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 18H4V8L12 13L20 8V18ZM12 11L4 6H20L12 11Z" fill="white"/>
                                        </svg>
                                    </div>
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">评论通知</h1>
                                </td>
                            </tr>
                            <!-- 内容区域 -->
                            <tr>
                                <td style="padding:35px;">
                                    <h2 style="margin:0 0 20px 0;color:' . $textColor . ';font-size:18px;font-weight:600;">{title}</h2>
                                    <div style="background:linear-gradient(135deg,#f8f9fa 0%,#ffffff 100%);padding:25px;border-radius:12px;margin-bottom:25px;border-left:4px solid ' . $accentColor . ';">
                                        <div style="margin-bottom:15px;line-height:1.8;color:' . $textColor . ';font-size:15px;">
                                            {subtitle}
                                        </div>
                                        <div style="line-height:1.8;color:' . $textColor . ';font-size:14px;padding-top:15px;border-top:1px dashed #dddddd;">
                                            {content}
                                        </div>
                                    </div>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <a href="{permalink}" style="display:inline-block;padding:14px 35px;background:linear-gradient(135deg,' . $accentColor . ' 0%,' . self::adjustColor($accentColor, -20) . ' 100%);color:#ffffff;text-decoration:none;border-radius:25px;font-size:15px;font-weight:500;box-shadow:0 4px 15px rgba(0,0,0,0.2);">立即查看 →</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <!-- 底部 -->
                            <tr>
                                <td style="background-color:#f8f9fa;padding:25px 30px;text-align:center;border-top:1px solid #eeeeee;">
                                    <p style="margin:0;color:#888888;font-size:13px;line-height:1.6;">
                                        <strong>{siteName}</strong> · 感谢您的关注<br>
                                        <span style="color:#bbbbbb;font-size:11px;">此邮件由系统自动发送，请勿回复</span>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
    
    /**
     * 优雅风格
     */
    private static function getElegantStyle($bgColor, $accentColor, $textColor)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>邮件通知</title>
        </head>
        <body style="margin:0;padding:0;background-color:' . $bgColor . ';font-family:\'Georgia\',\'Times New Roman\',serif;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:' . $bgColor . ';">
                <tr>
                    <td align="center" style="padding:50px 15px;">
                        <table width="580" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border:1px solid #e0e0e0;border-radius:4px;">
                            <!-- 头部装饰 -->
                            <tr>
                                <td style="padding:40px 40px 30px 40px;text-align:center;border-bottom:1px solid #f0f0f0;">
                                    <div style="width:50px;height:2px;background-color:' . $accentColor . ';margin:0 auto 20px;"></div>
                                    <h1 style="margin:0;color:' . $textColor . ';font-size:24px;font-weight:400;font-family:\'Georgia\',serif;">{title}</h1>
                                    <div style="width:50px;height:2px;background-color:' . $accentColor . ';margin:20px auto 0;"></div>
                                </td>
                            </tr>
                            <!-- 内容区域 -->
                            <tr>
                                <td style="padding:40px;">
                                    <p style="margin:0 0 25px 0;color:' . $textColor . ';font-size:15px;line-height:1.8;font-family:\'Georgia\',serif;">
                                        {subtitle}
                                    </p>
                                    <div style="padding:25px 30px;background-color:#fafafa;border-left:3px solid ' . $accentColor . ';margin-bottom:30px;">
                                        <p style="margin:0;color:' . $textColor . ';font-size:14px;line-height:1.8;font-style:italic;font-family:\'Georgia\',serif;">
                                            {content}
                                        </p>
                                    </div>
                                    <p style="text-align:center;margin:0;">
                                        <a href="{permalink}" style="display:inline-block;padding:12px 30px;border:1px solid ' . $accentColor . ';color:' . $accentColor . ';text-decoration:none;border-radius:2px;font-size:14px;font-family:\'Georgia\',serif;">阅读更多</a>
                                    </p>
                                </td>
                            </tr>
                            <!-- 底部 -->
                            <tr>
                                <td style="padding:25px 40px;text-align:center;border-top:1px solid #f0f0f0;">
                                    <p style="margin:0;color:#999999;font-size:12px;line-height:1.8;font-family:\'Georgia\',serif;">
                                        — {siteName}<br>
                                        <span style="color:#cccccc;">此邮件由系统自动发送</span>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
    
    /**
     * 可爱风格
     */
    private static function getCuteStyle($bgColor, $accentColor, $textColor)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>邮件通知</title>
        </head>
        <body style="margin:0;padding:0;background-color:' . $bgColor . ';font-family:\'Comic Sans MS\',\'Chalkboard SE\',sans-serif;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:' . $bgColor . ';">
                <tr>
                    <td align="center" style="padding:30px 15px;">
                        <table width="600" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 8px 25px rgba(0,0,0,0.1);">
                            <!-- 头部 -->
                            <tr>
                                <td style="background:linear-gradient(90deg,' . $accentColor . ',#ff9a9e);padding:30px;text-align:center;">
                                    <span style="display:inline-block;font-size:28px;margin-bottom:10px;">💬</span>
                                    <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">新消息提醒</h1>
                                </td>
                            </tr>
                            <!-- 内容区域 -->
                            <tr>
                                <td style="padding:30px;">
                                    <div style="background:linear-gradient(135deg,#fff5f5,#fff);padding:25px;border-radius:15px;margin-bottom:20px;border:2px dashed ' . $accentColor . ';">
                                        <h3 style="margin:0 0 15px 0;color:' . $accentColor . ';font-size:16px;">✏️ ' . $accentColor . '</h3>
                                        <p style="margin:0 0 15px 0;color:' . $textColor . ';font-size:14px;line-height:1.8;">
                                            {subtitle}
                                        </p>
                                        <div style="background-color:#fff;padding:15px;border-radius:10px;margin-top:15px;">
                                            <p style="margin:0;color:' . $textColor . ';font-size:13px;line-height:1.6;">
                                                💡 {content}
                                            </p>
                                        </div>
                                    </div>
                                    <div style="text-align:center;">
                                        <a href="{permalink}" style="display:inline-block;padding:15px 40px;background:linear-gradient(90deg,' . $accentColor . ',#ff9a9e);color:#ffffff;text-decoration:none;border-radius:50px;font-size:15px;font-weight:bold;box-shadow:0 4px 15px rgba(0,0,0,0.2);">戳我查看 →</a>
                                    </div>
                                </td>
                            </tr>
                            <!-- 底部 -->
                            <tr>
                                <td style="background-color:#fff9fa;padding:20px;text-align:center;border-top:2px solid #ffe0e0;">
                                    <p style="margin:0;color:#ff9a9e;font-size:12px;">
                                        ✨ 来自 <strong>{siteName}</strong> 的问候 ✨<br>
                                        <span style="opacity:0.7;">系统自动发送，请勿回复哦~</span>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
    
    /**
     * 调整颜色亮度
     */
    private static function adjustColor($color, $amount)
    {
        $color = str_replace('#', '', $color);
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        $r = max(0, min(255, $r + $amount));
        $g = max(0, min(255, $g + $amount));
        $b = max(0, min(255, $b + $amount));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
