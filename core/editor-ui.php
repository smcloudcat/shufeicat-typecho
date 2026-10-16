<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 后台编辑器 UI 钩子
 * 从 functions.php 分层迁移
 */

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

/* ===== 「更多」下拉（2026-10-16 阶段三：低频按钮收纳）===== */
/* 面板展开时的全屏防误触遮罩：移动端工具栏换行后，向下展开的面板会盖住
 * 下一行按钮（复现：395px 下点「专注」实际命中面板内「视频」项）——遮罩挡掉
 * 面板外一切点击，点遮罩冒泡到 document 关闭面板 */
.shufei-qi-more-mask {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 999;
}
.wmd-button-row li.shufei-qi-more.open .shufei-qi-more-mask {
    display: block;
}
.wmd-button-row li.shufei-qi-more {
    position: relative;
}
.shufei-qi-more-panel {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    display: none;
    min-width: 110px;
    margin-top: 2px;
    padding: 4px 0;
    background: #fff;
    border: 1px solid #d9d9d6;
    border-radius: 4px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, .12);
}
.wmd-button-row li.shufei-qi-more.open .shufei-qi-more-panel {
    display: block;
}
/* 面板项需盖过原生 #wmd-button-bar .wmd-button-row li{display:inline-block}
 * （(1,1,1) 带 ID，普通类选择器压不过，必须同款 ID 前缀提权） */
#wmd-button-bar .wmd-button-row .shufei-qi-more-panel li.shufei-qi-btn {
    display: block;
    white-space: nowrap;
    padding: 0 10px;
    margin-right: 0;
}

/* 移动端（≤640px）：工具栏会换行，absolute 下拉会盖住下一行按钮导致误触
 * （复现：395px 点「专注」实际命中面板内「视频」项）——改为底部弹出
 * bottom sheet：fixed 贴底、脱离工具栏流、大触摸目标，配防误触遮罩 */
@media (max-width: 640px) {
    .shufei-qi-more-panel {
        position: fixed;
        left: 8px;
        right: 8px;
        bottom: 12px;
        top: auto;
        max-height: 60vh;
        overflow-y: auto;
        border-radius: 12px;
        box-shadow: 0 -4px 24px rgba(0, 0, 0, .18);
        padding: 6px 0;
    }
    #wmd-button-bar .wmd-button-row .shufei-qi-more-panel li.shufei-qi-btn {
        padding: 12px 16px;
        font-size: 14px;
    }
}

/* ===== 图片粘贴/拖拽直传（2026-10-16 阶段三）===== */
.shufei-paste-toast {
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%) translateY(-8px);
    z-index: 100003;
    padding: 8px 18px;
    background: rgba(40, 40, 40, .92);
    color: #fff;
    font-size: 13px;
    border-radius: 999px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, .25);
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s, transform .2s;
}
.shufei-paste-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
#text.shufei-drag-over {
    border-color: var(--primary-color, #FF6B6B);
    border-style: dashed;
    background: rgba(255, 107, 107, .04);
}
</style>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-quick-insert.js?v=<?php echo shufei_get_theme_version(); ?>"></script>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-paste-upload.js?v=<?php echo shufei_get_theme_version(); ?>"></script>
<?php
}

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
/* 2026-10-15：工具栏样式已随合并进快捷按钮行移除，保留浮窗与弹窗内按钮样式 */
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

<!-- 2026-10-15：AI 助手工具栏合并进编辑器快捷按钮行（SHUFEI_QI_EXTRA），仅保留浮窗 -->

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

<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {aiAjaxUrl: <?php echo json_encode($aiAjaxUrl); ?>, styles: <?php echo json_encode($styles); ?>});</script>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/ai-writer.js?v=<?php echo shufei_get_theme_version(); ?>"></script>

<?php
}

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
/* 2026-10-15：工具栏样式已随合并进快捷按钮行移除，保留上传/历史弹窗样式 */
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

