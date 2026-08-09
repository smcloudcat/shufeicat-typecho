<?php
/**
 * 样式版本号与推荐设置提醒
 *
 * 通过数据库记录"已处理"的样式版本号。当模板的样式版本号比数据库记录的样式版本号新，
 * 或从未记录时，在后台主题设置页弹出推荐设置提醒。
 *
 * 后台弹窗三个操作：
 *  - 立即设置：自动应用推荐设置（不影响其他配置项），并记录当前样式版本号
 *  - 暂不设置：仅关闭弹窗，下次打开模板设置时再次提醒
 *  - 不再提醒：不应用设置，记录当前样式版本号，之后不再提醒（除非有新的样式版本推荐）
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 获取当前样式版本号
 * 修改推荐设置时，应同时递增该版本号，以触发新一轮提醒
 *
 * @return string
 */
function shufei_get_style_version()
{
    return '1.0.0';
}

/**
 * 推荐设置：字段名 => 目标值
 *
 * @return array
 */
function shufei_style_recommendations()
{
    return array(
        'pjaxLoad'          => 'on',
        'bgGradientEnabled' => 'on',
        'bgGradient'        => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'bgGradientDark'    => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
        'cardOpacity'       => '0.7',
        'weatherEnabled'    => 'on',
    );
}

/**
 * 推荐设置的展示文案（用于弹窗列表）
 *
 * @return array
 */
function shufei_style_recommendation_labels()
{
    return array(
        'pjaxLoad'          => '开启 Pjax 无刷新加载',
        'bgGradientEnabled' => '启用渐变背景',
        'bgGradient'        => '渐变背景设置为推荐渐变（白天模式）',
        'bgGradientDark'    => '夜间模式背景设置为推荐渐变',
        'cardOpacity'       => '盒子透明度设置为 0.7',
        'weatherEnabled'    => '开启侧边栏天气卡片',
    );
}

/**
 * 读取数据库记录的样式版本号
 *
 * @return string 空字符串表示从未记录
 */
function shufei_style_version_seen()
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'shufeiStyleVersionSeen'));
        return isset($row['value']) ? (string) $row['value'] : '';
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 是否需要提醒
 * 没有记录，或当前样式版本号比记录的新，返回 true
 *
 * @return bool
 */
function shufei_style_reminder_needed()
{
    $current = shufei_get_style_version();
    $seen = shufei_style_version_seen();
    if ($seen === '') {
        return true;
    }
    return version_compare($current, $seen, '>');
}

/**
 * 记录已处理的样式版本号
 *
 * @param string|null $version
 * @return bool
 */
function shufei_record_style_version($version = null)
{
    $version = $version === null ? shufei_get_style_version() : (string) $version;
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'shufeiStyleVersionSeen'));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $version))
                ->where('name = ?', 'shufeiStyleVersionSeen'));
        } else {
            $db->query($db->insert('table.options')->rows(array(
                'name'  => 'shufeiStyleVersionSeen',
                'user'  => 0,
                'value' => $version,
            )));
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 应用推荐设置：只覆盖推荐项，保留其他配置
 * 应用成功后同时记录当前样式版本号
 *
 * @return array 应用后的设置数组
 */
function shufei_apply_recommended_settings()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $themeKey = 'theme:' . $options->theme;
    $db = \Typecho\Db::get();

    $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', $themeKey));
    $settings = $row ? @json_decode($row['value'], true) : array();
    if (!is_array($settings)) {
        $settings = array();
    }

    foreach (shufei_style_recommendations() as $key => $value) {
        $settings[$key] = $value;
    }

    $value = json_encode($settings);
    if ($row) {
        $db->query($db->update('table.options')->rows(array('value' => $value))->where('name = ?', $themeKey));
    } else {
        $db->query($db->insert('table.options')->rows(array(
            'name'  => $themeKey,
            'user'  => 0,
            'value' => $value,
        )));
    }

    // 同步运行时缓存
    foreach (shufei_style_recommendations() as $key => $value) {
        $options->{$key} = $value;
    }

    shufei_record_style_version();

    return $settings;
}

/**
 * 在后台主题设置页输出推荐设置提醒弹窗
 * 无需提醒时直接返回
 */
