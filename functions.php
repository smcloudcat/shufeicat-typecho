<?php
/**
 * ShuFeiCat 主题 核心函数文件
 * 
 * 包含后台配置、分类 UI、核心业务逻辑钩子
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;


/**
 * 主题后台配置函数
 */
function themeConfig($form)
{
    $options = \Typecho\Widget::widget('Widget_Options');
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
        // ===== 提交按钮区：悬浮固定底部 =====
        '.typecho-option-submit { position: sticky; bottom: 0; z-index: 100; background: rgba(255,255,255,0.92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); padding: 12px 20px; border: 1px solid #e8e8e8; border-radius: 4px; text-align: right; margin-top: 12px; box-shadow: 0 -2px 10px rgba(0,0,0,0.06); }' .
        '.typecho-option-submit button { background: #467B96 !important; border: none !important; color: #fff !important; padding: 0 28px !important; height: 38px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 4px !important; cursor: pointer !important; font-weight: 500 !important; font-size: 13px !important; transition: background .15s !important; vertical-align: middle !important; margin: 0 !important; line-height: 1 !important; text-decoration: none !important; outline: none !important; }' .
        '.typecho-option-submit button:hover { background: #3a6478 !important; }' .
        '.typecho-option-submit button:disabled { opacity: 0.6; cursor: not-allowed; }' .
        // ===== 保存结果 Toast 提示 =====
        '.cat-save-toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 10px 20px; border-radius: 4px; font-size: 13px; z-index: 10000; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-weight: 500; }' .
        '.cat-save-toast.show { display: block; animation: catToastIn .2s ease; }' .
        '.cat-save-toast.success { background: #f6ffed; border: 1px solid #b7eb8f; color: #389e0d; }' .
        '.cat-save-toast.error { background: #fff2f0; border: 1px solid #ffccc7; color: #cf1322; }' .
        '.cat-save-toast i { margin-right: 6px; }' .
        '@keyframes catToastIn { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }' .
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
                    '<li data-id="cat-seo">SEO 设置</li>' .
                    '<li data-id="cat-mail">评论邮件通知</li>' .
                    '<li data-id="cat-ai">AI 助手</li>' .
                    '<li data-id="cat-storage">图片存储</li>' .
                    '<li data-id="cat-verify">人机验证</li>' .
                    '<li data-id="cat-enhance">功能增强</li>' .
                    '<li data-id="cat-data">数据管理</li>' .
                    '<li data-id="cat-update">更新设置</li>' .
                '</ul>' .
            '</div>' .
            '<div class="cat-config-main" id="cat-panes"></div>' .
        '</div>' .
    '</div>';
    echo $html;

    echo '<script src="' . $options->themeUrl . '/assets/js/admin/theme-settings.js?v=' . shufei_get_theme_version() . '"></script>';


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

    echo '<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {version: ' . json_encode(shufei_get_theme_version()) . '});</script>';
    echo '<script src="' . $options->themeUrl . '/assets/js/admin/data-management.js?v=' . shufei_get_theme_version() . '"></script>';


    // GitHub 项目选择器
    $githubReposHtml = '<div class="typecho-option cat-group-sidebar-github-selector" data-toggle-dep="githubEnabled" style="display:none">' .
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

    echo '<script src="' . $options->themeUrl . '/assets/js/admin/github-selector.js?v=' . shufei_get_theme_version() . '"></script>';


    // 邮件测试功能
    $mailTestAjaxUrl = rtrim($options->themeUrl, '/') . '/core/mail-test-ajax.php';
    $mailTestHtml = '<div class="typecho-option cat-group-mail" id="cat-mail-test-wrap" style="display:none" data-ajax="' . htmlspecialchars($mailTestAjaxUrl) . '">' .
        '<label class="typecho-label">发送测试邮件</label>' .
        '<div class="description" style="margin-bottom:12px;">填写一个测试接收邮箱，点击下方按钮即可使用当前已保存的 SMTP 配置发送一封测试邮件，用于验证邮件功能是否正常。<b>修改下方 SMTP 配置后，请先点击页面底部「保存设置」再测试。</b></div>' .
        '<div class="cat-data-section" style="padding:0;border:none;">' .
            '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">' .
                '<input type="text" id="cat-mail-test-to" placeholder="例如：test@example.com" style="flex:1;min-width:220px;padding:8px 10px;font-size:13px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />' .
                '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-mail-test-btn"><i class="fa fa-paper-plane" style="margin-right:6px"></i>发送测试邮件</button>' .
            '</div>' .
            '<div class="cat-data-status" id="cat-mail-test-status"></div>' .
        '</div>' .
    '</div>';
    echo $mailTestHtml;

    echo '<script src="' . $options->themeUrl . '/assets/js/admin/mail-test.js?v=' . shufei_get_theme_version() . '"></script>';


    $currentVersion = shufei_get_theme_version();
    $updateCfg = shufei_get_update_config();
    $updateChannel = $updateCfg['channel'];
    $updateApiUrlVal = $updateCfg['apiUrl'];
    $updateOwnerVal = $updateCfg['owner'];
    $updateRepoVal = $updateCfg['repo'];
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
        '.shufei-update-notice.latest{background:#e6f7ff;border-color:#91d5ff;color:#096dd9;}' .
        '.shufei-update-notice.info{background:#e6f7ff;border-color:#91d5ff;color:#096dd9;}' .
        '.shufei-update-notice.error{background:#fff2f0;border-color:#ffccc7;color:#cf1322;}' .
        '.shufei-update-notice a{color:#467B96;}' .
        '.shufei-update-notice.loading{background:#fafafa;border-color:#e8e8e8;color:#999;}' .
        '.shufei-update-notice.loading::before{content:"";display:inline-block;width:14px;height:14px;border:2px solid #ccc;border-top-color:#467B96;border-radius:50%;animation:shufeiSpin .6s linear infinite;margin-right:8px;vertical-align:-2px;}' .
        '@keyframes shufeiSpin{to{transform:rotate(360deg)}}' .
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

    // 统一渲染完整 data 属性，初始状态由 JS 根据 radio 当前值决定
    echo '<div class="shufei-update-notice loading" id="shufei-update-notice"'
        . ' data-api="' . htmlspecialchars($updateApiUrlVal) . '"'
        . ' data-owner="' . htmlspecialchars($updateOwnerVal) . '"'
        . ' data-repo="' . htmlspecialchars($updateRepoVal) . '"'
        . ' data-version="' . htmlspecialchars($currentVersion) . '"'
        . ' data-channel="' . htmlspecialchars($updateChannel) . '"'
        . '>正在检查更新...</div>';

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

    $logoDisplayMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'logoDisplayMode',
        array(
            'auto' => _t('智能模式（推荐）'),
            'both' => _t('同时显示'),
            'logo-only' => _t('仅显示 LOGO'),
            'title-only' => _t('仅显示标题'),
        ),
        'auto',
        _t('LOGO 与标题显示方式'),
        _t('介绍：当 LOGO 与网站标题同时存在时的显示方式<br>智能模式：电脑端同时显示（紧凑布局），移动端仅显示 LOGO，避免拥挤<br>同时显示：所有设备均同时显示 LOGO 和标题<br>仅显示 LOGO：隐藏标题文字<br>仅显示标题：隐藏 LOGO 图片')
    );
    $logoDisplayMode->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($logoDisplayMode);

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
        _t('在这里填入站长头像，显示在左侧侧边栏顶部，支持两种方式：<br>1. 填写邮箱地址：自动使用「头像外观」中设置的 Gravatar 镜像源生成头像<br>2. 填写图片 URL：直接使用该图片作为头像<br>默认：QQ头像')
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
        '',
        _t('站长签名'),
        _t('在这里填入站长个性签名，显示在左侧侧边栏名称下方<br>留空则不显示签名<br>默认：Hello,world')
    );
    $authorSignature->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorSignature);

    $authorEmail = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorEmail',
        null,
        '',
        _t('站长邮箱'),
        _t('在这里填入站长邮箱地址，显示在左侧侧边栏底部联系方式中<br>留空则不显示邮箱<br>默认：yuncat@email.lwcat.cn')
    );
    $authorEmail->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorEmail);

    $authorGithub = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorGithub',
        null,
        '',
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

    echo '<script src="' . $options->themeUrl . '/assets/js/admin/update-check.js?v=' . shufei_get_theme_version() . '"></script>';


    // ===== 清空缓存 =====
    $_cacheOpts = \Typecho\Widget::widget('Widget_Options');
    $_storageAjaxUrl = rtrim($_cacheOpts->siteUrl, '/') . '/usr/themes/' . $_cacheOpts->theme . '/core/storage-ajax.php';
    echo '<div class="typecho-option cat-group-update" style="margin-bottom:20px">';
    echo '<label class="typecho-label">清空缓存</label>';
    echo '<p class="typecho-option-description" style="color:#999;font-size:12px;margin:0 0 12px">介绍：清空文章内容缓存与统计缓存。修改主题功能或迁移数据后可手动清空，强制下次访问重新生成。</p>';
    echo '<div class="shufei-update-actions">';
    echo '<button type="button" class="shufei-update-btn" id="shufei-clear-cache-btn" data-url="' . htmlspecialchars($_storageAjaxUrl) . '">清空缓存</button>';
    echo '</div>';
    echo '<div class="shufei-update-status" id="shufei-clear-cache-status"></div>';
    echo '</div>';

    echo '<script src="' . $options->themeUrl . '/assets/js/admin/clear-cache.js?v=' . shufei_get_theme_version() . '"></script>';


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
    $linksPageEnabled->setAttribute('data-toggle-group', 'linksPageEnabled');
    $form->addInput($linksPageEnabled);

    $linksPageId = new \Typecho\Widget\Helper\Form\Element\Text(
        'linksPageId',
        null,
        null,
        _t('友链页面ID'),
        _t('介绍：填写友链独立页面的ID（在后台页面管理中查看）<br>如果留空，将尝试自动查找使用友链页面模板的页面')
    );
    $linksPageId->setAttribute('class', 'typecho-option cat-group-sidebar');
    $linksPageId->setAttribute('data-toggle-dep', 'linksPageEnabled');
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

    $bgGradientEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'bgGradientEnabled',
        array('off' => _t('关闭'), 'on' => _t('启用')),
        'on',
        _t('渐变背景'),
        _t('介绍：启用后使用自定义渐变色作为页面背景<br>渐变背景优先级高于背景颜色，但低于背景图片<br>夜间模式下可单独设置渐变色，留空则夜间关闭渐变')
    );
    $bgGradientEnabled->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgGradientEnabled);

    $bgGradient = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgGradient',
        null,
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        _t('渐变背景 CSS 值'),
        _t('介绍：填写合法的 CSS 渐变值，应用于白天模式<br>示例：<br>linear-gradient(135deg, #667eea 0%, #764ba2 100%)<br>linear-gradient(to right, #f6d365 0%, #fda085 100%)<br>radial-gradient(circle, #1a2980 0%, #26d0ce 100%)')
    );
    $bgGradient->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgGradient);

    $bgGradientDark = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgGradientDark',
        null,
        'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
        _t('夜间模式渐变背景'),
        _t('介绍：夜间模式下的渐变背景 CSS 值，留空则夜间模式关闭渐变使用纯色<br>示例：linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)')
    );
    $bgGradientDark->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgGradientDark);

    $bgGradientAttachment = new \Typecho\Widget\Helper\Form\Element\Radio(
        'bgGradientAttachment',
        array('fixed' => _t('固定（推荐）'), 'scroll' => _t('跟随滚动')),
        'fixed',
        _t('渐变背景滚动方式'),
        _t('介绍：固定背景在滚动时保持不动，视觉效果更佳<br>跟随滚动则背景随页面滚动，长页面可能出现渐变接缝')
    );
    $bgGradientAttachment->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgGradientAttachment);

    $cardOpacity = new \Typecho\Widget\Helper\Form\Element\Text(
        'cardOpacity',
        null,
        '0.7',
        _t('盒子透明度'),
        _t('介绍：设置页面中各盒子（导航栏、侧边栏、文章卡片等）的透明度<br>取值范围 0 ~ 1，1 为完全不透明，0 为完全透明<br>默认：0.7（半透明）<br>设置背景图片或渐变背景后建议调低透明度，让背景透出')
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
        'on',
        _t('Pjax加载'),
        _t('介绍：开启后，全站页面切换将使用Pjax方式，实现无刷新加载，提升用户体验')
    );
    $pjaxLoad->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxLoad);

    $pjaxLoadStyle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'pjaxLoadStyle',
        array(
            'progress' => _t('顶部进度条'),
            'circle' => _t('圆形旋转器'),
            'dots' => _t('底部圆点'),
            'wave' => _t('波浪文字'),
            'spin-ring' => _t('渐变圆环')
        ),
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
    $statsEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
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
        'on',
        _t('侧边栏天气卡片'),
        _t('介绍：开启后，将在右侧侧边栏显示精美天气卡片<br>天气数据通过 IP 定位自动获取，无需配置')
    );
    $weatherEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($weatherEnabled);

    $tagsWidgetEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'tagsWidgetEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('标签云（右侧边栏）'),
        _t('介绍：开启后，将在右侧侧边栏显示标签栏盒子，展示全部标签')
    );
    $tagsWidgetEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($tagsWidgetEnabled);

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

    $postListPager = new \Typecho\Widget\Helper\Form\Element\Radio(
        'postListPager',
        array(
            'page'     => _t('页码分页'),
            'loadmore' => _t('加载更多')
        ),
        'page',
        _t('文章列表翻页方式'),
        _t('介绍：选择首页/分类/标签/搜索等文章列表的翻页方式<br>页码分页：底部显示上一页/下一页等页码按钮<br>加载更多：底部显示"加载更多"按钮，点击后直接在当前页下方追加下一页文章，无需切换页面')
    );
    $postListPager->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($postListPager);
    
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
    echo '<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {freeApiUrl: ' . json_encode($freeApiUrl) . ', freeApiKey: ' . json_encode($freeApiKey) . ', freeApiModel: ' . json_encode($freeApiModel) . ', aiAjaxUrl: ' . json_encode($aiAjaxUrl) . '});</script>';
    echo '<script src="' . $options->themeUrl . '/assets/js/admin/ai-settings.js?v=' . shufei_get_theme_version() . '"></script>';


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
        'on',
        _t('Mermaid 图表渲染'),
        _t('介绍：开启后，支持在文章中使用 ```mermaid 代码块渲染流程图、时序图、甘特图等<br>使用方法：在代码块标记后加上 mermaid，例如：<br>```mermaid<br>graph TD<br>&nbsp;&nbsp;&nbsp;&nbsp;A[开始] --> B[结束]<br>```<br>支持所有 Mermaid 官方图表类型')
    );
    $mermaidEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($mermaidEnabled);

    $echartsEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'echartsEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
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

    $commentIpRegionEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentIpRegionEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('评论 IP 归属地显示'),
        _t('介绍：开启后，评论列表会在评论时间后显示评论者 IP 归属地（如：广东省中山市）<br>归属地通过 https://api.lwcat.cn/api/ip/ 接口查询，仅对填写了 IP 的评论生效')
    );
    $commentIpRegionEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($commentIpRegionEnabled);

    // ===== 导航增强配置 =====
    $customNavItems = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'customNavItems',
        null,
        null,
        _t('自定义导航项'),
        _t('介绍：每行一个导航项，格式：图标类名|名称|链接<br>图标使用 Font Awesome 4.7 图标类名，例如：<br>fa-book|我的项目|https://example.com/projects<br>fa-download|资源下载|https://example.com/download<br>留空则不显示自定义导航')
    );
    $customNavItems->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($customNavItems);

    $categoryIcons = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'categoryIcons',
        null,
        null,
        _t('分类目录图标设置'),
        _t('介绍：为分类目录设置自定义图标，通过分类缩略名匹配<br>每行一个，格式：分类缩略名|图标类名<br>例如：<br>tech|fa-laptop<br>life|fa-coffee<br>code|fa-code<br>未设置的分类将使用默认图标 fa-folder-open-o')
    );
    $categoryIcons->setAttribute('class', 'typecho-option cat-group-sidebar');
    $form->addInput($categoryIcons);

    $guestbookEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'guestbookEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('留言板功能'),
        _t('介绍：开启后，在左侧导航栏添加留言板入口，用户可以在留言板页面留言<br>留言功能基于 Typecho 评论系统实现，需要先创建一个独立页面并选择"留言板"模板')
    );
    $guestbookEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $guestbookEnabled->setAttribute('data-toggle-group', 'guestbookEnabled');
    $form->addInput($guestbookEnabled);

    $guestbookPageId = new \Typecho\Widget\Helper\Form\Element\Text(
        'guestbookPageId',
        null,
        null,
        _t('留言板页面ID'),
        _t('介绍：填写留言板独立页面的ID（在后台页面管理中查看）<br>如果留空，将尝试自动查找使用留言板模板的页面')
    );
    $guestbookPageId->setAttribute('class', 'typecho-option cat-group-sidebar');
    $guestbookPageId->setAttribute('data-toggle-dep', 'guestbookEnabled');
    $form->addInput($guestbookPageId);

    $githubEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'githubEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'on',
        _t('GitHub 项目展示'),
        _t('介绍：开启后，在左侧导航栏添加 GitHub 项目入口，用户可在独立页面查看公开项目<br>需要先创建一个独立页面并选择"GitHub 项目"模板')
    );
    $githubEnabled->setAttribute('class', 'typecho-option cat-group-sidebar');
    $githubEnabled->setAttribute('data-toggle-group', 'githubEnabled');
    $form->addInput($githubEnabled);

    $githubUsername = new \Typecho\Widget\Helper\Form\Element\Text(
        'githubUsername',
        null,
        null,
        _t('GitHub 用户名'),
        _t('介绍：填写 GitHub 用户名，保存后可在下方获取项目列表并选择展示的项目<br>留空则不显示 GitHub 项目页面入口')
    );
    $githubUsername->setAttribute('class', 'typecho-option cat-group-sidebar');
    $githubUsername->setAttribute('data-toggle-dep', 'githubEnabled');
    $form->addInput($githubUsername);

    $githubCacheTime = new \Typecho\Widget\Helper\Form\Element\Text(
        'githubCacheTime',
        null,
        '3600',
        _t('GitHub 项目缓存时间（秒）'),
        _t('介绍：GitHub API 请求结果的缓存时间，默认 3600 秒（1小时）<br>建议设置 1800-7200 秒，避免频繁请求 API 导致限流')
    );
    $githubCacheTime->setAttribute('class', 'typecho-option cat-group-sidebar');
    $githubCacheTime->setAttribute('data-toggle-dep', 'githubEnabled');
    $form->addInput($githubCacheTime);

    $githubSelectedRepos = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'githubSelectedRepos',
        null,
        null,
        _t('展示的 GitHub 项目'),
        _t('介绍：点击下方"获取项目列表"按钮加载项目，勾选需要展示的项目<br>如果不选择任何项目，则展示全部公开项目')
    );
    $githubSelectedRepos->setAttribute('class', 'typecho-option cat-group-sidebar');
    $githubSelectedRepos->setAttribute('data-toggle-dep', 'githubEnabled');
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

    // 输出推荐设置提醒弹窗
    shufei_render_style_reminder();
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

