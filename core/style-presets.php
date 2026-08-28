<?php
/**
 * 样式存储（外观预设）核心逻辑
 *
 * 将主题「外观设置」分组中的字段保存为多个可切换的样式预设，
 * 用户可随时保存当前配置、一键切换历史样式、删除不需要的预设。
 * 预设数据存储于 options 表 name = shufeiStylePresets 的 JSON 字段。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 样式预设允许保存的字段白名单
 * 仅收录「外观设置」(cat-group-appearance) 分组的相关字段
 *
 * @return array
 */
function shufei_style_preset_fields()
{
    return array(
        'themeColor',
        'bgColor',
        'bgImage',
        'bgGradientEnabled',
        'bgGradient',
        'bgGradientDark',
        'bgGradientAttachment',
        'cardOpacity',
        'postListStyle',
    );
}

/**
 * 读取全部样式预设
 *
 * @return array list of [id, name, created, settings]
 */
function shufei_style_presets_get_all()
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'shufeiStylePresets'));
        $data = ($row && isset($row['value'])) ? @json_decode($row['value'], true) : array();
        return is_array($data) ? $data : array();
    } catch (\Throwable $e) {
        return array();
    }
}

/**
 * 写入全部样式预设
 *
 * @param array $presets
 * @return bool
 */
function shufei_style_presets_save_all($presets)
{
    try {
        $db = \Typecho\Db::get();
        $value = json_encode(array_values($presets));
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'shufeiStylePresets'));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $value))->where('name = ?', 'shufeiStylePresets'));
        } else {
            $db->query($db->insert('table.options')->rows(array(
                'name'  => 'shufeiStylePresets',
                'user'  => 0,
                'value' => $value,
            )));
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 保存一个新样式预设（最多保留 20 个，超出丢弃最旧的）
 *
 * @param string $name     预设名称
 * @param array  $settings 字段 => 值
 * @return array 新预设数组
 */
function shufei_style_presets_save($name, $settings)
{
    $allowed = shufei_style_preset_fields();
    $clean = array();
    foreach ($allowed as $key) {
        if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
            $clean[$key] = is_scalar($settings[$key]) ? (string) $settings[$key] : $settings[$key];
        }
    }

    $preset = array(
        'id'         => md5(uniqid(mt_rand(), true)),
        'name'       => (string) $name,
        'created_at' => time(),
        'settings'   => $clean,
    );

    $presets = shufei_style_presets_get_all();
    array_unshift($presets, $preset);
    $presets = array_slice($presets, 0, 20);
    shufei_style_presets_save_all($presets);

    return $preset;
}

/**
 * 删除指定样式预设
 *
 * @param string $id
 * @return bool
 */
function shufei_style_presets_delete($id)
{
    $presets = shufei_style_presets_get_all();
    $found = false;
    $next = array();
    foreach ($presets as $p) {
        if (isset($p['id']) && $p['id'] === $id) {
            $found = true;
            continue;
        }
        $next[] = $p;
    }
    if (!$found) {
        return false;
    }
    return shufei_style_presets_save_all($next);
}

/**
 * 官方推荐样式（写死代码，不支持删除）
 *
 * 面向新用户/未使用过样式存储的用户，提供三套一键外观风格。
 * image 为预览图地址（占位，待替换）；推荐样式可被 apply，但不可被 delete。
 *
 * @return array
 */