<!-- 2026-10-15：图片存储工具栏合并进编辑器快捷按钮行，profile 下拉移入上传弹窗 -->
<div class="shufei-storage-up-modal-mask" id="shufei-storage-up-mask"></div>
<div class="shufei-storage-up-modal" id="shufei-storage-up-modal">
    <div class="shufei-storage-up-modal-header">
        <span>上传图片到存储</span>
        <span class="shufei-storage-up-modal-close" id="shufei-storage-up-close">×</span>
    </div>
    <div class="shufei-storage-up-modal-body">
        <div class="shufei-storage-up-options" style="margin-bottom:10px;">
            <label>存储 Profile：<select id="shufei-storage-profile-select" title="切换存储 Profile" style="padding:4px 8px;border:1px solid #d9d9d9;border-radius:4px;font-size:12px;max-width:220px;"></select></label>
            <span class="shufei-storage-status-tip" id="shufei-storage-status-tip" style="margin-left:8px;color:#888;font-size:12px;"></span>
        </div>
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

<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {ajaxUrl: <?php echo json_encode($ajaxUrl); ?>});</script>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/storage-editor.js?v=<?php echo shufei_get_theme_version(); ?>"></script>

<?php
}

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
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-cursor-fix.js?v=<?php echo shufei_get_theme_version(); ?>"></script>

<?php
}

/**
 * 输出文章引用编辑器 UI（chips + 搜索弹窗；入口按钮在编辑器快捷按钮行）
 *
 * 2026-10-15 起改为正文 [cite:cid] 标记语法：入口按钮由 article-cite.js
 * 注册进快捷按钮行（SHUFEI_QI_EXTRA），点选文章即在光标处插入标记；
 * chips 仅在正文已有引用标记时显示（实时解析 textarea）。
 *
 * 注意：bottom 钩子在 </form> 之后触发，隐藏域必须由 JS append 进表单。
 *
 * @param mixed $post 文章/页面 Edit widget（write-post.php 传 $post，write-page.php 传 $page）
 */