/* 加载分层核心模块（从 functions.php 迁移） */
if (file_exists(dirname(__FILE__) . '/core/update.php')) {
    require_once dirname(__FILE__) . '/core/update.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/update.php');
}
if (file_exists(dirname(__FILE__) . '/core/style-version.php')) {
    require_once dirname(__FILE__) . '/core/style-version.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/style-version.php');
}
if (file_exists(dirname(__FILE__) . '/core/storage-ui.php')) {
    require_once dirname(__FILE__) . '/core/storage-ui.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/storage-ui.php');
}
if (file_exists(dirname(__FILE__) . '/core/template-helpers.php')) {
    require_once dirname(__FILE__) . '/core/template-helpers.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/template-helpers.php');
}
if (file_exists(dirname(__FILE__) . '/core/comments.php')) {
    require_once dirname(__FILE__) . '/core/comments.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/comments.php');
}
if (file_exists(dirname(__FILE__) . '/core/markdown.php')) {
    require_once dirname(__FILE__) . '/core/markdown.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/markdown.php');
}
if (file_exists(dirname(__FILE__) . '/core/captcha-config.php')) {
    require_once dirname(__FILE__) . '/core/captcha-config.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/captcha-config.php');
}
if (file_exists(dirname(__FILE__) . '/core/seo.php')) {
    require_once dirname(__FILE__) . '/core/seo.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/seo.php');
}
if (file_exists(dirname(__FILE__) . '/core/media.php')) {
    require_once dirname(__FILE__) . '/core/media.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/media.php');
}
if (file_exists(dirname(__FILE__) . '/core/github-cache.php')) {
    require_once dirname(__FILE__) . '/core/github-cache.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/github-cache.php');
}
if (file_exists(dirname(__FILE__) . '/core/emoji.php')) {
    require_once dirname(__FILE__) . '/core/emoji.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/emoji.php');
}
if (file_exists(dirname(__FILE__) . '/core/editor-ui.php')) {
    require_once dirname(__FILE__) . '/core/editor-ui.php';
} else {
    error_log('[ShuFeiCat] 核心模块缺失: core/editor-ui.php');
}


