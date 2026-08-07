<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 验证码配置获取
 * 从 functions.php 分层迁移
 */

/**
 * 获取当前验证码类型
 *
 * @return string none|turnstile|captcha_number|captcha_alpha|captcha_alnum
 */
function shufei_get_captcha_type()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->captchaType) ? $options->captchaType : 'none';
}

/**
 * 检查Turnstile人机验证是否启用
 *
 * @return bool
 */
function shufei_is_turnstile_enabled()
{
    return shufei_get_captcha_type() === 'turnstile';
}

/**
 * 检查极验 Geetest v4 人机验证是否启用
 *
 * @return bool
 */
function shufei_is_geetest_enabled()
{
    return shufei_get_captcha_type() === 'geetest';
}

/**
 * 检查图片验证码是否启用
 *
 * @return bool
 */
function shufei_is_captcha_enabled()
{
    $type = shufei_get_captcha_type();
    return in_array($type, array('captcha_number', 'captcha_alpha', 'captcha_alnum'));
}

/**
 * 获取验证码字符类型（用于captcha.php的type参数）
 *
 * @return string number|alpha|alnum
 */
function shufei_get_captcha_char_type()
{
    $type = shufei_get_captcha_type();
    if ($type === 'captcha_number') return 'number';
    if ($type === 'captcha_alpha') return 'alpha';
    return 'alnum';
}

/**
 * 获取验证码位数
 *
 * @return int
 */
function shufei_get_captcha_length()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $length = isset($options->captchaLength) ? intval($options->captchaLength) : 4;
    return max(4, min(6, $length));
}

/**
 * 获取Turnstile Site Key
 *
 * @return string
 */
function shufei_get_turnstile_site_key()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->turnstileSiteKey) ? $options->turnstileSiteKey : '';
}

/**
 * 获取极验 Geetest v4 Captcha ID
 *
 * @return string
 */
function shufei_get_geetest_captcha_id()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->geetestCaptchaId) ? $options->geetestCaptchaId : '';
}

/**
 * 获取极验 Geetest v4 Captcha Key
 *
 * @return string
 */
function shufei_get_geetest_captcha_key()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->geetestCaptchaKey) ? $options->geetestCaptchaKey : '';
}

