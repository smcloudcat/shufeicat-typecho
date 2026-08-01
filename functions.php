<?php
/**
 * ShuFeiCat 主题 核心函数文件
 * 
 * 包含后台配置、分类 UI、核心业务逻辑钩子
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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
    return '1.5.0-rc.2';
}

/**
 * 主题后台配置函数
 */
function themeConfig($form)
{
    $css = '<style>' .
        // ===== 主容器：左右两栏布局 =====
        '.cat-config-container { display: flex; background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 20px; overflow: hidden; font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; color: #333; }' .
        // ===== 侧边栏导航 =====
        '.cat-config-aside { width: 170px; background: #fbfbfb; border-right: 1px solid #ececec; flex-shrink: 0; padding: 0; }' .
        '.cat-config-logo { padding: 14px 18px; font-weight: 600; color: #467B96; font-size: 14px; border-bottom: 1px solid #ececec; letter-spacing: 0.3px; }' .
        '.cat-config-aside ul { list-style: none; margin: 0; padding: 4px 0; }' .
        '.cat-config-aside li { padding: 9px 18px 9px 20px; cursor: pointer; color: #595959; font-size: 13px; transition: background .15s, color .15s; border-left: 2px solid transparent; line-height: 1.4; }' .
        '.cat-config-aside li:hover { background: #f0f0f0; color: #467B96; }' .
        '.cat-config-aside li.active { background: #fff; color: #467B96; font-weight: 600; border-left-color: #467B96; }' .
        // ===== 主内容区 =====
        '.cat-config-main { flex: 1; padding: 8px 28px 24px; min-height: 480px; min-width: 0; }' .
        '.typecho-option-list:not(.typecho-option-submit) { display: none !important; }' .
        '.cat-pane { display: none; }' .
        '.cat-pane.active { display: block; animation: catFadeIn .2s ease; }' .
        '@keyframes catFadeIn { from { opacity: 0; } to { opacity: 1; } }' .
        // ===== 选项行：紧凑列表式布局 =====
        '.cat-config-main .typecho-option { padding: 14px 0; margin: 0; border-bottom: 1px solid #f0f0f0; }' .
        '.cat-config-main .typecho-option:last-child { border-bottom: none; }' .
        '.cat-config-main .typecho-option label.typecho-label { display: block; font-weight: 600; margin-bottom: 6px; color: #262626; font-size: 13px; line-height: 1.4; }' .
        '.cat-config-main .description { color: #8c8c8c; font-size: 12px; margin-top: 6px; line-height: 1.5; }' .
        // ===== 表单输入元素：统一小圆角 =====
        '.cat-config-main input[type=text], .cat-config-main textarea, .cat-config-main select, .cat-config-main input[type=number] { width: 100%; padding: 7px 10px; border: 1px solid #d9d9d9; border-radius: 4px; background: #fff; transition: border-color .15s, box-shadow .15s; box-sizing: border-box; font-size: 13px; color: #333; }' .
        '.cat-config-main input:focus, .cat-config-main textarea:focus, .cat-config-main select:focus { border-color: #467B96; outline: none; box-shadow: 0 0 0 2px rgba(70, 123, 150, 0.12); }' .
        '.cat-config-main textarea { min-height: 72px; resize: vertical; font-family: inherit; }' .
        // ===== radio/checkbox 选项：紧凑横排，小圆角 =====
        '.cat-config-main ul { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }' .
        '.cat-config-main ul li { margin: 0; }' .
        '.cat-config-main ul li label { display: inline-flex; align-items: center; cursor: pointer; font-size: 12px; color: #595959; padding: 4px 10px; border: 1px solid #d9d9d9; border-radius: 3px; transition: all .15s; background: #fff; line-height: 1.4; user-select: none; }' .
        '.cat-config-main ul li label:hover { border-color: #467B96; color: #467B96; }' .
        '.cat-config-main ul li input[type=radio], .cat-config-main ul li input[type=checkbox] { margin-right: 4px; accent-color: #467B96; }' .
        '.cat-config-main ul li:has(input:checked) label { border-color: #467B96; color: #467B96; background: #eef5f8; font-weight: 500; }' .
        // ===== 提交按钮区 =====
        '.typecho-option-submit { background: #fbfbfb; padding: 16px 20px; border: 1px solid #e8e8e8; border-radius: 4px; text-align: right; margin-top: 12px; }' .
        '.typecho-option-submit button { background: #467B96 !important; border: none !important; color: #fff !important; padding: 0 28px !important; height: 38px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 4px !important; cursor: pointer !important; font-weight: 500 !important; font-size: 13px !important; transition: background .15s !important; vertical-align: middle !important; margin: 0 !important; line-height: 1 !important; text-decoration: none !important; outline: none !important; }' .
        '.typecho-option-submit button:hover { background: #3a6478 !important; }' .
        // ===== API 状态提示框 =====
        '.api-status-box { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; font-size: 12px; border: 1px solid transparent; line-height: 1.6; }' .
        '.api-success { background: #f6ffed; border-color: #b7eb8f; color: #389e0d; }' .
        '.api-error { background: #fff2f0; border-color: #ffccc7; color: #cf1322; }' .
        // ===== 数据管理区块 =====
        '.cat-data-section { padding: 16px 0; border-bottom: 1px solid #f0f0f0; }' .
        '.cat-data-section:last-child { border-bottom: none; }' .
        '.cat-data-title { font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #262626; }' .
        '.cat-data-desc { color: #8c8c8c; font-size: 12px; margin-bottom: 12px; line-height: 1.6; }' .
        '.cat-data-btn { display: inline-block; padding: 7px 18px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; transition: opacity .15s; text-decoration: none; line-height: 1.4; }' .
        '.cat-data-btn:hover { opacity: 0.88; }' .
        '.cat-data-btn-primary { background: #467B96; color: #fff; }' .
        '.cat-data-btn-warning { background: #e67e22; color: #fff; }' .
        '.cat-data-btn-danger { background: #e74c3c; color: #fff; }' .
        '.cat-file-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }' .
        '.cat-file-label { display: inline-block; padding: 7px 14px; background: #fff; border: 1px dashed #d9d9d9; border-radius: 4px; color: #595959; font-size: 12px; cursor: pointer; transition: border-color .15s, color .15s; position: relative; overflow: hidden; line-height: 1.4; }' .
        '.cat-file-label:hover { border-color: #467B96; color: #467B96; }' .
        '.cat-file-label input[type=file] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }' .
        '.cat-file-name { color: #467B96; font-size: 12px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }' .
        '.cat-data-status { margin-top: 10px; padding: 10px 14px; border-radius: 4px; font-size: 12px; display: none; line-height: 1.6; }' .
        '.cat-data-status.show { display: block; }' .
        '.cat-data-status.success { background: #f6ffed; border: 1px solid #b7eb8f; color: #389e0d; }' .
        '.cat-data-status.error { background: #fff2f0; border: 1px solid #ffccc7; color: #cf1322; }' .
        '.cat-data-status.info { background: #e6f7ff; border: 1px solid #91d5ff; color: #096dd9; }' .
        '.cat-data-warning { background: #fffbe6; border: 1px solid #ffe58f; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; color: #d48806; font-size: 12px; line-height: 1.6; }' .
        '.cat-data-warning i { margin-right: 6px; }' .
        // ===== 桌面端：侧边栏 sticky =====
        '@media (min-width: 769px) {' .
            '.cat-config-aside { position: sticky; top: 0; align-self: flex-start; max-height: 100vh; overflow-y: auto; }' .
        '}' .
        // ===== 移动端适配：侧边栏改为顶部横向滚动 =====
        '@media (max-width: 768px) {' .
            '.cat-config-container { flex-direction: column; border-radius: 4px; margin-bottom: 12px; }' .
            '.cat-config-aside { width: 100%; border-right: none; border-bottom: 1px solid #ececec; position: relative; }' .
            '.cat-config-logo { padding: 12px 14px; font-size: 13px; }' .
            '#cat-tabs { display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin; padding: 0; }' .
            '#cat-tabs::-webkit-scrollbar { height: 3px; }' .
            '#cat-tabs::-webkit-scrollbar-thumb { background: #d9d9d9; border-radius: 2px; }' .
            '.cat-config-aside li { padding: 10px 14px; white-space: nowrap; border-left: none; border-bottom: 2px solid transparent; font-size: 12px; }' .
            '.cat-config-aside li.active { background: transparent; border-bottom-color: #467B96; border-left-color: transparent; }' .
            '.cat-config-aside li:hover { background: #f0f0f0; }' .
            '.cat-config-main { padding: 6px 14px 18px; min-height: 240px; width: 100%; box-sizing: border-box; }' .
            '.cat-config-main .typecho-option { padding: 12px 0; }' .
            '.cat-config-main .typecho-option label.typecho-label { font-size: 13px; margin-bottom: 6px; }' .
            '.cat-config-main input[type=text], .cat-config-main textarea, .cat-config-main select, .cat-config-main input[type=number] { padding: 8px 10px; font-size: 13px; }' .
            '.cat-config-main textarea { min-height: 64px; }' .
            '.typecho-option-submit { padding: 14px; text-align: center; }' .
            '.typecho-option-submit button { width: 100%; max-width: 260px; height: 40px !important; padding: 0 18px !important; }' .
            '.cat-data-section { padding: 12px 0; }' .
            '.cat-data-title { font-size: 12px; }' .
            '.cat-data-desc { font-size: 11px; line-height: 1.5; }' .
            '.cat-file-row { flex-direction: column; align-items: stretch; gap: 8px; }' .
            '.cat-file-label { text-align: center; padding: 10px; }' .
            '.cat-data-btn { width: 100%; text-align: center; padding: 9px; }' .
            '.cat-data-warning { font-size: 11px; padding: 9px 12px; line-height: 1.5; }' .
            '.cat-config-main ul { gap: 5px; }' .
            '.cat-config-main ul li label { padding: 5px 9px; font-size: 11px; }' .
        '}' .
        '@media (max-width: 480px) {' .
            '.cat-config-logo { padding: 10px 12px; font-size: 12px; }' .
            '.cat-config-aside li { padding: 8px 12px; font-size: 11px; }' .
            '.cat-config-main { padding: 4px 12px 14px; }' .
            '.cat-config-main .typecho-option { padding: 10px 0; }' .
            '.cat-config-main .typecho-option label.typecho-label { font-size: 12px; }' .
            '.cat-config-main .description { font-size: 11px; }' .
            '.typecho-option-submit { padding: 12px; }' .
            '.typecho-option-submit button { height: 38px !important; font-size: 12px !important; }' .
        '}' .
        '</style>';
    echo $css;

    $html = '<div id="cat-tpl" style="display:none">' .
        '<div class="cat-config-container">' .
            '<div class="cat-config-aside">' .
                '<div class="cat-config-logo">ShuFeiCat 设置</div>' .
                '<ul id="cat-tabs">' .
                    '<li data-id="cat-basic" class="active">基本设置</li>' .
                    '<li data-id="cat-avatar">头像外观</li>' .
                    '<li data-id="cat-appearance">外观设置</li>' .
                    '<li data-id="cat-pjax">Pjax无刷新</li>' .
                    '<li data-id="cat-resource">资源加载</li>' .
                    '<li data-id="cat-article">文章缩略图</li>' .
                    '<li data-id="cat-sidebar">侧边栏设置</li>' .
                    '<li data-id="cat-stats">文章统计</li>' .
                    '<li data-id="cat-seo">SEO 设置</li>' .
                    '<li data-id="cat-mail">评论邮件通知</li>' .
                    '<li data-id="cat-ai">AI 助手</li>' .
                    '<li data-id="cat-storage">图片存储</li>' .
                    '<li data-id="cat-verify">人机验证</li>' .
                    '<li data-id="cat-enhance">功能增强</li>' .
                    '<li data-id="cat-nav">导航增强</li>' .
                    '<li data-id="cat-data">数据管理</li>' .
                    '<li data-id="cat-update">更新设置</li>' .
                '</ul>' .
            '</div>' .
            '<div class="cat-config-main" id="cat-panes"></div>' .
        '</div>' .
    '</div>';
    echo $html;

    $js = '<script>' .
    '(function() {' .
        'window.addEventListener("load", function() {' .
            'var f = document.querySelector(".main form");' .
            'if (!f) return;' .
            'var c = document.getElementById("cat-tpl").querySelector(".cat-config-container");' .
            'var pWrap = c.querySelector("#cat-panes");' .
            'f.insertBefore(c, f.firstChild);' .
            'var ids = ["cat-basic", "cat-avatar", "cat-appearance", "cat-pjax", "cat-resource", "cat-article", "cat-sidebar", "cat-stats", "cat-seo", "cat-mail", "cat-ai", "cat-storage", "cat-verify", "cat-enhance", "cat-nav", "cat-data", "cat-update"];' .
            'ids.forEach(function(id) {' .
                'var p = document.createElement("div");' .
                'p.id = id; p.className = "cat-pane" + (id === "cat-basic" ? " active" : "");' .
                'pWrap.appendChild(p);' .
            '});' .
            'document.querySelectorAll(".typecho-option:not(.typecho-option-submit)").forEach(function(el) {' .
                'var m = el.className.match(/cat-group-[\w-]+/);' .
                'if (m) {' .
                    'var target = document.getElementById(m[0].replace("cat-group-", "cat-"));' .
                    'if (target) target.appendChild(el);' .
                '} else {' .
                    'var basic = document.getElementById("cat-basic");' .
                    'if (basic) basic.appendChild(el);' .
                '}' .
            '});' .
            'document.querySelectorAll("#cat-tabs li").forEach(function(li) {' .
                'li.onclick = function() {' .
                    'document.querySelectorAll("#cat-tabs li").forEach(function(n) { n.classList.remove("active"); });' .
                    'this.classList.add("active");' .
                    'document.querySelectorAll(".cat-pane").forEach(function(p) { p.classList.remove("active"); });' .
                    'var targetPane = document.getElementById(this.getAttribute("data-id"));' .
                    'if (targetPane) targetPane.classList.add("active");' .
                '};' .
            '});' .
        '});' .
    '})();' .
    '</script>';
    echo $js;

    $dataHtml = '<div class="typecho-option cat-group-data" style="display:none">' .
        '<div class="cat-data-warning">' .
            '<i class="fa fa-exclamation-triangle"></i>数据管理说明：导出功能会将当前主题的所有配置项保存为 JSON 文件；导入功能会从 JSON 文件恢复配置并自动保存。<b>导入操作将覆盖当前所有设置，请谨慎操作！</b>' .
        '</div>' .
        '<div class="cat-data-section">' .
            '<div class="cat-data-title">导出配置</div>' .
            '<div class="cat-data-desc">将当前主题的所有配置项导出为 JSON 文件，可用于备份或迁移到其他站点。</div>' .
            '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-export-btn">' .
                '<i class="fa fa-download" style="margin-right:6px"></i>导出配置文件' .
            '</button>' .
        '</div>' .
        '<div class="cat-data-section">' .
            '<div class="cat-data-title">导入配置</div>' .
            '<div class="cat-data-desc">从之前导出的 JSON 文件中恢复主题配置。导入后当前配置将被完全覆盖并自动保存，建议先导出备份。</div>' .
            '<div class="cat-file-row">' .
                '<label class="cat-file-label">' .
                    '<i class="fa fa-file-text-o" style="margin-right:6px"></i>选择配置文件' .
                    '<input type="file" id="cat-import-file" accept=".json">' .
                '</label>' .
                '<span class="cat-file-name" id="cat-file-name">未选择文件</span>' .
                '<button type="button" class="cat-data-btn cat-data-btn-warning" id="cat-import-btn" disabled>' .
                    '<i class="fa fa-upload" style="margin-right:6px"></i>导入配置' .
                '</button>' .
            '</div>' .
            '<div class="cat-data-status" id="cat-import-status"></div>' .
        '</div>' .
    '</div>';
    echo $dataHtml;

    $dataJs = '<script>' .
    '(function() {' .
        'window.addEventListener("load", function() {' .
            'var dataEl = document.querySelector(".cat-group-data");' .
            'if (dataEl) dataEl.style.display = "";' .

            'function collectFormData() {' .
                'var form = document.querySelector(".main form");' .
                'if (!form) return {};' .
                'var data = {};' .
                'var inputs = form.querySelectorAll("input[name], textarea[name], select[name]");' .
                'inputs.forEach(function(el) {' .
                    'var name = el.getAttribute("name");' .
                    'if (!name || name.indexOf("do") === 0) return;' .
                    'if (el.type === "checkbox") {' .
                        'if (el.checked) {' .
                            'if (!data[name]) data[name] = [];' .
                            'data[name].push(el.value);' .
                        '}' .
                    '} else if (el.type === "radio") {' .
                        'if (el.checked) data[name] = el.value;' .
                    '} else {' .
                        'data[name] = el.value;' .
                    '}' .
                '});' .
                'return data;' .
            '}' .

            'var exportBtn = document.getElementById("cat-export-btn");' .
            'if (exportBtn) {' .
                'exportBtn.addEventListener("click", function() {' .
                    'var data = collectFormData();' .
                    'var exportData = {' .
                        'theme: "shufeicat-typecho",' .
                        'version: "' . shufei_get_theme_version() . '",' .
                        'exportTime: new Date().toLocaleString("zh-CN"),' .
                        'options: data' .
                    '};' .
                    'var json = JSON.stringify(exportData, null, 2);' .
                    'var blob = new Blob([json], {type: "application/json"});' .
                    'var url = URL.createObjectURL(blob);' .
                    'var a = document.createElement("a");' .
                    'a.href = url;' .
                    'a.download = "shufeicat-config-" + new Date().toISOString().slice(0,19).replace(/[T:]/g, "") + ".json";' .
                    'document.body.appendChild(a);' .
                    'a.click();' .
                    'document.body.removeChild(a);' .
                    'URL.revokeObjectURL(url);' .
                '});' .
            '}' .

            'var fileInput = document.getElementById("cat-import-file");' .
            'var fileName = document.getElementById("cat-file-name");' .
            'var importBtn = document.getElementById("cat-import-btn");' .
            'var importStatus = document.getElementById("cat-import-status");' .

            'if (fileInput) {' .
                'fileInput.addEventListener("change", function() {' .
                    'if (this.files && this.files[0]) {' .
                        'fileName.textContent = this.files[0].name;' .
                        'importBtn.disabled = false;' .
                        'importStatus.className = "cat-data-status";' .
                        'importStatus.textContent = "";' .
                    '} else {' .
                        'fileName.textContent = "未选择文件";' .
                        'importBtn.disabled = true;' .
                    '}' .
                '});' .
            '}' .

            'if (importBtn) {' .
                'importBtn.addEventListener("click", function() {' .
                    'if (!fileInput || !fileInput.files || !fileInput.files[0]) {' .
                        'importStatus.className = "cat-data-status show error";' .
                        'importStatus.textContent = "请先选择配置文件";' .
                        'return;' .
                    '}' .
                    'if (!confirm("导入配置将覆盖当前所有主题设置并自动保存，确定要继续吗？")) return;' .
                    'var reader = new FileReader();' .
                    'reader.onload = function(e) {' .
                        'try {' .
                            'var importData = JSON.parse(e.target.result);' .
                            'if (!importData || !importData.options || typeof importData.options !== "object") {' .
                                'importStatus.className = "cat-data-status show error";' .
                                'importStatus.textContent = "无效的配置文件格式，请检查文件内容";' .
                                'return;' .
                            '}' .
                            'var form = document.querySelector(".main form");' .
                            'if (!form) {' .
                                'importStatus.className = "cat-data-status show error";' .
                                'importStatus.textContent = "未找到设置表单";' .
                                'return;' .
                            '}' .
                            'var options = importData.options;' .
                            'var formInputs = {};' .
                            'form.querySelectorAll("input[name], textarea[name], select[name]").forEach(function(el) {' .
                                'var n = el.getAttribute("name");' .
                                'if (n && n.indexOf("do") !== 0) {' .
                                    'if (!formInputs[n]) formInputs[n] = [];' .
                                    'formInputs[n].push(el);' .
                                '}' .
                            '});' .
                            'for (var key in options) {' .
                                'if (!formInputs[key]) continue;' .
                                'var els = formInputs[key];' .
                                'var val = options[key];' .
                                'els.forEach(function(el) {' .
                                    'if (el.type === "checkbox") {' .
                                        'var vals = Array.isArray(val) ? val : [val];' .
                                        'el.checked = vals.indexOf(el.value) !== -1;' .
                                    '} else if (el.type === "radio") {' .
                                        'el.checked = (el.value === String(val));' .
                                    '} else if (el.tagName === "SELECT") {' .
                                        'el.value = val;' .
                                    '} else {' .
                                        'el.value = val || "";' .
                                    '}' .
                                '});' .
                            '}' .
                            'importStatus.className = "cat-data-status show success";' .
                            'importStatus.textContent = "配置已填入表单，正在自动保存...";' .
                            'var submitBtn = form.querySelector("button[type=submit], input[type=submit]");' .
                            'if (submitBtn) {' .
                                'setTimeout(function() { submitBtn.click(); }, 800);' .
                            '} else {' .
                                'importStatus.textContent = "配置已填入表单，请手动点击保存设置按钮。";' .
                            '}' .
                        '} catch(err) {' .
                            'importStatus.className = "cat-data-status show error";' .
                            'importStatus.textContent = "解析配置文件失败：" + err.message;' .
                        '}' .
                    '};' .
                    'reader.readAsText(fileInput.files[0]);' .
                '});' .
            '}' .
        '});' .
    '})();' .
    '</script>';
    echo $dataJs;

    // GitHub 项目选择器
    $githubReposHtml = '<div class="typecho-option cat-group-nav-github-selector" style="display:none">' .
        '<div class="cat-data-section">' .
            '<div class="cat-data-title">GitHub 项目选择</div>' .
            '<div class="cat-data-desc">填写 GitHub 用户名并保存设置后，点击下方按钮获取项目列表，勾选需要在前台展示的项目。<br>如果不勾选任何项目，则默认展示全部公开项目。</div>' .
            '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">' .
                '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-github-fetch-btn">' .
                    '<i class="fa fa-github" style="margin-right:6px"></i>获取项目列表' .
                '</button>' .
                '<button type="button" class="cat-data-btn cat-data-btn-warning" id="cat-github-toggle-btn" style="display:none;">' .
                    '<i class="fa fa-exchange" style="margin-right:6px"></i>一键反选' .
                '</button>' .
            '</div>' .
            '<span id="cat-github-fetch-status" style="margin-left:12px;font-size:13px;color:#999;"></span>' .
            '<div id="cat-github-repos-container" style="margin-top:15px;max-height:400px;overflow-y:auto;"></div>' .
        '</div>' .
    '</div>';
    echo $githubReposHtml;

    echo <<<GITHUBJS
<script>
(function() {
    window.addEventListener("load", function() {
        var selectorEl = document.querySelector(".cat-group-nav-github-selector");
        if (selectorEl) {
            selectorEl.style.display = "";
            var navPane = document.getElementById("cat-nav");
            if (navPane) navPane.appendChild(selectorEl);
        }

        var fetchBtn = document.getElementById("cat-github-fetch-btn");
        var fetchStatus = document.getElementById("cat-github-fetch-status");
        var reposContainer = document.getElementById("cat-github-repos-container");
        var selectedReposInput = document.querySelector("textarea[name=githubSelectedRepos]");
        var toggleBtn = document.getElementById("cat-github-toggle-btn");

        function getSelectedRepos() {
            if (!selectedReposInput || !selectedReposInput.value.trim()) return [];
            try { return JSON.parse(selectedReposInput.value); } catch(e) { return []; }
        }

        function updateSelectedRepos() {
            if (!reposContainer) return;
            var checked = reposContainer.querySelectorAll("input[data-repo-name]:checked");
            var selected = [];
            checked.forEach(function(cb) { selected.push(cb.getAttribute("data-repo-name")); });
            if (selectedReposInput) selectedReposInput.value = selected.length > 0 ? JSON.stringify(selected) : "";
        }

        function renderRepos(repos) {
            if (!reposContainer) return;
            var selected = getSelectedRepos();
            if (repos.length === 0) {
                reposContainer.innerHTML = '<div style="padding:20px;text-align:center;color:#999;">暂无公开项目</div>';
                return;
            }
            var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;">';
            repos.forEach(function(repo) {
                var isChecked = selected.length === 0 || selected.indexOf(repo.name) !== -1;
                html += '<label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#fafafa;border:1px solid #eee;border-radius:6px;cursor:pointer;transition:all .2s;font-size:13px;" onmouseover="this.style.borderColor=\'#467B96\'" onmouseout="this.style.borderColor=\'#eee\'">';
                html += '<input type="checkbox" data-repo-name="' + repo.name + '" ' + (isChecked ? "checked" : "") + ' style="margin-top:2px;accent-color:#467B96;">';
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="font-weight:600;color:#333;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + repo.name + '</div>';
                html += '<div style="color:#999;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (repo.description || '暂无描述') + '</div>';
                html += '<div style="margin-top:4px;display:flex;gap:12px;color:#aaa;font-size:11px;">';
                if (repo.language) {
                    html += '<span><i class="fa fa-circle" style="font-size:8px;color:#467B96;"></i> ' + repo.language + '</span>';
                }
                html += '<span><i class="fa fa-star"></i> ' + repo.stars + '</span>';
                html += '</div></div></label>';
            });
            html += '</div>';
            reposContainer.innerHTML = html;

            reposContainer.querySelectorAll("input[data-repo-name]").forEach(function(cb) {
                cb.addEventListener("change", updateSelectedRepos);
            });

            if (toggleBtn) toggleBtn.style.display = "";
        }

        if (toggleBtn) {
            toggleBtn.addEventListener("click", function() {
                if (!reposContainer) return;
                reposContainer.querySelectorAll("input[data-repo-name]").forEach(function(cb) {
                    cb.checked = !cb.checked;
                });
                updateSelectedRepos();
            });
        }

        if (fetchBtn) {
            fetchBtn.addEventListener("click", function() {
                var usernameInput = document.querySelector("input[name=githubUsername]");
                var username = usernameInput ? usernameInput.value.trim() : "";
                if (!username) {
                    fetchStatus.textContent = "请先填写 GitHub 用户名并保存设置";
                    fetchStatus.style.color = "#e74c3c";
                    return;
                }
                fetchStatus.textContent = "正在获取项目列表...";
                fetchStatus.style.color = "#999";
                fetchBtn.disabled = true;

                var page = 1;
                var allRepos = [];

                function fetchPage() {
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", "https://api.github.com/users/" + encodeURIComponent(username) + "/repos?sort=stars&per_page=100&page=" + page, true);
                    xhr.setRequestHeader("Accept", "application/vnd.github.v3+json");
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (!Array.isArray(data) || data.length === 0) {
                                    renderRepos(allRepos);
                                    fetchStatus.textContent = "共获取到 " + allRepos.length + " 个项目";
                                    fetchStatus.style.color = "#389e0d";
                                    fetchBtn.disabled = false;
                                    return;
                                }
                                data.forEach(function(r) {
                                    allRepos.push({
                                        name: r.name || "",
                                        description: r.description || "",
                                        language: r.language || "",
                                        stars: r.stargazers_count || 0,
                                        forks: r.forks_count || 0
                                    });
                                });
                                if (data.length < 100) {
                                    renderRepos(allRepos);
                                    fetchStatus.textContent = "共获取到 " + allRepos.length + " 个项目";
                                    fetchStatus.style.color = "#389e0d";
                                    fetchBtn.disabled = false;
                                } else {
                                    page++;
                                    fetchPage();
                                }
                            } catch(e) {
                                fetchStatus.textContent = "解析数据失败";
                                fetchStatus.style.color = "#e74c3c";
                                fetchBtn.disabled = false;
                            }
                        } else if (xhr.status === 403) {
                            fetchStatus.textContent = "GitHub API 请求频率受限，请稍后再试";
                            fetchStatus.style.color = "#e74c3c";
                            fetchBtn.disabled = false;
                        } else if (xhr.status === 404) {
                            fetchStatus.textContent = "用户名不存在，请检查后重试";
                            fetchStatus.style.color = "#e74c3c";
                            fetchBtn.disabled = false;
                        } else {
                            fetchStatus.textContent = "请求失败 (HTTP " + xhr.status + ")";
                            fetchStatus.style.color = "#e74c3c";
                            fetchBtn.disabled = false;
                        }
                    };
                    xhr.onerror = function() {
                        fetchStatus.textContent = "网络请求失败，请检查网络连接";
                        fetchStatus.style.color = "#e74c3c";
                        fetchBtn.disabled = false;
                    };
                    xhr.send();
                }

                fetchPage();
            });
        }
    });
})();
</script>
GITHUBJS;

    $currentVersion = shufei_get_theme_version();
    $updateCfg = shufei_get_update_config();
    $updateChannel = $updateCfg['channel'];
    $updateApiUrlVal = $updateCfg['apiUrl'];
    $updateOwnerVal = $updateCfg['owner'];
    $updateRepoVal = $updateCfg['repo'];
    $updateResult = shufei_check_theme_update();
    $channelLabels = array('stable' => '正式版', 'dev' => '开发版', 'manual' => '手动检查');
    $chLabel = isset($channelLabels[$updateChannel]) ? $channelLabels[$updateChannel] : $updateChannel;

    echo '<style>' .
        '.shufei-update-box{padding:18px 20px;border-radius:8px;margin-bottom:25px;background:#fff;border:1px solid #e5e5e5;box-shadow:0 4px 15px rgba(0,0,0,0.04);font-family:"PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;}' .
        '.shufei-update-head{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;}' .
        '.shufei-update-title{font-weight:bold;font-size:15px;color:#333;}' .
        '.shufei-update-ver{font-size:13px;color:#666;}' .
        '.shufei-update-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;color:#fff;background:#467B96;}' .
        '.shufei-update-badge.dev{background:#e67e22;}' .
        '.shufei-update-badge.manual{background:#95a5a6;}' .
        '.shufei-update-notice{padding:12px 14px;border-radius:6px;font-size:13px;line-height:1.7;margin-bottom:14px;border:1px solid transparent;}' .
        '.shufei-update-notice.has-update{background:#fffbe6;border-color:#ffe58f;color:#d48806;}' .
        '.shufei-update-notice.latest{background:#f6ffed;border-color:#b7eb8f;color:#389e0d;}' .
        '.shufei-update-notice.info{background:#e6f7ff;border-color:#91d5ff;color:#096dd9;}' .
        '.shufei-update-notice.error{background:#fff2f0;border-color:#ffccc7;color:#cf1322;}' .
        '.shufei-update-notice a{color:#467B96;}' .
        '.shufei-update-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}' .
        '.shufei-update-btn{background:#467B96;color:#fff;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit;}' .
        '.shufei-update-btn:hover{opacity:.9;transform:translateY(-1px);}' .
        '.shufei-update-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}' .
        '.shufei-update-select{height:auto !important;padding:8px 30px 8px 12px !important;line-height:normal !important;box-sizing:border-box;border:1px solid #ddd;border-radius:6px;font-size:13px;background:#fafafa;font-family:inherit;vertical-align:middle;}' .
        '.shufei-update-status{margin-top:12px;padding:12px 14px;border-radius:6px;font-size:13px;line-height:1.7;display:none;border:1px solid transparent;}' .
        '.shufei-update-status.show{display:block;}' .
        '.shufei-update-status.success{background:#f6ffed;border-color:#b7eb8f;color:#389e0d;}' .
        '.shufei-update-status.error{background:#fff2f0;border-color:#ffccc7;color:#cf1322;}' .
        '.shufei-update-status.info{background:#e6f7ff;border-color:#91d5ff;color:#096dd9;}' .
        '.shufei-update-status a{color:#467B96;}' .
        '</style>';

    echo '<div class="shufei-update-box">';
    echo '<div class="shufei-update-head">';
    echo '<span class="shufei-update-title">主题更新检查</span>';
    echo '<span class="shufei-update-ver">当前版本：<b>v' . htmlspecialchars($currentVersion) . '</b></span>';
    echo '<span class="shufei-update-badge ' . htmlspecialchars($updateChannel) . '">' . htmlspecialchars($chLabel) . '</span>';
    echo '</div>';

    if ($updateResult && isset($updateResult['code']) && $updateResult['code'] == 1 && !empty($updateResult['version'])) {
        $remoteVersion = $updateResult['version'];
        if (version_compare($remoteVersion, $currentVersion, '>')) {
            $noticeMsg = '发现新版本 <b>v' . htmlspecialchars($remoteVersion) . '</b>';
            if (!empty($updateResult['msg'])) {
                $noticeMsg .= '<br>更新内容：' . htmlspecialchars($updateResult['msg']);
            }
            if (!empty($updateResult['url'])) {
                $noticeMsg .= '<br>下载链接：<a href="' . htmlspecialchars($updateResult['url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($updateResult['url']) . '</a>';
            }
            echo '<div class="shufei-update-notice has-update">' . $noticeMsg . '</div>';
        } else {
            echo '<div class="shufei-update-notice latest">当前已是最新版本</div>';
        }
    } elseif ($updateChannel === 'manual') {
        echo '<div class="shufei-update-notice info">已切换为手动检查模式，请前往"更新设置"分类中手动检查更新（不会自动请求网络）</div>';
    } else {
        $msg = ($updateResult && isset($updateResult['msg'])) ? $updateResult['msg'] : '检测失败，请稍后重试';
        echo '<div class="shufei-update-notice error">' . htmlspecialchars($msg) . '</div>';
    }

    echo '</div>';
    
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
    );
    $logoUrl->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的URL地址')));

    $faviconUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'faviconUrl',
        null,
        null,
        _t('站点 Favicon 地址'),
        _t('在这里填入 favicon 图标的 URL 地址（支持 .ico / .png / .svg 等格式），将显示在浏览器标签页上<br>可填写完整 URL（如 https://example.com/favicon.ico）或相对路径（如 /favicon.ico）<br>留空则不输出 favicon 标签')
    );
    $faviconUrl->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($faviconUrl);

    $authorAvatar = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorAvatar',
        null,
        'https://q1.qlogo.cn/g?b=qq&nk=3522934828&s=100',
        _t('站长头像'),
        _t('在这里填入站长头像的URL地址，显示在左侧侧边栏顶部<br>默认：QQ头像')
    );
    $authorAvatar->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorAvatar);

    $authorName = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorName',
        null,
        '云猫',
        _t('站长名称'),
        _t('在这里填入站长名称，显示在左侧侧边栏头像下方<br>默认：云猫')
    );
    $authorName->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorName);

    $authorSignature = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorSignature',
        null,
        'Hello,world',
        _t('站长签名'),
        _t('在这里填入站长个性签名，显示在左侧侧边栏名称下方<br>默认：Hello,world')
    );
    $authorSignature->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorSignature);

    $authorEmail = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorEmail',
        null,
        'yuncat@email.lwcat.cn',
        _t('站长邮箱'),
        _t('在这里填入站长邮箱地址，显示在左侧侧边栏底部联系方式中<br>留空则不显示邮箱<br>默认：yuncat@email.lwcat.cn')
    );
    $authorEmail->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorEmail);

    $authorGithub = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorGithub',
        null,
        'https://github.com/smcloudcat/shufeicat-typecho',
        _t('站长GitHub'),
        _t('在这里填入GitHub主页地址，显示在左侧侧边栏底部联系方式中<br>留空则不显示GitHub<br>默认：https://github.com/smcloudcat/shufeicat-typecho')
    );
    $authorGithub->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorGithub);

    $authorQQ = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorQQ',
        null,
        '',
        _t('站长QQ'),
        _t('在这里填入QQ号码，显示在左侧侧边栏底部联系方式中<br>留空则不显示QQ')
    );
    $authorQQ->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorQQ);

    // ===== 更新设置 =====
    $updateApiUrlField = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiUpdateApiUrl',
        null,
        'https://githubver.czzu.cn/',
        _t('更新接口地址'),
        _t('介绍：主题更新检查的 API 地址，默认为官方更新中心<br>如自建更新服务，请填写你部署的 api.php 完整地址（例如 https://your-domain.com/api.php）')
    );
    $updateApiUrlField->setAttribute('class', 'typecho-option cat-group-update');
    $form->addInput($updateApiUrlField->addRule('url', _t('请填写一个合法的URL地址')));

    $updateChannelField = new \Typecho\Widget\Helper\Form\Element\Radio(
        'shufeiUpdateChannel',
        array(
            'stable' => _t('正式版（自动）'),
            'dev'    => _t('开发版（自动）'),
            'manual' => _t('手动检查')
        ),
        'stable',
        _t('更新通道'),
        _t('介绍：选择主题更新检查的方式与通道<br><b>正式版</b>：仅自动检查正式版（Stable）更新，稳定优先<br><b>开发版</b>：自动检查开发版（rc/beta 等预发布）更新，体验新功能<br><b>手动检查</b>：不自动请求网络，仅在下方"立即检查更新"区域手动获取')
    );
    $updateChannelField->setAttribute('class', 'typecho-option cat-group-update');
    $form->addInput($updateChannelField);

    $updateOwnerField = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiUpdateOwner',
        null,
        'smcloudcat',
        _t('GitHub 仓库 Owner'),
        _t('介绍：用于更新检查时定位项目，对应 GitHub 仓库的所有者<br>该值需与更新服务后台中的项目 owner 一致')
    );
    $updateOwnerField->setAttribute('class', 'typecho-option cat-group-update');
    $form->addInput($updateOwnerField);

    $updateRepoField = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiUpdateRepo',
        null,
        'shufeicat-typecho',
        _t('GitHub 仓库名'),
        _t('介绍：用于更新检查时定位项目，对应 GitHub 仓库名称<br>该值需与更新服务后台中的项目 repo 一致')
    );
    $updateRepoField->setAttribute('class', 'typecho-option cat-group-update');
    $form->addInput($updateRepoField);

    // ===== 立即检查更新（手动触发） =====
    echo '<div class="typecho-option cat-group-update" style="margin-bottom:20px">';
    echo '<label class="typecho-label">立即检查更新</label>';
    echo '<p class="typecho-option-description" style="color:#999;font-size:12px;margin:0 0 12px">介绍：直连更新接口强制检查最新版本（跨域已开启），可临时切换正式版/开发版通道</p>';
    echo '<div class="shufei-update-actions">';
    echo '<select class="shufei-update-select" id="shufei-check-channel">';
    echo '<option value="stable"' . ($updateChannel === 'stable' ? ' selected' : '') . '>检查正式版</option>';
    echo '<option value="dev"' . ($updateChannel === 'dev' ? ' selected' : '') . '>检查开发版</option>';
    echo '</select>';
    echo '<button type="button" class="shufei-update-btn" id="shufei-check-btn" data-api="' . htmlspecialchars($updateApiUrlVal) . '" data-owner="' . htmlspecialchars($updateOwnerVal) . '" data-repo="' . htmlspecialchars($updateRepoVal) . '" data-version="' . htmlspecialchars($currentVersion) . '">立即检查更新</button>';
    echo '</div>';
    echo '<div class="shufei-update-status" id="shufei-check-status"></div>';
    echo '</div>';

    $updateJs = <<<'UPDATEJS'
