<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 更新检查模块
 * 从 functions.php 分层迁移
 */

/**
 * 获取更新检查相关配置
 *
 * @return array 配置项：api_url / channel / owner / repo
 */
function shufei_get_update_config()
{
    $options = \Typecho\Widget::widget('Widget_Options');

    $apiUrl = isset($options->shufeiUpdateApiUrl) ? trim($options->shufeiUpdateApiUrl) : '';
    if ($apiUrl === '') {
        $apiUrl = 'https://githubver.czzu.cn/';
    }

    $channel = isset($options->shufeiUpdateChannel) ? $options->shufeiUpdateChannel : 'stable';
    if (!in_array($channel, array('stable', 'dev', 'manual'), true)) {
        $channel = 'stable';
    }

    $owner = isset($options->shufeiUpdateOwner) ? trim($options->shufeiUpdateOwner) : '';
    if ($owner === '') {
        $owner = 'smcloudcat';
    }

    $repo = isset($options->shufeiUpdateRepo) ? trim($options->shufeiUpdateRepo) : '';
    if ($repo === '') {
        $repo = 'shufeicat-typecho';
    }

    return compact('apiUrl', 'channel', 'owner', 'repo');
}

/**
 * 检测主题更新（重构版）
 *
 * 支持三种通道：
 *  - stable : 仅自动获取正式版（Stable）
 *  - dev    : 自动获取开发版（含 rc/beta 等预发布）
 *  - manual : 手动检查，不自动请求网络（仅当 $force=true 时发起）
 *
 * @param bool   $force   是否强制刷新（忽略缓存，且 manual 模式下才会真正发起请求）
 * @param string $channel 指定通道覆盖配置（stable/dev），仅用于手动检查时
 * @return array 更新检测结果：code(1有更新/0无更新或失败) / msg / version / url / channel / from_cache
 */
function shufei_check_theme_update($force = false, $channel = null)
{
    $currentVersion = shufei_get_theme_version();
    $cfg = shufei_get_update_config();
    $configChannel = $cfg['channel'];

    // 解析本次实际使用的通道
    if ($channel !== null && in_array($channel, array('stable', 'dev'), true)) {
        $requestChannel = $channel;
    } else {
        $requestChannel = ($configChannel === 'manual') ? 'stable' : $configChannel;
    }

    // 手动模式且未强制：不发起任何网络请求
    if ($configChannel === 'manual' && !$force) {
        return array(
            'code'    => 0,
            'msg'     => '已切换为手动检查模式，点击"立即检查更新"按钮获取最新版本信息',
            'channel' => 'manual',
        );
    }

    // 缓存（按通道分别缓存）
    $cacheTime = 3600;
    $cacheFile = dirname(__FILE__) . '/cache/update_check_' . $requestChannel . '.json';
    if (!$force && file_exists($cacheFile)) {
        $cache = @json_decode(@file_get_contents($cacheFile), true);
        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTime) {
            $cached = is_array($cache['result']) ? $cache['result'] : array('code' => 0, 'msg' => '无缓存数据');
            $cached['from_cache'] = true;
            return $cached;
        }
    }

    // 构造博客地址（用于统计）
    $blogUrl = '';
    if (defined('__TYPECHO_SITE_URL__')) {
        $blogUrl = constant('__TYPECHO_SITE_URL__');
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $blogUrl = $protocol . $_SERVER['HTTP_HOST'];
    }

    $query = http_build_query(array(
        'owner'   => $cfg['owner'],
        'repo'    => $cfg['repo'],
        'version' => $currentVersion,
        'channel' => $requestChannel,
        'blogurl' => $blogUrl,
    ));
    $sep = (strpos($cfg['apiUrl'], '?') === false) ? '?' : '&';
    $updateUrl = $cfg['apiUrl'] . $sep . $query;

    $result = array('code' => 0, 'msg' => '检测失败，请稍后重试', 'channel' => $requestChannel);
    $ua = 'ShuFeiCat-Theme/' . $currentVersion;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $updateUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($httpCode == 200 && $response) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        } elseif ($err) {
            $result['msg'] = '检测失败：' . $err;
        }
    } else {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => 10, 'header' => 'User-Agent: ' . $ua),
        ));
        $response = @file_get_contents($updateUrl, false, $ctx);
        if ($response) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }
    }

    $result['channel'] = $requestChannel;

    // 写缓存
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode(array(
        'timestamp' => time(),
        'result'    => $result,
    )));

    return $result;
}

/**
 * 获取主题版本号
 * 
 * @return string 主题版本号
 */
function shufei_get_theme_version()
{
    return '1.5.0-rc.6';
}

