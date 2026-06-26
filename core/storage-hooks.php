<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 多存储后端上传钩子
 *
 * 通过注册 Widget\Upload 的以下钩子拦截图片上传：
 *  - uploadHandle:        上传新文件时
 *  - modifyHandle:        替换已有附件时（重新上传）
 *  - attachmentHandle:    生成附件公开访问 URL 时
 *  - deleteHandle:        删除附件时
 *  - attachmentDataHandle: 取附件二进制数据时
 *
 * 仅当：
 *  - 主题存储功能已开启（shufeiStorageEnabled === 'on'）
 *  - 当前存在已激活的 Profile（shufeiStorageActiveProfile）
 *  - 上传文件为图片类型
 * 时才拦截；非图片或配置缺失则回退到 Typecho 原生逻辑。
 */
class ShufeiStorageHooks
{
    /**
     * 注册所有钩子
     */
    public static function register()
    {
        \Typecho\Plugin::factory('Widget\Upload')->uploadHandle = __CLASS__ . '::uploadHandle';
        \Typecho\Plugin::factory('Widget\Upload')->modifyHandle = __CLASS__ . '::modifyHandle';
        \Typecho\Plugin::factory('Widget\Upload')->attachmentHandle = __CLASS__ . '::attachmentHandle';
        \Typecho\Plugin::factory('Widget\Upload')->deleteHandle = __CLASS__ . '::deleteHandle';
        \Typecho\Plugin::factory('Widget\Upload')->attachmentDataHandle = __CLASS__ . '::attachmentDataHandle';
    }

    /**
     * 读取存储功能配置
     *
     * @return array {
     *     enabled: bool,
     *     activeProfileId: string,
     *     activeProfile: array|null,
     *     processing: array
     * }
     */
    public static function getConfig()
    {
        try {
            $options = \Typecho\Widget::widget('Widget_Options');
        } catch (\Throwable $e) {
            return array('enabled' => false, 'activeProfileId' => '', 'activeProfile' => null, 'processing' => array(), 'profilesJson' => '');
        }

        $enabled = isset($options->shufeiStorageEnabled) ? ($options->shufeiStorageEnabled === 'on') : false;
        $activeId = isset($options->shufeiStorageActiveProfile) ? $options->shufeiStorageActiveProfile : '';
        $profilesJson = isset($options->shufeiStorageProfiles) ? $options->shufeiStorageProfiles : '';
        $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
        if (!is_array($profiles)) {
            $profiles = array();
        }

        $activeProfile = null;
        foreach ($profiles as $p) {
            if (isset($p['id']) && $p['id'] === $activeId) {
                $activeProfile = $p;
                break;
            }
        }

        $processing = array(
            'compress'           => isset($options->shufeiStorageCompress) ? $options->shufeiStorageCompress : 'off',
            'compressQuality'    => isset($options->shufeiStorageCompressQuality) ? intval($options->shufeiStorageCompressQuality) : 80,
            'webp'               => isset($options->shufeiStorageWebp) ? $options->shufeiStorageWebp : 'off',
            'watermark'          => isset($options->shufeiStorageWatermark) ? $options->shufeiStorageWatermark : 'off',
            'watermarkType'      => isset($options->shufeiStorageWatermarkType) ? $options->shufeiStorageWatermarkType : 'text',
            'watermarkText'      => isset($options->shufeiStorageWatermarkText) ? $options->shufeiStorageWatermarkText : '',
            'watermarkImage'     => isset($options->shufeiStorageWatermarkImage) ? $options->shufeiStorageWatermarkImage : '',
            'watermarkPosition'  => isset($options->shufeiStorageWatermarkPosition) ? $options->shufeiStorageWatermarkPosition : 'br',
            'watermarkOpacity'   => isset($options->shufeiStorageWatermarkOpacity) ? intval($options->shufeiStorageWatermarkOpacity) : 50,
            'watermarkSize'      => isset($options->shufeiStorageWatermarkSize) ? intval($options->shufeiStorageWatermarkSize) : 16,
            'watermarkColor'     => isset($options->shufeiStorageWatermarkColor) ? $options->shufeiStorageWatermarkColor : '#FFFFFF',
            'watermarkFont'      => isset($options->shufeiStorageWatermarkFont) ? $options->shufeiStorageWatermarkFont : '',
        );

        return array(
            'enabled' => $enabled,
            'activeProfileId' => $activeId,
            'activeProfile' => $activeProfile,
            'processing' => $processing,
            'profilesJson' => $profilesJson,
        );
    }