<script>
(function(){
    var btn = document.getElementById("shufei-check-btn");
    if (!btn) return;
    var status = document.getElementById("shufei-check-status");
    var chSel = document.getElementById("shufei-check-channel");
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
        });
    }
    btn.addEventListener("click", function(){
        var ch = chSel.value;
        var api = btn.getAttribute("data-api");
        var sep = api.indexOf("?") === -1 ? "?" : "&";
        var url = api + sep + "owner=" + encodeURIComponent(btn.getAttribute("data-owner"))
            + "&repo=" + encodeURIComponent(btn.getAttribute("data-repo"))
            + "&version=" + encodeURIComponent(btn.getAttribute("data-version"))
            + "&channel=" + encodeURIComponent(ch);
        status.className = "shufei-update-status show info";
        status.innerHTML = "正在检查更新...";
        btn.disabled = true;
        fetch(url).then(function(r){ return r.json(); }).then(function(d){
            if (d.code == 1 && d.version) {
                var h = "<b>发现新版本：v" + esc(d.version) + "</b>";
                if (d.url) h += "<br>下载链接：<a href=\"" + esc(d.url) + "\" target=\"_blank\" rel=\"noopener\">" + esc(d.url) + "</a>";
                if (d.msg) h += "<br>更新内容：" + esc(d.msg);
                status.className = "shufei-update-status show success";
                status.innerHTML = h;
            } else {
                status.className = "shufei-update-status show info";
                status.innerHTML = esc(d.msg || "当前已是最新版本");
            }
        }).catch(function(e){
            status.className = "shufei-update-status show error";
            status.innerHTML = "检查失败：" + esc(e.message);
        }).finally(function(){ btn.disabled = false; });
    });
})();
</script>
UPDATEJS;
    echo $updateJs;

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        array(
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowRecentComments' => _t('显示最近回复'),
            'ShowArchive'        => _t('显示归档'),
            'ShowOther'          => _t('显示其它杂项'),
            'ShowLinks'          => _t('显示友链')
        ),
        array('ShowRecentPosts', 'ShowRecentComments', 'ShowArchive', 'ShowOther', 'ShowLinks'),
        _t('侧边栏功能板块'),
        _t('介绍：选择要在侧边栏展示的功能板块（分类目录、页面导航、站长信息等为固定板块，始终显示）')
    );
    $sidebarBlock->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($sidebarBlock->multiMode());

    $links = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'links',
        null,
        null,
        _t('友链配置'),
        _t('介绍：每行一个友链，支持两种格式：<br>基本格式（逗号分隔）：<code>名称,链接地址</code><br>完整格式（竖线分隔，可带描述和头像）：<code>名称|链接地址|描述|头像地址</code><br>例如：<br>CC的小窝,https://lwcat.cn<br>谷歌|https://www.google.com|全球最大的搜索引擎|https://www.google.com/favicon.ico<br>描述和头像为可选项，留空则不显示；侧边栏列表仅显示名称和链接')
    );
    $links->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($links);

    $linksDropdownEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'linksDropdownEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'on',
        _t('侧边栏下拉友链列表'),
        _t('介绍：控制左侧侧边栏中折叠式友链列表的显示<br>开启独立友链页面后，可关闭此项以避免重复展示<br>注意：还需在上方"侧边栏功能板块"中勾选"显示友链"才会显示')
    );
    $linksDropdownEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($linksDropdownEnabled);

    $linksPageEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'linksPageEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('友链独立页面'),
        _t('介绍：开启后，在左侧导航栏添加友链页面入口，用户可在独立页面查看所有友链的卡片展示<br>需要先创建一个独立页面并选择"友链页面"模板')
    );
    $linksPageEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($linksPageEnabled);

    $linksPageId = new \Typecho\Widget\Helper\Form\Element\Text(
        'linksPageId',
        null,
        null,
        _t('友链页面ID'),
        _t('介绍：填写友链独立页面的ID（在后台页面管理中查看）<br>如果留空，将尝试自动查找使用友链页面模板的页面')
    );
    $linksPageId->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($linksPageId);

    $sidebarOrderLeft = new \Typecho\Widget\Helper\Form\Element\Text(
        'sidebarOrderLeft',
        null,
        'author,category,pages,guestbook,github,linkspage,customnav,links,other,contacts,footer',
        _t('左侧侧边栏显示顺序'),
        _t('介绍：用英文逗号分隔板块标识，按填写顺序从上到下显示<br>可选板块：<br><b>author</b> = 站长信息（固定）<br><b>category</b> = 分类目录（固定）<br><b>pages</b> = 页面导航（固定）<br><b>guestbook</b> = 留言板入口<br><b>github</b> = GitHub 入口<br><b>linkspage</b> = 友链页面入口<br><b>customnav</b> = 快捷导航<br><b>links</b> = 友链列表<br><b>other</b> = 其它杂项<br><b>contacts</b> = 联系方式<br><b>footer</b> = 底部信息（固定）<br>未填写的板块将按默认顺序追加到末尾；固定板块始终显示')
    );
    $sidebarOrderLeft->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($sidebarOrderLeft);

    $sidebarOrderRight = new \Typecho\Widget\Helper\Form\Element\Text(
        'sidebarOrderRight',
        null,
        'weather,toc,recent,comments,archive,ranking,stats',
        _t('右侧侧边栏显示顺序'),
        _t('介绍：用英文逗号分隔板块标识，按填写顺序从上到下显示<br>可选板块：<br><b>weather</b> = 天气卡片<br><b>toc</b> = 文章目录（仅文章页）<br><b>recent</b> = 最新文章<br><b>comments</b> = 最近回复<br><b>archive</b> = 归档<br><b>ranking</b> = 排行榜<br><b>stats</b> = 站点统计（固定）<br>未填写的板块将按默认顺序追加到末尾')
    );
    $sidebarOrderRight->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($sidebarOrderRight);

    $footerCopyrightEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'footerCopyrightEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('显示底部版权'),
        _t('介绍：是否在网站底部显示版权信息')
    );
    $footerCopyrightEnabled->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerCopyrightEnabled);

    $footerBeianNumber = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerBeianNumber',
        null,
        null,
        _t('网站备案号'),
        _t('介绍：填写网站的备案号（例如：京ICP备12345678号-1）<br>留空则不显示备案信息')
    );
    $footerBeianNumber->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerBeianNumber);

    $footerBeianLink = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerBeianLink',
        null,
        'https://beian.miit.gov.cn/',
        _t('备案号链接'),
        _t('介绍：备案号的跳转链接地址<br>默认：https://beian.miit.gov.cn/')
    );
    $footerBeianLink->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerBeianLink->addRule('url', _t('请填写一个合法的URL地址')));

    $footerGonganNumber = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerGonganNumber',
        null,
        null,
        _t('公安备案号'),
        _t('介绍：填写网站的公安备案号（例如：京公网安备 11010802012345号）<br>留空则不显示公安备案信息')
    );
    $footerGonganNumber->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerGonganNumber);

    $footerGonganLink = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerGonganLink',
        null,
        'https://beian.mps.gov.cn/',
        _t('公安备案链接'),
        _t('介绍：公安备案号的跳转链接地址<br>默认：https://beian.mps.gov.cn/')
    );
    $footerGonganLink->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerGonganLink->addRule('url', _t('请填写一个合法的URL地址')));

    $footerGonganIcon = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerGonganIcon',
        null,
        '{themeUrl}/assets/image/foot-logo.png',
        _t('公安备案图标'),
        _t('介绍：公安备案号前显示的图标图片地址<br>默认：{themeUrl}/assets/image/foot-logo.png（自动指向主题资源目录）<br>留空则不显示图标<br>可使用 {themeUrl} 占位符表示主题URL，也可填写完整URL')
    );
    $footerGonganIcon->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerGonganIcon);

    $footerBeianLayout = new \Typecho\Widget\Helper\Form\Element\Radio(
        'footerBeianLayout',
        array('newline' => _t('分行显示'), 'inline' => _t('同行显示')),
        'newline',
        _t('备案号布局'),
        _t('介绍：ICP备案号与公安备案号的显示方式<br>分行显示：公安备案号单独一行<br>同行显示：公安备案号与ICP备案号显示在同一行')
    );
    $footerBeianLayout->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerBeianLayout);

    $footerCustomText = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'footerCustomText',
        null,
        null,
        _t('自定义版权文本'),
        _t('介绍：自定义底部版权文本，支持HTML代码<br>可以使用以下变量：<br>{year} - 当前年份<br>{sitetitle} - 网站标题<br>{siteurl} - 网站URL')
    );
    $footerCustomText->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerCustomText);
    
    $gravatarSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'gravatarSource',
        array(
            'cat' => _t('Cat源'),
            'loli' => _t('Loli源'),
            'weavatar' => _t('weavatar源'),
            'cravatar' => _t('cravatar源'),
            'official' => _t('官方源')
        ),
        'cat',
        _t('Gravatar头像镜像源'),
        _t('介绍：选择Gravatar头像的访问来源<br>Cat源：https://gravatar.luoli.click/avatar/<br>Loli源：https://gravatar.loli.net/avatar/<br>weavatar源：https://weavatar.com/avatar/<br>cravatar源：https://cravatar.cn/avatar/')
    );
    $gravatarSource->setAttribute('class', 'typecho-option cat-group-avatar');
    $form->addInput($gravatarSource);

    $themeColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'themeColor',
        null,
        '#FF6B6B',
        _t('主题颜色'),
        _t('介绍：设置主题的主色调，应用于链接、按钮等元素<br>默认：#FF6B6B（珊瑚红）<br>请填写有效的十六进制颜色值，例如：#FF6B6B、#1E9FFF、#6C5CE7')
    );
    $themeColor->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($themeColor);

    $bgColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgColor',
        null,
        '#f8f9fc',
        _t('背景颜色'),
        _t('介绍：设置页面的背景颜色<br>默认：#f8f9fc（浅灰蓝色）<br>请填写有效的十六进制颜色值，例如：#f8f9fc、#ffffff、#f0f0f0')
    );
    $bgColor->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgColor);

    $bgImage = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgImage',
        null,
        null,
        _t('背景图片'),
        _t('介绍：设置页面的背景图片URL，留空则不设置背景图片<br>背景图片将覆盖背景颜色设置，建议使用高分辨率图片<br>示例：https://example.com/background.jpg')
    );
    $bgImage->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgImage->addRule('url', _t('请填写一个合法的URL地址')));

    $cardOpacity = new \Typecho\Widget\Helper\Form\Element\Text(
        'cardOpacity',
        null,
        '1',
        _t('盒子透明度'),
        _t('介绍：设置页面中各盒子（导航栏、侧边栏、文章卡片等）的透明度<br>取值范围 0 ~ 1，1 为完全不透明，0 为完全透明<br>默认：1（不透明）<br>设置背景图片后建议调低透明度，例如 0.85，让背景图片透出')
    );
    $cardOpacity->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($cardOpacity);

    $postListStyle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'postListStyle',
        array('card' => _t('卡片模式'), 'classic' => _t('经典模式'), 'minimal' => _t('极简模式')),
        'classic',
        _t('文章列表样式'),
        _t('介绍：选择首页文章列表的展示样式<br>卡片模式：缩略图在左侧，标题和摘要在右侧，信息更清晰<br>经典模式：缩略图作为背景覆盖，文字叠加在图片上<br>极简模式：无缩略图，纯文字列表，紧凑一行一条，适合文字博客快速浏览')
    );
    $postListStyle->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($postListStyle);

    $pjaxLoad = new \Typecho\Widget\Helper\Form\Element\Radio(
        'pjaxLoad',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('Pjax加载'),
        _t('介绍：开启后，全站页面切换将使用Pjax方式，实现无刷新加载，提升用户体验')
    );
    $pjaxLoad->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxLoad);

    $pjaxLoadStyle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'pjaxLoadStyle',
        array('progress' => _t('顶部进度条'), 'circle' => _t('圆形旋转器'), 'dots' => _t('底部圆点')),
        'progress',
        _t('Pjax加载动画'),
        _t('介绍：选择Pjax加载时的动画效果样式')
    );
    $pjaxLoadStyle->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxLoadStyle);

    $pjaxTimeout = new \Typecho\Widget\Helper\Form\Element\Text(
        'pjaxTimeout',
        null,
        '10000',
        _t('Pjax超时时间（毫秒）'),
        _t('介绍：建议5000-30000毫秒，超时后将使用传统方式加载')
    );
    $pjaxTimeout->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxTimeout);
    
    $resourceMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'resourceMode',
        array('local' => _t('本地资源'), 'cdn' => _t('官方CDN'), 'custom' => _t('自建CDN')),
        'local',
        _t('资源加载模式'),
        _t('介绍：选择主题资源的加载方式<br>本地资源：从主题目录加载<br>官方CDN：使用 jsDelivr CDN<br>自建CDN：使用自定义CDN地址')
    );
    $resourceMode->setAttribute('class', 'typecho-option cat-group-resource');
    $form->addInput($resourceMode);

    $customCdn = new \Typecho\Widget\Helper\Form\Element\Text(
        'customCdn',
        null,
        'https://cdn.lwcat.cn/shufeicat',
        _t('自建CDN地址'),
        _t('介绍：当选择"自建CDN"模式时，请填写CDN基础地址（例如https://cdn.lwcat.cn/shufeicat），不要以斜杠结尾<br>把"assets"整个文件夹打包即可（包括assets这个文件夹）')
    );
    $customCdn->setAttribute('class', 'typecho-option cat-group-resource');
    $form->addInput($customCdn->addRule('url', _t('请填写一个合法的URL地址')));
    
    $thumbnailSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'thumbnailSource',
        array('local' => _t('本地图片'), 'remote' => _t('远程图片')),
        'local',
        _t('文章随机缩略图来源'),
        _t('介绍：选择文章随机缩略图的来源方式<br>本地图片：使用主题自带默认图片<br>远程图片：使用自定义远程图片地址')
    );
    $thumbnailSource->setAttribute('class', 'typecho-option cat-group-article');
    $form->addInput($thumbnailSource);

    $statsEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'statsEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('文章统计功能'),
        _t('介绍：开启后，将启用文章浏览量和点赞功能')
    );
    $statsEnabled->setAttribute('class', 'typecho-option cat-group-stats');
    $form->addInput($statsEnabled);

    $rankingEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'rankingEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('排行榜功能'),
        _t('介绍：开启后，将在右侧侧边栏显示文章排行榜')
    );
    $rankingEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($rankingEnabled);

    $rankingType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'rankingType',
        array('views' => _t('按浏览量'), 'likes' => _t('按点赞数')),
        'views',
        _t('排行榜排序方式'),
        _t('介绍：选择侧边栏排行榜的排序依据')
    );
    $rankingType->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($rankingType);

    $rankingLimit = new \Typecho\Widget\Helper\Form\Element\Text(
        'rankingLimit',
        null,
        '5',
        _t('排行榜显示数量'),
        _t('介绍：设置侧边栏排行榜显示的文章数量，默认为5篇')
    );
    $rankingLimit->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($rankingLimit);

    $weatherEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'weatherEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('侧边栏天气卡片'),
        _t('介绍：开启后，将在右侧侧边栏显示精美天气卡片<br>天气数据通过 IP 定位自动获取，无需配置')
    );
    $weatherEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($weatherEnabled);

    $seoKeywords = new \Typecho\Widget\Helper\Form\Element\Text(
        'seoKeywords',
        null,
        null,
        _t('全站关键词（SEO）'),
        _t('介绍：作为首页/分类/标签/归档/搜索/404 等没有自定义关键词页面的默认 SEO 关键词<br>多个关键词请用英文逗号 "," 分隔<br>示例：云猫博客,Typecho 主题,个人博客,技术分享<br>留空则使用 Typecho 后台"站点描述"或站点标题作为兜底')
    );
    $seoKeywords->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoKeywords);

    $seoDescription = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'seoDescription',
        null,
        null,
        _t('全站 SEO 描述'),
        _t('介绍：作为首页/分类/标签/归档等页面的 SEO 描述（description）<br>留空则使用 Typecho 后台"站点描述"<br>建议 80-160 字之间')
    );
    $seoDescription->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoDescription);

    $seoOgImage = new \Typecho\Widget\Helper\Form\Element\Text(
        'seoOgImage',
        null,
        null,
        _t('默认社交分享图（OG Image）'),
        _t('介绍：用于 Open Graph / Twitter Card 分享的默认图片，建议 1200x630 像素<br>当文章/页面没有可用图片时使用此图<br>请填写完整的图片URL地址')
    );
    $seoOgImage->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoOgImage->addRule('url', _t('请填写一个合法的URL地址')));

    $seoSiteVerification = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'seoSiteVerification',
        null,
        null,
        _t('搜索引擎站点验证'),
        _t('介绍：用于搜索引擎站长平台验证，可粘贴完整 meta 标签的 content 值或完整 meta 标签<br>每行一个，例如：<br>google-site-verification=xxxxxx<br>baidu-site-verification=xxxxxx<br>也可以直接粘贴：&lt;meta name="google-site-verification" content="xxxxx" /&gt;')
    );
    $seoSiteVerification->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoSiteVerification);

    $seoRobots = new \Typecho\Widget\Helper\Form\Element\Radio(
        'seoRobots',
        array(
            'auto'    => _t('自动（推荐）'),
            'index'   => _t('全部可收录（index,follow）'),
            'noindex' => _t('全部不可收录（noindex,nofollow）')
        ),
        'auto',
        _t('默认 robots 设置'),
        _t('介绍：自动模式下，首页/文章/页面/分类/标签可被收录，搜索结果/404/分页后页会标记为 noindex')
    );
    $seoRobots->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoRobots);

    $remoteImages = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'remoteImages',
        null,
        null,
        _t('远程图片地址'),
        _t('介绍：当选择"远程图片"时，请填写图片地址，每行一个地址')
    );
    $remoteImages->setAttribute('class', 'typecho-option cat-group-article');
    $form->addInput($remoteImages);
    
    $commentMailEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentMailEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('是否开启评论邮件通知'),
        _t('介绍：开启后评论内容将会进行邮箱通知，该设置会影响用户发布评论速度')
    );
    $commentMailEnabled->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailEnabled);

    $commentMailHost = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailHost',
        null,
        null,
        '邮箱服务器地址',
        '例如：smtp.qq.com'
    );
    $commentMailHost->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailHost);

    $commentMailSMTPSecure = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentMailSMTPSecure',
        array('ssl' => _t('ssl'), 'tls' => _t('tls')),
        'ssl',
        _t('加密方式'),
        _t('介绍：用于选择登录鉴权加密方式')
    );
    $commentMailSMTPSecure->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailSMTPSecure);

    $commentMailPort = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailPort',
        null,
        null,
        '邮箱服务器端口号',
        '例如：465'
    );
    $commentMailPort->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailPort);

    $commentMailFromName = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailFromName',
        null,
        null,
        '发件人昵称',
        '例如：云猫'
    );
    $commentMailFromName->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailFromName);

    $commentMailAccount = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailAccount',
        null,
        null,
        '发件人邮箱',
        '例如：123456@qq.com'
    );
    $commentMailAccount->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailAccount);

    $commentMailPassword = new \Typecho\Widget\Helper\Form\Element\Password(
        'commentMailPassword',
        null,
        null,
        '邮箱授权码',
        '介绍：填写邮箱生成的授权码（以QQ邮箱为例：设置 > 账户 > IMAP/SMTP服务）'
    );
    $commentMailPassword->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailPassword);

    $commentMailTemplateMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentMailTemplateMode',
        array('builtin' => _t('使用内置模板'), 'custom' => _t('使用自定义 HTML 模板')),
        'builtin',
        _t('邮件模板模式'),
        _t('介绍：选择「自定义 HTML 模板」后，将使用下方填写的 HTML 作为邮件正文；留空或缺少必要占位符时会自动回退到内置模板')
    );
    $commentMailTemplateMode->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailTemplateMode);

    $commentMailStyle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentMailStyle',
        array(
            'simple'  => _t('简约'),
            'modern'  => _t('现代'),
            'elegant' => _t('优雅'),
            'cute'    => _t('可爱')
        ),
        'simple',
        _t('内置邮件样式'),
        _t('介绍：当模板模式为「使用内置模板」或自定义模板为空时生效')
    );
    $commentMailStyle->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailStyle);

    $commentMailBgColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailBgColor',
        null,
        '#f8f9fa',
        _t('邮件背景色'),
        _t('介绍：内置模板背景色，填写 HEX 颜色值，例如 #f8f9fa')
    );
    $commentMailBgColor->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailBgColor);

    $commentMailAccentColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailAccentColor',
        null,
        '#3498db',
        _t('邮件主题色'),
        _t('介绍：内置模板按钮、标题栏等强调色，填写 HEX 颜色值，例如 #3498db')
    );
    $commentMailAccentColor->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailAccentColor);

    $commentMailTextColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailTextColor',
        null,
        '#333333',
        _t('邮件文字色'),
        _t('介绍：内置模板主要文字颜色，填写 HEX 颜色值，例如 #333333')
    );
    $commentMailTextColor->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailTextColor);

    $commentMailNewSubject = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailNewSubject',
        null,
        '您的文章 [{postTitle}] 收到一条新的评论！',
        _t('新评论邮件标题模板'),
        _t('介绍：发送给博主的新评论通知标题。可用占位符：{siteName}、{postTitle}、{commentAuthor}、{commentIp}')
    );
    $commentMailNewSubject->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailNewSubject);

    $commentMailReplySubject = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailReplySubject',
        null,
        '您在 [{postTitle}] 的评论有了新的回复！',
        _t('回复通知邮件标题模板'),
        _t('介绍：发送给被回复用户的邮件标题。可用占位符：{siteName}、{postTitle}、{commentAuthor}')
    );
    $commentMailReplySubject->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailReplySubject);

    $commentMailCustomTemplate = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'commentMailCustomTemplate',
        null,
        null,
        _t('自定义邮件 HTML 模板'),
        _t('介绍：支持完整 HTML。常用占位符：{title}=通知标题、{subtitle}=通知说明、{content}=评论内容、{siteName}=站点名称、{postTitle}=文章标题、{commentAuthor}=评论作者、{commentIp}=评论 IP、{permalink}=文章链接、{commentLink}=评论链接、{year}=当前年份<br>示例：&lt;h2&gt;{title}&lt;/h2&gt;&lt;p&gt;{subtitle}&lt;/p&gt;&lt;div&gt;{content}&lt;/div&gt;&lt;a href=&quot;{commentLink}&quot;&gt;查看评论&lt;/a&gt;')
    );
    $commentMailCustomTemplate->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailCustomTemplate);

    // ===== AI 助手配置 =====
    $aiWriterEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiWriterEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('AI写作助手'),
        _t('介绍：开启后可在后台文章编辑器中使用 AI 美化（多种风格）、AI 续写、AI 检查功能')
    );
    $aiWriterEnabled->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiWriterEnabled);

    $aiModerationEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiModerationEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('AI评论审核'),
        _t('介绍：开启后将通过AI进行内容安全审核，该设置会影响评论速度')
    );
    $aiModerationEnabled->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiModerationEnabled);

    // 接口模式：统一接口 or 分别设置
    $aiUnifiedApi = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiUnifiedApi',
        array('on' => _t('统一接口（写作和审核共用）'), 'off' => _t('分别设置（写作和审核使用不同接口）')),
        'on',
        _t('接口模式'),
        _t('介绍：选择「统一接口」时，下方配置同时用于 AI 写作和评论审核；选择「分别设置」时可分别为写作和审核配置不同的接口')
    );
    $aiUnifiedApi->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiUnifiedApi);

    // 统一接口类型：免费接口 or 自定义接口
    $aiUnifiedApiType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiUnifiedApiType',
        array('free' => _t('免费接口（内置，无需填写）'), 'custom' => _t('自定义接口')),
        'custom',
        _t('统一接口类型'),
        _t('介绍：选择「免费接口」将使用内置的免费 AI 接口，无需填写下方地址和密钥；选择「自定义接口」需在下方填写您自己的 API 地址和密钥')
    );
    $aiUnifiedApiType->setAttribute('class', 'typecho-option cat-group-ai ai-unified-field ai-api-type-unified');
    $form->addInput($aiUnifiedApiType);

    // 统一接口配置（aiModerationApiUrl 同时作为统一接口地址，向后兼容）
    $aiModerationApiUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationApiUrl',
        null,
        null,
        _t('统一接口 API 地址'),
        _t('介绍：兼容 OpenAI 格式。可填入完整地址如 https://api.openai.com/v1/chat/completions，也可只填 https://api.openai.com/v1（将自动补全）')
    );
    $aiModerationApiUrl->setAttribute('class', 'typecho-option cat-group-ai ai-unified-field ai-custom-unified-field');
    $form->addInput($aiModerationApiUrl);

    $aiModerationApiKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'aiModerationApiKey',
        null,
        null,
        _t('统一接口 API 密钥'),
        _t('介绍：填写您的 AI 接口 API Key')
    );
    $aiModerationApiKey->setAttribute('class', 'typecho-option cat-group-ai ai-unified-field ai-custom-unified-field');
    $form->addInput($aiModerationApiKey);

    $aiModerationModel = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationModel',
        null,
        'gpt-3.5-turbo',
        _t('统一接口模型'),
        _t('介绍：填写使用的模型名称，如 gpt-3.5-turbo、gpt-4o 等')
    );
    $aiModerationModel->setAttribute('class', 'typecho-option cat-group-ai ai-unified-field ai-custom-unified-field');
    $form->addInput($aiModerationModel);

    // 分别设置模式：写作专用接口类型
    $aiWriterApiType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiWriterApiType',
        array('free' => _t('免费接口（内置，无需填写）'), 'custom' => _t('自定义接口')),
        'custom',
        _t('写作接口类型'),
        _t('介绍：仅当接口模式为「分别设置」时生效。选择「免费接口」将使用内置的免费 AI 接口')
    );
    $aiWriterApiType->setAttribute('class', 'typecho-option cat-group-ai ai-separate-writer-field ai-api-type-writer');
    $form->addInput($aiWriterApiType);

    // 分别设置模式：写作专用接口
    $aiWriterApiUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiWriterApiUrl',
        null,
        null,
        _t('写作接口 API 地址'),
        _t('介绍：仅当接口模式为「分别设置」且接口类型为「自定义接口」时生效。兼容 OpenAI 格式')
    );
    $aiWriterApiUrl->setAttribute('class', 'typecho-option cat-group-ai ai-separate-writer-field ai-custom-writer-field');
    $form->addInput($aiWriterApiUrl);

    $aiWriterApiKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'aiWriterApiKey',
        null,
        null,
        _t('写作接口 API 密钥'),
        _t('介绍：仅当接口模式为「分别设置」且接口类型为「自定义接口」时生效')
    );
    $aiWriterApiKey->setAttribute('class', 'typecho-option cat-group-ai ai-separate-writer-field ai-custom-writer-field');
    $form->addInput($aiWriterApiKey);

    $aiWriterModel = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiWriterModel',
        null,
        'gpt-3.5-turbo',
        _t('写作接口模型'),
        _t('介绍：仅当接口模式为「分别设置」且接口类型为「自定义接口」时生效')
    );
    $aiWriterModel->setAttribute('class', 'typecho-option cat-group-ai ai-separate-writer-field ai-custom-writer-field');
    $form->addInput($aiWriterModel);

    // 分别设置模式：审核专用接口类型
    $aiModerationApiType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiModerationApiType',
        array('free' => _t('免费接口（内置，无需填写）'), 'custom' => _t('自定义接口')),
        'custom',
        _t('审核接口类型'),
        _t('介绍：仅当接口模式为「分别设置」时生效。选择「免费接口」将使用内置的免费 AI 接口')
    );
    $aiModerationApiType->setAttribute('class', 'typecho-option cat-group-ai ai-separate-moderation-field ai-api-type-moderation');
    $form->addInput($aiModerationApiType);

    // 分别设置模式：审核专用接口（使用 aiModerationApiUrl 等字段，标签动态切换）
    $aiModerationSepApiUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationSepApiUrl',
        null,
        null,
        _t('审核接口 API 地址'),
        _t('介绍：仅当接口模式为「分别设置」且接口类型为「自定义接口」时生效。兼容 OpenAI 格式')
    );
    $aiModerationSepApiUrl->setAttribute('class', 'typecho-option cat-group-ai ai-separate-moderation-field ai-custom-moderation-field');
    $form->addInput($aiModerationSepApiUrl);

    $aiModerationSepApiKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'aiModerationSepApiKey',
        null,
        null,
        _t('审核接口 API 密钥'),
        _t('介绍：仅当接口模式为「分别设置」且接口类型为「自定义接口」时生效')
    );
    $aiModerationSepApiKey->setAttribute('class', 'typecho-option cat-group-ai ai-separate-moderation-field ai-custom-moderation-field');
    $form->addInput($aiModerationSepApiKey);

    $aiModerationSepModel = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationSepModel',
        null,
        'gpt-3.5-turbo',
        _t('审核接口模型'),
        _t('介绍：仅当接口模式为「分别设置」且接口类型为「自定义接口」时生效')
    );
    $aiModerationSepModel->setAttribute('class', 'typecho-option cat-group-ai ai-separate-moderation-field ai-custom-moderation-field');
    $form->addInput($aiModerationSepModel);

    // ===== AI 审核高级设置 =====
    $aiModerationPromptLevel = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiModerationPromptLevel',
        array(
            'lenient' => _t('宽松'),
            'normal'  => _t('标准'),
            'strict'  => _t('严格')
        ),
        'normal',
        _t('AI审核力度'),
        _t('介绍：选择审核提示词的严格程度。「宽松」只拦截明显违规内容；「标准」按常规标准审核；「严格」对边界情况倾向于判定不通过')
    );
    $aiModerationPromptLevel->setAttribute('class', 'typecho-option cat-group-ai ai-moderation-advanced');
    $form->addInput($aiModerationPromptLevel);

    $aiModerationStrategy = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiModerationStrategy',
        array(
            'auto_publish'  => _t('通过则直接发布，不通过则进入人工审核'),
            'manual_review' => _t('全部进入人工审核（AI 仅作参考）'),
            'reject'        => _t('不通过则直接拦截（标记为垃圾）')
        ),
        'auto_publish',
        _t('审核后处理方式'),
        _t('介绍：设置 AI 审核结果对评论状态的处理方式')
    );
    $aiModerationStrategy->setAttribute('class', 'typecho-option cat-group-ai ai-moderation-advanced');
    $form->addInput($aiModerationStrategy);

    $aiModerationErrorStrategy = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiModerationErrorStrategy',
        array(
            'waiting' => _t('接口异常时进入人工审核（推荐）'),
            'pass'    => _t('接口异常时直接放行')
        ),
        'waiting',
        _t('AI审核失败处理'),
        _t('介绍：当 AI 接口请求失败或超时时的兜底策略。建议选择「人工审核」更安全；选择「放行」可保证评论不卡顿但有漏审风险')
    );
    $aiModerationErrorStrategy->setAttribute('class', 'typecho-option cat-group-ai ai-moderation-advanced');
    $form->addInput($aiModerationErrorStrategy);

    $aiModerationTimeout = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationTimeout',
        null,
        '30',
        _t('AI审核超时时间（秒）'),
        _t('介绍：AI 接口请求的超时时间，建议 15-60 秒。超时时间过长会导致评论提交变慢')
    );
    $aiModerationTimeout->setAttribute('class', 'typecho-option cat-group-ai ai-moderation-advanced');
    $form->addInput($aiModerationTimeout);

    $aiModerationPrompt = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'aiModerationPrompt',
        null,
        null,
        _t('自定义审核提示词（可选）'),
        _t('介绍：留空则使用上方「审核力度」对应的内置提示词。填写后将完全替代内置提示词，可用 {content} 占位符表示评论内容')
    );
    $aiModerationPrompt->setAttribute('class', 'typecho-option cat-group-ai ai-moderation-advanced');
    $form->addInput($aiModerationPrompt);

    // 接口测试按钮与状态显示
    echo '<div class="typecho-option cat-group-ai">'
        . '<div style="margin-bottom:12px;font-weight:bold;color:#333;">接口连通性测试</div>'
        . '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">'
        . '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-test-unified-api" style="display:none;">测试统一接口</button>'
        . '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-test-writer-api" style="display:none;">测试写作接口</button>'
        . '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-test-moderation-api" style="display:none;">测试审核接口</button>'
        . '</div>'
        . '<div class="cat-data-status" id="cat-api-test-status" style="display:block;max-width:100%;"></div>'
        . '</div>';

    // AI 设置页面的 JS：接口模式切换 + 接口类型切换 + 测试按钮
    $aiOptions = \Typecho\Widget::widget('Widget_Options');
    $aiAjaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/ai-writer-ajax.php', $aiOptions->siteUrl);
    // 内置免费接口配置（供前端测试时回传后端，避免在 JS 中硬编码密钥）
    $freeApiUrl = AiModeration::FREE_API_URL;
    $freeApiKey = AiModeration::FREE_API_KEY;
    $freeApiModel = AiModeration::FREE_API_MODEL;
    // 使用 heredoc 避免单引号/双引号在 PHP 字符串拼接中误判（曾导致 "Undefined constant label" 错误）
    echo <<<HTML
