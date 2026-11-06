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
/* ===== 图片存储 Profile 列表（2026-11-03 重绘：对齐 Typecho 后台设计语言
 * 2px 圆角 / #E9E9E6 灰按钮 / #D9D9D6 细边框 / #467B96 primary / 白底浅投影，
 * 去掉原先那套 8px 圆角 + 绿色系 + 蓝底 modal header 的「第三方组件风」）===== */
.shufei-storage-wrap { padding: 0; }
.shufei-storage-card {
    border: 1px solid #D9D9D6;
    border-radius: 2px;
    padding: 12px 14px;
    margin-bottom: 8px;
    background: #FFF;
    transition: border-color .15s, background .15s;
}
.shufei-storage-card:hover { border-color: #c9c9c4; }
/* 激活态：浅蓝底 + 左侧主色竖条（inset 阴影，不改变盒模型宽度） */
.shufei-storage-card.active {
    border-color: #467B96;
    background: #F4F8FA;
    box-shadow: inset 2px 0 0 #467B96;
}
.shufei-storage-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.shufei-storage-card-title { font-weight: 600; color: #333; font-size: 13px; }
.shufei-storage-card-driver { color: #999; font-size: 12px; margin-left: 8px; }
.shufei-storage-card-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.shufei-storage-btn {
    display: inline-block;
    padding: 0 11px;
    height: 26px;
    line-height: 24px;
    font-size: 12px;
    border-radius: 2px;
    cursor: pointer;
    border: 1px solid #D9D9D6;
    background: #E9E9E6;
    color: #666;
    transition: background .15s, color .15s, border-color .15s;
    font-family: inherit;
}
.shufei-storage-btn:hover { background: #dbdbd6; color: #333; }
.shufei-storage-btn.primary {
    background: #467B96;
    color: #FFF;
    border-color: #467B96;
}
.shufei-storage-btn.primary:hover { background: #3c6a81; border-color: #3c6a81; }
.shufei-storage-btn.success {
    background: #467B96;
    color: #FFF;
    border-color: #467B96;
}
.shufei-storage-btn.success:hover { background: #3c6a81; border-color: #3c6a81; }
.shufei-storage-btn.danger {
    background: #FFF;
    color: #cf1322;
    border-color: #f0c2c0;
}
.shufei-storage-btn.danger:hover { background: #fff1f0; border-color: #cf1322; }
.shufei-storage-badge {
    display: inline-block;
    padding: 1px 8px;
    font-size: 11px;
    line-height: 1.6;
    border-radius: 2px;
    background: rgba(70, 123, 150, .12);
    color: #467B96;
    margin-left: 8px;
    vertical-align: 1px;
}
.shufei-storage-add-btn {
    padding: 0 14px;
    height: 30px;
    line-height: 28px;
    background: #467B96;
    color: #FFF;
    border: 1px solid #467B96;
    border-radius: 2px;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
    transition: background .15s;
}
.shufei-storage-add-btn:hover { background: #3c6a81; border-color: #3c6a81; }
.shufei-storage-modal-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, .45);
    z-index: 10000;
    display: none;
}
.shufei-storage-modal {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 620px;
    max-width: 92vw;
    max-height: 85vh;
    background: #FFF;
    border: 1px solid #D9D9D6;
    border-radius: 2px;
    box-shadow: 0 6px 28px rgba(0, 0, 0, .18);
    z-index: 10001;
    display: none;
    flex-direction: column;
    overflow: hidden;
}
.shufei-storage-modal.show, .shufei-storage-modal-mask.show { display: flex; }
/* header 用白底 + 底边框（原来是蓝底白字，和后台其余面板完全不像） */
.shufei-storage-modal-header {
    padding: 11px 16px;
    background: #FBFBFA;
    border-bottom: 1px solid #E9E9E6;
    color: #333;
    font-weight: 600;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.shufei-storage-modal-close {
    cursor: pointer;
    font-size: 17px;
    line-height: 1;
    color: #999;
    padding: 2px 4px;
    border-radius: 2px;
}
.shufei-storage-modal-close:hover { color: #333; background: #EFEFEC; }
.shufei-storage-modal-body { padding: 16px 18px; overflow-y: auto; flex: 1; }
.shufei-storage-modal-footer {
    padding: 10px 16px;
    border-top: 1px solid #E9E9E6;
    background: #FBFBFA;
    text-align: right;
}
.shufei-storage-field { margin-bottom: 14px; }
.shufei-storage-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #333;
    font-size: 12px;
}
.shufei-storage-field input,
.shufei-storage-field select,
.shufei-storage-field textarea {
    width: 100%;
    padding: 7px 9px;
    border: 1px solid #D9D9D6;
    border-radius: 2px;
    box-sizing: border-box;
    font-size: 13px;
    font-family: inherit;
}
.shufei-storage-field input:focus,
.shufei-storage-field select:focus,
.shufei-storage-field textarea:focus {
    border-color: #467B96;
    outline: none;
}
.shufei-storage-field select { height: auto !important; padding-right: 30px !important; line-height: normal !important; }
.shufei-storage-field .desc { color: #999; font-size: 12px; margin-top: 4px; line-height: 1.6; }
.shufei-storage-test-status {
    margin-top: 10px;
    padding: 8px 11px;
    border-radius: 2px;
    font-size: 12px;
    display: none;
    line-height: 1.6;
}
.shufei-storage-test-status.show { display: block; }
.shufei-storage-test-status.success { background: #F1F8F3; border: 1px solid #b7dfc3; color: #2f7a45; }
.shufei-storage-test-status.error { background: #FFF4F2; border: 1px solid #f0c2c0; color: #cf1322; }
.shufei-storage-wm-image-field, .shufei-storage-wm-text-field { display: none; }
/* 空列表提示 */
#shufei-storage-empty { color: #999; font-size: 12px; }
</style>

<div class="typecho-option cat-group-storage shufei-storage-wrap">
    <div style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="font-weight:600; color:#333; font-size:13px;">存储 Profile 列表</div>
        <button type="button" class="shufei-storage-add-btn" id="shufei-storage-add-btn">+ 新建 Profile</button>
    </div>
    <div id="shufei-storage-list"></div>
    <div id="shufei-storage-empty" style="text-align:center; padding:26px 0; color:#999; font-size:12px; display:none;">
        暂无存储 Profile，点击右上角「新建 Profile」添加。
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
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/storage-profile.js?v=<?php echo filemtime(dirname(__FILE__) . '/../assets/js/admin/storage-profile.js') ?: shufei_get_theme_version(); ?>"></script>

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
<script src="<?php echo $options->themeUrl; ?>/assets/js/admin/storage-images.js?v=<?php echo filemtime(dirname(__FILE__) . '/../assets/js/admin/storage-images.js') ?: shufei_get_theme_version(); ?>"></script>

    <?php
}