/**
 * 解析评论内容中的 Markdown 语法
 * 支持与文章相同的扩展语法
 *
 * @param string $text 评论原始文本
 * @return string 解析后的 HTML
 */


/**
 * ===== 文章编辑器快捷插入功能 =====
 * 在后台文章/页面 Markdown 编辑器工具栏中添加快捷按钮
 * 支持所有增强 Markdown 语法：高亮、公式、任务列表、提示框、折叠、图片、视频、音乐、Mermaid、ECharts
 * 利用 Typecho 的 admin/write-post.php 和 admin/write-page.php 的 bottom 钩子
 */


/**
 * ===== AI 写作助手编辑器集成 =====
 * 在后台文章/页面编辑器中注入 AI 美化、续写、检查功能
 * 仅当 AI 写作助手开启时显示
 */


/**
 * ===== 图片存储编辑器集成 =====
 * 在后台文章/页面编辑器中注入「图片上传」按钮与 Profile 切换下拉框
 * 仅当图片存储功能开启且存在已激活 Profile 时显示
 */



/**
 * ===== 钩子注册（集中管理）=====
 * 所有 Typecho 插件钩子在此统一注册
 * 钩子引用的函数定义在 core/ 子模块中（已在上方 require）
 */

// 注册钩子（整合AI审核功能）
\Typecho\Plugin::factory('Widget_Feedback')->comment = 'shufei_comment_check';

// 注册 KaTeX 内容过滤器（防止 Markdown 破坏数学公式语法）
// handle 使用 Widget\Base\Contents，因为 ___content() 中 Contents::pluginHandle() 的 static::class 解析为该类
// nativeClassName() 会将反斜杠转为下划线，最终查找键为 Widget_Base_Contents:content
\Typecho\Plugin::factory('Widget\Base\Contents')->content = 'shufei_katex_content_filter';

// 注册 Markdown 扩展内容过滤器（在 Markdown 解析后处理扩展语法）
\Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = 'shufei_markdown_ext_filter';

// 注册后台编辑器钩子（文章 + 页面）
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_quick_insert_js';

\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_quick_insert_js';

// 注册 AI 写作助手后台编辑器钩子（文章 + 页面）
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_ai_writer_editor_ui';

\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_ai_writer_editor_ui';

// 注册图片存储后台编辑器钩子（文章 + 页面）
\Typecho\Plugin::factory('admin/write-post.php')->bottom = 'shufei_storage_editor_ui';

\Typecho\Plugin::factory('admin/write-page.php')->bottom = 'shufei_storage_editor_ui';

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

