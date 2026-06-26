<?php
/**
 * 多存储后端 AJAX 接口
 *
 * 支持以下 action（POST）：
 *  - test_connection: 测试某 Profile 配置连通性（无需先保存）
 *  - upload_image:    上传图片到指定 Profile（绕过 Typecho 原生上传，用于编辑器多选/批量）
 *  - list_profiles:   列出当前已保存的 Profile 与激活状态（供编辑器下拉框使用）
 *  - list_images:     列出指定 Profile 下的已上传图片（分页）
 *  - delete_image:    删除指定 Profile 下的某张图片
 *
 * 仅允许已登录的 administrator / editor 使用。
 *
 * 鉴权方式与 ai-writer-ajax.php 一致：手动初始化 Cookie 前缀以读取后台登录 Cookie。
 */

header('Content-Type: application/json; charset=utf-8');

// 全局 fatal error handler：任何阶段的致命错误都返回 JSON 而非空响应（避免 Cloudflare 504）
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array('success' => false, 'message' => 'PHP致命错误: ' . $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')'));
    }
});

// 载入 Typecho 配置
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        echo json_encode(array('success' => false, 'message' => '系统配置文件缺失'));
        exit;
    }
}

// 手动定义 __TYPECHO_ROOT_URL__ 以让 Cookie 前缀与后台一致
if (!defined('__TYPECHO_ROOT_URL__')) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $isSecure ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $knownSuffix = '/usr/themes/ShuFeiCat/core/storage-ajax.php';
    $basePath = '';
    if (substr($scriptName, -strlen($knownSuffix)) === $knownSuffix) {
        $basePath = substr($scriptName, 0, -strlen($knownSuffix));
    } else {
        $basePath = dirname(dirname(dirname(dirname(dirname($scriptName)))));
    }
    define('__TYPECHO_ROOT_URL__', rtrim($protocol . '://' . $host . $basePath, '/'));
}

try {
    $options = \Widget\Options::alloc();
    \Typecho\Cookie::setPrefix($options->rootUrl);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '系统初始化失败: ' . $e->getMessage()));
    exit;
}