function shufei_article_cite_editor_ui($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');

    // 后台检索端点
    $ajaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/article-cite-ajax.php', $options->siteUrl);

    // 当前已配置的引用（编辑已有文章时回显；新文章为空数组）
    $currentCid = 0;
    $current = array();
    $currentCids = array();
    if (is_object($post) && isset($post->cid)) {
        $currentCid = intval($post->cid);
    }
    if (is_object($post) && $currentCid > 0 && isset($post->fields)) {
        $raw = $post->fields->{SHUFEI_ARTICLE_CITE_FIELD};
        if (isset($raw)) {
            $cids = shufei_get_article_cites($raw);
            if (!empty($cids)) {
                // 编辑器回显不加隐私锁（有权查看全部状态），按选择顺序输出
                $info = shufei_get_article_cites_info_admin($cids);
                foreach ($cids as $cid) {
                    if (isset($info[$cid])) {
                        $current[] = $info[$cid];
                        $currentCids[] = $cid;
                    }
                }
            }
        }
    }
    ?>
<style>
/* ===== 文章引用编辑器 UI ===== */
/* 2026-10-15：工具栏样式已随合并进快捷按钮行移除，保留 chips 与弹窗样式 */
/* chips（编辑器上方，有引用标记时显示） */
.shufei-cite-chips {
    display: none;
    flex-wrap: wrap;
    gap: 6px;
    margin: 6px 0 10px;
}
.shufei-cite-chips.has-items { display: flex; }
.shufei-cite-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 3px 8px;
    background: #fff7e6;
    border: 1px solid #ffd591;
    border-radius: 4px;
    font-size: 12px;
    color: #874d00;
}
.shufei-cite-chip-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 220px;
}
.shufei-cite-chip-badge {
    flex-shrink: 0;
    padding: 0 6px;
    background: rgba(250, 140, 22, 0.15);
    border-radius: 8px;
    font-size: 11px;
    color: #ad4e00;
}
.shufei-cite-chip-remove {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: rgba(250, 140, 22, 0.25);
    color: #ad4e00;
    font-size: 10px;
    line-height: 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.shufei-cite-chip-remove:hover {
    background: #d4380d;
    color: #fff;
}

/* 旧版 fields 引用（待迁移 chip，2026-10-15 新语法兼容） */
.shufei-cite-chip-legacy {
    background: #f6ffed;
    border-color: #b7eb8f;
    color: #389e0d;
}
.shufei-cite-chip-migrate {
    flex-shrink: 0;
    padding: 0 8px;
    border: none;
    border-radius: 8px;
    background: rgba(82, 196, 26, 0.2);
    color: #237804;
    font-size: 11px;
    line-height: 18px;
    cursor: pointer;
    transition: all 0.2s;
}
.shufei-cite-chip-migrate:hover {
    background: #52c41a;
    color: #fff;
}

/* 搜索弹窗 */
.shufei-cite-modal-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 10020;
    display: none;
}
.shufei-cite-modal {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 640px;
    max-width: 92vw;
    max-height: 85vh;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
    z-index: 10021;
    display: none;
    flex-direction: column;
    overflow: hidden;
}
.shufei-cite-modal.show, .shufei-cite-modal-mask.show { display: flex; }
.shufei-cite-modal-header {
    padding: 12px 16px;
    background: #fa8c16;
    color: #fff;
    font-weight: bold;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.shufei-cite-modal-close {
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0 4px;
    opacity: 0.85;
}
.shufei-cite-modal-close:hover { opacity: 1; }
.shufei-cite-modal-body {
    padding: 14px 16px;
    overflow-y: auto;
    flex: 1;
}
.shufei-cite-search-row {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}
.shufei-cite-search-row input {
    flex: 1;
    padding: 6px 10px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    font-size: 13px;
    box-sizing: border-box;
}
.shufei-cite-search-row input:focus {
    border-color: #fa8c16;
    outline: none;
}
.shufei-cite-results {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #f0f0f0;
    border-radius: 4px;
}
.shufei-cite-result-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.15s;
}
.shufei-cite-result-item + .shufei-cite-result-item {
    border-top: 1px solid #f5f5f5;
}
.shufei-cite-result-item:hover { background: #fff7e6; }
.shufei-cite-result-item.selected { background: #fff2e8; }
.shufei-cite-result-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #333;
}
.shufei-cite-result-item.selected .shufei-cite-result-title { color: #d46b08; font-weight: 500; }
.shufei-cite-result-date {
    flex-shrink: 0;
    font-size: 11px;
    color: #999;
}
.shufei-cite-result-badge {
    flex-shrink: 0;
    padding: 0 6px;
    background: #f0f0f0;
    border-radius: 8px;
    font-size: 11px;
    color: #666;
}
.shufei-cite-result-badge.draft { background: #fff7e6; color: #ad4e00; }
.shufei-cite-result-badge.waiting { background: #e6f7ff; color: #096dd9; }
.shufei-cite-result-badge.private { background: #f9f0ff; color: #722ed1; }
.shufei-cite-result-badge.hidden { background: #f5f5f5; color: #8c8c8c; }
.shufei-cite-result-check {
    flex-shrink: 0;
    color: #fa8c16;
    visibility: hidden;
}
.shufei-cite-result-item.selected .shufei-cite-result-check { visibility: visible; }
.shufei-cite-empty {
    padding: 30px 20px;
    text-align: center;
    color: #999;
    font-size: 13px;
}
.shufei-cite-modal-footer {
    padding: 10px 16px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    background: #fafafa;
}
.shufei-cite-tip {
    font-size: 11px;
    color: #999;
    line-height: 1.5;
}
.shufei-cite-modal-footer .btn {
    padding: 5px 16px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid #d9d9d9;
    background: #fff;
    color: #595959;
}
.shufei-cite-modal-footer .btn:hover { border-color: #fa8c16; color: #fa8c16; }
.shufei-cite-modal-footer .btn-primary {
    background: #fa8c16;
    border-color: #fa8c16;
    color: #fff;
}
.shufei-cite-modal-footer .btn-primary:hover { background: #ffa940; border-color: #ffa940; color: #fff; }
</style>

<!-- 2026-10-15：文章引用工具栏合并进编辑器快捷按钮行，chips 仅在有引用标记时显示 -->
<div class="shufei-cite-chips" id="shufei-cite-chips" style="display:none;"></div>

<div class="shufei-cite-modal-mask" id="shufei-cite-mask"></div>
<div class="shufei-cite-modal" id="shufei-cite-modal">
    <div class="shufei-cite-modal-header">
        <span>选择要引用的文章（最多 <?php echo SHUFEI_ARTICLE_MAX_CITES; ?> 篇）</span>
        <span class="shufei-cite-modal-close" id="shufei-cite-close">&times;</span>
    </div>
    <div class="shufei-cite-modal-body">
        <div class="shufei-cite-search-row">
            <input type="text" id="shufei-cite-search-input" placeholder="输入文章标题关键词搜索，留空显示最新文章…" autocomplete="off">
        </div>
        <div class="shufei-cite-results" id="shufei-cite-results"></div>
    </div>
    <div class="shufei-cite-modal-footer">
        <span class="shufei-cite-tip">点击文章即在正文光标处插入引用标记，可随时在正文中删除</span>
        <button type="button" class="btn btn-primary" id="shufei-cite-done-btn">完成</button>
    </div>
</div>

<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {
    articleCiteAjaxUrl: <?php echo json_encode($ajaxUrl); ?>,
    articleCiteMax: <?php echo intval(SHUFEI_ARTICLE_MAX_CITES); ?>,
    articleCiteCid: <?php echo $currentCid; ?>,
    articleCiteCurrent: <?php echo json_encode($current, JSON_UNESCAPED_UNICODE); ?>
});</script>

<?php if (!empty($currentCids)): ?>
<script>
/* 旧版 fields 引用兜底预填（vanilla，不依赖 jQuery）：
 * 2026-10-15 起引用改为正文 [cite:cid] 标记语法（article-cite.js 光标处插入）。
 * 本隐藏域仅用于**保留旧数据**：自定义字段面板已用 isFieldReadOnly 隐藏该字段，
 * 若表单不提交 fields[shufeiCitePosts]，核心 applyFields 会删除该字段行。
 * 保留旧值 → 旧文章保存后字段不丢 → 正文无 [cite: 标记时文末兜底渲染继续生效
 * （见 post.php / page.php 的 strpos 条件）。
 * 新语法不写此域；用户在编辑器点「迁移到正文」后标记进正文，字段沦为无害死数据。 */
(function () {
    var form = document.querySelector('form[name="write_post"]') || document.querySelector('form[name="write_page"]');
    if (!form) return;
    if (form.querySelector('input[name="fields[shufeiCitePosts]"]')) return;
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'fields[shufeiCitePosts]';
    input.value = <?php echo json_encode(json_encode(array_values($currentCids))); ?>;
    form.appendChild(input);
})();
</script>
<?php endif; ?>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/article-cite.js?v=<?php echo shufei_get_theme_version(); ?>"></script>

<?php
}

/**
 * 输出编辑器焦点模式（沉浸式写作）CSS 与 JS
 *
 * 2026-10-16 编辑器重构 · 阶段一：
 * - 焦点模式隐藏后台 chrome（顶栏/页标题/页脚/自定义字段/提交栏），
 *   正文收窄居中、textarea 撑满视口；右侧「选项/附件」变抽屉（仍位于 form 内，提交不受影响）
 * - 当前段落高亮 + 打字机模式 + 底部悬浮操作条 + Ctrl+Shift+F/Esc 快捷键
 * - 入口：编辑器快捷按钮行「专注」按钮（editor-focus.js 自行注册 SHUFEI_QI_EXTRA）
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_editor_focus_ui($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    if (empty($options->markdown)) {
        return;
    }
    ?>
<style>
/* ===== 编辑器焦点模式（2026-10-16 · 阶段一：沉浸）===== */
/* 隐藏后台 chrome（自定义字段与提交栏退出焦点后照常可用） */
body.shufei-focus-mode header.typecho-head-nav,
body.shufei-focus-mode .typecho-page-title,
body.shufei-focus-mode footer.typecho-foot,
body.shufei-focus-mode #custom-field,
body.shufei-focus-mode .submit,
body.shufei-focus-mode cite.edit-draft-notice {
    display: none !important;
}

body.shufei-focus-mode .main {
    padding-top: 10px;
}

/* 主栏收窄居中，腾出沉浸空间 */
body.shufei-focus-mode .typecho-post-area > [class*="col-tb-9"] {
    flex: 0 0 100%;
    max-width: 900px;
    margin: 0 auto;
}

/* 侧栏变右侧抽屉（DOM 仍在 form 内，提交/附件不受影响） */
body.shufei-focus-mode #edit-secondary {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 340px;
    max-width: 88vw;
    margin: 0;
    padding: 14px;
    background: #fff;
    border-left: 1px solid #e3e3e0;
    box-shadow: -8px 0 28px rgba(0, 0, 0, .12);
    transform: translateX(105%);
    transition: transform .25s ease;
    z-index: 100000;
    overflow-y: auto;
}
body.shufei-focus-mode #edit-secondary.shufei-drawer-open {
    transform: translateX(0);
}

/* 抽屉遮罩：打开时挡住编辑区防误触，点击关闭（移动端必需） */
#shufei-drawer-mask {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .35);
    z-index: 99999;
}

/* 标题无框化，聚焦时下划线强调 */
body.shufei-focus-mode #title {
    font-size: 22px;
    height: auto;
    padding: 10px 12px;
    background: transparent;
    border: 1px solid transparent;
    border-bottom: 2px solid #e8e8e4;
    border-radius: 6px 6px 0 0;
}
body.shufei-focus-mode #title:focus {
    border-bottom-color: #467B96;
    outline: none;
}

/* textarea 包裹容器与段落高亮条 */
.shufei-focus-wrap {
    position: relative;
}
.shufei-focus-hl {
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 0;
    background: rgba(70, 123, 150, .10);
    border-radius: 3px;
    pointer-events: none;
    opacity: 0;
    transition: opacity .12s;
    z-index: 0;
}
.shufei-focus-hl.on {
    opacity: 1;
}
body.shufei-focus-mode #text {
    background: transparent;
    position: relative;
    z-index: 1;
    caret-color: #467B96;
    border-color: #e8e8e4;
    border-radius: 8px;
    font-size: 15px;
    line-height: 1.8;
    padding: 14px 16px;
}