function shufei_recommended_presets()
{
    return array(
        array(
            'id'       => 'rec-fc-1',
            'name'     => '珊瑚紫韵（渐变经典）',
            'desc'     => '珊瑚红主题色 × 紫罗兰渐变背景 · 经典文章列表',
            'image'    => 'https://img-cf.czzu.cn/1787928621083_s452dkvk.png',
            'settings' => array(
                'themeColor'        => '#FF6B6B',
                'bgColor'           => '#ffffff',
                'bgImage'           => '',
                'bgGradientEnabled' => 'on',
                'bgGradient'        => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'cardOpacity'       => '0.7',
                'postListStyle'     => 'classic',
            ),
        ),
        array(
            'id'       => 'rec-fc-2',
            'name'     => '静谧紫罗兰（卡片）',
            'desc'     => '紫色主题色 × 白色简约背景 · 卡片文章列表',
            'image'    => 'https://img-cf.czzu.cn/1787928699296_mrzy2ann.png',
            'settings' => array(
                'themeColor'        => '#6C5CE7',
                'bgColor'           => '#ffffff',
                'bgImage'           => '',
                'bgGradientEnabled' => 'off',
                'bgGradient'        => '',
                'cardOpacity'       => '0.7',
                'postListStyle'     => 'card',
            ),
        ),
        array(
            'id'       => 'rec-fc-3',
            'name'     => '暖阳金秋（卡片）',
            'desc'     => '金黄主题色 × 白色简约背景 · 卡片文章列表',
            'image'    => 'https://img-cf.czzu.cn/1787928767684_f66qwfgd.png',
            'settings' => array(
                'themeColor'        => '#9B8F27',
                'bgColor'           => '#ffffff',
                'bgImage'           => '',
                'bgGradientEnabled' => 'off',
                'bgGradient'        => '',
                'cardOpacity'       => '0.7',
                'postListStyle'     => 'card',
            ),
        ),
    );
}

/**
 * 由推荐样式计算缩略占位背景（图片加载失败时兜底）
 *
 * @param array $settings
 * @return string CSS background 值
 */
function shufei_recommended_preview_bg($settings)
{
    if (isset($settings['bgGradientEnabled']) && $settings['bgGradientEnabled'] === 'on'
        && !empty($settings['bgGradient'])) {
        return (string) $settings['bgGradient'];
    }
    return !empty($settings['bgColor']) ? (string) $settings['bgColor'] : '#ffffff';
}

/**
 * 应用指定样式到主题设置（仅覆盖样式包含的字段）
 *
 * @param string $id
 * @return array $preset 成功; null 未找到
 */
function shufei_style_presets_apply($id)
{
    $presets = shufei_style_presets_get_all();
    $preset = null;
    foreach ($presets as $p) {
        if (isset($p['id']) && $p['id'] === $id) {
            $preset = $p;
            break;
        }
    }
    if ($preset === null) {
        foreach (shufei_recommended_presets() as $rec) {
            if (isset($rec['id']) && $rec['id'] === $id) {
                $preset = array(
                    'id'       => $rec['id'],
                    'name'     => '官方推荐 · ' . $rec['name'],
                    'settings' => $rec['settings'],
                );
                break;
            }
        }
    }
    if ($preset === null) {
        return null;
    }
    if (!isset($preset['settings']) || !is_array($preset['settings'])) {
        return $preset;
    }

    $options = \Typecho\Widget::widget('Widget_Options');
    $themeKey = 'theme:' . $options->theme;
    $db = \Typecho\Db::get();

    $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', $themeKey));
    $settings = $row ? @json_decode($row['value'], true) : array();
    if (!is_array($settings)) {
        $settings = array();
    }

    foreach ($preset['settings'] as $key => $value) {
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
    foreach ($preset['settings'] as $key => $value) {
        $options->{$key} = $value;
    }

    return $preset;
}

/**
 * 读取推荐样式引导弹窗状态
 *
 * @return string ''（未看过）/ applied（已应用过推荐）/ dismissed（主动不再显示）
 */
function shufei_style_tip_status()
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'shufeiStyleRecommendTip'));
        return ($row && isset($row['value'])) ? (string) $row['value'] : '';
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 写入推荐样式引导弹窗状态
 *
 * @param string $value applied / dismissed
 * @return bool
 */
function shufei_style_tip_set($value)
{
    try {
        $db = \Typecho\Db::get();
        $name = 'shufeiStyleRecommendTip';
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', $name));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $value))->where('name = ?', $name));
        } else {
            $db->query($db->insert('table.options')->rows(array(
                'name'  => $name,
                'user'  => 0,
                'value' => $value,
            )));
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}