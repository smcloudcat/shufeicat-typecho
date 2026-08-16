<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片存储 UI 渲染
 * 从 functions.php 分层迁移
 */

/**
 * 渲染图片存储 Profile 可视化管理界面（HTML + JS）
 *
 * - 隐藏的 textarea (shufeiStorageProfiles) 与 text (shufeiStorageActiveProfile) 由本界面读写
 * - 提供：新增 / 编辑 / 删除 / 测试连接 / 设为激活
 */
function shufei_render_storage_profile_ui()
{
    // 延迟加载存储驱动类（themeConfig 在无 __TYPECHO_ADMIN__ 的前台路由下也会调用本函数）
    if (!class_exists('ShufeiStorageDriver')) {
        require_once dirname(__FILE__) . '/storage-drivers.php';
    }
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
        <button type="button" class="shufei-storage-btn primary" id="shufei-storage-save-btn">确认</button>
        <button type="button" class="shufei-storage-btn" id="shufei-storage-cancel-btn">取消</button>
    </div>
</div>

<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {drivers: <?php echo $driversJson; ?>, driverFields: <?php echo $fieldsJson; ?>, ajaxUrl: <?php echo json_encode($ajaxUrl); ?>});</script>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/storage-profile.js?v=<?php echo shufei_get_theme_version(); ?>"></script>

    <?php
}

/**
 * 渲染图片存储「图片管理」面板（分页列表 + 删除）
 * 在后台设置页 Profile 管理 UI 之后输出
 */
function shufei_render_storage_images_ui()
{
    // 延迟加载存储驱动类（themeConfig 在无 __TYPECHO_ADMIN__ 的前台路由下也会调用本函数）
    if (!class_exists('ShufeiStorageDriver')) {
        require_once dirname(__FILE__) . '/storage-drivers.php';
    }
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

<script>window.SHUFEI_ADMIN = Object.assign(window.SHUFEI_ADMIN || {}, {ajaxUrl: <?php echo json_encode($ajaxUrl); ?>});</script>
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/storage-images.js?v=<?php echo shufei_get_theme_version(); ?>"></script>

    <?php
}