/* 底部悬浮操作条（2026-10-16 重构：对齐 Typecho 后台设计语言——
 * 2px 圆角 / #E9E9E6 灰底按钮 / #467B96 primary / 白底细边框浮层，去毛玻璃去胶囊去 emoji） */
.shufei-focus-bar {
    position: fixed;
    left: 50%;
    bottom: 18px;
    transform: translateX(-50%);
    display: none;
    align-items: center;
    gap: 4px;
    background: #FFF;
    border: 1px solid #D9D9D6;
    border-radius: 2px;
    padding: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
    z-index: 100001;
    font-size: 13px;
}
body.shufei-focus-mode .shufei-focus-bar {
    display: inline-flex;
}
.sf-fb-btn {
    border: none;
    background: #E9E9E6;
    cursor: pointer;
    padding: 0 12px;
    height: 28px;
    line-height: 28px;
    border-radius: 2px;
    font-size: 13px;
    color: #666;
    white-space: nowrap;
    transition: background .15s, color .15s;
}
.sf-fb-btn:hover {
    background: #dbdbd6;
    color: #333;
}
.sf-fb-btn:active {
    background: #d6d6d0;
}
.sf-fb-btn .fa {
    margin-right: 4px;
}
/* 开关类激活态：等同后台 primary（深蓝白字），一眼看出「开没开」 */
.sf-fb-btn.on {
    background: #467B96;
    color: #FFF;
}
.sf-fb-btn.on:hover {
    background: #3c6a81;
}
/* 发布按钮：后台 .primary 同款 */
.sf-fb-btn.sf-fb-primary {
    background: #467B96;
    color: #fff;
}
.sf-fb-btn.sf-fb-primary:hover {
    background: #3c6a81;
}
.sf-fb-btn.sf-fb-primary:active {
    background: #39647a;
}
.sf-fb-sep {
    width: 1px;
    height: 16px;
    background: #D9D9D6;
    margin: 0 2px;
}
.sf-fb-status {
    margin-left: 4px;
    padding-right: 6px;
    color: #999;
    font-size: 12px;
    white-space: nowrap;
}