function shufei_render_style_reminder()
{
    if (!shufei_style_reminder_needed()) {
        return;
    }

    $options = \Typecho\Widget::widget('Widget_Options');
    $version = shufei_get_style_version();
    $ajaxUrl = rtrim($options->themeUrl, '/') . '/core/style-ajax.php';
    $labels = shufei_style_recommendation_labels();

    $listHtml = '';
    foreach ($labels as $label) {
        $listHtml .= '<li><i class="fa fa-check-circle"></i>' . htmlspecialchars($label) . '</li>';
    }

    echo '<style>' .
        '.shufei-style-mask{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:99990;display:flex;align-items:center;justify-content:center;padding:20px;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;}' .
        '.shufei-style-box{width:100%;max-width:440px;background:#fff;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,0.25);overflow:hidden;animation:shufeiStyleIn .22s ease;}' .
        '@keyframes shufeiStyleIn{from{opacity:0;transform:translateY(-16px) scale(0.97);}to{opacity:1;transform:none;}}' .
        '.shufei-style-head{display:flex;align-items:center;gap:10px;padding:16px 20px;background:#fbfbfb;border-bottom:1px solid #ececec;}' .
        '.shufei-style-title{font-size:15px;font-weight:700;color:#262626;}' .
        '.shufei-style-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;color:#fff;background:#467B96;}' .
        '.shufei-style-body{padding:16px 20px 8px;}' .
        '.shufei-style-desc{font-size:13px;color:#595959;line-height:1.7;margin:0 0 12px;}' .
        '.shufei-style-list{list-style:none;margin:0 0 14px;padding:0;}' .
        '.shufei-style-list li{display:flex;align-items:center;gap:8px;padding:7px 0;font-size:13px;color:#333;line-height:1.5;border-bottom:1px dashed #f0f0f0;}' .
        '.shufei-style-list li:last-child{border-bottom:none;}' .
        '.shufei-style-list i{color:#389e0d;font-size:14px;}' .
        '.shufei-style-note{font-size:12px;color:#8c8c8c;line-height:1.6;background:#fafafa;border:1px solid #f0f0f0;border-radius:6px;padding:8px 12px;margin-bottom:16px;}' .
        '.shufei-style-actions{display:flex;align-items:center;gap:10px;padding:14px 20px;background:#fbfbfb;border-top:1px solid #ececec;flex-wrap:wrap;}' .
        '.shufei-style-btn{border:none;border-radius:6px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit;}' .
        '.shufei-style-btn:hover{opacity:.9;}' .
        '.shufei-style-btn:disabled{opacity:.5;cursor:not-allowed;}' .
        '.shufei-style-btn.primary{background:#467B96;color:#fff;}' .
        '.shufei-style-btn.plain{background:#fff;color:#595959;border:1px solid #d9d9d9;}' .
        '.shufei-style-btn.ghost{background:transparent;color:#8c8c8c;border:none;padding-left:4px;}' .
        '</style>';

    echo '<div class="shufei-style-mask" id="shufei-style-mask">';
    echo '<div class="shufei-style-box" id="shufei-style-box" data-ajax="' . htmlspecialchars($ajaxUrl) . '">';
    echo '<div class="shufei-style-head">';
    echo '<span class="shufei-style-title">发现新的推荐设置</span>';
    echo '<span class="shufei-style-badge">样式版本 v' . htmlspecialchars($version) . '</span>';
    echo '</div>';
    echo '<div class="shufei-style-body">';
    echo '<p class="shufei-style-desc">当前模板推荐应用以下设置，使用可以获得更好的视觉效果，点击「立即设置」即可一键完成，不会改动其他配置项：</p>';
    echo '<ul class="shufei-style-list">' . $listHtml . '</ul>';
    echo '<div class="shufei-style-note"><i class="fa fa-info-circle" style="margin-right:6px;color:#467B96;"></i>仅在“后台-外观-设置外观”页面提醒，不会打扰访客。</div>';
    echo '</div>';
    echo '<div class="shufei-style-actions">';
    echo '<button type="button" class="shufei-style-btn primary" data-action="apply">立即设置</button>';
    echo '<button type="button" class="shufei-style-btn plain" data-action="later">暂不设置</button>';
    echo '<button type="button" class="shufei-style-btn ghost" data-action="dismiss">不再提醒</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<script src="' . rtrim($options->themeUrl, '/') . '/assets/js/admin/style-reminder.js?v=' . htmlspecialchars($version) . '"></script>';
}