    /**
     * 是否为图片类型
     */
    public static function isImage($file)
    {
        $name = isset($file['name']) ? $file['name'] : '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') $ext = 'jpg';
        $imageExts = array('jpg', 'png', 'gif', 'webp', 'bmp');
        if (in_array($ext, $imageExts, true)) {
            return true;
        }
        $tmp = isset($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmp && function_exists('getimagesize')) {
            $info = @getimagesize($tmp);
            if ($info && isset($info[2]) && $info[2] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否应当拦截本次上传
     */
    protected static function shouldIntercept($file)
    {
        $cfg = self::getConfig();
        if (!$cfg['enabled']) {
            return false;
        }
        if (!$cfg['activeProfile']) {
            return false;
        }
        if (!self::isImage($file)) {
            return false;
        }
        return true;
    }

    /**
     * uploadHandle 钩子
     *
     * @param array $file 上传文件数组
     * @return array|false
     */
    public static function uploadHandle(array $file)
    {
        if (!self::shouldIntercept($file)) {
            return false;
        }

        $cfg = self::getConfig();
        $profile = $cfg['activeProfile'];
        $driverId = isset($profile['driver']) ? $profile['driver'] : '';
        $driverConfig = isset($profile['config']) ? $profile['config'] : array();

        // 生成远程文件名：YYYY/MM/crc32.ext
        $origName = isset($file['name']) ? $file['name'] : 'image';
        $ext = ShufeiImageProcessor::guessExt($origName);
        $date = new \Typecho\Date();
        $remoteName = $date->year . '/' . $date->month . '/' . sprintf('%u', crc32(uniqid())) . '.' . $ext;

        $tmpPath = isset($file['tmp_name']) ? $file['tmp_name'] : '';
        if (!$tmpPath || !is_file($tmpPath)) {
            return false;
        }

        // 应用图片处理
        $processed = self::applyImageProcessing($tmpPath, $cfg['processing'], $ext);
        $localPath = $processed['path'];
        $mime = $processed['mime'];
        $finalExt = $processed['ext'];
        // 若扩展名因 WebP 改变，更新 remoteName
        if ($finalExt !== $ext) {
            $remoteName = preg_replace('/\.[^.]+$/', '', $remoteName) . '.' . $finalExt;
        }

        // 本地驱动：写入 usr/uploads/
        if ($driverId === 'local') {
            return self::saveLocal($localPath, $remoteName, $origName, $finalExt, $mime);
        }

        // 远程驱动
        $driver = ShufeiStorageDriver::factory($driverId);
        if (!$driver) {
            return false;
        }

        $result = $driver->upload($localPath, $remoteName, $mime, $driverConfig);

        // 清理临时处理文件（若与原 tmp 不同）
        if ($localPath !== $tmpPath) {
            @unlink($localPath);
        }

        if (!$result) {
            return false;
        }

        $size = isset($result['size']) ? $result['size'] : (isset($file['size']) ? $file['size'] : 0);

        return array(
            'name' => $origName,
            'path' => $result['url'], // 远程 URL 作为 path
            'size' => $size,
            'type' => $finalExt,
            'mime' => $mime,
            // 额外元信息用于删除/修改
            '_driver' => $driverId,
            '_key' => isset($result['key']) ? $result['key'] : '',
            '_profile_id' => $cfg['activeProfileId'],
        );
    }

    /**
     * modifyHandle 钩子（替换附件）
     */
    public static function modifyHandle(array $content, array $file)
    {
        if (!self::shouldIntercept($file)) {
            return false;
        }
        // 复用上传逻辑，但保留原 name/path
        $cfg = self::getConfig();
        $profile = $cfg['activeProfile'];
        $driverId = isset($profile['driver']) ? $profile['driver'] : '';
        $driverConfig = isset($profile['config']) ? $profile['config'] : array();

        $origPath = isset($content['attachment']->path) ? $content['attachment']->path : '';
        $origName = isset($content['attachment']->name) ? $content['attachment']->name : (isset($file['name']) ? $file['name'] : 'image');
        $origType = isset($content['attachment']->type) ? $content['attachment']->type : '';

        $tmpPath = isset($file['tmp_name']) ? $file['tmp_name'] : '';
        if (!$tmpPath || !is_file($tmpPath)) {
            return false;
        }

        $ext = ShufeiImageProcessor::guessExt($origName);
        $processed = self::applyImageProcessing($tmpPath, $cfg['processing'], $ext);
        $localPath = $processed['path'];
        $mime = $processed['mime'];
        $finalExt = $processed['ext'];

        // 删除旧文件（远程驱动）
        if ($driverId !== 'local' && strpos($origPath, '://') !== false) {
            $oldKey = isset($content['attachment']->_key) ? $content['attachment']->_key : '';
            if ($oldKey) {
                $drv = ShufeiStorageDriver::factory($driverId);
                if ($drv) {
                    $drv->delete(array('key' => $oldKey, 'url' => $origPath), $driverConfig);
                }
            }
        } elseif ($driverId === 'local' && $origPath && strpos($origPath, '://') === false) {
            @unlink(__TYPECHO_ROOT_DIR__ . '/' . $origPath);
        }

        // 重新上传，使用新的 remoteName（保留年月结构）
        $date = new \Typecho\Date();
        $remoteName = $date->year . '/' . $date->month . '/' . sprintf('%u', crc32(uniqid())) . '.' . $finalExt;

        if ($driverId === 'local') {
            $result = self::saveLocal($localPath, $remoteName, $origName, $finalExt, $mime);
        } else {
            $driver = ShufeiStorageDriver::factory($driverId);
            if (!$driver) {
                if ($localPath !== $tmpPath) @unlink($localPath);
                return false;
            }
            $r = $driver->upload($localPath, $remoteName, $mime, $driverConfig);
            if ($localPath !== $tmpPath) @unlink($localPath);
            if (!$r) {
                return false;
            }
            $result = array(
                'name' => $origName,
                'path' => $r['url'],
                'size' => isset($r['size']) ? $r['size'] : 0,
                'type' => $finalExt,
                'mime' => $mime,
                '_driver' => $driverId,
                '_key' => isset($r['key']) ? $r['key'] : '',
                '_profile_id' => $cfg['activeProfileId'],
            );
        }

        return $result;
    }

    /**
     * attachmentHandle 钩子（生成 URL）
     *
     * @param \Typecho\Config $attachment
     * @return string
     */
    public static function attachmentHandle($attachment)
    {
        $path = $attachment->path;
        // 远程 URL 直接返回
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        // 本地路径：使用 Typecho 原生逻辑
        $options = \Typecho\Widget::widget('Widget_Options');
        return \Typecho\Common::url(
            $path,
            defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : $options->siteUrl
        );
    }

    /**
     * deleteHandle 钩子
     */
    public static function deleteHandle(array $content)
    {
        $path = isset($content['attachment']->path) ? $content['attachment']->path : '';
        $driverId = isset($content['attachment']->_driver) ? $content['attachment']->_driver : '';
        $key = isset($content['attachment']->_key) ? $content['attachment']->_key : '';

        // 远程驱动：调用 driver delete
        if ($driverId && $driverId !== 'local' && strpos($path, '://') !== false) {
            $cfg = self::getConfig();
            $profile = $cfg['activeProfile'];
            // 若当前 Profile 与上传时不同，尝试从 profile 列表中按 id 查找
            $useProfile = $profile;
            $profileId = isset($content['attachment']->_profile_id) ? $content['attachment']->_profile_id : '';
            if ($profileId && (!$profile || $profile['id'] !== $profileId)) {
                $profilesJson = isset($cfg['profilesJson']) ? $cfg['profilesJson'] : '';
                $profiles = $profilesJson ? @json_decode($profilesJson, true) : array();
                foreach ((array)$profiles as $p) {
                    if (isset($p['id']) && $p['id'] === $profileId) {
                        $useProfile = $p;
                        break;
                    }
                }
            }
            $driver = ShufeiStorageDriver::factory($driverId);
            if ($driver && $useProfile) {
                $driverConfig = isset($useProfile['config']) ? $useProfile['config'] : array();
                return $driver->delete(array('key' => $key, 'url' => $path), $driverConfig);
            }
            return true; // 无法删除也认为成功，避免阻塞 Typecho 删除流程
        }

        // 本地：原生 unlink
        if ($path) {
            return @unlink(__TYPECHO_ROOT_DIR__ . '/' . $path);
        }
        return false;
    }

    /**
     * attachmentDataHandle 钩子
     */
    public static function attachmentDataHandle(array $content)
    {
        $path = isset($content['attachment']->path) ? $content['attachment']->path : '';
        if (preg_match('#^https?://#i', $path)) {
            // 远程：通过 HTTP 获取
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        }
        // 本地
        return file_get_contents(
            \Typecho\Common::url(
                $path,
                defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__
            )
        );
    }

    /**
     * 应用图片处理（压缩/转 WebP/水印）
     */
    protected static function applyImageProcessing($tmpPath, array $processing, $origExt)
    {
        $options = array(
            'compress'          => !empty($processing['compress']) && $processing['compress'] === 'on',
            'compressQuality'   => isset($processing['compressQuality']) ? intval($processing['compressQuality']) : 80,
            'webp'              => !empty($processing['webp']) && $processing['webp'] === 'on',
            'watermark'         => !empty($processing['watermark']) && $processing['watermark'] === 'on',
            'watermarkType'     => isset($processing['watermarkType']) ? $processing['watermarkType'] : 'text',
            'watermarkText'     => isset($processing['watermarkText']) ? $processing['watermarkText'] : '',
            'watermarkImage'    => isset($processing['watermarkImage']) ? $processing['watermarkImage'] : '',
            'watermarkPosition' => isset($processing['watermarkPosition']) ? $processing['watermarkPosition'] : 'br',
            'watermarkOpacity'  => isset($processing['watermarkOpacity']) ? intval($processing['watermarkOpacity']) : 50,
            'watermarkSize'     => isset($processing['watermarkSize']) ? intval($processing['watermarkSize']) : 16,
            'watermarkColor'    => isset($processing['watermarkColor']) ? $processing['watermarkColor'] : '#FFFFFF',
            'watermarkFont'     => isset($processing['watermarkFont']) ? $processing['watermarkFont'] : '',
        );

        // 水印图片路径解析为绝对路径
        if ($options['watermarkImage'] !== '') {
            $img = $options['watermarkImage'];
            if (!preg_match('#^(/|https?://)#i', $img) && !is_file($img)) {
                $abs = __TYPECHO_ROOT_DIR__ . '/' . ltrim($img, '/');
                if (is_file($abs)) {
                    $options['watermarkImage'] = $abs;
                }
            }
        }

        // 字体路径解析
        if ($options['watermarkFont'] !== '') {
            $font = $options['watermarkFont'];
            if (!is_file($font)) {
                $abs = __TYPECHO_ROOT_DIR__ . '/' . ltrim($font, '/');
                if (is_file($abs)) {
                    $options['watermarkFont'] = $abs;
                }
            }
        }

        return ShufeiImageProcessor::process($tmpPath, $options);
    }

    /**
     * 本地驱动保存文件（遵循 Typecho 原生 usr/uploads/YYYY/MM 结构）
     */
    protected static function saveLocal($localPath, $remoteName, $origName, $ext, $mime)
    {
        $uploadDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
        $rootDir = defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__;

        $absDir = $rootDir . $uploadDir . '/' . dirname($remoteName);
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) {
            return false;
        }
        $absPath = $rootDir . $uploadDir . '/' . $remoteName;
        $relPath = $uploadDir . '/' . $remoteName;

        // 移动文件
        if (is_uploaded_file($localPath)) {
            if (!@move_uploaded_file($localPath, $absPath)) {
                return false;
            }
        } else {
            if (!@copy($localPath, $absPath)) {
                return false;
            }
            @unlink($localPath);
        }

        $size = @filesize($absPath);
        return array(
            'name' => $origName,
            'path' => $relPath,
            'size' => $size,
            'type' => $ext,
            'mime' => $mime,
            '_driver' => 'local',
            '_key' => $relPath,
            '_profile_id' => '',
        );
    }
}

// 自动注册钩子（仅当主题文件被加载时）
ShufeiStorageHooks::register();
