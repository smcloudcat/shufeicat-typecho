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
</style>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/editor-quick-insert.js?v=<?php echo shufei_get_theme_version(); ?>"></script>
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