<script>
(function(){
    function initAiSettings(){
        var modeRadios=document.querySelectorAll("input[name=aiUnifiedApi]");
        var unifiedTypeRadios=document.querySelectorAll("input[name=aiUnifiedApiType]");
        var writerTypeRadios=document.querySelectorAll("input[name=aiWriterApiType]");
        var modTypeRadios=document.querySelectorAll("input[name=aiModerationApiType]");
        var modEnabledRadios=document.querySelectorAll("input[name=aiModerationEnabled]");
        var unifiedFields=document.querySelectorAll(".ai-unified-field");
        var writerFields=document.querySelectorAll(".ai-separate-writer-field");
        var modFields=document.querySelectorAll(".ai-separate-moderation-field");
        var customUnifiedFields=document.querySelectorAll(".ai-custom-unified-field");
        var customWriterFields=document.querySelectorAll(".ai-custom-writer-field");
        var customModFields=document.querySelectorAll(".ai-custom-moderation-field");
        var modAdvancedFields=document.querySelectorAll(".ai-moderation-advanced");
        var btnUnified=document.getElementById("cat-test-unified-api");
        var btnWriter=document.getElementById("cat-test-writer-api");
        var btnMod=document.getElementById("cat-test-moderation-api");

        function getRadio(name,def){
            var r=document.querySelector("input[name="+name+"]:checked");
            return r?r.value:def;
        }
        function getMode(){return getRadio("aiUnifiedApi","on");}
        function getUnifiedType(){return getRadio("aiUnifiedApiType","custom");}
        function getWriterType(){return getRadio("aiWriterApiType","custom");}
        function getModType(){return getRadio("aiModerationApiType","custom");}
        function getModEnabled(){return getRadio("aiModerationEnabled","off");}

        function updateFields(){
            var mode=getMode();
            // 接口模式：统一/分别
            unifiedFields.forEach(function(el){el.style.display=mode==="on"?"":"none";});
            writerFields.forEach(function(el){el.style.display=mode==="off"?"":"none";});
            modFields.forEach(function(el){el.style.display=mode==="off"?"":"none";});
            // 接口类型：免费模式下隐藏自定义字段
            var uType=getUnifiedType();
            var wType=getWriterType();
            var mType=getModType();
            if(mode==="on"){
                customUnifiedFields.forEach(function(el){el.style.display=uType==="custom"?"":"none";});
            }else{
                customUnifiedFields.forEach(function(el){el.style.display="none";});
            }
            if(mode==="off"){
                customWriterFields.forEach(function(el){el.style.display=wType==="custom"?"":"none";});
                customModFields.forEach(function(el){el.style.display=mType==="custom"?"":"none";});
            }else{
                customWriterFields.forEach(function(el){el.style.display="none";});
                customModFields.forEach(function(el){el.style.display="none";});
            }
            // AI 审核高级设置：仅在 AI 评论审核开启时显示
            var modOn=getModEnabled()==="on";
            modAdvancedFields.forEach(function(el){el.style.display=modOn?"":"none";});
            // 测试按钮显示
            if(btnUnified)btnUnified.style.display=mode==="on"?"inline-block":"none";
            if(btnWriter)btnWriter.style.display=mode==="off"?"inline-block":"none";
            if(btnMod)btnMod.style.display=mode==="off"?"inline-block":"none";
        }
        [modeRadios,unifiedTypeRadios,writerTypeRadios,modTypeRadios,modEnabledRadios].forEach(function(group){
            group.forEach(function(r){r.addEventListener("change",updateFields);});
        });
        updateFields();

        var statusEl=document.getElementById("cat-api-test-status");
        function showStatus(msg,type){
            if(!statusEl)return;
            statusEl.className="cat-data-status show "+(type||"info");
            statusEl.innerHTML=msg;
        }

        /**
         * 执行测试
         * @param apiType "free" 使用内置免费接口；"custom" 使用表单填写的地址密钥
         */
        function doTest(btn,apiType,urlInput,keyInput,modelInput,label){
            var url="",key="",model="gpt-3.5-turbo";
            if(apiType==="free"){
                url="{$freeApiUrl}";
                key="{$freeApiKey}";
                model="{$freeApiModel}";
            }else{
                if(!urlInput||!keyInput||!modelInput){showStatus("接口字段缺失","error");return;}
                url=urlInput.value.trim();
                key=keyInput.value.trim();
                model=modelInput.value.trim()||"gpt-3.5-turbo";
                if(!url||!key){showStatus("请先填写 API 地址和密钥","error");return;}
            }
            btn.disabled=true;
            btn.textContent="测试中...";
            showStatus("正在测试 "+label+"...","info");
            fetch("{$aiAjaxUrl}",{
                method:"POST",
                credentials:"same-origin",
                headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body:"action=test_api&api_type="+encodeURIComponent(apiType)
                    +"&api_url="+encodeURIComponent(url)
                    +"&api_key="+encodeURIComponent(key)
                    +"&model="+encodeURIComponent(model)
            })
            .then(function(r){return r.json();})
            .then(function(data){
                var type=data.success?"success":"error";
                showStatus("<b>"+label+"测试结果：</b><br>"+(data.message||"未知结果"),type);
                btn.disabled=false;
                btn.textContent="测试"+label;
            })
            .catch(function(e){
                showStatus("测试请求失败："+e,"error");
                btn.disabled=false;
                btn.textContent="测试"+label;
            });
        }

        if(btnUnified)btnUnified.addEventListener("click",function(){
            doTest(btnUnified,getUnifiedType(),getFieldInputs("aiModerationApiUrl"),getFieldInputs("aiModerationApiKey"),getFieldInputs("aiModerationModel"),"统一接口");
        });
        if(btnWriter)btnWriter.addEventListener("click",function(){
            doTest(btnWriter,getWriterType(),getFieldInputs("aiWriterApiUrl"),getFieldInputs("aiWriterApiKey"),getFieldInputs("aiWriterModel"),"写作接口");
        });
        if(btnMod)btnMod.addEventListener("click",function(){
            doTest(btnMod,getModType(),getFieldInputs("aiModerationSepApiUrl"),getFieldInputs("aiModerationSepApiKey"),getFieldInputs("aiModerationSepModel"),"审核接口");
        });

        function getFieldInputs(fieldName){
            var els=document.getElementsByName(fieldName);
            return els.length>0?els[0]:null;
        }
    }
    if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",initAiSettings);}else{initAiSettings();}
})();
</script>
HTML;


    $captchaType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'captchaType',
        array(
            'none' => _t('关闭'),
            'turnstile' => _t('Cloudflare Turnstile'),
            'geetest' => _t('极验 Geetest v4'),
            'captcha_number' => _t('图片验证码（纯数字）'),
            'captcha_alpha' => _t('图片验证码（纯字母）'),
            'captcha_alnum' => _t('图片验证码（数字+字母）')
        ),
        'none',
        _t('评论验证方式'),
        _t('介绍：选择评论提交时的人机验证方式。图片验证码无需第三方服务，Turnstile 需要 Cloudflare 账号，极验 Geetest v4 需要极验账号')
    );
    $captchaType->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($captchaType);

    $turnstileSiteKey = new \Typecho\Widget\Helper\Form\Element\Text(
        'turnstileSiteKey',
        null,
        null,
        _t('Turnstile Site Key'),
        _t('介绍：填写 Cloudflare Turnstile 提供的 Site Key（仅 Turnstile 验证方式需要）')
    );
    $turnstileSiteKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileSiteKey);

    $turnstileSecretKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'turnstileSecretKey',
        null,
        null,
        _t('Turnstile Secret Key'),
        _t('介绍：填写 Cloudflare Turnstile 提供的 Secret Key（仅 Turnstile 验证方式需要）')
    );
    $turnstileSecretKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileSecretKey);

    $geetestCaptchaId = new \Typecho\Widget\Helper\Form\Element\Text(
        'geetestCaptchaId',
        null,
        null,
        _t('极验 Captcha ID'),
        _t('介绍：填写极验 Geetest v4 后台的 Captcha ID（仅极验验证方式需要）')
    );
    $geetestCaptchaId->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($geetestCaptchaId);

    $geetestCaptchaKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'geetestCaptchaKey',
        null,
        null,
        _t('极验 Captcha Key'),
        _t('介绍：填写极验 Geetest v4 后台的 Captcha Key（服务器端校验用，仅极验验证方式需要）')
    );
    $geetestCaptchaKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($geetestCaptchaKey);

    $captchaLength = new \Typecho\Widget\Helper\Form\Element\Radio(
        'captchaLength',
        array('4' => _t('4位'), '5' => _t('5位'), '6' => _t('6位')),
        '4',
        _t('图片验证码位数'),
        _t('介绍：选择图片验证码的字符位数（仅图片验证码方式有效）')
    );
    $captchaLength->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($captchaLength);

    // ===== 功能增强配置 =====
    $codeHighlightEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'codeHighlightEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('代码高亮（Prism.js）'),
        _t('介绍：开启后，文章中的代码块将使用 Prism.js 进行语法高亮渲染<br>关闭后，代码块将以纯文本形式显示')
    );
    $codeHighlightEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($codeHighlightEnabled);

    $mermaidEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'mermaidEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('Mermaid 图表渲染'),
        _t('介绍：开启后，支持在文章中使用 ```mermaid 代码块渲染流程图、时序图、甘特图等<br>使用方法：在代码块标记后加上 mermaid，例如：<br>```mermaid<br>graph TD<br>&nbsp;&nbsp;&nbsp;&nbsp;A[开始] --> B[结束]<br>```<br>支持所有 Mermaid 官方图表类型')
    );
    $mermaidEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($mermaidEnabled);

    $echartsEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'echartsEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('ECharts 图表渲染'),
        _t('介绍：开启后，支持在文章中使用 ```echarts 代码块渲染 ECharts 图表<br>使用方法：在代码块标记后加上 echarts，代码内容为标准 ECharts option JSON 配置<br>例如：```echarts<br>{"xAxis":{"type":"category","data":["A","B","C"]},"yAxis":{"type":"value"},"series":[{"data":[120,200,150],"type":"bar"}]}<br>```<br>支持所有 ECharts 官方图表类型和配置参数')
    );
    $echartsEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($echartsEnabled);

    $katexEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'katexEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('KaTeX 数学公式渲染'),
        _t('介绍：开启后，支持在文章中渲染数学公式<br>行内公式：使用 $...$ 包裹，例如 $E=mc^2$<br>块级公式：使用 $$...$$ 包裹，例如：<br>$$<br>\\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}<br>$$<br>支持常见数学符号和公式结构')
    );
    $katexEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($katexEnabled);

    $markdownExtEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'markdownExtEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('Markdown 扩展语法'),
        _t('介绍：开启后，支持以下扩展 Markdown 语法：<br><br>' .
            '<b>1. 图片大小</b>：在 alt 文本后加 |宽x高，例如 <code>![描述|300x200](url)</code> 或 <code>![描述|50%](url)</code><br>' .
            '<b>2. 图片对齐</b>：在 alt 文本后加 #对齐方式，例如 <code>![描述#center](url)</code> 支持 left/center/right<br>' .
            '<b>3. 图片标题</b>：alt 文本自动显示为图片下方标题（figcaption）<br>' .
            '<b>4. 高亮文本</b>：使用 ==包裹==，例如 <code>==高亮内容==</code><br>' .
            '<b>5. 任务列表</b>：<code>- [x] 已完成</code> 和 <code>- [ ] 未完成</code><br>' .
            '<b>6. 提示框</b>：引用块首行写 <code>[!tip]</code>，支持 tip/note/info/warning/danger<br>' .
            '&nbsp;&nbsp;&nbsp;&nbsp;例如：<code>> [!tip] 提示标题</code><br>' .
            '<b>7. 折叠区块</b>：引用块首行写 <code>[details:标题]</code><br>' .
            '&nbsp;&nbsp;&nbsp;&nbsp;例如：<code>> [details:点击展开]</code><br>' .
            '<b>8. 图片懒加载</b>：自动为所有图片添加 loading="lazy"')
    );
    $markdownExtEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($markdownExtEnabled);

    // ===== 导航增强配置 =====
    $customNavItems = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'customNavItems',
        null,
        null,
        _t('自定义导航项'),
        _t('介绍：每行一个导航项，格式：图标类名|名称|链接<br>图标使用 Font Awesome 4.7 图标类名，例如：<br>fa-book|我的项目|https://example.com/projects<br>fa-download|资源下载|https://example.com/download<br>留空则不显示自定义导航')
    );
    $customNavItems->setAttribute('class', 'typecho-option cat-group-nav');
    $form->addInput($customNavItems);

    $categoryIcons = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'categoryIcons',
        null,
        null,
        _t('分类目录图标设置'),
        _t('介绍：为分类目录设置自定义图标，通过分类缩略名匹配<br>每行一个，格式：分类缩略名|图标类名<br>例如：<br>tech|fa-laptop<br>life|fa-coffee<br>code|fa-code<br>未设置的分类将使用默认图标 fa-folder-open-o')
    );
    $categoryIcons->setAttribute('class', 'typecho-option cat-group-nav');
    $form->addInput($categoryIcons);

    $guestbookEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'guestbookEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('留言板功能'),
        _t('介绍：开启后，在左侧导航栏添加留言板入口，用户可以在留言板页面留言<br>留言功能基于 Typecho 评论系统实现，需要先创建一个独立页面并选择"留言板"模板')
    );
    $guestbookEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($guestbookEnabled);

    $guestbookPageId = new \Typecho\Widget\Helper\Form\Element\Text(
        'guestbookPageId',
        null,
        null,
        _t('留言板页面ID'),
        _t('介绍：填写留言板独立页面的ID（在后台页面管理中查看）<br>如果留空，将尝试自动查找使用留言板模板的页面')
    );
    $guestbookPageId->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($guestbookPageId);

    $githubUsername = new \Typecho\Widget\Helper\Form\Element\Text(
        'githubUsername',
        null,
        null,
        _t('GitHub 用户名'),
        _t('介绍：填写 GitHub 用户名，保存后可在下方获取项目列表并选择展示的项目<br>留空则不显示 GitHub 项目页面入口')
    );
    $githubUsername->setAttribute('class', 'typecho-option cat-group-nav');
    $form->addInput($githubUsername);

    $githubCacheTime = new \Typecho\Widget\Helper\Form\Element\Text(
        'githubCacheTime',
        null,
        '3600',
        _t('GitHub 项目缓存时间（秒）'),
        _t('介绍：GitHub API 请求结果的缓存时间，默认 3600 秒（1小时）<br>建议设置 1800-7200 秒，避免频繁请求 API 导致限流')
    );
    $githubCacheTime->setAttribute('class', 'typecho-option cat-group-nav');
    $form->addInput($githubCacheTime);

    $githubSelectedRepos = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'githubSelectedRepos',
        null,
        null,
        _t('展示的 GitHub 项目'),
        _t('介绍：点击下方"获取项目列表"按钮加载项目，勾选需要展示的项目<br>如果不选择任何项目，则展示全部公开项目')
    );
    $githubSelectedRepos->setAttribute('class', 'typecho-option cat-group-nav');
    $form->addInput($githubSelectedRepos);

    /* ==================== 图片存储设置 ==================== */
    $storageEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'shufeiStorageEnabled',
        array('on' => _t('启用'), 'off' => _t('关闭')),
        'off',
        _t('图片存储功能'),
        _t('开启后，文章编辑器中的图片上传将通过下方配置的存储后端处理；非图片文件仍走 Typecho 原生逻辑。')
    );
    $storageEnabled->setAttribute('class', 'typecho-option cat-group-storage');
    $form->addInput($storageEnabled);

    $storageProfiles = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'shufeiStorageProfiles',
        null,
        '[]',
        _t('存储 Profile 配置（JSON）'),
        _t('此字段由下方可视化管理界面自动维护，请勿手动修改。')
    );
    $storageProfiles->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-profiles-field');
    $form->addInput($storageProfiles);

    $storageActive = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageActiveProfile',
        null,
        '',
        _t('当前激活的 Profile ID'),
        _t('点击下方 Profile 卡片上的"设为激活"按钮自动填充。')
    );
    $storageActive->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-active-field');
    $form->addInput($storageActive);

    // 图片处理：压缩
    $storageCompress = new \Typecho\Widget\Helper\Form\Element\Radio(
        'shufeiStorageCompress',
        array('on' => _t('启用'), 'off' => _t('关闭')),
        'off',
        _t('图片压缩'),
        _t('上传时自动有损压缩 JPEG/PNG，可显著减小文件体积。')
    );
    $storageCompress->setAttribute('class', 'typecho-option cat-group-storage');
    $form->addInput($storageCompress);

    $storageCompressQuality = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageCompressQuality',
        null,
        '80',
        _t('压缩质量 (1-100)'),
        _t('JPEG 质量，推荐 75-85。WebP 也会使用此质量参数。')
    );
    $storageCompressQuality->setAttribute('class', 'typecho-option cat-group-storage');
    $form->addInput($storageCompressQuality);

    // 图片处理：WebP
    $storageWebp = new \Typecho\Widget\Helper\Form\Element\Radio(
        'shufeiStorageWebp',
        array('on' => _t('启用'), 'off' => _t('关闭')),
        'off',
        _t('自动转 WebP'),
        _t('上传 JPEG/PNG 时自动转为 WebP 格式（需服务器 GD 库支持 WebP）。')
    );
    $storageWebp->setAttribute('class', 'typecho-option cat-group-storage');
    $form->addInput($storageWebp);

    // 图片处理：水印
    $storageWatermark = new \Typecho\Widget\Helper\Form\Element\Radio(
        'shufeiStorageWatermark',
        array('on' => _t('启用'), 'off' => _t('关闭')),
        'off',
        _t('图片水印'),
        _t('上传时自动添加水印（支持文字或图片水印）。仅对 JPEG/PNG 生效，GIF/WEBP 不加水印。')
    );
    $storageWatermark->setAttribute('class', 'typecho-option cat-group-storage');
    $form->addInput($storageWatermark);

    $storageWatermarkType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'shufeiStorageWatermarkType',
        array('text' => _t('文字水印'), 'image' => _t('图片水印')),
        'text',
        _t('水印类型'),
        _t('文字水印使用 GD 内置字体或 TTF 字体；图片水印需提供水印图片路径。')
    );
    $storageWatermarkType->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-type');
    $form->addInput($storageWatermarkType);

    $storageWatermarkText = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageWatermarkText',
        null,
        '',
        _t('水印文字内容'),
        _t('支持中英文，建议不超过 20 字。')
    );
    $storageWatermarkText->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-text');
    $form->addInput($storageWatermarkText);

    $storageWatermarkImage = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageWatermarkImage',
        null,
        '',
        _t('水印图片路径'),
        _t('PNG 图片路径，相对网站根目录（如 usr/themes/ShuFeiCat/static/wm.png）或绝对路径。建议使用带透明度的 PNG。')
    );
    $storageWatermarkImage->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-image');
    $form->addInput($storageWatermarkImage);

    $storageWatermarkPosition = new \Typecho\Widget\Helper\Form\Element\Select(
        'shufeiStorageWatermarkPosition',
        array(
            'tl' => _t('左上角'), 'tc' => _t('顶部居中'), 'tr' => _t('右上角'),
            'ml' => _t('左侧居中'), 'mc' => _t('正中'), 'mr' => _t('右侧居中'),
            'bl' => _t('左下角'), 'bc' => _t('底部居中'), 'br' => _t('右下角'),
        ),
        'br',
        _t('水印位置'),
        _t('水印在图片上的位置。')
    );
    $storageWatermarkPosition->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-position');
    $form->addInput($storageWatermarkPosition);

    $storageWatermarkOpacity = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageWatermarkOpacity',
        null,
        '50',
        _t('水印透明度 (0-100)'),
        _t('0 完全透明，100 完全不透明。仅对图片水印生效。')
    );
    $storageWatermarkOpacity->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-opacity');
    $form->addInput($storageWatermarkOpacity);

    $storageWatermarkSize = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageWatermarkSize',
        null,
        '16',
        _t('水印字体大小 (px)'),
        _t('文字水印的字体大小。需配合 TTF 字体使用。')
    );
    $storageWatermarkSize->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-size');
    $form->addInput($storageWatermarkSize);

    $storageWatermarkColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageWatermarkColor',
        null,
        '#FFFFFF',
        _t('水印颜色'),
        _t('十六进制颜色值，如 #FFFFFF（白）或 #000000（黑）。需配合 TTF 字体使用。')
    );
    $storageWatermarkColor->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-color');
    $form->addInput($storageWatermarkColor);

    $storageWatermarkFont = new \Typecho\Widget\Helper\Form\Element\Text(
        'shufeiStorageWatermarkFont',
        null,
        '',
        _t('水印 TTF 字体路径（可选）'),
        _t('留空则使用 GD 内置位图字体（不支持中文）。中文字体可填如 usr/themes/ShuFeiCat/static/msyh.ttf。')
    );
    $storageWatermarkFont->setAttribute('class', 'typecho-option cat-group-storage shufei-storage-wm-font');
    $form->addInput($storageWatermarkFont);

    // 输出 Profile 管理 UI
    shufei_render_storage_profile_ui();
    // 输出图片管理 UI
    shufei_render_storage_images_ui();
}