// 鉴权
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录'));
        exit;
    }
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        echo json_encode(array('success' => false, 'message' => '权限不足，仅管理员或编辑可使用'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// 载入核心依赖
require_once dirname(__FILE__) . '/image-processor.php';
require_once dirname(__FILE__) . '/storage-drivers.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'test_connection':
        handleTestConnection();
        break;

    case 'list_profiles':
        handleListProfiles();
        break;

    case 'upload_image':
        handleUploadImage();
        break;

    case 'switch_profile':
        handleSwitchProfile();
        break;

    case 'list_images':
        handleListImages();
        break;

    case 'delete_image':
        handleDeleteImage();
        break;

    default:
        echo json_encode(array('success' => false, 'message' => '未知操作: ' . htmlspecialchars($action)));
        break;
}

exit;

/**
 * 测试连接
 */
function handleTestConnection()
{
    @set_time_limit(120);
    $driver = isset($_POST['driver']) ? trim($_POST['driver']) : '';
    $config = isset($_POST['config']) ? $_POST['config'] : array();

    // config 可能以 JSON 字符串提交
    if (is_string($config)) {
        $decoded = @json_decode($config, true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }
    if (!is_array($config)) {
        $config = array();
    }

    $drv = ShufeiStorageDriver::factory($driver);
    if (!$drv) {
        echo json_encode(array('success' => false, 'message' => '不支持的存储驱动: ' . htmlspecialchars($driver)));
        return;
    }

    // 1) 先验证连接（凭据/可达性）
    $connResult = $drv->testConnection($config);
    if (empty($connResult['success'])) {
        echo json_encode($connResult, JSON_UNESCAPED_UNICODE);
        return;
    }

    // 2) 生成一张小型测试图片并实际上传，验证完整上传链路
    $testFile = tempnam(sys_get_temp_dir(), 'shufei_test_');
    if ($testFile === false) {
        echo json_encode(array('success' => false, 'message' => '无法创建临时文件'));
        return;
    }
    // 用 GD 生成一张 100x60 的测试 PNG，写上"测试"字样
    if (function_exists('imagecreatetruecolor')) {
        $im = @imagecreatetruecolor(100, 60);
        if ($im) {
            $bg = imagecolorallocate($im, 240, 248, 255);
            $fg = imagecolorallocate($im, 60, 100, 180);
            imagefilledrectangle($im, 0, 0, 100, 60, $bg);
            imagestring($im, 3, 18, 22, 'TEST UPLOAD', $fg);
            imagepng($im, $testFile);
            imagedestroy($im);
        } else {
            // GD 创建失败，写一个最小 PNG
            file_put_contents($testFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='));
        }
    } else {
        file_put_contents($testFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='));
    }

    $remoteName = 'shufei-test-' . date('YmdHis') . '-' . substr(md5(uniqid()), 0, 8) . '.png';
    $uploadResult = $drv->upload($testFile, $remoteName, 'image/png', $config);
    @unlink($testFile);

    if (!$uploadResult) {
        echo json_encode(array(
            'success' => false,
            'message' => $connResult['message'] . '；连接正常，但测试上传失败，请检查存储桶权限或路径配置',
        ), JSON_UNESCAPED_UNICODE);
        return;
    }

    // 3) 尝试删除测试图片（清理）
    $deleteOk = true;
    $deleteMsg = '';
    try {
        $meta = array(
            'key' => isset($uploadResult['key']) ? $uploadResult['key'] : '',
            'name' => isset($uploadResult['name']) ? $uploadResult['name'] : basename($remoteName),
        );
        $delResult = $drv->delete($meta, $config);
        $deleteOk = !empty($delResult);
        if (!$deleteOk) {
            $deleteMsg = '（测试图片已上传但删除失败，可能需要手动清理）';
        }
    } catch (\Throwable $e) {
        $deleteMsg = '（删除测试图片时异常：' . $e->getMessage() . '）';
    }

    $msg = $connResult['message'] . '；测试图片上传成功' . $deleteMsg;
    echo json_encode(array(
        'success' => true,
        'message' => $msg,
        'testUrl' => isset($uploadResult['url']) ? $uploadResult['url'] : '',
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * 列出已保存的 Profile（供编辑器下拉框使用）
 */
function handleListProfiles()
{
    try {
        $options = \Widget\Options::alloc();
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '系统初始化失败'));
        return;
    }

    $enabled = isset($options->shufeiStorageEnabled) ? ($options->shufeiStorageEnabled === 'on') : false;
    $activeId = isset($options->shufeiStorageActiveProfile) ? $options->shufeiStorageActiveProfile : '';
    $profilesJson = isset($options->shufeiStorageProfiles) ? $options->shufeiStorageProfiles : '';
    $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
    if (!is_array($profiles)) {
        $profiles = array();
    }

    $list = array();
    foreach ($profiles as $p) {
        $list[] = array(
            'id' => isset($p['id']) ? $p['id'] : '',
            'name' => isset($p['name']) ? $p['name'] : '未命名',
            'driver' => isset($p['driver']) ? $p['driver'] : '',
            'driverName' => isset(ShufeiStorageDriver::driverList()[$p['driver'] ?? '']) ? ShufeiStorageDriver::driverList()[$p['driver'] ?? ''] : '未知',
        );
    }

    // 处理选项
    $processing = array(
        'compress' => isset($options->shufeiStorageCompress) ? $options->shufeiStorageCompress : 'off',
        'webp' => isset($options->shufeiStorageWebp) ? $options->shufeiStorageWebp : 'off',
        'watermark' => isset($options->shufeiStorageWatermark) ? $options->shufeiStorageWatermark : 'off',
    );

    echo json_encode(array(
        'success' => true,
        'enabled' => $enabled,
        'activeProfileId' => $activeId,
        'profiles' => $list,
        'processing' => $processing,
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * 上传图片（绕过 Typecho 原生上传，用于编辑器批量上传）
 * 接收 multipart 表单：profile_id（可选，默认用激活的）+ file
 */
function handleUploadImage()
{
    // 上传可能涉及图片处理+远程上传，延长执行时间避免 504
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    try {
        $options = \Widget\Options::alloc();
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '系统初始化失败'));
        return;
    }

    $enabled = isset($options->shufeiStorageEnabled) ? ($options->shufeiStorageEnabled === 'on') : false;
    if (!$enabled) {
        echo json_encode(array('success' => false, 'message' => '图片存储功能未开启'));
        return;
    }

    $profilesJson = isset($options->shufeiStorageProfiles) ? $options->shufeiStorageProfiles : '';
    $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
    if (!is_array($profiles)) {
        $profiles = array();
    }

    $activeId = isset($options->shufeiStorageActiveProfile) ? $options->shufeiStorageActiveProfile : '';
    $useProfileId = isset($_POST['profile_id']) ? trim($_POST['profile_id']) : $activeId;

    $profile = null;
    foreach ($profiles as $p) {
        if (isset($p['id']) && $p['id'] === $useProfileId) {
            $profile = $p;
            break;
        }
    }
    if (!$profile) {
        echo json_encode(array('success' => false, 'message' => '未找到指定的存储 Profile'));
        return;
    }

    if (empty($_FILES['file'])) {
        echo json_encode(array('success' => false, 'message' => '未接收到文件（可能超过服务器 post_max_size 限制）'));
        return;
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        echo json_encode(array('success' => false, 'message' => '文件上传失败 (code=' . $file['error'] . ')'));
        return;
    }

    // 仅允许图片
    $isImg = false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (in_array($ext, array('jpg', 'png', 'gif', 'webp', 'bmp'), true)) {
        $isImg = true;
    }
    if (!$isImg) {
        echo json_encode(array('success' => false, 'message' => '仅支持图片格式'));
        return;
    }

    $driverId = isset($profile['driver']) ? $profile['driver'] : '';
    $driverConfig = isset($profile['config']) ? $profile['config'] : array();

    // 图片处理选项
    $processing = array(
        'compress' => isset($options->shufeiStorageCompress) ? $options->shufeiStorageCompress : 'off',
        'compressQuality' => isset($options->shufeiStorageCompressQuality) ? intval($options->shufeiStorageCompressQuality) : 80,
        'webp' => isset($options->shufeiStorageWebp) ? $options->shufeiStorageWebp : 'off',
        'watermark' => isset($options->shufeiStorageWatermark) ? $options->shufeiStorageWatermark : 'off',
        'watermarkType' => isset($options->shufeiStorageWatermarkType) ? $options->shufeiStorageWatermarkType : 'text',
        'watermarkText' => isset($options->shufeiStorageWatermarkText) ? $options->shufeiStorageWatermarkText : '',
        'watermarkImage' => isset($options->shufeiStorageWatermarkImage) ? $options->shufeiStorageWatermarkImage : '',
        'watermarkPosition' => isset($options->shufeiStorageWatermarkPosition) ? $options->shufeiStorageWatermarkPosition : 'br',
        'watermarkOpacity' => isset($options->shufeiStorageWatermarkOpacity) ? intval($options->shufeiStorageWatermarkOpacity) : 50,
        'watermarkSize' => isset($options->shufeiStorageWatermarkSize) ? intval($options->shufeiStorageWatermarkSize) : 16,
        'watermarkColor' => isset($options->shufeiStorageWatermarkColor) ? $options->shufeiStorageWatermarkColor : '#FFFFFF',
        'watermarkFont' => isset($options->shufeiStorageWatermarkFont) ? $options->shufeiStorageWatermarkFont : '',
    );

    $date = new \Typecho\Date();
    $remoteName = $date->year . '/' . $date->month . '/' . sprintf('%u', crc32(uniqid())) . '.' . $ext;

    // 应用图片处理
    $procOptions = array(
        'compress' => $processing['compress'] === 'on',
        'compressQuality' => $processing['compressQuality'],
        'webp' => $processing['webp'] === 'on',
        'watermark' => $processing['watermark'] === 'on',
        'watermarkType' => $processing['watermarkType'],
        'watermarkText' => $processing['watermarkText'],
        'watermarkImage' => $processing['watermarkImage'],
        'watermarkPosition' => $processing['watermarkPosition'],
        'watermarkOpacity' => $processing['watermarkOpacity'],
        'watermarkSize' => $processing['watermarkSize'],
        'watermarkColor' => $processing['watermarkColor'],
        'watermarkFont' => $processing['watermarkFont'],
    );
    // 水印图/字体路径解析
    if ($procOptions['watermarkImage'] !== '') {
        $img = $procOptions['watermarkImage'];
        if (!preg_match('#^(/|https?://)#i', $img) && !is_file($img)) {
            $abs = __TYPECHO_ROOT_DIR__ . '/' . ltrim($img, '/');
            if (is_file($abs)) {
                $procOptions['watermarkImage'] = $abs;
            }
        }
    }
    if ($procOptions['watermarkFont'] !== '') {
        $font = $procOptions['watermarkFont'];
        if (!is_file($font)) {
            $abs = __TYPECHO_ROOT_DIR__ . '/' . ltrim($font, '/');
            if (is_file($abs)) {
                $procOptions['watermarkFont'] = $abs;
            }
        }
    }

    $processed = ShufeiImageProcessor::process($file['tmp_name'], $procOptions);
    $localPath = $processed['path'];
    $mime = $processed['mime'];
    $finalExt = $processed['ext'];
    if ($finalExt !== $ext) {
        $remoteName = preg_replace('/\.[^.]+$/', '', $remoteName) . '.' . $finalExt;
    }

    // 本地驱动
    if ($driverId === 'local') {
        $uploadDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
        $rootDir = defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__;
        $absDir = $rootDir . $uploadDir . '/' . dirname($remoteName);
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) {
            echo json_encode(array('success' => false, 'message' => '无法创建上传目录'));
            return;
        }
        $absPath = $rootDir . $uploadDir . '/' . $remoteName;
        if (is_uploaded_file($localPath)) {
            $moved = @move_uploaded_file($localPath, $absPath);
        } else {
            $moved = @copy($localPath, $absPath);
            @unlink($localPath);
        }
        if (!$moved) {
            echo json_encode(array('success' => false, 'message' => '本地保存失败'));
            return;
        }
        $url = \Typecho\Common::url($uploadDir . '/' . $remoteName, defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : $options->siteUrl);
        echo json_encode(array(
            'success' => true,
            'url' => $url,
            'name' => $file['name'],
            'size' => @filesize($absPath),
        ));
        return;
    }

    // 远程驱动
    $driver = ShufeiStorageDriver::factory($driverId);
    if (!$driver) {
        if ($localPath !== $file['tmp_name']) @unlink($localPath);
        echo json_encode(array('success' => false, 'message' => '不支持的驱动'));
        return;
    }

    try {
        $result = $driver->upload($localPath, $remoteName, $mime, $driverConfig);
    } catch (\Throwable $e) {
        if ($localPath !== $file['tmp_name']) @unlink($localPath);
        echo json_encode(array('success' => false, 'message' => '远程上传异常: ' . $e->getMessage()));
        return;
    }
    if ($localPath !== $file['tmp_name']) @unlink($localPath);

    if (!$result) {
        $errMsg = $driver->lastError ? $driver->lastError : '未知错误';
        echo json_encode(array('success' => false, 'message' => '上传到远程存储失败: ' . $errMsg));
        return;
    }
    echo json_encode(array(
        'success' => true,
        'url' => $result['url'],
        'name' => $file['name'],
        'size' => isset($result['size']) ? $result['size'] : $file['size'],
    ));
}

/**
 * 切换激活 Profile（更新 options 表，无需打开设置页）
 */
function handleSwitchProfile()
{
    $profileId = isset($_POST['profile_id']) ? trim($_POST['profile_id']) : '';
    if ($profileId === '') {
        echo json_encode(array('success' => false, 'message' => '缺少 profile_id'));
        return;
    }

    try {
        $options = \Widget\Options::alloc();
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '系统初始化失败'));
        return;
    }

    $profilesJson = isset($options->shufeiStorageProfiles) ? $options->shufeiStorageProfiles : '';
    $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
    if (!is_array($profiles)) {
        $profiles = array();
    }
    $found = false;
    foreach ($profiles as $p) {
        if (isset($p['id']) && $p['id'] === $profileId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo json_encode(array('success' => false, 'message' => '指定的 Profile 不存在'));
        return;
    }

    try {
        $db = \Typecho\Db::get();
        $db->query($db->update('table.options')->rows(array('value' => $profileId))
            ->where('name = ?', 'shufeiStorageActiveProfile'));
        // 同时更新运行时 Widget_Options 缓存
        $options->shufeiStorageActiveProfile = $profileId;
        echo json_encode(array('success' => true, 'message' => '已切换为该 Profile'));
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '切换失败: ' . $e->getMessage()));
    }
}

/**
 * 公共：解析并返回指定 Profile 与对应驱动实例
 * 失败时输出 JSON 错误并返回 null，成功返回 ['profile'=>..., 'driver'=>...]
 */
function resolveProfileAndDriver()
{
    try {
        $options = \Widget\Options::alloc();
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '系统初始化失败'));
        return null;
    }

    $enabled = isset($options->shufeiStorageEnabled) ? ($options->shufeiStorageEnabled === 'on') : false;
    if (!$enabled) {
        echo json_encode(array('success' => false, 'message' => '图片存储功能未开启'));
        return null;
    }

    $profilesJson = isset($options->shufeiStorageProfiles) ? $options->shufeiStorageProfiles : '';
    $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
    if (!is_array($profiles)) {
        $profiles = array();
    }

    $activeId = isset($options->shufeiStorageActiveProfile) ? $options->shufeiStorageActiveProfile : '';
    $useProfileId = isset($_POST['profile_id']) ? trim($_POST['profile_id']) : $activeId;

    $profile = null;
    foreach ($profiles as $p) {
        if (isset($p['id']) && $p['id'] === $useProfileId) {
            $profile = $p;
            break;
        }
    }
    if (!$profile) {
        echo json_encode(array('success' => false, 'message' => '未找到指定的存储 Profile'));
        return null;
    }

    $driverId = isset($profile['driver']) ? $profile['driver'] : '';
    $driverConfig = isset($profile['config']) ? $profile['config'] : array();
    $driver = ShufeiStorageDriver::factory($driverId);
    if (!$driver) {
        echo json_encode(array('success' => false, 'message' => '不支持的驱动: ' . $driverId));
        return null;
    }

    return array('profile' => $profile, 'driver' => $driver, 'config' => $driverConfig, 'driverId' => $driverId);
}