/* 窄屏：操作条换行不溢出；按钮加大触摸目标（实测 35px 高） */
@media (max-width: 640px) {
    .shufei-focus-bar {
        flex-wrap: wrap;
        justify-content: center;
        max-width: 92vw;
    }
    .sf-fb-btn {
        padding: 0 12px;
        height: 35px;
        line-height: 35px;
        font-size: 13px;
    }
    .sf-fb-sep {
        display: none;
    }
}
</style>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-focus.js?v=<?php echo shufei_get_theme_version(); ?>"></script>
<?php
}

/**
 * 输出编辑器自动保存与状态增强（2026-10-16 编辑器重构 · 阶段二：方便）
 *
 * 背景：Typecho 原生自动保存读 $options->autoSave（站点选项 user=0），
 * 而用户设置里的「自动保存」存 user=uid 行——原生自动保存实际不生效。
 * 本组件用 localStorage 兜底自动保存，并补齐字数/阅读时间/快捷键/焦点状态联动。
 *
 * - 本地自动保存：停输 3s（+30s 兜底）存 localStorage，按 cid/new 分 key
 * - 恢复横幅：草稿与线上不同时提示恢复/丢弃，相同则静默清理
 * - 状态条：未保存/已保存时间 + 字数 + 阅读时间（焦点模式进悬浮条状态槽）
 * - 快捷键：Ctrl+S 存草稿 / Ctrl+Enter 发布 / Ctrl+Shift+P 预览
 * - 新文章自动聚焦标题；离开提醒沿用 Typecho 原生（不重复绑定）
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_editor_autosave_ui($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    if (empty($options->markdown)) {
        return;
    }
    ?>
<style>
/* ===== 本地草稿恢复横幅（2026-10-16 · 阶段二）===== */
.shufei-draft-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100002;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    background: #fff8e6;
    border-bottom: 1px solid #ffe7a8;
    color: #8a6d1a;
    font-size: 13px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
}
.shufei-draft-banner .sdb-icon {
    font-size: 15px;
}
.shufei-draft-banner .sdb-text {
    flex: 1;
    min-width: 0;
}
.shufei-draft-banner .sdb-btn {
    border: 1px solid #e3c878;
    background: #fff;
    color: #8a6d1a;
    border-radius: 4px;
    padding: 4px 14px;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
}
.shufei-draft-banner .sdb-btn:hover {
    background: #fff3d6;
}
.shufei-draft-banner .sdb-restore {
    background: #faad14;
    border-color: #faad14;
    color: #fff;
}
.shufei-draft-banner .sdb-restore:hover {
    background: #ffc53d;
}