/**
 * 渲染图片存储 Profile 可视化管理界面（HTML + JS）
 *
 * - 隐藏的 textarea (shufeiStorageProfiles) 与 text (shufeiStorageActiveProfile) 由本界面读写
 * - 提供：新增 / 编辑 / 删除 / 测试连接 / 设为激活
 */
function shufei_render_storage_profile_ui()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $ajaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/storage-ajax.php', $options->siteUrl);
    $drivers = ShufeiStorageDriver::driverList();

    // 字段定义（用于动态渲染配置表单）
    $driverFields = array();
    foreach ($drivers as $id => $name) {
        $drv = ShufeiStorageDriver::factory($id);
        if ($drv) {
            $driverFields[$id] = $drv->configFields();
        }
    }
    $driversJson = json_encode($drivers);
    $fieldsJson = json_encode($driverFields);
    ?>
<style>
.shufei-storage-wrap { padding: 0; }
.shufei-storage-card {
    border: 1px solid #e5e5e5; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px;
    background: #fafbfc; transition: all .2s;
}
.shufei-storage-card.active { border-color: #52c41a; background: #f6ffed; }
.shufei-storage-card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.shufei-storage-card-title { font-weight:bold; color:#333; font-size:14px; }
.shufei-storage-card-driver { color:#888; font-size:12px; margin-left:8px; }
.shufei-storage-card-actions { display:flex; gap:6px; flex-wrap:wrap; }
.shufei-storage-btn {
    display:inline-block; padding:4px 10px; font-size:12px; border-radius:4px; cursor:pointer;
    border:1px solid #d9d9d9; background:#fff; color:#595959; transition:all .2s;
}
.shufei-storage-btn:hover { border-color:#467B96; color:#467B96; }
.shufei-storage-btn.primary { background:#467B96; color:#fff; border-color:#467B96; }
.shufei-storage-btn.primary:hover { background:#3a6478; }
.shufei-storage-btn.danger { background:#fff; color:#cf1322; border-color:#ffa39e; }
.shufei-storage-btn.danger:hover { background:#fff1f0; }
.shufei-storage-btn.success { background:#52c41a; color:#fff; border-color:#52c41a; }
.shufei-storage-btn.success:hover { background:#389e0d; }
.shufei-storage-badge { display:inline-block; padding:2px 8px; font-size:11px; border-radius:10px; background:#52c41a; color:#fff; margin-left:6px; }
.shufei-storage-add-btn {
    padding:8px 20px; background:#467B96; color:#fff; border:none; border-radius:6px; cursor:pointer;
    font-size:13px; font-weight:600; transition:all .2s;
}
.shufei-storage-add-btn:hover { background:#3a6478; transform:translateY(-1px); }
.shufei-storage-modal-mask {
    position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.45); z-index:10000; display:none;
}
.shufei-storage-modal {
    position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    width:640px; max-width:92vw; max-height:85vh; background:#fff; border-radius:8px;
    box-shadow:0 8px 32px rgba(0,0,0,0.25); z-index:10001; display:none; flex-direction:column; overflow:hidden;
}
.shufei-storage-modal.show, .shufei-storage-modal-mask.show { display:flex; }
.shufei-storage-modal-header {
    padding:12px 16px; background:#467B96; color:#fff; font-weight:bold; font-size:14px;
    display:flex; justify-content:space-between; align-items:center;
}
.shufei-storage-modal-close { cursor:pointer; font-size:18px; line-height:1; }
.shufei-storage-modal-body { padding:16px 20px; overflow-y:auto; flex:1; }
.shufei-storage-modal-footer { padding:12px 16px; border-top:1px solid #f0f0f0; text-align:right; }
.shufei-storage-field { margin-bottom:14px; }
.shufei-storage-field label { display:block; font-weight:bold; margin-bottom:6px; color:#333; font-size:13px; }
.shufei-storage-field input, .shufei-storage-field select, .shufei-storage-field textarea {
    width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:13px;
}
.shufei-storage-field select { height:auto !important; padding-right:30px !important; line-height:normal !important; }
.shufei-storage-field .desc { color:#999; font-size:12px; margin-top:4px; line-height:1.6; }
.shufei-storage-test-status {
    margin-top:10px; padding:10px 12px; border-radius:4px; font-size:12px; display:none; line-height:1.6;
}
.shufei-storage-test-status.show { display:block; }
.shufei-storage-test-status.success { background:#f6ffed; border:1px solid #b7eb8f; color:#389e0d; }
.shufei-storage-test-status.error { background:#fff2f0; border:1px solid #ffccc7; color:#cf1322; }
.shufei-storage-wm-image-field, .shufei-storage-wm-text-field { display:none; }
</style>

<div class="typecho-option cat-group-storage shufei-storage-wrap">
    <div style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-weight:bold; color:#333; font-size:14px;">存储 Profile 列表</div>
        <button type="button" class="shufei-storage-add-btn" id="shufei-storage-add-btn">+ 新建 Profile</button>
    </div>
    <div id="shufei-storage-list"></div>
    <div id="shufei-storage-empty" style="text-align:center; padding:30px 0; color:#999; font-size:13px; display:none;">
        暂无存储 Profile，点击右上角"新建 Profile"添加。
    </div>
</div>

<div class="shufei-storage-modal-mask" id="shufei-storage-mask"></div>
<div class="shufei-storage-modal" id="shufei-storage-modal">
    <div class="shufei-storage-modal-header">
        <span id="shufei-storage-modal-title">新建 Profile</span>
        <span class="shufei-storage-modal-close" id="shufei-storage-modal-close">×</span>
    </div>
    <div class="shufei-storage-modal-body">
        <div class="shufei-storage-field">
            <label>Profile 名称</label>
            <input type="text" id="shufei-storage-profile-name" placeholder="例如：阿里云OSS-主站">
            <div class="desc">便于识别的名称，可重复。</div>
        </div>
        <div class="shufei-storage-field">
            <label>存储驱动</label>
            <select id="shufei-storage-profile-driver"></select>
            <div class="desc" id="shufei-storage-driver-desc"></div>
        </div>
        <div id="shufei-storage-config-fields"></div>
        <div class="shufei-storage-test-status" id="shufei-storage-test-status"></div>
    </div>
    <div class="shufei-storage-modal-footer">
        <button type="button" class="shufei-storage-btn" id="shufei-storage-test-btn">测试并上传</button>
        <button type="button" class="shufei-storage-btn primary" id="shufei-storage-save-btn">保存</button>
        <button type="button" class="shufei-storage-btn" id="shufei-storage-cancel-btn">取消</button>
    </div>
</div>

<script>
(function(){
    if (window.__shufeiStorageInit) return;
    window.__shufeiStorageInit = true;

    var DRIVERS = <?php echo $driversJson; ?>;
    var DRIVER_FIELDS = <?php echo $fieldsJson; ?>;
    var AJAX_URL = <?php echo json_encode($ajaxUrl); ?>;

    function $(id){ return document.getElementById(id); }

    function getProfiles(){
        var ta = document.querySelector('.shufei-storage-profiles-field textarea');
        if (!ta) return [];
        try {
            var v = JSON.parse(ta.value || '[]');
            return Array.isArray(v) ? v : [];
        } catch(e){ return []; }
    }
    function setProfiles(arr){
        var ta = document.querySelector('.shufei-storage-profiles-field textarea');
        if (ta) ta.value = JSON.stringify(arr, null, 2);
    }
    function getActiveId(){
        var inp = document.querySelector('.shufei-storage-active-field input');
        return inp ? inp.value : '';
    }
    function setActiveId(id){
        var inp = document.querySelector('.shufei-storage-active-field input');
        if (inp) inp.value = id;
    }

    function genId(){
        return 'p_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2,6);
    }

    function escapeHtml(s){
        s = (s === null || s === undefined) ? '' : String(s);
        return s.replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function renderList(){
        var list = getProfiles();
        var activeId = getActiveId();
        var wrap = $('shufei-storage-list');
        var empty = $('shufei-storage-empty');
        wrap.innerHTML = '';
        if (list.length === 0) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';
        list.forEach(function(p){
            var card = document.createElement('div');
            card.className = 'shufei-storage-card' + (p.id === activeId ? ' active' : '');
            var isActive = p.id === activeId;
            var driverName = DRIVERS[p.driver] || p.driver;
            var html = '<div class="shufei-storage-card-head">';
            html += '<div><span class="shufei-storage-card-title">' + escapeHtml(p.name) + '</span>';
            html += '<span class="shufei-storage-card-driver">' + escapeHtml(driverName) + '</span>';
            if (isActive) html += '<span class="shufei-storage-badge">已激活</span>';
            html += '</div>';
            html += '<div class="shufei-storage-card-actions">';
            if (!isActive) {
                html += '<button type="button" class="shufei-storage-btn success" data-act="activate" data-id="' + escapeHtml(p.id) + '">设为激活</button>';
            }
            html += '<button type="button" class="shufei-storage-btn" data-act="edit" data-id="' + escapeHtml(p.id) + '">编辑</button>';
            html += '<button type="button" class="shufei-storage-btn" data-act="test" data-id="' + escapeHtml(p.id) + '">测试</button>';
            html += '<button type="button" class="shufei-storage-btn danger" data-act="delete" data-id="' + escapeHtml(p.id) + '">删除</button>';
            html += '</div></div>';
            card.innerHTML = html;
            wrap.appendChild(card);
        });
    }

    // 事件委托
    $('shufei-storage-list').addEventListener('click', function(e){
        var btn = e.target.closest('button[data-act]');
        if (!btn) return;
        var act = btn.getAttribute('data-act');
        var id = btn.getAttribute('data-id');
        var profiles = getProfiles();
        var p = null;
        for (var i = 0; i < profiles.length; i++) {
            if (profiles[i].id === id) { p = profiles[i]; break; }
        }
        if (!p) return;
        if (act === 'activate') {
            setActiveId(id);
            renderList();
        } else if (act === 'edit') {
            openModal(p);
        } else if (act === 'delete') {
            if (!confirm('确定删除 Profile "' + p.name + '" 吗？')) return;
            var newArr = profiles.filter(function(x){ return x.id !== id; });
            setProfiles(newArr);
            if (getActiveId() === id) setActiveId('');
            renderList();
        } else if (act === 'test') {
            testProfile(p);
        }
    });

    // 模态框
    var editingId = null;
    function openModal(p){
        editingId = p ? p.id : null;
        $('shufei-storage-modal-title').textContent = p ? '编辑 Profile' : '新建 Profile';
        $('shufei-storage-profile-name').value = p ? (p.name || '') : '';
        var driverSel = $('shufei-storage-profile-driver');
        driverSel.innerHTML = '';
        for (var did in DRIVERS) {
            var opt = document.createElement('option');
            opt.value = did; opt.textContent = DRIVERS[did];
            driverSel.appendChild(opt);
        }
        if (p && p.driver) driverSel.value = p.driver;
        renderConfigFields(p ? (p.config || {}) : {});
        $('shufei-storage-test-status').className = 'shufei-storage-test-status';
        $('shufei-storage-test-status').textContent = '';
        $('shufei-storage-mask').classList.add('show');
        $('shufei-storage-modal').classList.add('show');
        updateDriverDesc();
    }
    function closeModal(){
        $('shufei-storage-mask').classList.remove('show');
        $('shufei-storage-modal').classList.remove('show');
        editingId = null;
    }

    function renderConfigFields(existingConfig){
        var driverId = $('shufei-storage-profile-driver').value;
        var fields = DRIVER_FIELDS[driverId] || [];
        var wrap = $('shufei-storage-config-fields');
        wrap.innerHTML = '';
        fields.forEach(function(f){
            var div = document.createElement('div');
            div.className = 'shufei-storage-field';
            var label = document.createElement('label');
            label.textContent = f.label;
            div.appendChild(label);
            var input;
            var val = (existingConfig && existingConfig[f.name] !== undefined) ? existingConfig[f.name] : (f.default || '');
            if (f.type === 'select') {
                input = document.createElement('select');
                (f.options || []).forEach(function(opt){
                    var o = document.createElement('option');
                    o.value = opt.value; o.textContent = opt.label;
                    if (String(val) === String(opt.value)) o.selected = true;
                    input.appendChild(o);
                });
            } else if (f.type === 'password') {
                input = document.createElement('input');
                input.type = 'password';
                input.value = val;
            } else if (f.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3; input.value = val;
            } else {
                input = document.createElement('input');
                input.type = 'text'; input.value = val;
            }
            input.name = 'cfg_' + f.name;
            input.setAttribute('data-field', f.name);
            div.appendChild(input);
            if (f.desc) {
                var d = document.createElement('div');
                d.className = 'desc'; d.innerHTML = f.desc;
                div.appendChild(d);
            }
            wrap.appendChild(div);
        });
    }

    function updateDriverDesc(){
        var driverId = $('shufei-storage-profile-driver').value;
        var desc = {
            local: '本地存储：图片保存至 usr/uploads/，遵循 Typecho 原生目录结构。',
            lsky: 'Lsky Pro 兰空图床：支持 v1（/api/upload）与 v2（/api/v1/）接口。',
            s3: 'AWS S3 兼容：AWS S3、MinIO、Cloudflare R2、阿里云 OSS（S3 兼容模式）。',
            webdav: 'WebDAV：标准 WebDAV 协议，支持 Nextcloud / 坚果云 / 群晖等。',
            aliyunoss: '阿里云 OSS：使用 V1 签名直传，需提供 Endpoint/Bucket/AccessKey。',
            tencentcos: '腾讯云 COS：使用 COS V5 签名直传。',
            qiniukodo: '七牛云 KODO：使用管理 AccessToken 签名直传。',
            upyun: '又拍云 USS：使用 HMAC-SHA1 签名直传。',
            catimg: '小猫咪图床：通过 X-API-Key 认证上传至 img.czzu.cn 或自建实例，支持 jpg/png/gif/webp/svg。'
        };
        $('shufei-storage-driver-desc').textContent = desc[driverId] || '';
    }

    $('shufei-storage-profile-driver').addEventListener('change', function(){
        renderConfigFields({});
        updateDriverDesc();
    });
    $('shufei-storage-add-btn').addEventListener('click', function(){ openModal(null); });
    $('shufei-storage-modal-close').addEventListener('click', closeModal);
    $('shufei-storage-cancel-btn').addEventListener('click', closeModal);
    $('shufei-storage-mask').addEventListener('click', closeModal);

    function collectConfig(){
        var cfg = {};
        var inputs = $('shufei-storage-config-fields').querySelectorAll('[data-field]');
        inputs.forEach(function(el){
            cfg[el.getAttribute('data-field')] = el.value;
        });
        return cfg;
    }

    $('shufei-storage-save-btn').addEventListener('click', function(){
        var name = $('shufei-storage-profile-name').value.trim();
        var driver = $('shufei-storage-profile-driver').value;
        if (!name) { alert('请填写 Profile 名称'); return; }
        var cfg = collectConfig();
        var profiles = getProfiles();
        if (editingId) {
            for (var i = 0; i < profiles.length; i++) {
                if (profiles[i].id === editingId) {
                    profiles[i].name = name;
                    profiles[i].driver = driver;
                    profiles[i].config = cfg;
                    break;
                }
            }
        } else {
            var newP = { id: genId(), name: name, driver: driver, config: cfg };
            profiles.push(newP);
            // 若是第一个，自动激活
            if (profiles.length === 1) setActiveId(newP.id);
        }
        setProfiles(profiles);
        renderList();
        closeModal();
    });

    function showTestStatus(msg, type){
        var el = $('shufei-storage-test-status');
        el.className = 'shufei-storage-test-status show ' + type;
        el.textContent = msg;
    }

    $('shufei-storage-test-btn').addEventListener('click', function(){
        var driver = $('shufei-storage-profile-driver').value;
        var cfg = collectConfig();
        showTestStatus('正在测试连接并上传测试图片，请稍候...', '');
        var fd = new FormData();
        fd.append('action', 'test_connection');
        fd.append('driver', driver);
        fd.append('config', JSON.stringify(cfg));
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success) {
                    showTestStatus(d.message || '✓ 连接成功', 'success');
                } else {
                    showTestStatus(d.message || '连接失败', 'error');
                }
            })
            .catch(function(e){ showTestStatus('请求失败: ' + e.message, 'error'); });
    });

    function testProfile(p){
        if (!p) return;
        if (!confirm('测试 Profile "' + p.name + '"？将上传一张测试图片并自动删除。')) return;
        var fd = new FormData();
        fd.append('action', 'test_connection');
        fd.append('driver', p.driver);
        fd.append('config', JSON.stringify(p.config || {}));
        alert('正在测试 ' + p.name + '，请稍候...');
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                alert((d.success ? '✓ ' : '✗ ') + (d.message || (d.success ? '连接成功' : '连接失败')));
            })
            .catch(function(e){ alert('请求失败: ' + e.message); });
    }

    // 水印类型切换
    function updateWatermarkFields(){
        var wmOn = document.querySelector('input[name=shufeiStorageWatermark]:checked');
        wmOn = wmOn && wmOn.value === 'on';
        var wmType = document.querySelector('input[name=shufeiStorageWatermarkType]:checked');
        wmType = wmType ? wmType.value : 'text';
        document.querySelectorAll('.shufei-storage-wm-text, .shufei-storage-wm-image, .shufei-storage-wm-type, .shufei-storage-wm-color, .shufei-storage-wm-size, .shufei-storage-wm-font, .shufei-storage-wm-position, .shufei-storage-wm-opacity').forEach(function(el){
            // 全部按水印总开关控制
            if (!wmOn) { el.style.display = 'none'; return; }
            // 文字字段与图片字段按类型切换
            if (el.classList.contains('shufei-storage-wm-image') && wmType !== 'image') { el.style.display = 'none'; return; }
            if ((el.classList.contains('shufei-storage-wm-text') || el.classList.contains('shufei-storage-wm-color') || el.classList.contains('shufei-storage-wm-size') || el.classList.contains('shufei-storage-wm-font')) && wmType !== 'text') { el.style.display = 'none'; return; }
            el.style.display = '';
        });
    }
    document.querySelectorAll('input[name=shufeiStorageWatermark], input[name=shufeiStorageWatermarkType]').forEach(function(r){
        r.addEventListener('change', updateWatermarkFields);
    });

    // 在页面加载完成后初始化（等待 Tab 渲染完成）
    window.addEventListener('load', function(){
        // 显示 storage 字段（cat-group-storage 已被 Tab 系统处理）
        updateWatermarkFields();
        renderList();
    })();
})();
</script>
    <?php
}

/**
 * 渲染图片存储「图片管理」面板（分页列表 + 删除）
 * 在后台设置页 Profile 管理 UI 之后输出
 */
function shufei_render_storage_images_ui()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $ajaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/storage-ajax.php', $options->siteUrl);

    // 当前已保存的 Profile 列表（用于下拉选择）
    $profilesJson = isset($options->shufeiStorageProfiles) ? $options->shufeiStorageProfiles : '';
    $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
    if (!is_array($profiles)) $profiles = array();
    $activeId = isset($options->shufeiStorageActiveProfile) ? $options->shufeiStorageActiveProfile : '';

    $drivers = ShufeiStorageDriver::driverList();
    ?>
<div class="typecho-option cat-group-storage" style="margin-top:25px;border-top:1px solid #eee;padding-top:20px;">
    <section class="typecho-page-title">
        <h2>图片管理</h2>
        <p class="description">查看已上传到各存储 Profile 的图片，支持删除。注意：删除操作不可恢复。</p>
    </section>

    <div style="display:flex;align-items:center;gap:10px;margin:15px 0;flex-wrap:wrap;">
        <label>选择 Profile：</label>
        <select id="shufei-img-profile" style="min-width:240px;height:auto !important;padding:8px 30px 8px 12px !important;line-height:normal !important;box-sizing:border-box;">
            <?php foreach ($profiles as $p): ?>
                <option value="<?php echo htmlspecialchars($p['id']); ?>" <?php if ($p['id'] === $activeId) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($p['name'] . ' [' . (isset($drivers[$p['driver']]) ? $drivers[$p['driver']] : $p['driver']) . ']'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="shufei-img-refresh" class="btn primary">加载图片</button>
        <span id="shufei-img-status" style="color:#999;font-size:13px;"></span>
    </div>

    <div id="shufei-img-toolbar" style="display:flex;align-items:center;gap:10px;margin:10px 0;">
        <button type="button" id="shufei-img-prev" class="btn">&laquo; 上一页</button>
        <span id="shufei-img-page-info" style="font-size:13px;color:#666;">-</span>
        <button type="button" id="shufei-img-next" class="btn">下一页 &raquo;</button>
        <span style="margin-left:auto;font-size:12px;color:#999;">点击图片复制 URL；点击右上角 &times; 删除</span>
    </div>

    <div id="shufei-img-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;min-height:120px;">
        <div style="grid-column:1/-1;color:#999;text-align:center;padding:30px;">请点击「加载图片」查看</div>
    </div>
</div>

<script>
(function(){
    var ajaxUrl = <?php echo json_encode($ajaxUrl); ?>;
    var grid = document.getElementById('shufei-img-grid');
    var sel = document.getElementById('shufei-img-profile');
    var statusEl = document.getElementById('shufei-img-status');
    var pageInfo = document.getElementById('shufei-img-page-info');
    var prevBtn = document.getElementById('shufei-img-prev');
    var nextBtn = document.getElementById('shufei-img-next');
    var page = 1, limit = 24, total = 0;

    function status(msg, color){
        statusEl.textContent = msg;
        statusEl.style.color = color || '#999';
    }

    function humanSize(b){
        if (!b) return '0 B';
        if (b < 1024) return b + ' B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
        return (b/1024/1024).toFixed(2) + ' MB';
    }

    function load(){
        var pid = sel.value;
        if (!pid){ status('请先创建并选择 Profile', '#c00'); return; }
        status('加载中...', '#999');
        grid.innerHTML = '<div style="grid-column:1/-1;color:#999;text-align:center;padding:30px;">加载中...</div>';
        var fd = new FormData();
        fd.append('action', 'list_images');
        fd.append('profile_id', pid);
        fd.append('page', page);
        fd.append('limit', limit);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                if (!res || !res.success){
                    status(res && res.message ? res.message : '加载失败', '#c00');
                    grid.innerHTML = '<div style="grid-column:1/-1;color:#c00;text-align:center;padding:30px;">'+(res&&res.message?res.message:'加载失败')+'</div>';
                    return;
                }
                var data = res.data || {};
                total = data.total || 0;
                var list = data.list || [];
                status('共 ' + total + ' 张图片', '#080');
                pageInfo.textContent = '第 ' + page + '/' + Math.max(1, Math.ceil(total/limit)) + ' 页';
                if (!list.length){
                    grid.innerHTML = '<div style="grid-column:1/-1;color:#999;text-align:center;padding:30px;">暂无图片</div>';
                    return;
                }
                grid.innerHTML = '';
                list.forEach(function(item){
                    var cell = document.createElement('div');
                    cell.style.cssText = 'position:relative;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;background:#fafafa;';
                    var img = document.createElement('img');
                    img.src = item.url;
                    img.loading = 'lazy';
                    img.style.cssText = 'width:100%;height:140px;object-fit:cover;cursor:pointer;display:block;background:#fff;';
                    img.title = '点击复制 URL';
                    img.onerror = function(){ img.style.display='none'; cell.querySelector('.shufei-ph').style.display='flex'; };
                    img.onclick = function(){
                        if (navigator.clipboard){
                            navigator.clipboard.writeText(item.url).then(function(){ status('已复制 URL', '#080'); });
                        } else {
                            var ta = document.createElement('textarea'); ta.value = item.url; document.body.appendChild(ta); ta.select();
                            try { document.execCommand('copy'); status('已复制 URL', '#080'); } catch(e){ status('复制失败', '#c00'); }
                            document.body.removeChild(ta);
                        }
                    };
                    var ph = document.createElement('div');
                    ph.className = 'shufei-ph';
                    ph.style.cssText = 'width:100%;height:140px;display:none;align-items:center;justify-content:center;color:#999;font-size:12px;background:#f0f0f0;';
                    ph.textContent = '图片加载失败';
                    var del = document.createElement('div');
                    del.innerHTML = '&times;';
                    del.style.cssText = 'position:absolute;top:2px;right:4px;width:22px;height:22px;line-height:20px;text-align:center;background:rgba(0,0,0,0.55);color:#fff;border-radius:50%;cursor:pointer;font-size:16px;';
                    del.title = '删除该图片';
                    del.onclick = function(e){
                        e.stopPropagation();
                        if (!confirm('确定删除这张图片吗？此操作不可恢复。\n\n' + item.name)) return;
                        del.style.background = 'rgba(0,0,0,0.3)';
                        del.textContent = '...';
                        var fd2 = new FormData();
                        fd2.append('action', 'delete_image');
                        fd2.append('profile_id', pid);
                        if (item.id) fd2.append('image_id', item.id);
                        if (item.key) fd2.append('key', item.key);
                        if (item.url) fd2.append('url', item.url);
                        fetch(ajaxUrl, {method:'POST', body:fd2, credentials:'same-origin'})
                            .then(function(r){return r.json();})
                            .then(function(res2){
                                if (res2 && res2.success){
                                    status('已删除', '#080');
                                    cell.style.transition='opacity .3s'; cell.style.opacity='0';
                                    setTimeout(function(){ cell.remove(); total--; status('共 ' + total + ' 张图片', '#080'); }, 300);
                                } else {
                                    status(res2 && res2.message ? res2.message : '删除失败', '#c00');
                                    del.style.background = 'rgba(0,0,0,0.55)'; del.innerHTML='&times;';
                                }
                            })
                            .catch(function(err){
                                status('网络错误: ' + err.message, '#c00');
                                del.style.background = 'rgba(0,0,0,0.55)'; del.innerHTML='&times;';
                            });
                    };
                    var info = document.createElement('div');
                    info.style.cssText = 'padding:6px 8px;font-size:11px;color:#666;border-top:1px solid #eee;background:#fff;';
                    info.innerHTML = '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="'+(item.name||'').replace(/"/g,'&quot;')+'">'+(item.name||'')+'</div><div style="color:#999;margin-top:2px;">'+humanSize(item.size)+(item.time?' · '+item.time:'')+'</div>';
                    cell.appendChild(img);
                    cell.appendChild(ph);
                    cell.appendChild(del);
                    cell.appendChild(info);
                    grid.appendChild(cell);
                });
            })
            .catch(function(err){
                status('网络错误: ' + err.message, '#c00');
                grid.innerHTML = '<div style="grid-column:1/-1;color:#c00;text-align:center;padding:30px;">网络错误</div>';
            });
    }

    document.getElementById('shufei-img-refresh').onclick = function(){ page = 1; load(); };
    prevBtn.onclick = function(){ if (page > 1){ page--; load(); } };
    nextBtn.onclick = function(){ if (page * limit < total){ page++; load(); } };
    sel.onchange = function(){ page = 1; };
})();
</script>
    <?php
}

/**
 * 随机缩略图逻辑
 */
function shufei_get_random_thumbnail()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $thumbnailSource = isset($options->thumbnailSource) ? $options->thumbnailSource : 'local';
    
    if ($thumbnailSource === 'remote') {
        $remoteImages = isset($options->remoteImages) ? $options->remoteImages : '';
        if (!empty($remoteImages)) {
            $imageList = preg_split('/\R/', trim($remoteImages));
            if (!empty($imageList)) {
                return $imageList[array_rand($imageList)];
            }
        }
    }
    
    $siteUrl = isset($options->siteUrl) ? $options->siteUrl : '';
    $themeUrl = \Typecho\Common::url('/usr/themes/ShuFeiCat/', $siteUrl);
    $defaultImages = array('default-1.svg', 'default-2.svg', 'default-3.svg', 'default-4.svg', 'default-5.svg');
    return $themeUrl . 'image/' . $defaultImages[array_rand($defaultImages)];
}

/**
 * 后台自定义字段定义
 */
function themeFields($layout)
{
    // 修复自定义字段提示词与输入框重叠的问题
    echo '<style>' .
        '.typecho-post-option .description { clear: both; display: block; margin-top: 6px; }' .
        '</style>';

    $thumbnail = new \Typecho\Widget\Helper\Form\Element\Text('thumbnail', NULL, NULL, _t('文章缩略图'), _t('留空则自动获取文章图片或随机图片'));
    $layout->addItem($thumbnail);
    $excerpt = new \Typecho\Widget\Helper\Form\Element\Text('excerpt', NULL, NULL, _t('文章简介'), _t('留空则自动截取文章内容'));
    $layout->addItem($excerpt);
    $keywords = new \Typecho\Widget\Helper\Form\Element\Text('keywords', NULL, NULL, _t('文章关键词（SEO）'), _t('用于 SEO 关键词 meta 标签，多个关键词请用英文逗号 "," 分隔<br>示例：Typecho 主题,ShuFeiCat,SEO 优化<br>留空则自动使用全站关键词或文章标签'));
    $layout->addItem($keywords);
    $sticky = new \Typecho\Widget\Helper\Form\Element\Radio('sticky', array('0' => _t('普通文章'), '1' => _t('置顶文章')), '0', _t('文章置顶'), _t('选择置顶后，该文章将在首页顶部显示'));
    $layout->addItem($sticky);
    $articleAlert = new \Typecho\Widget\Helper\Form\Element\Textarea('articleAlert', NULL, NULL, _t('文章提示弹窗'), _t('填写后，文章页面顶部将显示提示弹窗。支持HTML。留空则不显示弹窗'));
    $layout->addItem($articleAlert);
    $disableLike = new \Typecho\Widget\Helper\Form\Element\Radio('disableLike', array('0' => _t('允许点赞'), '1' => _t('关闭点赞')), '0', _t('点赞功能控制'), _t('选择关闭点赞后，该文章将不显示点赞按钮'));
    $layout->addItem($disableLike);

    // ===== 文章投票功能 =====
    $voteQuestion = new \Typecho\Widget\Helper\Form\Element\Text('voteQuestion', NULL, NULL, _t('投票问题'), _t('填写后将在文章底部显示投票卡片。留空则不显示投票<br>示例：你觉得这篇文章对你有帮助吗？'));
    $layout->addItem($voteQuestion);

    $voteOptions = new \Typecho\Widget\Helper\Form\Element\Textarea('voteOptions', NULL, NULL, _t('投票选项'), _t('每行一个选项，至少填写 2 个选项<br>示例：<br>非常有帮助<br>一般般<br>没什么帮助'));
    $layout->addItem($voteOptions);

    $voteDeadline = new \Typecho\Widget\Helper\Form\Element\Text('voteDeadline', NULL, NULL, _t('投票截止时间'), _t('可选。格式：YYYY-MM-DD 或 YYYY-MM-DD HH:MM<br>示例：2026-12-31 23:59<br>留空表示不限制截止时间'));
    $layout->addItem($voteDeadline);
}

/* 加载核心逻辑库（去除 @ 静默加载，改为文件存在性检查） */
$_coreLibs = array(
    dirname(__FILE__) . '/core/mail.php',
    dirname(__FILE__) . '/core/ai-moderation.php',
    dirname(__FILE__) . '/core/ai-writer.php',
    dirname(__FILE__) . '/core/post-stats.php',
    dirname(__FILE__) . '/core/vote.php',
    dirname(__FILE__) . '/core/image-processor.php',
    dirname(__FILE__) . '/core/storage-drivers.php',
    dirname(__FILE__) . '/core/storage-hooks.php',
);
foreach ($_coreLibs as $_lib) {
    if (file_exists($_lib)) {
        require_once $_lib;
    } else {
        // 记录缺失文件到错误日志，便于排查
        error_log('[ShuFeiCat] 核心库缺失: ' . $_lib);
    }
}

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
 */
function shufei_turnstile_verify_curl($secretKey, $token) {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
    )));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        return json_decode($response, true);
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
    if (!function_exists('curl_init')) {
        return null;
    }
    $postFields = http_build_query(array(
        'captcha_id' => $captchaId,
        'lot_number' => $lotNumber,
        'captcha_output' => $captchaOutput,
        'pass_token' => $passToken,
        'gen_time' => $genTime,
        'sign_token' => $signToken
    ));
    $ch = curl_init('https://gcaptcha4.geetest.com/validate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

// 注册钩子（整合AI审核功能）
\Typecho\Plugin::factory('Widget_Feedback')->comment = 'shufei_comment_check';

// 注册 KaTeX 内容过滤器（防止 Markdown 破坏数学公式语法）
// handle 使用 Widget\Base\Contents，因为 ___content() 中 Contents::pluginHandle() 的 static::class 解析为该类
// nativeClassName() 会将反斜杠转为下划线，最终查找键为 Widget_Base_Contents:content
\Typecho\Plugin::factory('Widget\Base\Contents')->content = 'shufei_katex_content_filter';

// 注册 Markdown 扩展内容过滤器（在 Markdown 解析后处理扩展语法）
\Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = 'shufei_markdown_ext_filter';

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

/**
 * 获取所有置顶文章的CID
 *
 * @return array 置顶文章CID数组
 */
function shufei_get_sticky_cids()
{
    $db = \Typecho\Db::get();
    $rows = $db->fetchAll($db->select('cid')->from('table.fields')
        ->where('name = ?', 'sticky')
        ->where('str_value = ?', '1'));

    $cids = [];
    foreach ($rows as $row) {
        $cids[] = $row['cid'];
    }
    return $cids;
}

/**
 * 获取文章缩略图
 *
 * @param object $post 文章对象
 * @return string 缩略图URL
 */
function shufei_get_post_thumbnail($post)
{
    // 1. 首先检查文章自定义字段中的缩略图
    $thumbnail = $post->fields->thumbnail;
    if (!empty($thumbnail)) {
        return $thumbnail;
    }
    
    // 2. 从文章内容中提取第一张图片（支持HTML img标签）
    $content = $post->content ?? '';
    preg_match_all('/<img.*?src=["\'](.*?)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        return $matches[1][0];
    }
    
    // 3. 从文章原始文本中提取第一张图片（支持Markdown格式 ![alt](url)）
    if (!empty($post->text)) {
        // 先去掉 Typecho 的 <!--markdown--> 前缀
        $rawText = preg_replace('/^<!--markdown-->/', '', $post->text);
        // 剔除行内代码块，避免匹配到示例语法如 `![desc](url)` 中的 url
        $cleanText = preg_replace('/`[^`]*`/', '', $rawText);
        preg_match_all('/!\[.*?\]\(([^)]+)\)/', $cleanText, $mdMatches);
        if (!empty($mdMatches[1][0])) {
            return $mdMatches[1][0];
        }
    }
    
    // 4. 使用随机缩略图
    return shufei_get_random_thumbnail();
}

/**
 * 获取Gravatar头像URL
 * 
 * @param string $email 邮箱地址
 * @param int $size 头像尺寸
 * @return string 头像URL
 */
function shufei_get_gravatar_url($email, $size = 80)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $gravatarSource = isset($options->gravatarSource) ? $options->gravatarSource : 'cat';
    
    // 根据配置选择镜像源
    switch ($gravatarSource) {
        case 'loli':
            $baseUrl = 'https://gravatar.loli.net/avatar/';
            break;
        case 'weavatar':
            $baseUrl = 'https://weavatar.com/avatar/';
            break;
        case 'cravatar':
            $baseUrl = 'https://cravatar.cn/avatar/';
            break;
        case 'official':
            $baseUrl = 'https://secure.gravatar.com/avatar/';
            break;
        case 'cat':
        default:
            $baseUrl = 'https://gravatar.luoli.click/avatar/';
            break;
    }
    
    $hash = md5(strtolower(trim($email)));
    return $baseUrl . $hash . '?s=' . $size . '&d=identicon&r=g';
}

/**
 * 获取当前 Archive widget（用于检测当前页面类型与获取内容）
 *
 * @return \Typecho\Widget\Archive|null
 */
function shufei_get_archive()
{
    static $archive = null;
    if ($archive === null) {
        try {
            $archive = \Typecho\Widget::widget('Widget_Archive');
        } catch (\Exception $e) {
            $archive = false;
        }
    }
    return $archive ?: null;
}

/**
 * 当前页面是否为文章页
 */
function shufei_is_post()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('post');
}

/**
 * 当前页面是否为独立页面
 */
function shufei_is_page()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('page');
}

/**
 * 当前页面是否为分类页
 */
function shufei_is_category()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('category');
}

/**
 * 当前页面是否为标签页
 */
function shufei_is_tag()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('tag');
}

/**
 * 当前页面是否为搜索结果页
 */
function shufei_is_search()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('search');
}

/**
 * 当前页面是否为作者归档页
 */
function shufei_is_author()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('author');
}

/**
 * 当前页面是否为日期归档页
 */
function shufei_is_archive()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('archive');
}

