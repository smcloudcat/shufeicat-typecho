<?php
/**
 * 密码验证限速：按 IP + 目标内容做失败计数与锁定，防止无限爆破
 * 数据存于 core/cache/ 下的小文件，跨请求/会话共享
 */
if (!defined('__TYPECHO_ROOT_DIR__') && !defined('SHUFEI_RATELIMIT_OK')) exit;

define('SHUFEI_PWD_MAX_ATTEMPTS', 5);  // 窗口内最大失败次数
define('SHUFEI_PWD_WINDOW', 300);      // 计数窗口（秒）
define('SHUFEI_PWD_LOCK', 600);        // 超限后锁定时长（秒）

/**
 * 获取当前客户端 IP（用于限速计数，不做安全决策）
 */
function shufei_pwd_limit_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $xff = trim((string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($xff !== '') {
            $ip = trim(explode(',', $xff)[0]);
        }
    }
    return $ip;
}

function shufei_pwd_limit_file($cid)
{
    $key = hash('sha256', shufei_pwd_limit_ip() . '|' . (int)$cid);
    $dir = dirname(__FILE__) . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/pwd_' . $key . '.json';
}

function shufei_pwd_limit_load($file)
{
    if (!is_file($file)) {
        return array('fail' => array(), 'lock_until' => 0);
    }
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) {
        return array('fail' => array(), 'lock_until' => 0);
    }
    if (!isset($data['fail']) || !is_array($data['fail'])) {
        $data['fail'] = array();
    }
    if (!isset($data['lock_until'])) {
        $data['lock_until'] = 0;
    }
    return $data;
}

/**
 * 检查当前 IP + CID 是否允许尝试密码
 *
 * @param int $cid
 * @return bool true=允许尝试，false=已被锁定
 */
function shufei_password_rate_allowed($cid)
{
    $now = time();
    $file = shufei_pwd_limit_file($cid);
    $data = shufei_pwd_limit_load($file);

    if (!empty($data['lock_until']) && $data['lock_until'] > $now) {
        return false;
    }

    $data['fail'] = array_filter($data['fail'], function ($t) use ($now) {
        return ($now - (int)$t) < SHUFEI_PWD_WINDOW;
    });

    return count($data['fail']) < SHUFEI_PWD_MAX_ATTEMPTS;
}

/**
 * 记录一次结果：成功则清除计数，失败则累计并在超限时锁定
 *
 * @param int  $cid
 * @param bool $success
 */
function shufei_password_rate_record($cid, $success)
{
    $now = time();
    $file = shufei_pwd_limit_file($cid);

    if ($success) {
        @unlink($file);
        return;
    }

    $data = shufei_pwd_limit_load($file);
    $data['fail'][] = $now;
    if (count($data['fail']) >= SHUFEI_PWD_MAX_ATTEMPTS) {
        $data['lock_until'] = $now + SHUFEI_PWD_LOCK;
        $data['fail'] = array();
    }
    @file_put_contents($file, json_encode($data), LOCK_EX);
}