/* ===== 编辑器状态条（2026-10-16 · 阶段二）===== */
.shufei-editor-status {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 99998;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background: rgba(255, 255, 255, .94);
    border: 1px solid #e3e3e0;
    border-radius: 999px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
    font-size: 12px;
    color: #666;
    pointer-events: none;
    user-select: none;
}
.shufei-editor-status .ses-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex: none;
}
.shufei-editor-status .ses-ok {
    background: #52c41a;
}
.shufei-editor-status .ses-warn {
    background: #faad14;
}
.shufei-editor-status .ses-idle {
    background: #bfbfbf;
}
.shufei-editor-status .ses-meta {
    color: #999;
}

/* 焦点模式下状态条让位给悬浮操作条状态槽 */
body.shufei-focus-mode .shufei-editor-status {
    display: none !important;
}
</style>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-autosave.js?v=<?php echo shufei_get_theme_version(); ?>"></script>
<?php
}

/**
 * 输出编辑器免保存实时预览的接口配置与脚本（2026-10-16 预览优化）
 *
 * 接管「预览文章」按钮：编辑器当前内容 → preview-ajax.php（前台同款渲染
 * 管线）→ preview-render.php 整页呈现。不保存草稿、不污染 draft 字段，
 * 新文章（无 cid）也能立即预览（原生 write-js.php 三分支落空静默无响应）。
 *
 * @param mixed $post 文章/页面对象（由钩子传入，此处未使用）
 */
function shufei_editor_preview_ui($post)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $ajaxUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/preview-ajax.php', $options->siteUrl);
    $renderUrl = \Typecho\Common::url('usr/themes/ShuFeiCat/core/preview-render.php', $options->siteUrl);
    ?>
<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {previewAjaxUrl: <?php echo json_encode($ajaxUrl); ?>, previewRenderUrl: <?php echo json_encode($renderUrl); ?>});</script>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-preview.js?v=<?php echo shufei_get_theme_version(); ?>"></script>
<?php
}