/**
 * 当前页面是否为 404
 */
function shufei_is_404()
{
    // 模板为 404.php 时确定为 404 页面
    $template = \Typecho\Widget::widget('Widget_Options')->template;
    if ($template === '404.php') {
        return true;
    }
    // 否则通过排除法：不是任何已知页面类型时视为 404
    return !shufei_is_post() && !shufei_is_page() && !shufei_is_category()
        && !shufei_is_tag() && !shufei_is_search() && !shufei_is_author()
        && !shufei_is_archive();
}

/**
 * 净化 URL，仅允许 http/https 协议，防止 CSS/JS 注入
 * 用于在 style 属性或 src 属性中输出用户可控的 URL
 *
 * @param string $url 原始 URL
 * @return string 净化后的 URL，若协议不允许则返回空字符串
 */
function shufei_sanitize_url($url)
{
    if (empty($url)) return '';
    $url = trim($url);
    // 仅允许 http:// 和 https:// 协议；相对路径（以 / 或 ./ 开头）也允许
    if (preg_match('#^https?://#i', $url) || preg_match('#^(\./|/)#', $url)) {
        // 移除可能用于 CSS 注入的字符：() ; 以及控制字符
        $url = preg_replace('/[\x00-\x1F\x7F-\x9F\(\);]/', '', $url);
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * 截取并清理文本作为 SEO 描述
 *
 * @param string $text 原始文本
 * @param int $length 截取长度
 * @return string
 */
function shufei_seo_trim($text, $length = 120)
{
    if ($text === null) {
        $text = '';
    }
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    return htmlspecialchars($text);
}

/**
 * 获取当前页面的 SEO 关键词
 *
 * 优先级：文章自定义关键词 > 文章标签 > 全站 SEO 关键词 > 站点标题
 *
 * @return string
 */
function shufei_get_seo_keywords()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $keywords = '';

    if (shufei_is_post() || shufei_is_page()) {
        $post = shufei_get_archive();
        if ($post && !empty($post->fields->keywords)) {
            $keywords = $post->fields->keywords;
        } elseif ($post && !empty($post->tags) && is_array($post->tags)) {
            $tagNames = array();
            foreach ($post->tags as $tag) {
                if (isset($tag['name'])) {
                    $tagNames[] = $tag['name'];
                }
            }
            $keywords = implode(',', $tagNames);
        }
    }

    if (empty($keywords) && !empty($options->seoKeywords)) {
        $keywords = $options->seoKeywords;
    }

    if (empty($keywords)) {
        $keywords = $options->title ?? '';
    }

    return htmlspecialchars(trim((string)$keywords, ' ,'));
}

/**
 * 获取当前页面的 SEO 描述
 *
 * 优先级：文章自定义 excerpt > 文章内容截取 > 全站 SEO 描述 > Typecho 站点描述
 *
 * @return string
 */
function shufei_get_seo_description()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $description = '';

    if (shufei_is_post()) {
        $post = shufei_get_archive();
        if ($post && !empty($post->fields->excerpt)) {
            $description = shufei_seo_trim($post->fields->excerpt, 160);
        } elseif ($post && !empty($post->content)) {
            $description = shufei_seo_trim($post->content, 160);
        }
    } elseif (shufei_is_page()) {
        $page = shufei_get_archive();
        if ($page && !empty($page->content)) {
            $description = shufei_seo_trim($page->content, 160);
        }
    }

    if (empty($description) && !empty($options->seoDescription)) {
        $description = shufei_seo_trim($options->seoDescription, 160);
    }

    if (empty($description) && !empty($options->description)) {
        $description = shufei_seo_trim($options->description, 160);
    }

    if (empty($description)) {
        $description = htmlspecialchars($options->title ?? '', ENT_QUOTES, 'UTF-8');
    }

    return $description;
}

/**
 * 获取当前页面的 SEO 标题
 *
 * @return string
 */
function shufei_get_seo_title()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $archive = shufei_get_archive();
    $siteTitle = htmlspecialchars($options->title ?? '', ENT_QUOTES, 'UTF-8');

    if (shufei_is_post() && $archive) {
        return htmlspecialchars($archive->title ?? '', ENT_QUOTES, 'UTF-8') . ' - ' . $siteTitle;
    }

    if (shufei_is_page() && $archive) {
        return htmlspecialchars($archive->title ?? '', ENT_QUOTES, 'UTF-8') . ' - ' . $siteTitle;
    }

    if (shufei_is_category() && $archive) {
        $categoryName = $archive->name ?? ($archive->title ?? '');
        return sprintf(_t('分类 %s 下的文章'), htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8')) . ' - ' . $siteTitle;
    }

    if (shufei_is_tag() && $archive) {
        $tagName = $archive->name ?? ($archive->title ?? '');
        return sprintf(_t('标签 %s 下的文章'), htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8')) . ' - ' . $siteTitle;
    }

    if (shufei_is_search()) {
        $s = isset($_GET['s']) ? htmlspecialchars(trim($_GET['s']), ENT_QUOTES, 'UTF-8') : '';
        if (empty($s) && $archive && !empty($archive->archiveTitle)) {
            $s = htmlspecialchars($archive->archiveTitle, ENT_QUOTES, 'UTF-8');
        }
        return sprintf(_t('包含关键字 %s 的文章'), $s) . ' - ' . $siteTitle;
    }

    if (shufei_is_author() && $archive) {
        $name = !empty($archive->screenName) ? $archive->screenName : (!empty($archive->name) ? $archive->name : '');
        return sprintf(_t('%s 发布的文章'), htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . ' - ' . $siteTitle;
    }

    if (shufei_is_archive()) {
        return _t('文章归档') . ' - ' . $siteTitle;
    }

    return $siteTitle;
}

/**
 * 获取社交分享图（OG / Twitter Card）
 *
 * @param object|null $archive 当前内容对象（文章/页面）
 * @return string
 */
function shufei_get_seo_og_image($archive = null)
{
    $options = \Typecho\Widget::widget('Widget_Options');

    if ($archive && !empty($archive->fields->thumbnail)) {
        return $archive->fields->thumbnail;
    }

    if ($archive && !empty($archive->content)) {
        preg_match_all('/<img.*?src=["\'](.*?)["\']/', $archive->content, $matches);
        if (!empty($matches[1][0])) {
            return $matches[1][0];
        }
    }

    if (!empty($options->seoOgImage)) {
        return $options->seoOgImage;
    }

    return '';
}

/**
 * 获取当前页面的 canonical URL
 *
 * 覆盖范围：文章/页面/分类/标签/作者/归档/首页分页
 * 分页页面使用各自完整 URL，不被统一指向首页
 *
 * @return string
 */
function shufei_get_canonical_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $archive = shufei_get_archive();

    // 文章/独立页面：使用 permalink
    if (shufei_is_post() || shufei_is_page()) {
        if ($archive && !empty($archive->permalink)) {
            return $archive->permalink;
        }
    }

    // 列表型页面：使用 archive 路由生成的 permalink（含分页参数）
    if ($archive && method_exists($archive, 'permalink') && !shufei_is_404() && !shufei_is_search()) {
        try {
            $permalink = $archive->permalink;
            if (!empty($permalink)) {
                return $permalink;
            }
        } catch (\Exception $e) {}
    }

    // 兜底使用站点首页
    return $options->siteUrl;
}

/**
 * 获取分页页面的 prev/next URL（用于 SEO link rel）
 *
 * @return array ['prev' => url|null, 'next' => url|null]
 */
function shufei_get_prev_next_page()
{
    $result = array('prev' => null, 'next' => null);
    $archive = shufei_get_archive();
    if (!$archive) return $result;

    // 仅在列表型页面（首页/分类/标签/作者/归档）生效
    if (shufei_is_post() || shufei_is_page() || shufei_is_404() || shufei_is_search()) {
        return $result;
    }

    try {
        $currentPage = isset($archive->_currentPage) ? intval($archive->_currentPage) : 1;
        $totalPage = 0;
        if (method_exists($archive, 'getTotal') && method_exists($archive, 'parameter') && $archive->parameter) {
            $pageSize = intval($archive->parameter->pageSize);
            if ($pageSize > 0) {
                $totalPage = ceil(intval($archive->getTotal()) / $pageSize);
            }
        }
        if ($totalPage <= 1) return $result;

        // 生成上一页 URL
        if ($currentPage > 1) {
            $result['prev'] = shufei_build_page_url($archive, $currentPage - 1);
        }
        // 生成下一页 URL
        if ($currentPage < $totalPage) {
            $result['next'] = shufei_build_page_url($archive, $currentPage + 1);
        }
    } catch (\Exception $e) {}

    return $result;
}

/**
 * 根据当前 archive 上下文构建指定页码的 URL
 *
 * @param object $archive
 * @param int $page
 * @return string
 */
function shufei_build_page_url($archive, $page)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $siteUrl = $options->siteUrl;

    try {
        // 利用 Typecho 自带的 pageLink 逻辑生成 URL
        $route = null;
        if (method_exists($archive, 'getRoute')) {
            $route = $archive->getRoute();
        }

        // 简化策略：在当前 permalink 基础上替换页码
        $currentUrl = $archive->permalink;
        $currentPage = isset($archive->_currentPage) ? intval($archive->_currentPage) : 1;

        // 常见模式 1: /page/N/
        if (preg_match('#/page/' . $currentPage . '/?$#', $currentUrl)) {
            return preg_replace('#/page/' . $currentPage . '/?$#', '/page/' . $page . '/', $currentUrl);
        }
        // 常见模式 2: ?page=N（query string）
        if (preg_match('#[?&]page=' . $currentPage . '($|&)#', $currentUrl)) {
            return preg_replace('#([?&]page=)' . $currentPage . '#', '${1}' . $page, $currentUrl);
        }
        // 第一页通常无页码标记，向下翻页时追加 /page/N/
        if ($currentPage === 1) {
            // 去掉 query string 后追加 /page/N/
            $base = preg_replace('#\?.*$#', '', $currentUrl);
            $base = rtrim($base, '/') . '/page/' . $page . '/';
            return $base;
        }
    } catch (\Exception $e) {}

    return $siteUrl;
}

/**
 * 获取文章字数（纯文本字符数）
 *
 * @param object|null $archive
 * @return int
 */
function shufei_get_word_count($archive = null)
{
    if (!$archive) $archive = shufei_get_archive();
    if (!$archive || empty($archive->text)) return 0;

    $text = $archive->text;
    // 去除 Markdown 标记
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    $text = preg_replace('/`[^`]*`/', '', $text);
    $text = preg_replace('/!\[.*?\]\(.*?\)/', '', $text);
    $text = preg_replace('/\[([^\]]*)\]\(.*?\)/', '$1', $text);
    $text = preg_replace('/^#{1,6}\s+/m', '', $text);
    $text = preg_replace('/^[>\-\*\+]\s*/m', '', $text);
    $text = preg_replace('/\$\$[\s\S]+?\$\$/', '', $text);
    $text = preg_replace('/(?<!\$)\$(?!\$)[^\$\n]+?(?<!\$)\$(?!\$)/', '', $text);
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', '', $text);

    return mb_strlen($text, 'UTF-8');
}

/**
 * 估算阅读时长（分钟）
 *
 * @param object|null $archive
 * @return int
 */
function shufei_get_reading_time($archive = null)
{
    $wordCount = shufei_get_word_count($archive);
    // 中文阅读速度约 300-500 字/分钟
    $minutes = max(1, intval(ceil($wordCount / 400)));
    return $minutes;
}

/**
 * 获取 OG 图片尺寸信息
 *
 * @param string $imageUrl
 * @return array|null ['width' => int, 'height' => int]
 */
function shufei_get_og_image_dimensions($imageUrl)
{
    if (empty($imageUrl)) return null;

    // 仅处理本站图片，避免对外部图片发起请求
    $siteUrl = \Typecho\Widget::widget('Widget_Options')->siteUrl;
    $siteHost = parse_url($siteUrl, PHP_URL_HOST);
    $imgHost = parse_url($imageUrl, PHP_URL_HOST);

    // 远程图片无法可靠获取尺寸，返回默认 1200x630
    if (empty($imgHost) || $imgHost !== $siteHost) {
        return array('width' => 1200, 'height' => 630);
    }

    // 本站图片：尝试转换为服务器路径
    $themeDir = dirname(__FILE__);
    $rootDir = dirname(dirname(dirname(dirname(__FILE__)))); // usr/themes/ShuFeiCat -> 根目录
    $imgPath = parse_url($imageUrl, PHP_URL_PATH);

    // 候选路径
    $candidates = array();
    if ($imgPath) {
        $candidates[] = $rootDir . $imgPath;
        $candidates[] = rtrim($rootDir, '/') . $imgPath;
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $info = @getimagesize($path);
            if ($info && isset($info[0]) && isset($info[1]) && $info[0] > 0) {
                return array('width' => $info[0], 'height' => $info[1]);
            }
        }
    }

    // 无法获取时返回默认
    return array('width' => 1200, 'height' => 630);
}

/**
 * 生成面包屑结构化数据
 *
 * @return array BreadcrumbList JSON-LD 数组
 */
function shufei_get_breadcrumbs_jsonld()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $siteUrl = $options->siteUrl;
    $siteTitle = $options->title;
    $archive = shufei_get_archive();

    $items = array();
    $items[] = array(
        '@type' => 'ListItem',
        'position' => 1,
        'name' => $siteTitle,
        'item' => $siteUrl
    );

    $position = 2;

    if (shufei_is_post() && $archive) {
        // 首页 > 分类 > 文章
        if (!empty($archive->categories) && is_array($archive->categories)) {
            $cat = $archive->categories[0];
            if (isset($cat['permalink']) && isset($cat['name'])) {
                $items[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $cat['name'],
                    'item' => $cat['permalink']
                );
            }
        }
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $archive->title,
            'item' => $archive->permalink
        );
    } elseif (shufei_is_page() && $archive) {
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $archive->title,
            'item' => $archive->permalink
        );
    } elseif (shufei_is_category() && $archive) {
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $archive->name,
            'item' => $archive->permalink
        );
    } elseif (shufei_is_tag() && $archive) {
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => '标签: ' . $archive->name,
            'item' => $archive->permalink
        );
    } elseif (shufei_is_author() && $archive) {
        $name = !empty($archive->screenName) ? $archive->screenName : $archive->name;
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $name,
            'item' => $archive->permalink
        );
    } elseif (shufei_is_archive() && $archive) {
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => '归档',
            'item' => $archive->permalink
        );
    }

    // 仅有首页时不输出面包屑
    if (count($items) <= 1) return null;

    return array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    );
}

/**
 * 输出当前页面的 robots meta 值
 *
 * @return string
 */
function shufei_get_robots_content()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $mode = !empty($options->seoRobots) ? $options->seoRobots : 'auto';

    if ($mode === 'noindex') {
        return 'noindex,nofollow';
    }
    if ($mode === 'index') {
        return 'index,follow';
    }

    // auto 模式
    if (shufei_is_search() || shufei_is_404()) {
        return 'noindex,nofollow';
    }

    // 分页大于 1 标记为 noindex
    if (!empty($_GET['page']) && intval($_GET['page']) > 1) {
        return 'noindex,follow';
    }

    return 'index,follow';
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
    $contentHash = md5($rawText . ($widget->modified ?? $widget->created));
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

/**
 * 获取分类目录的自定义图标
 * 通过分类缩略名匹配后台配置的图标
 *
 * @param string $slug 分类缩略名
 * @return string Font Awesome 图标类名
 */
function shufei_get_category_icon($slug)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $categoryIcons = isset($options->categoryIcons) ? $options->categoryIcons : '';

    if (!empty($categoryIcons)) {
        $lines = preg_split('/\R/', trim($categoryIcons));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = explode('|', $line, 2);
            if (count($parts) == 2) {
                $iconSlug = trim($parts[0]);
                $iconClass = trim($parts[1]);
                if ($iconSlug === $slug) {
                    return $iconClass;
                }
            }
        }
    }

    return 'fa-folder-open-o';
}

/**
 * 获取自定义导航项
 *
 * @return array 导航项数组，每项包含 icon, name, url
 */
function shufei_get_custom_nav_items()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $customNavItems = isset($options->customNavItems) ? $options->customNavItems : '';
    $items = array();

    if (!empty($customNavItems)) {
        $lines = preg_split('/\R/', trim($customNavItems));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = explode('|', $line, 3);
            if (count($parts) >= 2) {
                $items[] = array(
                    'icon' => !empty($parts[0]) ? trim($parts[0]) : 'fa-link',
                    'name' => trim($parts[1]),
                    'url'  => isset($parts[2]) ? trim($parts[2]) : '#'
                );
            }
        }
    }

    return $items;
}

/**
 * 获取留言板页面链接
 *
 * @return string 留言板页面URL，未找到返回空字符串
 */
function shufei_get_guestbook_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $db = \Typecho\Db::get();

    // 优先使用配置的页面ID
    $pageId = isset($options->guestbookPageId) ? trim($options->guestbookPageId) : '';
    if (!empty($pageId)) {
        $row = $db->fetchRow($db->select('cid', 'slug')->from('table.contents')
            ->where('cid = ?', intval($pageId))
            ->where('status = ?', 'publish'));
        if ($row) {
            return $options->index . '/' . $row['slug'] . '.html';
        }
    }

    // 自动查找使用留言板模板的页面
    $rows = $db->fetchAll($db->select('cid', 'slug')->from('table.contents')
        ->where('template = ?', 'guestbook.php')
        ->where('status = ?', 'publish')
        ->limit(1));

    if (!empty($rows)) {
        return $options->index . '/' . $rows[0]['slug'] . '.html';
    }

    return '';
}

/**
 * 获取友链独立页面URL
 * 优先使用配置的页面ID，其次自动查找使用 links.php 模板的页面
 *
 * @return string 友链页面URL，未找到返回空字符串
 */
function shufei_get_links_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $db = \Typecho\Db::get();

    // 优先使用配置的页面ID
    $pageId = isset($options->linksPageId) ? trim($options->linksPageId) : '';
    if (!empty($pageId)) {
        $row = $db->fetchRow($db->select('cid', 'slug')->from('table.contents')
            ->where('cid = ?', intval($pageId))
            ->where('status = ?', 'publish'));
        if ($row) {
            return $options->index . '/' . $row['slug'] . '.html';
        }
    }

    // 自动查找使用友链页面模板的页面
    $rows = $db->fetchAll($db->select('cid', 'slug')->from('table.contents')
        ->where('template = ?', 'links.php')
        ->where('status = ?', 'publish')
        ->limit(1));

    if (!empty($rows)) {
        return $options->index . '/' . $rows[0]['slug'] . '.html';
    }

    return '';
}