/**
 * 列出指定 Profile 的图片
 * 参数：profile_id（可选，默认激活）, page, limit
 */
function handleListImages()
{
    $resolved = resolveProfileAndDriver();
    if (!$resolved) return;
    $driver = $resolved['driver'];
    $config = $resolved['config'];

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;

    try {
        $result = $driver->listImages($config, $page, $limit);
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '查询异常: ' . $e->getMessage()));
        return;
    }
    if (!$result) {
        echo json_encode(array('success' => false, 'message' => '查询失败: ' . ($driver->lastError ? $driver->lastError : '未知错误')));
        return;
    }
    echo json_encode($result);
}

/**
 * 删除指定 Profile 的某张图片
 * 参数：profile_id（可选）, image_id（catimg 用）, key（local 用）
 */
function handleDeleteImage()
{
    $resolved = resolveProfileAndDriver();
    if (!$resolved) return;
    $driver = $resolved['driver'];
    $config = $resolved['config'];

    $imageId = isset($_POST['image_id']) ? trim($_POST['image_id']) : '';
    $key = isset($_POST['key']) ? trim($_POST['key']) : '';
    $url = isset($_POST['url']) ? trim($_POST['url']) : '';

    $meta = array();
    if ($imageId !== '') $meta['id'] = $imageId;
    if ($key !== '') $meta['key'] = $key;
    if ($url !== '') $meta['url'] = $url;

    try {
        $ok = $driver->delete($meta, $config);
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '删除异常: ' . $e->getMessage()));
        return;
    }
    if (!$ok) {
        echo json_encode(array('success' => false, 'message' => '删除失败: ' . ($driver->lastError ? $driver->lastError : '请检查图片是否存在')));
        return;
    }
    echo json_encode(array('success' => true, 'message' => '已删除'));
}