/**
 * 解析友链配置，返回结构化的友链数组
 * 支持两种格式：
 *   基本格式（逗号）：名称,链接地址
 *   完整格式（竖线）：名称|链接地址|描述|头像地址
 * 描述和头像为可选项
 *
 * @return array 友链数组，每个元素包含 name, url, description, avatar
 */
function shufei_parse_links()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $linksRaw = isset($options->links) ? trim($options->links) : '';
    if (empty($linksRaw)) {
        return array();
    }

    $result = array();
    $lines = explode("\n", $linksRaw);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // 优先使用竖线分隔（完整格式），其次使用逗号分隔（基本格式）
        if (strpos($line, '|') !== false) {
            $parts = array_map('trim', explode('|', $line, 4));
        } else {
            $parts = array_map('trim', explode(',', $line, 4));
        }

        if (count($parts) < 2 || empty($parts[0]) || empty($parts[1])) {
            continue;
        }

        $result[] = array(
            'name'        => $parts[0],
            'url'         => $parts[1],
            'description' => isset($parts[2]) ? $parts[2] : '',
            'avatar'      => isset($parts[3]) ? $parts[3] : ''
        );
    }

    return $result;
}

/**
 * 获取 GitHub 项目列表（带缓存）
 * 优化：缓存过期时返回旧缓存并异步刷新，避免前台同步阻塞最长 50 秒
 *
 * @return array 项目列表数组
 */
function shufei_get_github_repos()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $username = isset($options->githubUsername) ? trim($options->githubUsername) : '';

    if (empty($username)) {
        return array();
    }

    // 获取选定的项目列表
    $selectedRepos = array();
    $selectedReposRaw = isset($options->githubSelectedRepos) ? trim($options->githubSelectedRepos) : '';
    if (!empty($selectedReposRaw)) {
        $decoded = @json_decode($selectedReposRaw, true);
        if (is_array($decoded)) {
            $selectedRepos = $decoded;
        }
    }

    $cacheTime = isset($options->githubCacheTime) ? intval($options->githubCacheTime) : 3600;
    $cacheFile = dirname(__FILE__) . '/cache/github_repos.json';
    $refreshingFlag = dirname(__FILE__) . '/cache/github_repos.refreshing';

    // 按选定项目过滤的闭包
    $filterSelected = function($repos) use ($selectedRepos) {
        if (!empty($selectedRepos)) {
            $repos = array_filter($repos, function($repo) use ($selectedRepos) {
                return in_array($repo['name'], $selectedRepos);
            });
            $repos = array_values($repos);
        }
        return $repos;
    };

    // 尝试从缓存读取
    $cachedRepos = null;
    $cacheIsFresh = false;
    if (file_exists($cacheFile)) {
        $cache = @json_decode(@file_get_contents($cacheFile), true);
        if ($cache && isset($cache['timestamp'], $cache['repos'])) {
            $cachedRepos = $cache['repos'];
            $cacheIsFresh = (time() - $cache['timestamp']) < $cacheTime;
            if ($cacheIsFresh) {
                // 缓存新鲜，直接返回
                return $filterSelected($cachedRepos);
            }
        }
    }

    // 缓存过期或不存在时：
    // 1. 若存在旧缓存，立即返回旧数据，避免前台阻塞
    // 2. 通过标志文件避免并发刷新
    if ($cachedRepos !== null) {
        // 触发后台刷新（仅当没有正在进行的刷新时）
        if (!file_exists($refreshingFlag) || (time() - @filemtime($refreshingFlag)) > 300) {
            @touch($refreshingFlag);
            shufei_refresh_github_repos_cache($username, $cacheFile);
            @unlink($refreshingFlag);
        }
        return $filterSelected($cachedRepos);
    }

    // 完全无缓存时，仅同步获取第一页（最多 10 秒）以快速填充缓存
    $allRepos = shufei_fetch_github_repos_page($username, 1);
    // 若第一页已满 100 条，再异步获取剩余页（不阻塞当前请求）
    if (count($allRepos) >= 100) {
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode(array(
            'timestamp' => time(),
            'repos' => $allRepos
        )));
        // 后台补全剩余页
        if (!file_exists($refreshingFlag) || (time() - @filemtime($refreshingFlag)) > 300) {
            @touch($refreshingFlag);
            shufei_refresh_github_repos_cache($username, $cacheFile);
            @unlink($refreshingFlag);
        }
        return $filterSelected($allRepos);
    }

    // 写入缓存
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode(array(
        'timestamp' => time(),
        'repos' => $allRepos
    )));

    return $filterSelected($allRepos);
}

/**
 * 抓取 GitHub API 单页数据
 *
 * @param string $username GitHub 用户名
 * @param int $page 页码
 * @return array 该页项目列表
 */
function shufei_fetch_github_repos_page($username, $page)
{
    $apiUrl = 'https://api.github.com/users/' . urlencode($username) . '/repos?sort=stars&per_page=100&page=' . $page;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ShuFeiCat-Typecho-Theme');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200 || !$response) {
        return array();
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return array();
    }

    $repos = array();
    foreach ($data as $repo) {
        $repos[] = array(
            'name' => isset($repo['name']) ? $repo['name'] : '',
            'full_name' => isset($repo['full_name']) ? $repo['full_name'] : '',
            'description' => isset($repo['description']) ? $repo['description'] : '',
            'url' => isset($repo['html_url']) ? $repo['html_url'] : '',
            'stars' => isset($repo['stargazers_count']) ? $repo['stargazers_count'] : 0,
            'forks' => isset($repo['forks_count']) ? $repo['forks_count'] : 0,
            'language' => isset($repo['language']) ? $repo['language'] : '',
            'updated_at' => isset($repo['updated_at']) ? $repo['updated_at'] : ''
        );
    }
    return $repos;
}

/**
 * 后台刷新 GitHub 仓库缓存（获取全部页）
 * 单页超时 10s，最多 5 页，但仅在缓存过期或不存在时调用
 *
 * @param string $username GitHub 用户名
 * @param string $cacheFile 缓存文件路径
 */
function shufei_refresh_github_repos_cache($username, $cacheFile)
{
    $allRepos = array();
    $maxPages = 5;

    for ($page = 1; $page <= $maxPages; $page++) {
        $pageRepos = shufei_fetch_github_repos_page($username, $page);
        if (empty($pageRepos)) {
            break;
        }
        $allRepos = array_merge($allRepos, $pageRepos);
        if (count($pageRepos) < 100) {
            break;
        }
    }

    if (!empty($allRepos)) {
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode(array(
            'timestamp' => time(),
            'repos' => $allRepos
        )));
    }
}

/**
 * 解析评论内容中的 Markdown 语法
 * 支持与文章相同的扩展语法
 *
 * @param string $text 评论原始文本
 * @return string 解析后的 HTML
 */
/**
 * 解析评论内容中的表情代码为图片
 * 支持阿鲁、QQ、微博、贴吧表情
 *
 * @param string $html HTML 内容
 * @param object $options 主题选项
 * @return string 处理后的 HTML 内容
 */
function shufei_parse_emoji_code($html, $options = null)
{
    if (empty($html)) return $html;

    // 支持 CDN/custom 模式
    $resourceMode = !empty($options->resourceMode) ? $options->resourceMode : 'local';
    $customCdn = !empty($options->customCdn) ? rtrim($options->customCdn, '/') : '';
    $emojiAssetBase = rtrim($options->themeUrl, '/') . '/assets/vendor/jquery-emoji';
    if ($resourceMode === 'custom' && $customCdn) {
        $emojiAssetBase = $customCdn . '/assets/vendor/jquery-emoji';
    }
    $basePath = $emojiAssetBase . '/images/emoji/';

    // 阿鲁表情: [aru_1] ~ [aru_164]
    $html = preg_replace_callback('/\[aru_(\d+)\]/', function($m) use ($basePath) {
        return '<img class="wp-smiley" src="' . $basePath . 'aru/' . $m[1] . '.png" alt="[aru_' . $m[1] . ']" />';
    }, $html);

    // QQ表情: [qq:微笑] [qq:撇嘴] 等（新格式，避免Markdown冲突）
    $qqEmojiMap = array(
        '微笑','撇嘴','色','发呆','得意','流泪','害羞','闭嘴','睡','大哭',
        '尴尬','呲牙','发怒','调皮','惊讶','难过','酷','冷汗','抓狂','吐',
        '偷笑','可爱','白眼','傲慢','饥饿','困','惊恐','流汗','憨笑','大兵',
        '奋斗','咒骂','疑问','嘘','晕','折磨','衰','骷髅','敲打','再见',
        '擦汗','抠鼻','鼓掌','嗅大了','坏笑','左哼哼','右哼哼','哈欠','鄙视','委屈',
        '可怜','阴险','亲亲','吓','快哭了','菜刀','西瓜','啤酒','篮球','乒乓',
        '咖啡','饭','猪头','玫瑰','凋谢','心','心碎','蛋糕','闪电','炸弹',
        '刀','足球','瓢虫','便便','夜晚','太阳','礼物','拥抱','强','弱',
        '握手','胜利','抱拳','勾引','拳头','差劲','爱你','NO','OK','爱情',
        '飞吻','发财','帅','雨伞','高铁左车头','车厢','高铁右车头','纸巾','右太极','左太极',
        '献吻','街舞','激动','挥动','跳绳','回头','磕头','转圈','怄火','发抖',
        '跳跳','爆筋','沙发','钱','蜡烛','枪','灯','香蕉','吻','下雨',
        '闹钟','囍','棒棒糖','面条','车','邮件','风车','药丸','奶瓶','灯笼',
        '青蛙','戒指','K歌','熊猫','喝彩','购物','多云','鞭炮','飞机','气球'
    );
    $qqPattern = '/\[qq:(' . implode('|', array_map('preg_quote', $qqEmojiMap)) . ')\]/';
    $html = preg_replace_callback($qqPattern, function($m) use ($basePath) {
        return '<img class="wp-smiley" src="' . $basePath . 'qq/' . urlencode($m[1]) . '.gif" alt="[qq:' . $m[1] . ']" />';
    }, $html);

    // 微博表情: [wb:doge] [wb:aini] 等（新格式，key为显示名，value为文件名）
    $wbEmojiMap = array(
        'doge' => 'doge', 'miao' => 'miao',
        'dog1' => 'dog1', 'dog2' => 'dog2', 'dog3' => 'dog3', 'dog4' => 'dog4',
        'dog5' => 'dog5', 'dog6' => 'dog6', 'dog7' => 'dog7', 'dog8' => 'dog8',
        'dog9' => 'dog9', 'dog10' => 'dog10', 'dog11' => 'dog11', 'dog12' => 'dog12',
        'dog13' => 'dog13', 'dog14' => 'dog14', 'dog15' => 'dog15',
        '二哈' => 'erha', '爱你' => 'aini', '奥特曼' => 'aoteman',
        '拜拜' => 'baibai', '悲伤' => 'beishang', '鄙视' => 'bishi',
        '闭嘴' => 'bizui', '馋嘴' => 'chanzui', '吃惊' => 'chijing',
        '打哈气' => 'dahaqi', '打脸' => 'dalian', '顶' => 'ding',
        '肥皂' => 'feizao', '感冒' => 'ganmao', '鼓掌' => 'guzhang',
        '哈哈' => 'haha', '害羞' => 'haixiu', '呵呵' => 'hehe',
        '黑线' => 'heixian', '哼' => 'heng', '花心' => 'huaxin',
        '挤眼' => 'jiyan', '可爱' => 'keai', '可怜' => 'kelian',
        '哭' => 'ku', '困' => 'kun', '懒得理你' => 'landelini',
        '累' => 'lei', '男孩儿' => 'nanhaier', '怒' => 'nu',
        '怒骂' => 'numa', '女孩儿' => 'nvhaier', '钱' => 'qian',
        '亲亲' => 'qinqin', '傻眼' => 'shayan', '生病' => 'shengbing',
        '神兽' => 'shenshou', '失望' => 'shiwang', '衰' => 'shuai',
        '睡觉' => 'shuijiao', '思考' => 'sikao', '太开心' => 'taikaixin',
        '偷笑' => 'touxiao', '吐' => 'tu', '兔子' => 'tuzi',
        '挖鼻屎' => 'wabishi', '委屈' => 'weiqu', '笑哭' => 'xiaoku',
        '熊猫' => 'xiongmao', '嘻嘻' => 'xixi', '嘘' => 'xu',
        '阴险' => 'yinxian', '疑问' => 'yiwen', '右哼哼' => 'youhengheng',
        '晕' => 'yun', '抓狂' => 'zhuakuang', '猪头' => 'zhutou',
        '最右' => 'zuiyou', '左哼哼' => 'zuohengheng', '给力' => 'geili',
        '互粉' => 'hufen', '囧' => 'jiong', '萌' => 'meng',
        '神马' => 'shenma', 'v5' => 'v5', '囍' => 'xi', '织' => 'zhi'
    );
    $wbKeys = array_keys($wbEmojiMap);
    $wbPattern = '/\[wb:(' . implode('|', array_map('preg_quote', $wbKeys)) . ')\]/';
    $html = preg_replace_callback($wbPattern, function($m) use ($basePath, $wbEmojiMap) {
        $filename = $wbEmojiMap[$m[1]];
        return '<img class="wp-smiley" src="' . $basePath . 'weibo/' . $filename . '.png" alt="[wb:' . $m[1] . ']" />';
    }, $html);

    // 贴吧表情: [tb:呵呵] [tb:哈哈] 等（新格式，避免Markdown #号冲突）
    $tiebaEmojiMap = array(
        '呵呵','哈哈','吐舌','太开心','笑眼','花心','小乖','乖','捂嘴笑','滑稽',
        '你懂的','不高兴','怒','汗','黑线','泪','真棒','喷','惊哭','阴险',
        '鄙视','酷','啊','狂汗','what','疑问','酸爽','呀咩爹','委屈','惊讶',
        '睡觉','笑尿','挖鼻','吐','犀利','小红脸','懒得理','勉强','爱心','心碎',
        '玫瑰','礼物','彩虹','太阳','星星月亮','钱币','茶杯','蛋糕','大拇指','胜利',
        'haha','OK','沙发','手纸','香蕉','便便','药丸','红领巾','蜡烛','音乐','灯泡'
    );
    $tiebaPattern = '/\[tb:(' . implode('|', array_map('preg_quote', $tiebaEmojiMap)) . ')\]/';
    $html = preg_replace_callback($tiebaPattern, function($m) use ($basePath) {
        return '<img class="wp-smiley" src="' . $basePath . 'tieba/' . urlencode($m[1]) . '.png" alt="[tb:' . $m[1] . ']" />';
    }, $html);

    return $html;
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

/**
 * ===== 文章编辑器快捷插入功能 =====
 * 在后台文章/页面 Markdown 编辑器工具栏中添加快捷按钮
 * 支持所有增强 Markdown 语法：高亮、公式、任务列表、提示框、折叠、图片、视频、音乐、Mermaid、ECharts
 * 利用 Typecho 的 admin/write-post.php 和 admin/write-page.php 的 bottom 钩子
 */

// 注册后台编辑器钩子（文章 + 页面）
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_quick_insert_js';
\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_quick_insert_js';

/**
 * 输出快捷插入功能所需的 CSS 和 JavaScript
 * 将快捷按钮注入到 Markdown 编辑器工具栏（#wmd-button-bar），位于"撰写/预览"标签左侧
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_quick_insert_js($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    if (empty($options->markdown)) {
        return;
    }
    ?>
<style>
/* ===== 工具栏自动换行 ===== */
.wmd-button-row {
    height: auto !important;
    min-height: 26px;
    padding-bottom: 4px !important;
    white-space: normal;
}
.wmd-button-row li {
    white-space: nowrap;
}

/* ===== 所有按钮统一样式（原 sprite 按钮也用文字替代）===== */
.wmd-button-row li span {
    width: auto !important;
    height: 20px;
    line-height: 20px;
    padding: 0 5px;
    font-size: 12px;
    color: #666;
    background: none !important;
    display: block;
    white-space: nowrap;
}
.wmd-button-row li:hover {
    background-color: #E9E9E6;
}
.wmd-button-row li:hover span {
    color: #467B96;
}

/* 分隔符 */
.wmd-button-row li.shufei-qi-sep {
    display: inline-block;
    width: 1px;
    height: 16px;
    margin: 0 4px;
    padding: 0;
    background: #d0d0d0;
    vertical-align: middle;
    cursor: default;
}
.wmd-button-row li.shufei-qi-sep:hover {
    background: #d0d0d0;
}

/* ===== 预览区样式（与前台一致）===== */
#wmd-preview {
    line-height: 1.8;
    color: #333;
    font-size: 15px;
}
#wmd-preview h1, #wmd-preview h2, #wmd-preview h3,
#wmd-preview h4, #wmd-preview h5, #wmd-preview h6 {
    margin: 1.2em 0 0.6em;
    font-weight: bold;
    line-height: 1.3;
}
#wmd-preview h1 { font-size: 1.8em; border-bottom: 1px solid #eee; padding-bottom: .3em; }
#wmd-preview h2 { font-size: 1.5em; border-bottom: 1px solid #eee; padding-bottom: .3em; }
#wmd-preview h3 { font-size: 1.3em; }
#wmd-preview h4 { font-size: 1.1em; }
#wmd-preview h5 { font-size: 1em; }
#wmd-preview h6 { font-size: .9em; color: #999; }
#wmd-preview p { margin: 0.8em 0; }
#wmd-preview a { color: #467B96; text-decoration: none; }
#wmd-preview a:hover { text-decoration: underline; }
#wmd-preview blockquote {
    margin: 1em 0;
    padding: 10px 16px;
    border-left: 4px solid #467B96;
    background: #f7f7f9;
    color: #666;
}
#wmd-preview blockquote p { margin: 0.4em 0; }
#wmd-preview code {
    padding: 2px 6px;
    background: #f0f0f0;
    border-radius: 3px;
    font-size: 0.9em;
    color: #c7254e;
}
#wmd-preview pre {
    margin: 1em 0;
    padding: 14px 18px;
    background: #2d2d2d;
    border-radius: 5px;
    overflow-x: auto;
}
#wmd-preview pre code {
    padding: 0;
    background: none;
    color: #ccc;
    font-size: 13px;
}
#wmd-preview ul, #wmd-preview ol { margin: 0.8em 0; padding-left: 2em; }
#wmd-preview li { margin: 0.3em 0; }
#wmd-preview img { max-width: 100%; height: auto; border-radius: 4px; }
#wmd-preview table {
    margin: 1em 0;
    border-collapse: collapse;
    width: 100%;
}
#wmd-preview th, #wmd-preview td {
    border: 1px solid #ddd;
    padding: 8px 12px;
}
#wmd-preview th { background: #f5f5f5; font-weight: bold; }
#wmd-preview hr { border: none; border-top: 1px solid #eee; margin: 1.5em 0; }

/* ===== 全屏模式适配 ===== */
.fullscreen .wmd-button-row {
    padding-bottom: 0 !important;
}
.fullscreen #wmd-preview {
    display: block !important;
}
/* 全屏模式下隐藏高级选项按钮（避免从 .submit 与 #wmd-preview 间隙露出） */
.fullscreen #advance-panel {
    display: none !important;
}

/* 模态对话框 */
.shufei-modal-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.4);
    z-index: 99999;
    display: none;
}
.shufei-modal-mask.active { display: block; }
.shufei-modal {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.18);
    z-index: 100000;
    min-width: 380px;
    max-width: 90vw;
    display: none;
}
.shufei-modal.active { display: block; }
.shufei-modal-header {
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    font-weight: bold;
    color: #467B96;
    font-size: 14px;
}
.shufei-modal-body { padding: 16px; }
.shufei-modal-body .field { margin-bottom: 12px; }
.shufei-modal-body .field:last-child { margin-bottom: 0; }
.shufei-modal-body label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}
.shufei-modal-body input[type="text"],
.shufei-modal-body select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 13px;
    box-sizing: border-box;
}
.shufei-modal-body input[type="text"]:focus,
.shufei-modal-body select:focus {
    border-color: #467B96;
    outline: none;
}
.shufei-modal-body .field-row {
    display: flex;
    gap: 10px;
}
.shufei-modal-body .field-row .field { flex: 1; }
.shufei-modal-footer {
    padding: 12px 16px;
    border-top: 1px solid #eee;
    text-align: right;
}
.shufei-modal-footer .btn { margin-left: 8px; }
.shufei-modal-footer .btn-primary {
    background: #467B96;
    color: #fff;
    border: 1px solid #467B96;
}
.shufei-modal-footer .btn-cancel {
    background: #f5f5f5;
    color: #666;
    border: 1px solid #ddd;
}
</style>
<script>
(function ($) {
    $(document).ready(function () {
        var textarea = $('#text');
        if (textarea.length === 0) return;

        // 在光标位置插入文本，并触发预览刷新
        // 修复：保存/恢复 scrollTop，防止 setSelectionRange 和 pagedown 的 scrollableEditor
        // 导致 textarea 跳转到文章末尾
        function insertAtCursor(text) {
            var sel = textarea.getSelection();
            var offset = (sel ? sel.start : 0) + text.length;
            // 保存插入前的滚动位置
            var savedScrollTop = textarea[0].scrollTop;
            var savedWindowScroll = window.scrollY;
            textarea.replaceSelection(text);
            textarea.setSelection(offset, offset);
            // setSelectionRange 会触发浏览器自动滚动，立即恢复
            textarea[0].scrollTop = savedScrollTop;
            textarea.trigger('input');
            // pagedown input 处理可能修改 scrollTop，再次恢复
            textarea[0].scrollTop = savedScrollTop;
            textarea.focus();
            // focus 可能再次触发滚动，再次恢复
            textarea[0].scrollTop = savedScrollTop;
            window.scrollTo(0, savedWindowScroll);
            // 用 setInterval 持续恢复，覆盖 scrollableEditor 的 p() 500ms 平滑滚动
            // 8ms 频率高于 p() 的 rAF(16ms)，600ms 覆盖 p() 的 500ms 动画 + 余量
            var restoreCount = 0;
            var restoreInterval = setInterval(function () {
                textarea[0].scrollTop = savedScrollTop;
                window.scrollTo(0, savedWindowScroll);
                restoreCount++;
                if (restoreCount >= 75) {  // 约 600ms（75次 * 8ms）
                    clearInterval(restoreInterval);
                }
            }, 8);
        }

        // 在选中文本两侧包裹标记（如 ==高亮==），无选中时插入占位
        // 修复：同样保存/恢复 scrollTop
        function wrapSelection(before, after, placeholder) {
            var sel = textarea.getSelection();
            var text = sel && sel.text ? sel.text : (placeholder || '');
            var replacement = before + text + (after || before);
            var start = sel ? sel.start : 0;
            // 保存滚动位置
            var savedScrollTop = textarea[0].scrollTop;
            var savedWindowScroll = window.scrollY;
            textarea.replaceSelection(replacement);
            // 选中插入的文本部分（不含包裹标记）
            textarea.setSelection(start + before.length, start + before.length + text.length);
            textarea[0].scrollTop = savedScrollTop;
            textarea.trigger('input');
            textarea[0].scrollTop = savedScrollTop;
            textarea.focus();
            textarea[0].scrollTop = savedScrollTop;
            window.scrollTo(0, savedWindowScroll);
            // 持续恢复
            var restoreCount = 0;
            var restoreInterval = setInterval(function () {
                textarea[0].scrollTop = savedScrollTop;
                window.scrollTo(0, savedWindowScroll);
                restoreCount++;
                if (restoreCount >= 75) {
                    clearInterval(restoreInterval);
                }
            }, 8);
        }

        // 创建并显示模态对话框
        function showModal(title, fields, callback) {
            $('#shufei-modal-mask, #shufei-modal').remove();

            var mask = $('<div class="shufei-modal-mask" id="shufei-modal-mask"></div>');
            var modal = $('<div class="shufei-modal" id="shufei-modal"></div>');
            var header = $('<div class="shufei-modal-header"></div>').text(title);
            var body = $('<div class="shufei-modal-body"></div>');
            var footer = $('<div class="shufei-modal-footer"></div>');
            var btnOk = $('<button type="button" class="btn btn-xs btn-primary">确定</button>');
            var btnCancel = $('<button type="button" class="btn btn-xs btn-cancel">取消</button>');

            var inputs = {};
            var currentRow = null;

            fields.forEach(function (f) {
                if (f.half && !currentRow) {
                    currentRow = $('<div class="field-row"></div>');
                    body.append(currentRow);
                } else if (!f.half) {
                    currentRow = null;
                }

                var fieldWrap = $('<div class="field"></div>');
                var label = $('<label></label>').attr('for', 'shufei-field-' + f.name).text(f.label);

                var input;
                if (f.type === 'select') {
                    input = $('<select></select>').attr('id', 'shufei-field-' + f.name);
                    (f.options || []).forEach(function (opt) {
                        var val = typeof opt === 'object' ? opt.value : opt;
                        var text = typeof opt === 'object' ? opt.text : opt;
                        var option = $('<option></option>').attr('value', val).text(text);
                        if (val === f.value) option.attr('selected', 'selected');
                        input.append(option);
                    });
                } else {
                    input = $('<input type="text" />')
                        .attr('id', 'shufei-field-' + f.name)
                        .attr('placeholder', f.placeholder || '');
                    if (f.value) input.val(f.value);
                }

                fieldWrap.append(label).append(input);
                inputs[f.name] = input;

                if (currentRow) {
                    currentRow.append(fieldWrap);
                    if (currentRow.children().length >= 2) currentRow = null;
                } else {
                    body.append(fieldWrap);
                }
            });

            footer.append(btnCancel).append(btnOk);
            modal.append(header).append(body).append(footer);
            $('body').append(mask).append(modal);
            mask.addClass('active');
            modal.addClass('active');

            var firstInput = body.find('input, select').first();
            if (firstInput.length) firstInput.focus();

            function closeModal() {
                mask.removeClass('active').remove();
                modal.removeClass('active').remove();
            }

            btnOk.on('click', function () {
                var values = {};
                var hasValue = false;
                for (var name in inputs) {
                    values[name] = $.trim(inputs[name].val());
                    if (values[name]) hasValue = true;
                }
                if (hasValue) callback(values);
                closeModal();
                textarea.focus();
            });

            btnCancel.on('click', function () { closeModal(); textarea.focus(); });
            mask.on('click', function () { closeModal(); textarea.focus(); });

            modal.on('keydown', function (e) {
                if (e.keyCode === 13) { e.preventDefault(); btnOk.trigger('click'); }
                else if (e.keyCode === 27) { e.preventDefault(); btnCancel.trigger('click'); }
            });
        }

        // ===== 直接插入类（无需弹窗）=====

        // 高亮文本：==高亮内容==
        function insertHighlight() {
            wrapSelection('==', '==', '高亮内容');
        }

        // 回复可见：[reply]内容[/reply]
        function insertReply() {
            wrapSelection('[reply]', '[/reply]', '此处内容需要回复后才可查看');
        }

        // 行内代码：`code`（包裹选中文本）
        function insertCode() {
            wrapSelection('`', '`', 'code');
        }

        // 代码块：```\ncode\n```
        function insertCodeBlock() {
            insertAtCursor('\n```\ncode\n```\n');
        }

        // 数学公式：$$\n公式\n$$
        function insertMath() {
            insertAtCursor('\n$$\nE = mc^2\n$$\n');
        }

        // 任务列表：- [ ] 任务项
        function insertTask() {
            insertAtCursor('\n- [ ] 任务项\n- [ ] 任务项\n- [x] 已完成项\n');
        }

        // 提示框：> [!tip] 标题
        function insertTip() {
            insertAtCursor('\n> [!tip] 提示标题\n> 提示内容写在这里\n> 可以换行继续写\n');
        }

        // 折叠区块：> [details:标题]
        function insertDetails() {
            insertAtCursor('\n> [details:点击展开查看]\n> 折叠的内容写在这里\n> 可以换行继续写\n');
        }

        // Mermaid 流程图
        function insertMermaid() {
            insertAtCursor('\n```mermaid\ngraph TD\n    A[开始] --> B[步骤一]\n    B --> C[步骤二]\n    C --> D[结束]\n```\n');
        }

        // ECharts 图表
        function insertEcharts() {
            insertAtCursor('\n```echarts\n{\n  "xAxis": { "type": "category", "data": ["A", "B", "C"] },\n  "yAxis": { "type": "value" },\n  "series": [{ "data": [120, 200, 150], "type": "bar" }]\n}\n```\n');
        }

        // ===== 弹窗插入类 =====

        // 图片插入：![描述|宽x高#对齐](url)
        function insertImage() {
            showModal('插入图片', [
                { name: 'url', label: '图片地址 *', placeholder: 'https://example.com/image.jpg' },
                { name: 'alt', label: '图片描述', placeholder: '图片说明文字（可选，会显示为标题）' },
                { name: 'width', label: '宽度', placeholder: '如 300 或 50%', half: true },
                { name: 'height', label: '高度', placeholder: '如 200（可选）', half: true },
                {
                    name: 'align', label: '对齐方式', type: 'select', value: '',
                    options: [
                        { value: '', text: '默认' },
                        { value: 'center', text: '居中' },
                        { value: 'left', text: '左对齐' },
                        { value: 'right', text: '右对齐' }
                    ]
                }
            ], function (v) {
                if (!v.url) return;
                var altParts = [];
                if (v.alt) altParts.push(v.alt);
                var sizeStr = '';
                if (v.width && v.height) sizeStr = v.width + 'x' + v.height;
                else if (v.width) sizeStr = v.width;
                if (sizeStr) altParts.push(sizeStr);
                var altText = altParts.join('|');
                if (v.align) altText += '#' + v.align;
                insertAtCursor('\n![' + altText + '](' + v.url + ')\n');
            });
        }

        // 视频插入：[video src="url" poster="..." autoplay="true"]
        function insertVideo() {
            showModal('插入视频', [
                { name: 'src', label: '视频地址 *', placeholder: 'https://example.com/video.mp4' },
                { name: 'poster', label: '封面图地址', placeholder: 'https://example.com/poster.jpg（可选）' },
                {
                    name: 'autoplay', label: '自动播放', type: 'select', value: 'false',
                    options: [
                        { value: 'false', text: '否' },
                        { value: 'true', text: '是' }
                    ]
                }
            ], function (v) {
                if (!v.src) return;
                var attrs = 'src="' + v.src + '"';
                if (v.poster) attrs += ' poster="' + v.poster + '"';
                if (v.autoplay === 'true') attrs += ' autoplay="true"';
                insertAtCursor('\n[video ' + attrs + ']\n');
            });
        }

        // 音乐插入：[music src="url" title="..." artist="..." cover="..."]
        function insertMusic() {
            showModal('插入音乐', [
                { name: 'src', label: '音乐地址 *', placeholder: 'https://example.com/song.mp3' },
                { name: 'title', label: '歌曲名', placeholder: '如：晴天（可选）', half: true },
                { name: 'artist', label: '艺术家', placeholder: '如：周杰伦（可选）', half: true },
                { name: 'cover', label: '封面图地址', placeholder: 'https://example.com/cover.jpg（可选）' }
            ], function (v) {
                if (!v.src) return;
                var attrs = 'src="' + v.src + '"';
                if (v.title) attrs += ' title="' + v.title + '"';
                if (v.artist) attrs += ' artist="' + v.artist + '"';
                if (v.cover) attrs += ' cover="' + v.cover + '"';
                insertAtCursor('\n[music ' + attrs + ']\n');
            });
        }

        // ===== 重建工具栏：原按钮文字化 + 新按钮 + 统一排序 =====

        // 原编辑器按钮 ID → 文字标签
        var origLabels = {
            'wmd-bold-button': '加粗',
            'wmd-italic-button': '斜体',
            'wmd-link-button': '链接',
            'wmd-quote-button': '引用',
            'wmd-olist-button': '有序列表',
            'wmd-ulist-button': '无序列表',
            'wmd-heading-button': '标题',
            'wmd-hr-button': '分割线',
            'wmd-more-button': '摘要',
            'wmd-undo-button': '撤销',
            'wmd-redo-button': '重做',
            'wmd-fullscreen-button': '全屏',
            'wmd-exit-fullscreen-button': '退出全屏',
            'wmd-help-button': '帮助'
        };

        // 将原 sprite 按钮的文字标签写入 span，移除背景图
        function textifyOrig(li, label) {
            var span = li.find('span');
            if (span.length) {
                span.css('background-image', 'none').width('auto').text(label);
            }
            return li;
        }

        // 重建整个工具栏，按功能分组排列
        function rebuildToolbar() {
            var buttonRow = $('.wmd-button-row');
            if (buttonRow.length === 0) return false;
            if (buttonRow.data('shufei-rebuilt')) return true;

            // 收集并 detach 所有原按钮（保留事件绑定）
            var origBtns = {};
            buttonRow.find('li').each(function () {
                var li = $(this);
                var id = li.attr('id');
                if (id) {
                    origBtns[id] = li.detach();
                } else {
                    li.remove();
                }
            });

            // 删除原图片和代码按钮（由新按钮替代）
            delete origBtns['wmd-image-button'];
            delete origBtns['wmd-code-button'];

            // 辅助函数
            function appendOrig(id) {
                if (origBtns[id]) {
                    buttonRow.append(textifyOrig(origBtns[id], origLabels[id] || ''));
                }
            }
            function appendNew(action, label, title) {
                buttonRow.append('<li class="shufei-qi-btn" data-action="' + action + '" title="' + title + '"><span>' + label + '</span></li>');
            }
            function appendSep() {
                buttonRow.append('<li class="shufei-qi-sep"></li>');
            }

            // === 第1组：文字格式 ===
            appendOrig('wmd-bold-button');       // 加粗
            appendOrig('wmd-italic-button');      // 斜体
            appendNew('highlight', '高亮', '高亮文本 ==高亮==');
            appendNew('code', '代码', '行内代码 `code`');
            appendSep();

            // === 第2组：结构 ===
            appendOrig('wmd-heading-button');     // 标题
            appendOrig('wmd-quote-button');       // 引用
            appendOrig('wmd-olist-button');       // 有序列表
            appendOrig('wmd-ulist-button');       // 无序列表
            appendNew('task', '任务', '任务列表 - [ ]');
            appendOrig('wmd-hr-button');          // 分割线
            appendSep();

            // === 第3组：插入 ===
            appendOrig('wmd-link-button');        // 链接
            appendNew('image', '图片', '插入图片（支持大小/对齐）');
            appendNew('video', '视频', '插入视频（支持封面）');
            appendNew('music', '音乐', '插入音乐');
            appendNew('codeblock', '代码块', '代码块 ```code```');
            appendSep();

            // === 第4组：高级 ===
            appendNew('math', '公式', '数学公式 $$...$$');
            appendNew('tip', '提示', '提示框 > [!tip]');
            appendNew('details', '折叠', '折叠区块 > [details:]');
            appendNew('reply', '回复可见', '回复可见 [reply]内容[/reply]');
            appendNew('mermaid', '流程图', 'Mermaid 流程图/时序图/甘特图');
            appendNew('echarts', '图表', 'ECharts 数据图表');
            appendSep();

            // === 第5组：工具 ===
            appendOrig('wmd-more-button');        // 摘要
            appendOrig('wmd-undo-button');        // 撤销
            appendOrig('wmd-redo-button');        // 重做
            appendOrig('wmd-fullscreen-button');  // 全屏
            if (origBtns['wmd-exit-fullscreen-button']) {
                appendOrig('wmd-exit-fullscreen-button'); // 退出全屏
            }
            appendOrig('wmd-help-button');        // 帮助

            buttonRow.data('shufei-rebuilt', true);

            return true;
        }

        // 尝试立即重建，若工具栏尚未创建则监听 DOM 变化
        if (!rebuildToolbar()) {
            var observer = new MutationObserver(function (mutations, obs) {
                if (rebuildToolbar()) obs.disconnect();
            });
            observer.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () { observer.disconnect(); }, 30000);
        }

        // 全屏模式：动态调整 #text 和 #wmd-preview 的 top 位置，避免被工具栏遮挡
        function adjustFullscreenLayout() {
            var isFs = $('#text').css('position') === 'absolute';
            if (isFs) {
                var barHeight = $('#wmd-button-bar').outerHeight(true) || 53;
                $('#text').css('top', barHeight + 'px');
                $('#wmd-preview').css('top', barHeight + 'px');
                // 让 .submit 覆盖到预览区顶部，避免工具栏换行后露出下方内容
                $('.submit').css('height', barHeight + 'px');
            } else {
                $('#text').css('top', '');
                $('#wmd-preview').css('top', '');
                $('.submit').css('height', '');
            }
        }

        // 全屏切换时处理 exit-fullscreen 按钮和布局调整
        $(document).on('click', '#wmd-fullscreen-button, #wmd-exit-fullscreen-button', function () {
            setTimeout(function () {
                // 文字化 exit-fullscreen 按钮
                var exitBtn = $('#wmd-exit-fullscreen-button');
                if (exitBtn.length) {
                    var span = exitBtn.find('span');
                    if (span.length && !span.text()) {
                        span.css('background-image', 'none').width('auto').text('退出全屏');
                    }
                }
                // 全屏可能重建工具栏，重新文字化所有 sprite 按钮
                $('.wmd-button-row li[id]').each(function () {
                    var li = $(this);
                    var id = li.attr('id');
                    if (origLabels[id]) {
                        var span = li.find('span');
                        if (span.length && !span.text()) {
                            span.css('background-image', 'none').width('auto').text(origLabels[id]);
                        }
                    }
                });
                // 调整内容区位置
                adjustFullscreenLayout();
            }, 100);
        });

        // 绑定按钮点击事件（事件委托，支持动态注入）
        $(document).on('click', '.shufei-qi-btn', function (e) {
            e.preventDefault();
            var action = $(this).data('action');
            switch (action) {
                case 'highlight': insertHighlight(); break;
                case 'reply': insertReply(); break;
                case 'code': insertCode(); break;
                case 'codeblock': insertCodeBlock(); break;
                case 'math': insertMath(); break;
                case 'task': insertTask(); break;
                case 'tip': insertTip(); break;
                case 'details': insertDetails(); break;
                case 'mermaid': insertMermaid(); break;
                case 'echarts': insertEcharts(); break;
                case 'image': insertImage(); break;
                case 'video': insertVideo(); break;
                case 'music': insertMusic(); break;
            }
        });
    });
})(jQuery);
</script>
<?php
}

/**
 * ===== AI 写作助手编辑器集成 =====
 * 在后台文章/页面编辑器中注入 AI 美化、续写、检查功能
 * 仅当 AI 写作助手开启时显示
 */

// 注册 AI 写作助手后台编辑器钩子（文章 + 页面）
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_ai_writer_editor_ui';
\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_ai_writer_editor_ui';

/**
 * 输出 AI 写作助手编辑器 UI（工具栏按钮 + 浮窗）
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_ai_writer_editor_ui($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');

    // 仅当 AI 写作助手开启时注入
    $aiWriterEnabled = isset($options->aiWriterEnabled) ? $options->aiWriterEnabled : 'off';
    if ($aiWriterEnabled !== 'on') {
        return;
    }

    // AI 写作 AJAX 端点
    $aiAjaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/ai-writer-ajax.php', $options->siteUrl);

    // 美化风格列表
    $styles = AiWriter::$beautifyStyles;
    ?>
<style>
/* ===== AI 写作助手样式 ===== */
.shufei-ai-toolbar {
    display: flex;
    gap: 6px;
    align-items: center;
    padding: 6px 8px;
    margin: 4px 0 8px;
    background: linear-gradient(135deg, #f0f7ff 0%, #fff5f6 100%);
    border: 1px solid #d6e4ff;
    border-radius: 6px;
    flex-wrap: wrap;
}
.shufei-ai-toolbar .ai-label {
    font-weight: bold;
    color: #1d39c4;
    font-size: 13px;
    margin-right: 4px;
}
.shufei-ai-btn {
    display: inline-block;
    padding: 4px 12px;
    font-size: 12px;
    color: #fff;
    background: #597ef7;
    border: 1px solid #597ef7;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1.6;
}
.shufei-ai-btn:hover {
    background: #4096ff;
    border-color: #4096ff;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(89, 126, 247, 0.35);
}
.shufei-ai-btn.beautify { background: #73d13d; border-color: #73d13d; }
.shufei-ai-btn.beautify:hover { background: #52c41a; border-color: #52c41a; box-shadow: 0 2px 6px rgba(82, 196, 26, 0.35); }
.shufei-ai-btn.continue { background: #ffa940; border-color: #ffa940; }
.shufei-ai-btn.continue:hover { background: #fa8c16; border-color: #fa8c16; box-shadow: 0 2px 6px rgba(250, 140, 22, 0.35); }
.shufei-ai-btn.check { background: #ff85c0; border-color: #ff85c0; }
.shufei-ai-btn.check:hover { background: #f759ab; border-color: #f759ab; box-shadow: 0 2px 6px rgba(247, 89, 171, 0.35); }

/* AI 浮窗 */
.shufei-ai-modal-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 10000;
    display: none;
}
.shufei-ai-modal {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 720px;
    max-width: 92vw;
    max-height: 85vh;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
    z-index: 10001;
    display: none;
    flex-direction: column;
    overflow: hidden;
}
.shufei-ai-modal.show, .shufei-ai-modal-mask.show { display: flex; }
.shufei-ai-modal-header {
    padding: 12px 16px;
    background: #597ef7;
    color: #fff;
    font-weight: bold;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.shufei-ai-modal-close {
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0 4px;
    opacity: 0.85;
}
.shufei-ai-modal-close:hover { opacity: 1; }
.shufei-ai-modal-body {
    padding: 16px;
    overflow-y: auto;
    flex: 1;
}
.shufei-ai-modal-footer {
    padding: 10px 16px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    background: #fafafa;
}
.shufei-ai-style-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}
.shufei-ai-style-opt {
    padding: 10px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    font-size: 13px;
}
.shufei-ai-style-opt:hover { border-color: #597ef7; color: #597ef7; }
.shufei-ai-style-opt.active {
    background: #597ef7;
    color: #fff;
    border-color: #597ef7;
}
.shufei-ai-style-desc {
    font-size: 11px;
    color: #8c8c8c;
    margin-top: 2px;
}
.shufei-ai-style-opt.active .shufei-ai-style-desc { color: rgba(255,255,255,0.8); }
.shufei-ai-result {
    width: 100%;
    min-height: 200px;
    padding: 10px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    font-family: 'SFMono-Regular', Consolas, monospace;
    font-size: 13px;
    line-height: 1.6;
    resize: vertical;
    box-sizing: border-box;
}
.shufei-ai-loading {
    text-align: center;
    padding: 40px 20px;
    color: #8c8c8c;
}
.shufei-ai-loading .spinner {
    display: inline-block;
    width: 28px;
    height: 28px;
    border: 3px solid #d6e4ff;
    border-top-color: #597ef7;
    border-radius: 50%;
    animation: shufei-ai-spin 0.8s linear infinite;
    margin-bottom: 10px;
}
@keyframes shufei-ai-spin { to { transform: rotate(360deg); } }
.shufei-ai-status {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    display: none;
}
.shufei-ai-status.info { display: block; background: #e6f7ff; border: 1px solid #91d5ff; color: #096dd9; }
.shufei-ai-status.success { display: block; background: #f6ffed; border: 1px solid #b7eb8f; color: #389e0d; }
.shufei-ai-status.error { display: block; background: #fff2f0; border: 1px solid #ffccc7; color: #cf1322; }
.shufei-ai-meta {
    font-size: 12px;
    color: #8c8c8c;
    margin-top: 4px;
}
.shufei-ai-len-input {
    width: 80px;
    padding: 4px 8px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    font-size: 13px;
}
.shufei-ai-select-wrap {
    margin-bottom: 12px;
    font-size: 13px;
    color: #595959;
}
.shufei-ai-select-wrap select {
    padding: 4px 8px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    font-size: 13px;
    margin-left: 6px;
}
</style>

<!-- AI 工具栏（注入到编辑器上方） -->
<div class="shufei-ai-toolbar" id="shufei-ai-toolbar" style="display:none;">
    <span class="ai-label">✨ AI 助手</span>
    <button type="button" class="shufei-ai-btn beautify" data-ai-action="beautify">美化</button>
    <button type="button" class="shufei-ai-btn continue" data-ai-action="continue">续写</button>
    <button type="button" class="shufei-ai-btn check" data-ai-action="check">检查</button>
</div>

<!-- AI 浮窗 -->
<div class="shufei-ai-modal-mask" id="shufei-ai-mask"></div>
<div class="shufei-ai-modal" id="shufei-ai-modal">
    <div class="shufei-ai-modal-header">
        <span id="shufei-ai-modal-title">AI 助手</span>
        <span class="shufei-ai-modal-close" id="shufei-ai-close">×</span>
    </div>
    <div class="shufei-ai-modal-body" id="shufei-ai-modal-body">
        <!-- 内容动态注入 -->
    </div>
    <div class="shufei-ai-modal-footer" id="shufei-ai-modal-footer">
        <!-- 按钮动态注入 -->
    </div>
</div>

<script>
(function ($) {
    $(function () {
        var ajaxUrl = '<?php echo $aiAjaxUrl; ?>';
        var styles = <?php echo json_encode($styles); ?>;
        var styleDescs = {
            'literary': '用词优美典雅，善用比喻修辞',
            'professional': '严谨准确，逻辑清晰',
            'vivid': '生动活泼，富有画面感',
            'concise': '简练精炼，直击要点',
            'humorous': '幽默有趣，不失分寸',
            'warm': '温暖抒情，富有感染力'
        };
        var currentStyle = 'literary';
        var lastResult = '';
        var lastAction = '';

        // 将工具栏注入到编辑器上方
        function injectToolbar() {
            if ($('#shufei-ai-toolbar').data('injected')) return;
            var $text = $('#text');
            if (!$text.length) return;
            $('#shufei-ai-toolbar').insertBefore($text).show().data('injected', true);
        }
        injectToolbar();
        // 重试注入（等待编辑器初始化）
        setTimeout(injectToolbar, 500);

        // 获取编辑器内容（选区优先）
        function getContent() {
            var $text = $('#text');
            var ta = $text[0];
            if (!ta) return '';
            var start = ta.selectionStart;
            var end = ta.selectionEnd;
            if (start !== end) {
                return ta.value.substring(start, end);
            }
            return ta.value;
        }

        // 替换编辑器内容（选区优先替换选区，否则追加）
        function applyResult(result) {
            var $text = $('#text');
            var ta = $text[0];
            if (!ta) return;
            var start = ta.selectionStart;
            var end = ta.selectionEnd;
            if (start !== end) {
                // 有选区：替换选区
                var newVal = ta.value.substring(0, start) + result + ta.value.substring(end);
                ta.value = newVal;
                ta.selectionStart = start;
                ta.selectionEnd = start + result.length;
            } else if (lastAction === 'continue') {
                // 续写：追加到末尾
                ta.value = ta.value + '\n\n' + result;
                ta.scrollTop = ta.scrollHeight;
            } else {
                // 美化：替换全部内容
                ta.value = result;
            }
            // 触发 change 让预览更新
            $text.trigger('change').trigger('input');
        }

        // 显示浮窗
        function showModal(title, bodyHtml, footerHtml) {
            $('#shufei-ai-modal-title').text(title);
            $('#shufei-ai-modal-body').html(bodyHtml);
            $('#shufei-ai-modal-footer').html(footerHtml || '');
            $('#shufei-ai-mask, #shufei-ai-modal').addClass('show');
        }

        function closeModal() {
            $('#shufei-ai-mask, #shufei-ai-modal').removeClass('show');
        }

        function setStatus(msg, type) {
            $('#shufei-ai-status').removeClass('info success error').addClass(type || 'info').html(msg).show();
        }

        // 显示加载
        function showLoading(text) {
            $('#shufei-ai-modal-body').html(
                '<div class="shufei-ai-loading"><div class="spinner"></div><div>' + (text || 'AI 正在思考中...') + '</div></div>'
            );
        }

        // 调用 AI 接口
        function callAi(action, data, onDone) {
            var postData = 'action=' + encodeURIComponent(action);
            for (var k in data) {
                if (data.hasOwnProperty(k)) {
                    postData += '&' + k + '=' + encodeURIComponent(data[k]);
                }
            }
            showLoading(action === 'beautify' ? 'AI 正在美化文章...' :
                        action === 'continue' ? 'AI 正在续写文章...' :
                        action === 'check' ? 'AI 正在检查文章...' : 'AI 处理中...');
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: postData,
                dataType: 'json',
                timeout: 90000
            }).done(function (resp) {
                if (resp && resp.success) {
                    lastResult = resp.content || '';
                    onDone(resp);
                } else {
                    $('#shufei-ai-modal-body').html(
                        '<div class="shufei-ai-status error">✗ ' + (resp && resp.message ? resp.message : 'AI 请求失败') + '</div>' +
                        '<div style="text-align:right;margin-top:12px;"><button type="button" class="shufei-ai-btn" onclick="jQuery(\'#shufei-ai-mask, #shufei-ai-modal\').removeClass(\'show\');">关闭</button></div>'
                    );
                }
            }).fail(function (xhr) {
                var msg = '请求失败';
                try { var r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch (e) {}
                $('#shufei-ai-modal-body').html(
                    '<div class="shufei-ai-status error">✗ ' + msg + ' (HTTP ' + xhr.status + ')</div>' +
                    '<div style="text-align:right;margin-top:12px;"><button type="button" class="shufei-ai-btn" onclick="jQuery(\'#shufei-ai-mask, #shufei-ai-modal\').removeClass(\'show\');">关闭</button></div>'
                );
            });
        }

        // 美化弹窗
        function openBeautify() {
            var content = getContent();
            if (!content.trim()) { alert('请先输入文章内容，或选中要美化的段落'); return; }
            lastAction = 'beautify';
            var styleHtml = '<div style="margin-bottom:8px;font-weight:bold;">选择美化风格：</div><div class="shufei-ai-style-grid">';
            for (var k in styles) {
                if (!styles.hasOwnProperty(k)) continue;
                var cls = k === currentStyle ? ' active' : '';
                styleHtml += '<div class="shufei-ai-style-opt' + cls + '" data-style="' + k + '">'
                    + '<div>' + styles[k] + '</div>'
                    + '<div class="shufei-ai-style-desc">' + (styleDescs[k] || '') + '</div>'
                    + '</div>';
            }
            styleHtml += '</div>';
            styleHtml += '<div class="shufei-ai-status" id="shufei-ai-status"></div>';
            showModal('AI 美化文章', styleHtml,
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">取消</button>' +
                '<button type="button" class="shufei-ai-btn beautify" id="shufei-ai-run">开始美化</button>');

            // 风格选择
            $('.shufei-ai-style-opt').on('click', function () {
                $('.shufei-ai-style-opt').removeClass('active');
                $(this).addClass('active');
                currentStyle = $(this).data('style');
            });
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-run').on('click', function () {
                callAi('beautify', { content: content, style: currentStyle }, function (resp) {
                    showResultEditor(resp.content, '美化结果', true);
                });
            });
        }

        // 续写弹窗
        function openContinue() {
            var content = getContent();
            if (!content.trim()) { alert('请先输入文章内容，AI 将基于已有内容续写'); return; }
            lastAction = 'continue';
            var html = '<div class="shufei-ai-select-wrap">续写字数：' +
                '<input type="number" class="shufei-ai-len-input" id="shufei-ai-len" value="300" min="100" max="2000" step="50"> 字（建议 100-2000）</div>' +
                '<div class="shufei-ai-meta">AI 将基于当前内容（或选区）自然续写，续写内容将追加到原文末尾。</div>' +
                '<div class="shufei-ai-status" id="shufei-ai-status"></div>';
            showModal('AI 续写文章', html,
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">取消</button>' +
                '<button type="button" class="shufei-ai-btn continue" id="shufei-ai-run">开始续写</button>');
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-run').on('click', function () {
                var len = parseInt($('#shufei-ai-len').val(), 10) || 300;
                callAi('continue', { content: content, length: len }, function (resp) {
                    showResultEditor(resp.content, '续写结果', true);
                });
            });
        }

        // 检查弹窗
        function openCheck() {
            var content = getContent();
            if (!content.trim()) { alert('请先输入文章内容'); return; }
            lastAction = 'check';
            showModal('AI 文章检查', '<div class="shufei-ai-meta">AI 将检查文章的错别字、语法、逻辑等问题并给出修改建议。</div><div class="shufei-ai-status" id="shufei-ai-status"></div>',
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">取消</button>' +
                '<button type="button" class="shufei-ai-btn check" id="shufei-ai-run">开始检查</button>');
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-run').on('click', function () {
                callAi('check', { content: content }, function (resp) {
                    // 检查结果只读展示
                    showResultViewer(resp.content, '检查结果');
                });
            });
        }

        // 显示可编辑结果（美化/续写）
        function showResultEditor(result, title, canApply) {
            var footer = '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">关闭</button>';
            if (canApply) {
                footer = '<button type="button" class="shufei-ai-btn" id="shufei-ai-copy">复制结果</button>' +
                    '<button type="button" class="shufei-ai-btn beautify" id="shufei-ai-apply">应用到文章</button>' +
                    '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">关闭</button>';
            }
            var body = '<div class="shufei-ai-meta">可在此预览/编辑 AI 生成结果，确认后点击「应用到文章」。</div>' +
                '<textarea class="shufei-ai-result" id="shufei-ai-result-text">' + $('<div>').text(result).html() + '</textarea>' +
                '<div class="shufei-ai-meta">字数：' + result.length + '</div>';
            showModal(title, body, footer);
            $('#shufei-ai-cancel').on('click', closeModal);
            if (canApply) {
                $('#shufei-ai-copy').on('click', function () {
                    var txt = $('#shufei-ai-result-text').val();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(txt);
                    } else {
                        var $tmp = $('<textarea>').val(txt).appendTo('body').select();
                        document.execCommand('copy'); $tmp.remove();
                    }
                    $(this).text('已复制').prop('disabled', true);
                });
                $('#shufei-ai-apply').on('click', function () {
                    applyResult($('#shufei-ai-result-text').val());
                    closeModal();
                });
            }
        }

        // 显示只读结果（检查）
        function showResultViewer(result, title) {
            // 简单 Markdown 渲染（标题、列表、加粗、代码）
            function esc(s) { return $('<div>').text(s).html(); }
            var html = esc(result);
            html = html.replace(/^### (.+)$/gm, '<h4>$1</h4>');
            html = html.replace(/^## (.+)$/gm, '<h3>$1</h3>');
            html = html.replace(/^# (.+)$/gm, '<h2>$1</h2>');
            html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
            html = html.replace(/(<li>[\s\S]+?<\/li>)/g, '<ul>$1</ul>');
            html = html.replace(/\n\n/g, '</p><p>');
            html = '<div style="font-size:14px;line-height:1.8;color:#333;"><p>' + html + '</p></div>';
            var footer = '<button type="button" class="shufei-ai-btn" id="shufei-ai-copy">复制结果</button>' +
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">关闭</button>';
            showModal(title, html, footer);
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-copy').on('click', function () {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(result);
                } else {
                    var $tmp = $('<textarea>').val(result).appendTo('body').select();
                    document.execCommand('copy'); $tmp.remove();
                }
                $(this).text('已复制').prop('disabled', true);
            });
        }

        // 工具栏按钮事件
        $(document).on('click', '.shufei-ai-btn[data-ai-action]', function (e) {
            e.preventDefault();
            var action = $(this).data('ai-action');
            if (action === 'beautify') openBeautify();
            else if (action === 'continue') openContinue();
            else if (action === 'check') openCheck();
        });

        // 关闭事件
        $('#shufei-ai-close, #shufei-ai-mask').on('click', closeModal);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    });
})(jQuery);
</script>
<?php
}

/**
 * ===== 图片存储编辑器集成 =====
 * 在后台文章/页面编辑器中注入「图片上传」按钮与 Profile 切换下拉框
 * 仅当图片存储功能开启且存在已激活 Profile 时显示
 */

// 注册图片存储后台编辑器钩子（文章 + 页面）
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_storage_editor_ui';
\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_storage_editor_ui';

/**
 * 输出图片存储编辑器 UI（工具栏按钮 + Profile 切换 + 上传弹窗）
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_storage_editor_ui($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');

    // 仅当存储功能开启时注入
    $enabled = isset($options->shufeiStorageEnabled) ? $options->shufeiStorageEnabled : 'off';
    if ($enabled !== 'on') {
        return;
    }

    $ajaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/storage-ajax.php', $options->siteUrl);
    ?>
<style>
/* ===== 图片存储编辑器 UI ===== */
.shufei-storage-editor-toolbar {
    display: flex; gap: 8px; align-items: center; padding: 6px 8px;
    margin: 4px 0 8px; background: linear-gradient(135deg, #f0fff4 0%, #f0f7ff 100%);
    border: 1px solid #b7eb8f; border-radius: 6px; flex-wrap: wrap;
}
.shufei-storage-editor-toolbar .se-label {
    font-weight: bold; color: #389e0d; font-size: 13px; margin-right: 4px;
}
.shufei-storage-editor-toolbar select {
    padding: 4px 8px; border: 1px solid #d9d9d9; border-radius: 4px;
    font-size: 12px; background: #fff; max-width: 220px;
}
.shufei-storage-upload-btn {
    display: inline-block; padding: 4px 14px; font-size: 12px; color: #fff;
    background: #52c41a; border: 1px solid #52c41a; border-radius: 4px;
    cursor: pointer; transition: all 0.2s; line-height: 1.6;
}
.shufei-storage-upload-btn:hover {
    background: #389e0d; border-color: #389e0d;
    transform: translateY(-1px); box-shadow: 0 2px 6px rgba(82, 196, 26, 0.35);
}
.shufei-storage-status-tip { color: #888; font-size: 12px; margin-left: auto; }

/* 上传弹窗 */
.shufei-storage-up-modal-mask {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.45); z-index: 10010; display: none;
}
.shufei-storage-up-modal {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 640px; max-width: 92vw; max-height: 85vh; background: #fff;
    border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    z-index: 10011; display: none; flex-direction: column; overflow: hidden;
}
.shufei-storage-up-modal.show, .shufei-storage-up-modal-mask.show { display: flex; }
.shufei-storage-up-modal-header {
    padding: 12px 16px; background: #52c41a; color: #fff;
    font-weight: bold; font-size: 14px; display: flex;
    justify-content: space-between; align-items: center;
}
.shufei-storage-up-modal-close { cursor: pointer; font-size: 18px; line-height: 1; }
.shufei-storage-up-modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
.shufei-storage-up-modal-footer {
    padding: 12px 16px; border-top: 1px solid #f0f0f0; text-align: right;
}
.shufei-storage-dropzone {
    border: 2px dashed #b7eb8f; border-radius: 8px; padding: 30px 20px;
    text-align: center; color: #888; cursor: pointer; transition: all .2s;
    background: #fafafa;
}
.shufei-storage-dropzone:hover, .shufei-storage-dropzone.dragover {
    border-color: #52c41a; background: #f6ffed; color: #389e0d;
}
.shufei-storage-dropzone .dz-icon { font-size: 32px; margin-bottom: 8px; }
.shufei-storage-dropzone .dz-text { font-size: 13px; }
.shufei-storage-dropzone .dz-hint { font-size: 11px; color: #bbb; margin-top: 6px; }
.shufei-storage-up-list { margin-top: 12px; max-height: 280px; overflow-y: auto; }
.shufei-storage-up-item {
    display: flex; align-items: center; gap: 10px; padding: 8px 10px;
    border: 1px solid #f0f0f0; border-radius: 4px; margin-bottom: 6px; font-size: 12px;
}
.shufei-storage-up-item .up-name { flex: 1; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.shufei-storage-up-item .up-status { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
.shufei-storage-up-item .up-status.pending { background: #fff7e6; color: #d48806; }
.shufei-storage-up-item .up-status.uploading { background: #e6f7ff; color: #096dd9; }
.shufei-storage-up-item .up-status.success { background: #f6ffed; color: #389e0d; }
.shufei-storage-up-item .up-status.error { background: #fff2f0; color: #cf1322; }
.shufei-storage-up-item .up-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
.shufei-storage-up-options { margin-top: 12px; padding: 10px; background: #fafafa; border-radius: 4px; font-size: 12px; color: #666; }
.shufei-storage-up-options label { margin-right: 12px; cursor: pointer; }

/* 历史图片按钮 */
.shufei-storage-history-btn {
    display: inline-block; padding: 4px 14px; font-size: 12px; color: #fff;
    background: #1890ff; border: 1px solid #1890ff; border-radius: 4px;
    cursor: pointer; transition: all 0.2s; line-height: 1.6;
}
.shufei-storage-history-btn:hover {
    background: #096dd9; border-color: #096dd9;
    transform: translateY(-1px); box-shadow: 0 2px 6px rgba(24, 144, 255, 0.35);
}

/* 历史图片弹窗 */
.shufei-storage-hist-modal-mask {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.45); z-index: 10012; display: none;
}
.shufei-storage-hist-modal {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 880px; max-width: 94vw; max-height: 85vh; background: #fff;
    border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    z-index: 10013; display: none; flex-direction: column; overflow: hidden;
}
.shufei-storage-hist-modal.show, .shufei-storage-hist-modal-mask.show { display: flex; }
.shufei-storage-hist-modal-header {
    padding: 12px 16px; background: #1890ff; color: #fff;
    font-weight: bold; font-size: 14px; display: flex;
    justify-content: space-between; align-items: center;
}
.shufei-storage-hist-modal-close { cursor: pointer; font-size: 18px; line-height: 1; }
.shufei-storage-hist-modal-body { padding: 12px 16px; overflow-y: auto; flex: 1; }
.shufei-storage-hist-toolbar {
    display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 12px;
}
.shufei-storage-hist-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px;
}
.shufei-storage-hist-cell {
    position: relative; border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden; background: #fafafa;
}
.shufei-storage-hist-cell img {
    width: 100%; height: 120px; object-fit: cover; cursor: pointer; display: block; background: #fff;
}
.shufei-storage-hist-cell .hist-ph {
    width: 100%; height: 120px; display: none; align-items: center; justify-content: center;
    color: #999; font-size: 11px; background: #f0f0f0;
}
.shufei-storage-hist-cell .hist-del {
    position: absolute; top: 2px; right: 4px; width: 22px; height: 22px; line-height: 20px;
    text-align: center; background: rgba(0,0,0,0.55); color: #fff; border-radius: 50%; cursor: pointer; font-size: 16px;
}
.shufei-storage-hist-cell .hist-insert {
    position: absolute; bottom: 32px; left: 4px; right: 4px; padding: 3px 0;
    background: rgba(24,144,255,0.9); color: #fff; font-size: 11px; text-align: center;
    cursor: pointer; opacity: 0; transition: opacity .2s;
}
.shufei-storage-hist-cell:hover .hist-insert { opacity: 1; }
.shufei-storage-hist-cell .hist-info {
    padding: 4px 6px; font-size: 10px; color: #666; border-top: 1px solid #eee; background: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.shufei-storage-hist-empty { color: #999; text-align: center; padding: 40px; grid-column: 1/-1; }
</style>

<div class="shufei-storage-editor-toolbar" id="shufei-storage-editor-toolbar" style="display:none;">
    <span class="se-label">图片存储</span>
    <select id="shufei-storage-profile-select" title="切换存储 Profile"></select>
    <button type="button" class="shufei-storage-upload-btn" id="shufei-storage-open-upload-btn">上传图片</button>
    <button type="button" class="shufei-storage-history-btn" id="shufei-storage-open-history-btn">历史图片</button>
    <span class="shufei-storage-status-tip" id="shufei-storage-status-tip"></span>
</div>

<div class="shufei-storage-up-modal-mask" id="shufei-storage-up-mask"></div>
<div class="shufei-storage-up-modal" id="shufei-storage-up-modal">
    <div class="shufei-storage-up-modal-header">
        <span>上传图片到存储</span>
        <span class="shufei-storage-up-modal-close" id="shufei-storage-up-close">×</span>
    </div>
    <div class="shufei-storage-up-modal-body">
        <div class="shufei-storage-up-options">
            <label><input type="checkbox" id="shufei-up-insert-markdown" checked> 上传后插入 Markdown</label>
            <label><input type="checkbox" id="shufei-up-insert-newline" checked> 每张图独占一行</label>
        </div>
        <div class="shufei-storage-dropzone" id="shufei-storage-dropzone">
            <div class="dz-icon">📁</div>
            <div class="dz-text">点击选择图片，或将图片拖拽到此处</div>
            <div class="dz-hint">支持 JPG / PNG / GIF / WEBP / BMP，可多选</div>
            <input type="file" id="shufei-storage-file-input" accept="image/*" multiple style="display:none;">
        </div>
        <div class="shufei-storage-up-list" id="shufei-storage-up-list"></div>
    </div>
    <div class="shufei-storage-up-modal-footer">
        <button type="button" class="shufei-storage-upload-btn" id="shufei-storage-start-upload-btn" style="background:#597ef7;border-color:#597ef7;">开始上传</button>
        <button type="button" id="shufei-storage-up-cancel-btn"
            style="padding:4px 14px;font-size:12px;color:#595959;background:#fff;border:1px solid #d9d9d9;border-radius:4px;cursor:pointer;">关闭</button>
    </div>
</div>

<!-- 历史图片弹窗 -->
<div class="shufei-storage-hist-modal-mask" id="shufei-storage-hist-mask"></div>
<div class="shufei-storage-hist-modal" id="shufei-storage-hist-modal">
    <div class="shufei-storage-hist-modal-header">
        <span>历史图片 — 点击图片插入 Markdown / 点击右上角 &times; 删除</span>
        <span class="shufei-storage-hist-modal-close" id="shufei-storage-hist-close">×</span>
    </div>
    <div class="shufei-storage-hist-modal-body">
        <div class="shufei-storage-hist-toolbar">
            <button type="button" id="shufei-hist-prev" class="btn" style="padding:3px 10px;font-size:12px;">&laquo; 上一页</button>
            <span id="shufei-hist-page" style="color:#666;">-</span>
            <button type="button" id="shufei-hist-next" class="btn" style="padding:3px 10px;font-size:12px;">下一页 &raquo;</button>
            <button type="button" id="shufei-hist-refresh" class="btn primary" style="padding:3px 10px;font-size:12px;">刷新</button>
            <span id="shufei-hist-status" style="margin-left:auto;color:#999;"></span>
        </div>
        <div class="shufei-storage-hist-grid" id="shufei-storage-hist-grid">
            <div class="shufei-storage-hist-empty">点击「刷新」加载已上传图片</div>
        </div>
    </div>
</div>

<script>
(function(){
    if (window.__shufeiStorageEditorInit) return;
    window.__shufeiStorageEditorInit = true;

    var AJAX_URL = <?php echo json_encode($ajaxUrl); ?>;
    var pendingFiles = [];
    var uploadResults = [];

    function $(id){ return document.getElementById(id); }

    function insertTextToEditor(text){
        var ta = document.getElementById('text') || document.querySelector('textarea[name=text]');
        if (!ta) { alert('未找到编辑器文本框'); return; }
        if (document.selection) {
            ta.focus();
            var sel = document.selection.createRange();
            sel.text = text;
            ta.focus();
        } else if (ta.selectionStart || ta.selectionStart === 0) {
            var startPos = ta.selectionStart;
            var endPos = ta.selectionEnd;
            var scrollTop = ta.scrollTop;
            ta.value = ta.value.substring(0, startPos) + text + ta.value.substring(endPos, ta.value.length);
            ta.focus();
            ta.selectionStart = startPos + text.length;
            ta.selectionEnd = startPos + text.length;
            ta.scrollTop = scrollTop;
        } else {
            ta.value += text;
            ta.focus();
        }
        // 触发 input 事件以便编辑器预览同步
        if (typeof Event !== 'undefined') {
            var ev = new Event('input', { bubbles: true });
            ta.dispatchEvent(ev);
        }
    }

    // 加载 Profile 列表
    function loadProfiles(){
        var fd = new FormData();
        fd.append('action', 'list_profiles');
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.success) {
                    $('shufei-storage-status-tip').textContent = d.message || '加载失败';
                    return;
                }
                if (!d.enabled) { return; }
                if (!d.profiles || d.profiles.length === 0) {
                    $('shufei-storage-status-tip').textContent = '尚未配置任何 Profile，请到主题设置中添加';
                    $('shufei-storage-editor-toolbar').style.display = 'flex';
                    return;
                }
                var sel = $('shufei-storage-profile-select');
                sel.innerHTML = '';
                d.profiles.forEach(function(p){
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name + '（' + (p.driverName || p.driver) + '）';
                    if (p.id === d.activeProfileId) opt.selected = true;
                    sel.appendChild(opt);
                });
                // 处理选项提示
                var tips = [];
                if (d.processing && d.processing.compress === 'on') tips.push('压缩');
                if (d.processing && d.processing.webp === 'on') tips.push('WebP');
                if (d.processing && d.processing.watermark === 'on') tips.push('水印');
                $('shufei-storage-status-tip').textContent = tips.length ? '已启用：' + tips.join(' / ') : '';
                $('shufei-storage-editor-toolbar').style.display = 'flex';
            })
            .catch(function(e){
                $('shufei-storage-status-tip').textContent = '加载失败: ' + e.message;
            });
    }

    // 切换 Profile
    $('shufei-storage-profile-select').addEventListener('change', function(){
        var pid = this.value;
        if (!pid) return;
        var fd = new FormData();
        fd.append('action', 'switch_profile');
        fd.append('profile_id', pid);
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                $('shufei-storage-status-tip').textContent = d.message || (d.success ? '已切换' : '切换失败');
            })
            .catch(function(e){ $('shufei-storage-status-tip').textContent = '切换失败: ' + e.message; });
    });

    // 上传弹窗
    function openUploadModal(){
        pendingFiles = [];
        uploadResults = [];
        $('shufei-storage-up-list').innerHTML = '';
        $('shufei-storage-up-mask').classList.add('show');
        $('shufei-storage-up-modal').classList.add('show');
    }
    function closeUploadModal(){
        $('shufei-storage-up-mask').classList.remove('show');
        $('shufei-storage-up-modal').classList.remove('show');
    }

    // ===== 历史图片 =====
    var histPage = 1, histLimit = 30, histTotal = 0;
    function histStatus(msg, color){
        var el = $('shufei-hist-status');
        el.textContent = msg || '';
        el.style.color = color || '#999';
    }
    function humanSize(b){
        if (!b) return '0 B';
        if (b < 1024) return b + ' B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
        return (b/1024/1024).toFixed(2) + ' MB';
    }
    function openHistoryModal(){
        $('shufei-storage-hist-mask').classList.add('show');
        $('shufei-storage-hist-modal').classList.add('show');
        if (!histTotal) loadHistory();
    }
    function closeHistoryModal(){
        $('shufei-storage-hist-mask').classList.remove('show');
        $('shufei-storage-hist-modal').classList.remove('show');
    }
    function loadHistory(){
        var sel = $('shufei-storage-profile-select');
        var pid = sel ? sel.value : '';
        if (!pid){ histStatus('请先选择 Profile', '#c00'); return; }
        histStatus('加载中...', '#999');
        var grid = $('shufei-storage-hist-grid');
        grid.innerHTML = '<div class="shufei-storage-hist-empty">加载中...</div>';
        var fd = new FormData();
        fd.append('action', 'list_images');
        fd.append('profile_id', pid);
        fd.append('page', histPage);
        fd.append('limit', histLimit);
        fetch(AJAX_URL, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                if (!res || !res.success){
                    histStatus(res && res.message ? res.message : '加载失败', '#c00');
                    grid.innerHTML = '<div class="shufei-storage-hist-empty">'+(res&&res.message?res.message:'加载失败')+'</div>';
                    return;
                }
                var data = res.data || {};
                histTotal = data.total || 0;
                var list = data.list || [];
                histStatus('共 ' + histTotal + ' 张', '#080');
                $('shufei-hist-page').textContent = '第 ' + histPage + '/' + Math.max(1, Math.ceil(histTotal/histLimit)) + ' 页';
                if (!list.length){
                    grid.innerHTML = '<div class="shufei-storage-hist-empty">暂无图片</div>';
                    return;
                }
                grid.innerHTML = '';
                list.forEach(function(item){
                    var cell = document.createElement('div');
                    cell.className = 'shufei-storage-hist-cell';
                    var img = document.createElement('img');
                    img.src = item.url; img.loading = 'lazy';
                    img.title = '点击插入 ' + (item.name||'');
                    img.onerror = function(){ img.style.display='none'; cell.querySelector('.hist-ph').style.display='flex'; };
                    img.onclick = function(){ insertImageMarkdown(item.url, item.name||''); closeHistoryModal(); };
                    var ph = document.createElement('div');
                    ph.className = 'hist-ph';
                    ph.textContent = '图片加载失败';
                    var ins = document.createElement('div');
                    ins.className = 'hist-insert';
                    ins.textContent = '插入';
                    ins.onclick = function(e){ e.stopPropagation(); insertImageMarkdown(item.url, item.name||''); closeHistoryModal(); };
                    var del = document.createElement('div');
                    del.className = 'hist-del';
                    del.innerHTML = '&times;';
                    del.title = '删除该图片';
                    del.onclick = function(e){
                        e.stopPropagation();
                        if (!confirm('确定删除这张图片吗？此操作不可恢复。\n\n' + (item.name||''))) return;
                        del.textContent = '...';
                        var fd2 = new FormData();
                        fd2.append('action', 'delete_image');
                        fd2.append('profile_id', pid);
                        if (item.id) fd2.append('image_id', item.id);
                        if (item.key) fd2.append('key', item.key);
                        if (item.url) fd2.append('url', item.url);
                        fetch(AJAX_URL, {method:'POST', body:fd2, credentials:'same-origin'})
                            .then(function(r){return r.json();})
                            .then(function(res2){
                                if (res2 && res2.success){
                                    histStatus('已删除', '#080');
                                    cell.style.transition='opacity .3s'; cell.style.opacity='0';
                                    setTimeout(function(){ cell.remove(); histTotal--; histStatus('共 ' + histTotal + ' 张', '#080'); }, 300);
                                } else {
                                    histStatus(res2 && res2.message ? res2.message : '删除失败', '#c00');
                                    del.innerHTML = '&times;';
                                }
                            })
                            .catch(function(err){
                                histStatus('网络错误: ' + err.message, '#c00');
                                del.innerHTML = '&times;';
                            });
                    };
                    var info = document.createElement('div');
                    info.className = 'hist-info';
                    info.title = item.name||'';
                    info.textContent = (item.name||'') + ' · ' + humanSize(item.size);
                    cell.appendChild(img);
                    cell.appendChild(ph);
                    cell.appendChild(ins);
                    cell.appendChild(del);
                    cell.appendChild(info);
                    grid.appendChild(cell);
                });
            })
            .catch(function(err){
                histStatus('网络错误: ' + err.message, '#c00');
                grid.innerHTML = '<div class="shufei-storage-hist-empty">网络错误</div>';
            });
    }
    function insertImageMarkdown(url, name){
        var md = '![' + (name||'') + '](' + url + ')\n';
        insertTextToEditor(md);
        histStatus('已插入: ' + (name||'图片'), '#080');
    }

    $('shufei-storage-open-upload-btn').addEventListener('click', openUploadModal);
    $('shufei-storage-up-close').addEventListener('click', closeUploadModal);
    $('shufei-storage-up-cancel-btn').addEventListener('click', closeUploadModal);
    $('shufei-storage-up-mask').addEventListener('click', closeUploadModal);

    $('shufei-storage-open-history-btn').addEventListener('click', openHistoryModal);
    $('shufei-storage-hist-close').addEventListener('click', closeHistoryModal);
    $('shufei-storage-hist-mask').addEventListener('click', closeHistoryModal);
    $('shufei-hist-refresh').addEventListener('click', function(){ histPage = 1; loadHistory(); });
    $('shufei-hist-prev').addEventListener('click', function(){ if (histPage > 1){ histPage--; loadHistory(); } });
    $('shufei-hist-next').addEventListener('click', function(){ if (histPage * histLimit < histTotal){ histPage++; loadHistory(); } });

    // 文件选择
    var dropzone = $('shufei-storage-dropzone');
    var fileInput = $('shufei-storage-file-input');
    dropzone.addEventListener('click', function(){ fileInput.click(); });
    fileInput.addEventListener('change', function(){
        if (this.files && this.files.length) addFiles(this.files);
        this.value = '';
    });
    ['dragenter','dragover'].forEach(function(ev){
        dropzone.addEventListener(ev, function(e){ e.preventDefault(); dropzone.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(function(ev){
        dropzone.addEventListener(ev, function(e){ e.preventDefault(); dropzone.classList.remove('dragover'); });
    });
    dropzone.addEventListener('drop', function(e){
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            addFiles(e.dataTransfer.files);
        }
    });

    function addFiles(fileList){
        for (var i = 0; i < fileList.length; i++) {
            var f = fileList[i];
            if (!f.type.startsWith('image/')) continue;
            pendingFiles.push({ file: f, status: 'pending', url: '', error: '' });
        }
        renderList();
    }

    function renderList(){
        var list = $('shufei-storage-up-list');
        list.innerHTML = '';
        pendingFiles.forEach(function(item, idx){
            var div = document.createElement('div');
            div.className = 'shufei-storage-up-item';
            var statusText = { pending: '待上传', uploading: '上传中', success: '成功', error: '失败' }[item.status] || item.status;
            var html = '';
            if (item.url) {
                html += '<img class="up-thumb" src="' + escapeAttr(item.url) + '">';
            } else if (item.file.type.startsWith('image/')) {
                html += '<img class="up-thumb" src="' + escapeAttr(URL.createObjectURL(item.file)) + '">';
            }
            html += '<span class="up-name">' + escapeHtml(item.file.name) + ' (' + formatSize(item.file.size) + ')</span>';
            html += '<span class="up-status ' + item.status + '">' + statusText + (item.error ? ': ' + escapeHtml(item.error) : '') + '</span>';
            div.innerHTML = html;
            list.appendChild(div);
        });
    }

    function escapeHtml(s){
        s = (s === null || s === undefined) ? '' : String(s);
        return s.replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function escapeAttr(s){ return escapeHtml(s).replace(/"/g, '&quot;'); }
    function formatSize(b){
        if (b < 1024) return b + 'B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + 'KB';
        return (b/1024/1024).toFixed(2) + 'MB';
    }

    // 上传逻辑：逐个串行上传
    $('shufei-storage-start-upload-btn').addEventListener('click', function(){
        if (pendingFiles.length === 0) { alert('请先选择图片'); return; }
        var profileId = $('shufei-storage-profile-select').value;
        if (!profileId) { alert('请先选择存储 Profile'); return; }
        var insertMd = $('shufei-up-insert-markdown').checked;
        var insertNewline = $('shufei-up-insert-newline').checked;
        var mdText = '';

        var idx = 0;
        function next(){
            if (idx >= pendingFiles.length) {
                // 全部完成，插入 Markdown
                if (insertMd && mdText) {
                    insertTextToEditor(mdText);
                }
                var ok = pendingFiles.filter(function(x){ return x.status === 'success'; }).length;
                var fail = pendingFiles.filter(function(x){ return x.status === 'error'; }).length;
                alert('上传完成：成功 ' + ok + ' 个' + (fail ? '，失败 ' + fail + ' 个' : ''));
                if (fail === 0) {
                    closeUploadModal();
                }
                return;
            }
            var item = pendingFiles[idx];
            if (item.status === 'success') { idx++; next(); return; }
            item.status = 'uploading';
            renderList();

            var fd = new FormData();
            fd.append('action', 'upload_image');
            fd.append('profile_id', profileId);
            fd.append('file', item.file, item.file.name);

            fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d.success) {
                        item.status = 'success';
                        item.url = d.url;
                        if (insertMd) {
                            mdText += '![](' + d.url + ')';
                            if (insertNewline) mdText += '\n\n';
                        }
                    } else {
                        item.status = 'error';
                        item.error = d.message || '上传失败';
                    }
                    renderList();
                    idx++; next();
                })
                .catch(function(e){
                    item.status = 'error';
                    item.error = e.message;
                    renderList();
                    idx++; next();
                });
        }
        next();
    });

    // ESC 关闭
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeUploadModal();
    });

    // 等编辑器加载完成后初始化
    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }

    function init(){
        // 将工具条移动到编辑器 textarea 上方（紧贴标题下方、内容区上方）
        var toolbar = $('shufei-storage-editor-toolbar');
        var ta = document.getElementById('text');
        if (ta && ta.parentNode) {
            ta.parentNode.insertBefore(toolbar, ta);
        }
        loadProfiles();
    }
})();
</script>
<?php
}

/**
 * ===== 历史文章修改后排序前置 =====
 * Typecho 核心在 Widget\Archive::execute() 中按 table.contents.created DESC 排序文章列表，
 * 修改历史文章不会更新 created 字段（只更新 modified），导致修改后的文章无法排到列表前面。
 *
 * 通过 query 钩子（在 Archive.php:1421 的 $this->query($select) 中触发）覆盖排序字段：
 *  1. cleanAttribute('order') 清除核心设置的 ORDER BY created DESC（Query::order() 是追加而非覆盖）
 *  2. order('table.contents.modified', DESC) 改为按修改时间降序
 *  3. 自行调用 $db->fetchAll($select, [$archive, 'push'])（query 钩子是 trigger 模式，
 *     注册回调后默认的 fetchAll 不会执行）
 *
 * 覆盖范围：首页、分类页、标签页、搜索结果、日期归档、作者文章页
 * 不影响：侧边栏最新文章（Widget\Comments\Recent 无此钩子）、后台管理列表
 *
 * 注意：handleInit 钩子（Archive.php:651）在 functions.php 加载前触发，无法用于此处。
 */
\Typecho\Plugin::factory('Widget\Archive')->query = function ($archive, $select) {
    // 仅对文章列表查询生效（避免误改其他 query 调用）
    $select->cleanAttribute('order');
    $select->order('table.contents.modified', \Typecho\Db::SORT_DESC);
    $db = \Typecho\Db::get();
    $db->fetchAll($select, [$archive, 'push']);
};

/**
 * ===== 修复编辑器插入图片/媒体后跳转到底部的问题 =====
 * 在后台文章/页面 Markdown 编辑器中，通过工具栏按钮（图片/链接等）插入内容后，
 * 编辑器显示会自动跳转到文章底部，非常不便。
 *
 * 跳转由两个机制叠加导致：
 *  1. 点击按钮时 pagedown 调用 l.focus() 让 textarea 获得焦点，浏览器自动滚动让光标可见
 *  2. 插入内容后 input 事件触发预览刷新，onPreviewRefresh 钩子调用 reloadScroll(true)，
 *     再加上图片异步加载完成后的二次 reloadScroll
 *
 * 修复策略：在 bottom 钩子（editor-js.php 和 file-upload-js.php 之后、footer.php 之前）注入 JS：
 *  - 工具栏点击事件（捕获阶段）保存滚动位置
 *  - textarea focus 事件恢复滚动位置（抵消 focus 导致的跳转）
 *  - textarea input 事件多次恢复滚动位置（抵消同步/异步 reloadScroll）
 *  - 包装 Typecho.uploadComplete / Typecho.insertFileToEditor 处理附件插入
 */
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_editor_cursor_fix';
\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_editor_cursor_fix';

/**
 * 输出编辑器光标位置修复 JS
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_editor_cursor_fix($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    if (empty($options->markdown)) {
        return;
    }
    ?>
<script>
/* ===== 修复编辑器插入图片/媒体后跳转到底部 =====
 * 跳转由多个机制叠加导致：
 *  1. ShuFeiCat insertAtCursor 中 textarea.setSelection() → jQuery.focus() + setSelectionRange()
 *     浏览器自动滚动 textarea 让光标可见
 *  2. textarea.trigger('input') → pagedown 刷新预览 → onPreviewRefresh → reloadScroll(true)
 *     reloadScroll 设置 preview.scrollTop 到光标对应行
 *  3. pagedown 的 input 处理：r.preview.scrollTop = (scrollHeight-clientHeight) * f(r.preview)
 *     重置 preview 滚动位置
 *  4. pagedown 的 window.scrollBy(0, t-n) 窗口滚动补偿
 *  5. scrollableEditor 的 p() 函数用 requestAnimationFrame + a.scrollTo({top: ...})
 *     在 500ms 内平滑滚动 textarea（这是 textarea 跳到末尾的真正根因）
 *
 * 修复策略：
 *  - 临时覆盖 textarea.scrollTo 为 no-op，让 p() 的 a.scrollTo({top: ...}) 失效
 *  - 用 setInterval 持续恢复 textarea/preview/window 三个元素滚动位置作双保险
 *  - 1200ms 后恢复原生 scrollTo 并停止持续恢复
 */
(function () {
    if (window.__shufeiEditorCursorFixBound) return;
    window.__shufeiEditorCursorFixBound = true;

    var ta = document.getElementById('text');
    var toolbar = document.getElementById('wmd-button-bar');
    if (!ta) return;

    // 预览区元素（pagedown 的 #wmd-preview）
    function getPreview() {
        return document.getElementById('wmd-preview');
    }

    // 状态变量
    var savedScrollY = 0;        // 窗口滚动位置
    var savedTaScroll = 0;       // textarea 滚动位置
    var savedPreviewScroll = 0;  // preview 滚动位置
    var pendingInsert = false;   // 有插入操作待处理
    var pendingTimeoutId = null; // pendingInsert 自动清除定时器
    var restoreIntervalId = null;// 持续恢复的 setInterval ID

    /**
     * 保存当前所有滚动位置
     */
    function saveScrollPositions() {
        savedScrollY = window.scrollY;
        savedTaScroll = ta.scrollTop;
        var preview = getPreview();
        savedPreviewScroll = preview ? preview.scrollTop : 0;
    }

    /**
     * 恢复所有滚动位置（窗口 + textarea + preview）
     */
    function restoreScroll() {
        window.scrollTo(0, savedScrollY);
        ta.scrollTop = savedTaScroll;
        var preview = getPreview();
        if (preview) preview.scrollTop = savedPreviewScroll;
    }

    /**
     * 清除 pendingInsert 标志，停止持续恢复
     */
    function clearPending() {
        pendingInsert = false;
        if (pendingTimeoutId) {
            clearTimeout(pendingTimeoutId);
            pendingTimeoutId = null;
        }
        if (restoreIntervalId) {
            clearInterval(restoreIntervalId);
            restoreIntervalId = null;
        }
    }

    /**
     * 设置 pendingInsert 标志，启动 60 秒超时自动清除
     */
    function setPending() {
        pendingInsert = true;
        if (pendingTimeoutId) clearTimeout(pendingTimeoutId);
        pendingTimeoutId = setTimeout(clearPending, 60000);
    }

    /* 1. 监听工具栏按钮点击 - 保存滚动位置
     * （insertAtCursor 中已有完整的保存/恢复逻辑，这里仅作辅助）
     */
    function handleToolbarClick(e) {
        var target = e.target;
        if (!target || !target.closest) return;
        var btn = target.closest('li[id^="wmd-"]') ||
                  target.closest('.shufei-qi-btn') ||
                  target.closest('.shufei-ai-btn');
        if (!btn) return;
        saveScrollPositions();
    }
    if (toolbar) {
        toolbar.addEventListener('click', handleToolbarClick, true);
    }
    document.addEventListener('click', handleToolbarClick, true);

    /* 2. input 事件 - 恢复滚动位置（双保险，覆盖附件插入等情况） */
    ta.addEventListener('input', function () {
        if (!pendingInsert) return;
        restoreScroll();
        // 重置定时器
        if (pendingTimeoutId) clearTimeout(pendingTimeoutId);
        pendingTimeoutId = setTimeout(function () {
            restoreScroll();
            clearPending();
        }, 2000);
    });

    /* 3. 处理附件插入（Typecho.insertFileToEditor / uploadComplete）
     * 这两个函数在 editor-js.php 中被重定义，可能在 bottom 钩子之后
     */
    function wrapTypechoFuncs() {
        if (!window.Typecho) return;
        // 包装 insertFileToEditor
        if (typeof Typecho.insertFileToEditor === 'function' && !Typecho.__shufeiWrapped_insert) {
            var origInsert = Typecho.insertFileToEditor;
            Typecho.insertFileToEditor = function () {
                saveScrollPositions();
                setPending();
                return origInsert.apply(this, arguments);
            };
            Typecho.__shufeiWrapped_insert = true;
        }
        // 包装 uploadComplete
        if (typeof Typecho.uploadComplete === 'function' && !Typecho.__shufeiWrapped_upload) {
            var origUpload = Typecho.uploadComplete;
            Typecho.uploadComplete = function () {
                saveScrollPositions();
                setPending();
                return origUpload.apply(this, arguments);
            };
            Typecho.__shufeiWrapped_upload = true;
        }
    }
    // 立即尝试包装
    wrapTypechoFuncs();
    // editor-js.php 可能在本脚本之后重定义这些函数，延迟再包装一次
    setTimeout(wrapTypechoFuncs, 0);
    setTimeout(wrapTypechoFuncs, 500);
})();
</script>
<?php
}
