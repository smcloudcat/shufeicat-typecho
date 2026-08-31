<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 多存储后端驱动
 *
 * 支持驱动：
 *  - local       本地存储（遵循 Typecho 原生逻辑，存储至 usr/uploads/）
 *  - lsky        Lsky Pro 兰空图床（v1 / v2 API）
 *  - s3          AWS S3 / 兼容（MinIO / Cloudflare R2 / 阿里云 OSS S3 兼容）
 *  - webdav      标准 WebDAV 协议
 *  - aliyunoss   阿里云 OSS（V1 签名）
 *  - tencentcos  腾讯云 COS（COS V5）
 *  - qiniukodo   七牛云 KODO
 *  - upyun       又拍云 USS
 *  - catimg      小猫咪图床（X-API-Key 认证）
 *
 * 每个驱动实现：
 *  - upload($localPath, $remoteName, $mime, array $config): array|false
 *      成功返回 ['url' => 公开访问URL, 'key' => 远程存储key/路径, 'size' => 字节, 'name' => 文件名]
 *  - delete(array $meta, array $config): bool
 *  - testConnection(array $config): array ['success' => bool, 'message' => string]
 *  - configFields(): array  配置字段定义（用于 UI 渲染）
 *
 * 工厂方法 ShufeiStorageDriver::factory($driver) 返回对应驱动实例。
 */

/**
 * 驱动基类
 */
abstract class ShufeiStorageDriver
{
    /**
     * 上传文件
     *
     * @param string $localPath  本地文件绝对路径
     * @param string $remoteName 远程文件名（含路径，如 2024/01/abc.jpg）
     * @param string $mime       MIME 类型
     * @param array  $config     驱动配置
     * @return array|false 成功返回 ['url','key','size','name']，失败返回 false
     */
    abstract public function upload($localPath, $remoteName, $mime, array $config);

    /**
     * 删除文件
     *
     * @param array $meta   附件元信息（含 key、url 等）
     * @param array $config 驱动配置
     * @return bool
     */
    abstract public function delete(array $meta, array $config);

    /**
     * 测试连接
     *
     * @param array $config 驱动配置
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function testConnection(array $config);

    /**
     * 配置字段定义
     * 每个字段: ['name' => 字段名, 'label' => 标签, 'type' => text|password|select|radio, 'options' => [...], 'default' => ..., 'placeholder' => ..., 'required' => bool, 'help' => ...]
     *
     * @return array
     */
    abstract public function configFields();

    /**
     * 列出已上传的图片（分页）
     *
     * @param array $config 驱动配置
     * @param int   $page   页码（从 1 开始）
     * @param int   $limit  每页数量
     * @return array ['success' => bool, 'message' => string, 'data' => ['total' => int, 'page' => int, 'limit' => int, 'list' => [['id','url','name','size','time'], ...]]]
     *               不支持列表功能的驱动返回 success=false
     */
    public function listImages(array $config, $page = 1, $limit = 20)
    {
        return array('success' => false, 'message' => '该驱动暂不支持图片列表查询');
    }

    /**
     * 驱动显示名
     *
     * @return string
     */
    abstract public function name();

    /**
     * 驱动标识
     *
     * @return string
     */
    abstract public function id();

    /**
     * 通用 HTTP 请求
     */
    /** 最后一次 HTTP 请求的错误信息（供调用方诊断） */
    public $lastError = '';

    protected function httpRequest($url, $method = 'GET', $options = array())
    {
        $this->lastError = '';
        $ch = curl_init();
        if (!$ch) {
            return array('code' => 0, 'body' => '', 'error' => 'curl_init failed');
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, isset($options['timeout']) ? $options['timeout'] : 120);

        $headers = isset($options['headers']) ? $options['headers'] : array();
        $body = isset($options['body']) ? $options['body'] : null;
        $multipart = isset($options['multipart']) ? $options['multipart'] : null;

        if ($multipart !== null) {
            // multipart 表单：优先流式写入临时文件再上传，避免大文件整读入内存（2-3 倍内存占用导致 OOM）
            // 不使用 CURLFile（某些共享主机上会导致 segfault），改用 CURLOPT_INFILE 流式上传
            $boundary = '----ShufeiBoundary' . md5(uniqid('', true));
            $tmp = @tmpfile();
            if ($tmp === false) {
                // 无法创建临时文件时回退到内存拼接（小文件场景）
                $bodyStr = '';
                foreach ($multipart as $fieldName => $field) {
                    if (is_array($field) && isset($field['file'])) {
                        // 文件字段
                        $filePath = $field['file'];
                        $fileName = isset($field['name']) ? $field['name'] : basename($filePath);
                        $fileMime = isset($field['mime']) ? $field['mime'] : 'application/octet-stream';
                        $fileContent = @file_get_contents($filePath);
                        if ($fileContent === false) {
                            return array('code' => 0, 'body' => '', 'error' => 'read file failed: ' . $filePath);
                        }
                        $bodyStr .= '--' . $boundary . "\r\n";
                        $bodyStr .= 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $fileName . '"' . "\r\n";
                        $bodyStr .= 'Content-Type: ' . $fileMime . "\r\n\r\n";
                        $bodyStr .= $fileContent . "\r\n";
                    } else {
                        // 普通字段
                        $bodyStr .= '--' . $boundary . "\r\n";
                        $bodyStr .= 'Content-Disposition: form-data; name="' . $fieldName . '"' . "\r\n\r\n";
                        $bodyStr .= $field . "\r\n";
                    }
                }
                $bodyStr .= '--' . $boundary . "--\r\n";

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
                $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
                $headers[] = 'Content-Length: ' . strlen($bodyStr);
            } else {
                // 流式：将 multipart body 分块写入临时文件，再通过文件句柄上传，避免大文件整读入内存
                $totalSize = 0;
                foreach ($multipart as $fieldName => $field) {
                    if (is_array($field) && isset($field['file'])) {
                        // 文件字段：分块流式写入
                        $filePath = $field['file'];
                        $fileName = isset($field['name']) ? $field['name'] : basename($filePath);
                        $fileMime = isset($field['mime']) ? $field['mime'] : 'application/octet-stream';
                        $head = '--' . $boundary . "\r\n"
                            . 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $fileName . '"' . "\r\n"
                            . 'Content-Type: ' . $fileMime . "\r\n\r\n";
                        fwrite($tmp, $head);
                        $totalSize += strlen($head);
                        $fh = @fopen($filePath, 'rb');
                        if ($fh === false) {
                            fclose($tmp);
                            return array('code' => 0, 'body' => '', 'error' => 'read file failed: ' . $filePath);
                        }
                        while (!feof($fh)) {
                            $chunk = fread($fh, 8192);
                            if ($chunk === false) {
                                break;
                            }
                            fwrite($tmp, $chunk);
                            $totalSize += strlen($chunk);
                        }
                        fclose($fh);
                        fwrite($tmp, "\r\n");
                        $totalSize += 2;
                    } else {
                        // 普通字段
                        $part = '--' . $boundary . "\r\n"
                            . 'Content-Disposition: form-data; name="' . $fieldName . '"' . "\r\n\r\n"
                            . $field . "\r\n";
                        fwrite($tmp, $part);
                        $totalSize += strlen($part);
                    }
                }
                $tail = '--' . $boundary . "--\r\n";
                fwrite($tmp, $tail);
                $totalSize += strlen($tail);
                rewind($tmp);

                curl_setopt($ch, CURLOPT_UPLOAD, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_INFILE, $tmp);
                curl_setopt($ch, CURLOPT_INFILESIZE, $totalSize);
                $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
            }
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // 释放流式上传的临时文件句柄
        if (isset($tmp) && is_resource($tmp)) {
            fclose($tmp);
        }

        // 保存错误信息供调用方诊断
        if ($error) {
            $this->lastError = 'cURL错误: ' . $error;
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            $this->lastError = 'HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 500);
        }

        return array(
            'code' => $httpCode,
            'body' => $response,
            'error' => $error,
        );
    }

    /**
     * 流式计算文件的 SHA256（用于 S3/COS 签名），避免 file_get_contents 整读大文件导致 OOM
     *
     * @param string $path
     * @return string|false 无法读取时返回 false
     */
    protected function hashFileSha256($path)
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $ctx = hash_init('sha256');
        while (!feof($fh)) {
            $chunk = fread($fh, 32768);
            if ($chunk === false) {
                break;
            }
            hash_update($ctx, $chunk);
        }
        fclose($fh);
        return hash_final($ctx);
    }

    /**
     * 拼接 URL（处理结尾斜杠）
     */
    protected function joinUrl($base, $path)
    {
        $base = rtrim($base, '/');
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }

    /**
     * 规范化配置中的 URL
     */
    protected function normalizeUrl($url)
    {
        $url = trim($url);
        return rtrim($url, '/');
    }

    /**
     * 校验必填字段
     *
     * @return array 错误消息数组
     */
    protected function validateRequired(array $config, array $fields)
    {
        $errors = array();
        foreach ($fields as $f) {
            if (!empty($f['required']) && (!isset($config[$f['name']]) || trim($config[$f['name']]) === '')) {
                $errors[] = '「' . $f['label'] . '」不能为空';
            }
        }
        return $errors;
    }

    /**
     * 工厂方法
     *
     * @param string $driver 驱动标识
     * @return ShufeiStorageDriver|null
     */
    public static function factory($driver)
    {
        $map = array(
            'local'      => 'ShufeiStorageDriverLocal',
            'lsky'       => 'ShufeiStorageDriverLsky',
            's3'         => 'ShufeiStorageDriverS3',
            'webdav'     => 'ShufeiStorageDriverWebDAV',
            'aliyunoss'  => 'ShufeiStorageDriverAliyunOss',
            'tencentcos' => 'ShufeiStorageDriverTencentCos',
            'qiniukodo'  => 'ShufeiStorageDriverQiniuKodo',
            'upyun'      => 'ShufeiStorageDriverUpyun',
            'catimg'     => 'ShufeiStorageDriverCatimg',
        );
        if (!isset($map[$driver])) {
            return null;
        }
        $class = $map[$driver];
        if (!class_exists($class)) {
            return null;
        }
        return new $class();
    }

    /**
     * 所有驱动列表（id => name）
     */
    public static function driverList()
    {
        return array(
            'local'      => '本地存储',
            'lsky'       => 'Lsky Pro 兰空图床',
            's3'         => 'AWS S3 / 兼容',
            'webdav'     => 'WebDAV',
            'aliyunoss'  => '阿里云 OSS',
            'tencentcos' => '腾讯云 COS',
            'qiniukodo'  => '七牛云 KODO',
            'upyun'      => '又拍云 USS',
            'catimg'     => '小猫咪图床',
        );
    }

    /**
     * 获取所有驱动的字段定义
     */
    public static function allConfigFields()
    {
        $all = array();
        foreach (self::driverList() as $id => $name) {
            $drv = self::factory($id);
            if ($drv) {
                $all[$id] = array(
                    'name' => $name,
                    'fields' => $drv->configFields(),
                );
            }
        }
        return $all;
    }
}

/**
 * 本地存储 — 遵循 Typecho 原生逻辑
 */
class ShufeiStorageDriverLocal extends ShufeiStorageDriver
{
    public function id() { return 'local'; }
    public function name() { return '本地存储'; }

    public function configFields()
    {
        return array(
            array(
                'name' => 'subdir',
                'label' => '子目录（可选）',
                'type' => 'text',
                'default' => '',
                'placeholder' => '如 images，留空则使用 usr/uploads',
                'required' => false,
                'help' => '在 usr/uploads/ 下创建的子目录，留空遵循 Typecho 原生 usr/uploads/YYYY/MM 结构',
            ),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        // 本地驱动实际由 storage-hooks 中的原生 Typecho 逻辑处理，这里仅作占位
        // 实际本地写入在 ShufeiStorageHooks::uploadHandle 中完成
        return false;
    }

    public function delete(array $meta, array $config)
    {
        // 优先用 path，其次用 key（listImages 返回的相对路径）
        $rel = '';
        if (isset($meta['path'])) {
            $rel = $meta['path'];
        } elseif (isset($meta['key'])) {
            $subdir = isset($config['subdir']) ? trim($config['subdir']) : '';
            $baseDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
            if ($subdir !== '') {
                $baseDir = $baseDir . '/' . $subdir;
            }
            $rel = ltrim($baseDir, '/') . '/' . $meta['key'];
        }
        if ($rel === '') {
            return false;
        }

        // 安全校验：拒绝空字节、绝对路径、目录穿越（..），并确保目标位于上传根目录之内，防止越权删除任意文件
        if (strpos($rel, "\0") !== false) {
            return false;
        }
        $normalized = str_replace('\\', '/', $rel);
        if ($normalized === '' || $normalized[0] == '/' || preg_match('/^[a-zA-Z]:\//', $normalized)) {
            return false; // 空路径或绝对路径
        }
        if (strpos($normalized, '..') !== false) {
            return false; // 目录穿越
        }

        $uploadRootDef = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
        $rootAbs = realpath(__TYPECHO_ROOT_DIR__ . '/' . ltrim($uploadRootDef, '/'));
        $dirAbs = realpath(dirname(__TYPECHO_ROOT_DIR__ . '/' . $normalized));
        if ($rootAbs === false || $dirAbs === false) {
            return false;
        }
        // 目标目录必须位于上传根目录之内
        $rootPrefix = rtrim($rootAbs, '/\\') . DIRECTORY_SEPARATOR;
        if ($dirAbs !== $rootAbs && strncmp($dirAbs, $rootPrefix, strlen($rootPrefix)) !== 0) {
            return false;
        }

        $fullPath = $dirAbs . DIRECTORY_SEPARATOR . basename($normalized);
        return @unlink($fullPath);
    }

    public function testConnection(array $config)
    {
        $subdir = isset($config['subdir']) ? trim($config['subdir']) : '';
        $baseDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
        if ($subdir !== '') {
            $baseDir = $baseDir . '/' . $subdir;
        }
        $absDir = __TYPECHO_ROOT_DIR__ . $baseDir;
        if (!is_dir($absDir)) {
            if (!@mkdir($absDir, 0755, true)) {
                return array('success' => false, 'message' => '目录不存在且无法创建：' . $absDir);
            }
        }
        if (!is_writable($absDir)) {
            return array('success' => false, 'message' => '目录不可写：' . $absDir);
        }
        return array('success' => true, 'message' => '✓ 本地目录可写：' . $absDir);
    }

    /**
     * 扫描本地 usr/uploads 目录下的图片文件
     */
    public function listImages(array $config, $page = 1, $limit = 20)
    {
        $subdir = isset($config['subdir']) ? trim($config['subdir']) : '';
        $baseDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
        if ($subdir !== '') {
            $baseDir = $baseDir . '/' . $subdir;
        }
        $absDir = __TYPECHO_ROOT_DIR__ . $baseDir;
        $baseUrl = defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : '';
        if ($baseUrl === '') {
            $options = \Widget\Options::alloc();
            $baseUrl = $options->siteUrl;
        }

        if (!is_dir($absDir)) {
            return array('success' => true, 'message' => '目录尚未创建', 'data' => array('total' => 0, 'page' => $page, 'limit' => $limit, 'list' => array()));
        }

        // 递归扫描图片文件
        $imgExts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
        $allFiles = array();
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $imgExts, true)) continue;
            $relPath = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($absDir))), '/');
            $allFiles[] = array(
                'file' => $f->getPathname(),
                'rel'  => $relPath,
                'time' => $f->getMTime(),
                'size' => $f->getSize(),
            );
        }
        // 按修改时间倒序
        usort($allFiles, function($a, $b) { return $b['time'] - $a['time']; });

        $total = count($allFiles);
        $page = max(1, intval($page));
        $limit = max(1, min(100, intval($limit)));
        $offset = ($page - 1) * $limit;
        $slice = array_slice($allFiles, $offset, $limit);

        $list = array();
        foreach ($slice as $item) {
            $list[] = array(
                'id'   => $item['rel'],
                'url'  => rtrim($baseUrl, '/') . '/' . ltrim($baseDir, '/') . '/' . $item['rel'],
                'name' => basename($item['rel']),
                'size' => intval($item['size']),
                'time' => date('Y-m-d H:i:s', $item['time']),
                'key'  => $item['rel'],
            );
        }

        return array(
            'success' => true,
            'message' => '获取成功',
            'data' => array(
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'list'  => $list,
            ),
        );
    }
}

/**
 * Lsky Pro 兰空图床
 *
 * 接口文档（v2）：https://www.ltimg.com/pages/doc
 *  - 上传：POST /api/v2/upload         （multipart: file, storage_id 可选）
 *  - 资料：GET  /api/v2/user/profile   （验证 Token）
 *  - 列表：GET  /api/v2/user/photos    （分页参数 page、per_page）
 *  - 删除：DELETE /api/v2/user/photos  （请求体 JSON 数组 [id, ...]）
 * 认证：Authorization: Bearer {token}，Accept: application/json
 *
 * v1 接口（/api/v1/*）仅保留上传与连接测试，无批量删除/列表接口。
 */
class ShufeiStorageDriverLsky extends ShufeiStorageDriver
{
    /** @var string|null 探测到的 storage_id 缓存（null=未探测，''=探测失败） */
    private static $detectedStorageId = null;

    public function id() { return 'lsky'; }
    public function name() { return 'Lsky Pro 兰空图床'; }

    public function configFields()
    {
        return array(
            array(
                'name' => 'api_url', 'label' => 'API 地址', 'type' => 'text',
                'default' => '', 'placeholder' => '如 https://www.ltimg.com',
                'required' => true, 'help' => '兰空图床站点地址，可带或不带 /api/v2 后缀（系统会自动处理）',
            ),
            array(
                'name' => 'api_token', 'label' => 'API Token', 'type' => 'password',
                'default' => '', 'placeholder' => '',
                'required' => true, 'help' => '在兰空图床「个人资料 > 生成 Token」获取',
            ),
            array(
                'name' => 'api_version', 'label' => 'API 版本', 'type' => 'select',
                'default' => 'v2', 'options' => array('v1' => 'v1', 'v2' => 'v2'),
                'required' => true, 'help' => '兰空图床 2.x 选择 v2，1.x 选择 v1',
            ),
            array(
                'name' => 'storage_id', 'label' => '存储策略 ID（可留空，但会影响上传速度）', 'type' => 'text',
                'default' => '', 'placeholder' => '留空自动探测，或填如 7',
                'required' => false, 'help' => '仅 v2。留空时上传前自动探测可用策略；ltimg.com 公共服务为 7；自建兰空请填后台「存储策略」中的 ID',
            ),
        );
    }

    /**
     * 读取 storage_id 配置（兼容旧名 strategy_id）
     */
    private function getStorageId(array $config)
    {
        if (isset($config['storage_id']) && $config['storage_id'] !== '') {
            return $config['storage_id'];
        }
        // 向后兼容：旧版本字段名为 strategy_id
        if (isset($config['strategy_id']) && $config['strategy_id'] !== '') {
            return $config['strategy_id'];
        }
        return '';
    }

    /**
     * 规范化 API 地址：去除尾部斜杠及误带的 /api/v1、/api/v2 后缀
     * 用户可能按文档标题填成 https://www.ltimg.com/api/v2，需自动纠正
     */
    private function normalizeApiUrl($url)
    {
        $url = trim($url);
        $url = rtrim($url, '/');
        // 去除误带的 /api/v2 或 /api/v1 后缀（不区分大小写）
        $url = preg_replace('#/api/v[12]$#i', '', $url);
        return $url;
    }

    /**
     * 读取 API 版本，缺省时回退 v2（兼容旧 Profile 缺少该字段的情况）
     */
    private function getVersion(array $config)
    {
        $v = isset($config['api_version']) ? trim($config['api_version']) : '';
        if ($v !== 'v1' && $v !== 'v2') {
            $v = 'v2';
        }
        return $v;
    }

    /**
     * 自动探测可用的 storage_id（仅 v2）
     * 遍历 1-20，用 1x1 测试图片尝试上传，成功后立即删除测试图片。
     * 探测结果缓存在静态属性中，避免单次请求内重复探测。
     *
     * @return string|int 探测到的 storage_id，失败返回 ''
     */
    private function detectStorageId(array $config)
    {
        // 静态缓存：同一请求内只探测一次
        if (self::$detectedStorageId !== null) {
            return self::$detectedStorageId;
        }

        $apiUrl = $this->normalizeApiUrl($config['api_url']);
        $token = isset($config['api_token']) ? $config['api_token'] : '';
        $url = $this->joinUrl($apiUrl, 'api/v2/upload');
        $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');

        // 1x1 透明 PNG（67 字节）
        $testPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
        $tmpFile = tempnam(sys_get_temp_dir(), 'lsky_probe_');
        if ($tmpFile === false) {
            self::$detectedStorageId = '';
            return '';
        }
        file_put_contents($tmpFile, $testPng);

        $detected = '';
        for ($sid = 1; $sid <= 20; $sid++) {
            $multipart = array(
                'file' => array(
                    'file' => $tmpFile,
                    'mime' => 'image/png',
                    'name' => 'probe.png',
                ),
                'storage_id' => strval($sid),
            );
            $resp = $this->httpRequest($url, 'POST', array(
                'multipart' => $multipart,
                'headers' => $headers,
                'timeout' => 30,
            ));
            if ($resp['error']) {
                continue;
            }
            $data = json_decode($resp['body'], true);
            if (isset($data['status']) && $data['status'] === 'success') {
                $detected = strval($sid);
                // 立即删除测试图片，避免污染图床
                if (isset($data['data']['id'])) {
                    $delUrl = $this->joinUrl($apiUrl, 'api/v2/user/photos');
                    $this->httpRequest($delUrl, 'DELETE', array(
                        'headers' => array(
                            'Authorization: Bearer ' . $token,
                            'Accept: application/json',
                            'Content-Type: application/json',
                        ),
                        'body' => json_encode(array(intval($data['data']['id']))),
                        'timeout' => 10,
                    ));
                }
                break;
            }
        }
        @unlink($tmpFile);
        self::$detectedStorageId = $detected;
        return $detected;
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $apiUrl = $this->normalizeApiUrl($config['api_url']);
        $token = isset($config['api_token']) ? $config['api_token'] : '';
        $version = $this->getVersion($config);

        if ($version === 'v1') {
            $url = $this->joinUrl($apiUrl, 'api/v1/upload');
            $headers = array('Authorization: ' . $token);
        } else {
            $url = $this->joinUrl($apiUrl, 'api/v2/upload');
            $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');
        }

        $multipart = array(
            'file' => array(
                'file' => $localPath,
                'mime' => $mime,
                'name' => basename($remoteName),
            ),
        );

        if ($version === 'v2') {
            $storageId = $this->getStorageId($config);
            if ($storageId === '') {
                // storage_id 未配置时自动探测
                $storageId = $this->detectStorageId($config);
            }
            if ($storageId !== '') {
                $multipart['storage_id'] = $storageId;
            }
        }

        $resp = $this->httpRequest($url, 'POST', array(
            'multipart' => $multipart,
            'headers' => $headers,
            'timeout' => 180,
        ));

        if ($resp['error']) {
            // httpRequest 已设置 lastError
            return false;
        }

        $data = json_decode($resp['body'], true);
        if (!$data) {
            $this->lastError = '响应解析失败: ' . substr((string)$resp['body'], 0, 300);
            return false;
        }

        // v2 返回 status: "success"（字符串）；v1 返回 code: 200（整数）
        $ok = false;
        if ($version === 'v2') {
            $ok = isset($data['status']) && $data['status'] === 'success';
        } else {
            $ok = isset($data['code']) && $data['code'] == 200;
        }
        if (!$ok) {
            // 提取 API 返回的具体错误信息，便于前端诊断（如"存储 不能为空"）
            $msg = isset($data['message']) ? $data['message'] : '上传失败';
            $detail = '';
            if (isset($data['data']['errors']) && is_array($data['data']['errors'])) {
                $parts = array();
                foreach ($data['data']['errors'] as $field => $errs) {
                    if (is_array($errs)) {
                        $parts[] = $field . ': ' . implode('; ', $errs);
                    } else {
                        $parts[] = $field . ': ' . $errs;
                    }
                }
                $detail = ' (' . implode(', ', $parts) . ')';
            }
            $this->lastError = $msg . $detail;
            return false;
        }

        // v2: data.public_url；v1: data.url 或 data.links.url
        $urlField = null;
        $imageId = '';
        if ($version === 'v2') {
            $urlField = isset($data['data']['public_url']) ? $data['data']['public_url'] : null;
            $imageId = isset($data['data']['id']) ? strval($data['data']['id']) : '';
        } else {
            $urlField = isset($data['data']['url']) ? $data['data']['url']
                : (isset($data['data']['links']['url']) ? $data['data']['links']['url'] : null);
        }
        if (!$urlField) {
            return false;
        }

        $size = is_file($localPath) ? filesize($localPath) : 0;
        return array(
            'url' => $urlField,
            // v2 用 image_id 作为 key，便于后续删除；v1 无 id 时退化为 URL
            'key' => $imageId !== '' ? $imageId : $urlField,
            'size' => $size,
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        $version = $this->getVersion($config);
        // v1 无批量删除接口，跳过
        if ($version === 'v1') {
            return true;
        }

        // 解析 image_id：优先 meta.id，其次 meta.key（数字时视为 id）
        $imageId = 0;
        if (isset($meta['id']) && $meta['id'] !== '') {
            $imageId = intval($meta['id']);
        } elseif (isset($meta['key']) && is_numeric($meta['key'])) {
            $imageId = intval($meta['key']);
        }

        if (!$imageId) {
            // 无 image_id 无法删除，返回 true 不阻塞 Typecho 删除流程
            return true;
        }

        $apiUrl = $this->normalizeApiUrl($config['api_url']);
        $token = isset($config['api_token']) ? $config['api_token'] : '';
        $url = $this->joinUrl($apiUrl, 'api/v2/user/photos');
        $headers = array(
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        );
        $body = json_encode(array($imageId));

        $resp = $this->httpRequest($url, 'DELETE', array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 20,
        ));

        if ($resp['error']) {
            return false;
        }
        return $resp['code'] >= 200 && $resp['code'] < 300;
    }

    /**
     * 列出当前用户上传的图片（仅 v2）
     * 接口：GET /api/v2/user/photos?page=N&per_page=M
     */
    public function listImages(array $config, $page = 1, $limit = 20)
    {
        $version = $this->getVersion($config);
        if ($version === 'v1') {
            return array('success' => false, 'message' => 'Lsky Pro v1 暂不支持图片列表查询');
        }

        $apiUrl = $this->normalizeApiUrl($config['api_url']);
        $token = isset($config['api_token']) ? $config['api_token'] : '';
        $page = max(1, intval($page));
        $limit = max(1, min(100, intval($limit)));

        $url = $this->joinUrl($apiUrl, 'api/v2/user/photos?page=' . $page . '&per_page=' . $limit);
        $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');

        $resp = $this->httpRequest($url, 'GET', array('headers' => $headers, 'timeout' => 20));
        if ($resp['error']) {
            return array('success' => false, 'message' => '请求失败: ' . $resp['error']);
        }
        if ($resp['code'] !== 200) {
            return array('success' => false, 'message' => 'HTTP ' . $resp['code'] . ': ' . substr((string)$resp['body'], 0, 200));
        }

        $data = json_decode($resp['body'], true);
        if (!$data || !isset($data['data']['data'])) {
            return array('success' => false, 'message' => isset($data['message']) ? $data['message'] : '解析响应失败');
        }

        $list = array();
        foreach ($data['data']['data'] as $item) {
            $list[] = array(
                'id'   => isset($item['id']) ? strval($item['id']) : '',
                'url'  => isset($item['public_url']) ? $item['public_url']
                    : (isset($item['thumbnail_url']) ? $item['thumbnail_url'] : ''),
                'name' => isset($item['name']) ? $item['name']
                    : (isset($item['filename']) ? $item['filename'] : ''),
                'size' => 0,
                'time' => isset($item['created_at']) ? $item['created_at'] : '',
                'key'  => isset($item['id']) ? strval($item['id']) : '',
            );
        }

        $total = isset($data['data']['meta']['total']) ? intval($data['data']['meta']['total']) : count($list);

        return array(
            'success' => true,
            'message' => '获取成功',
            'data' => array(
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'list'  => $list,
            ),
        );
    }

    public function testConnection(array $config)
    {
        // 兼容旧 Profile：api_version 缺失时回退 v2，避免 validateRequired 误报"API 版本不能为空"
        $config['api_version'] = $this->getVersion($config);
        $errors = $this->validateRequired($config, $this->configFields());
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        $apiUrl = $this->normalizeApiUrl($config['api_url']);
        $token = $config['api_token'];
        $version = $config['api_version'];

        // 通过 profile 接口验证 token
        if ($version === 'v1') {
            $url = $this->joinUrl($apiUrl, 'api/v1/profile');
            $headers = array('Authorization: ' . $token);
        } else {
            $url = $this->joinUrl($apiUrl, 'api/v2/user/profile');
            $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');
        }

        $resp = $this->httpRequest($url, 'GET', array('headers' => $headers, 'timeout' => 20));
        if ($resp['error']) {
            return array('success' => false, 'message' => '连接失败：' . $resp['error']);
        }
        if ($resp['code'] !== 200) {
            return array('success' => false, 'message' => '认证失败 (HTTP ' . $resp['code'] . ')，请检查 Token 或 API 版本');
        }
        $data = json_decode($resp['body'], true);
        $name = isset($data['data']['name']) ? $data['data']['name'] : '';
        $email = isset($data['data']['email']) ? $data['data']['email'] : '';
        $display = $name !== '' ? $name : $email;
        $msg = '✓ 认证成功' . ($display ? '，账户：' . $display : '');
        // v2 且 storage_id 未配置时提示将自动探测
        if ($version === 'v2' && $this->getStorageId($config) === '') {
            $msg .= '；存储策略 ID 未配置，将在上传时自动探测';
        }
        return array('success' => true, 'message' => $msg);
    }
}

/**
 * AWS S3 / 兼容（MinIO / R2 / OSS S3 兼容）
 * 使用 AWS Signature V4
 */
class ShufeiStorageDriverS3 extends ShufeiStorageDriver
{
    public function id() { return 's3'; }
    public function name() { return 'AWS S3 / 兼容'; }

    public function configFields()
    {
        return array(
            array('name' => 'access_key', 'label' => 'Access Key', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true, 'default' => ''),
            array('name' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'region', 'label' => 'Region', 'type' => 'text', 'required' => true, 'default' => 'us-east-1', 'placeholder' => '如 us-east-1 / ap-east-1 / auto（R2）'),
            array('name' => 'endpoint', 'label' => 'Endpoint（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 https://s3.amazonaws.com / MinIO/R2/OSS 自定义端点', 'help' => '留空使用 AWS 标准 Endpoint。MinIO/Cloudflare R2/阿里云 OSS（S3 兼容）需填写'),
            array('name' => 'path_style', 'label' => 'Path Style', 'type' => 'radio', 'default' => 'off', 'options' => array('on' => '开启', 'off' => '关闭'), 'help' => 'MinIO 需开启 Path Style；AWS S3 标准 / R2 关闭'),
            array('name' => 'custom_url', 'label' => '自定义访问域名（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 https://cdn.example.com', 'help' => 'CDN 加速域名，留空则使用 Endpoint 拼接'),
            array('name' => 'prefix', 'label' => '路径前缀（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 typecho/'),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $key = ltrim($remoteName, '/');
        if (!empty($config['prefix'])) {
            $key = trim($config['prefix'], '/') . '/' . $key;
        }
        $bucket = $config['bucket'];
        $endpoint = $this->resolveEndpoint($config);
        $host = $this->resolveHost($config);

        $url = $this->buildRequestUrl($config, $host, $key);

        // 流式计算内容哈希用于签名，避免整读大文件导致 OOM
        $payloadHash = $this->hashFileSha256($localPath);
        if ($payloadHash === false) {
            return false;
        }
        $headers = $this->signRequestV4($config, 'PUT', $url, $host, $key, $mime, $payloadHash, $payloadHash);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // 流式上传，避免整读文件
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        fclose($fh);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $code < 200 || $code >= 300) {
            return false;
        }

        $publicUrl = $this->buildPublicUrl($config, $host, $key);
        $size = filesize($localPath);
        return array(
            'url' => $publicUrl,
            'key' => $key,
            'size' => $size,
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        if (empty($meta['key'])) {
            return true;
        }
        $host = $this->resolveHost($config);
        $url = $this->buildRequestUrl($config, $host, $meta['key']);
        $headers = $this->signRequestV4($config, 'DELETE', $url, $host, $meta['key'], '', '', '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, array(
            array('name' => 'access_key', 'label' => 'Access Key', 'required' => true),
            array('name' => 'secret_key', 'label' => 'Secret Key', 'required' => true),
            array('name' => 'bucket', 'label' => 'Bucket', 'required' => true),
        ));
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        $host = $this->resolveHost($config);
        // ListObjects v2 with max-keys=1
        $url = $this->buildRequestUrl($config, $host, '', '?list-type=2&max-keys=1');
        $headers = $this->signRequestV4($config, 'GET', $url, $host, '', '', '', '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return array('success' => false, 'message' => '连接失败：' . $err);
        }
        if ($code !== 200) {
            $msg = $this->extractS3Error($resp);
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $code . ')' . ($msg ? '：' . $msg : ''));
        }
        return array('success' => true, 'message' => '✓ 连接成功，Bucket：' . $config['bucket']);
    }

    private function resolveEndpoint($config)
    {
        $endpoint = isset($config['endpoint']) ? trim($config['endpoint']) : '';
        if ($endpoint === '') {
            $region = isset($config['region']) ? trim($config['region']) : 'us-east-1';
            $endpoint = 'https://s3.' . $region . '.amazonaws.com';
        }
        return rtrim($endpoint, '/');
    }

    private function resolveHost($config)
    {
        $endpoint = $this->resolveEndpoint($config);
        $host = parse_url($endpoint, PHP_URL_HOST);
        return $host;
    }

    private function buildRequestUrl($config, $host, $key, $query = '')
    {
        $bucket = $config['bucket'];
        $pathStyle = !empty($config['path_style']) && $config['path_style'] === 'on';
        $endpoint = $this->resolveEndpoint($config);
        $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';

        if ($pathStyle) {
            $url = $scheme . '://' . $host . '/' . $bucket . '/' . $key . $query;
        } else {
            $url = $scheme . '://' . $bucket . '.' . $host . '/' . $key . $query;
        }
        return $url;
    }

    private function buildPublicUrl($config, $host, $key)
    {
        $customUrl = isset($config['custom_url']) ? trim($config['custom_url']) : '';
        if ($customUrl !== '') {
            return rtrim($customUrl, '/') . '/' . $key;
        }
        $pathStyle = !empty($config['path_style']) && $config['path_style'] === 'on';
        $endpoint = $this->resolveEndpoint($config);
        $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';
        $bucket = $config['bucket'];
        if ($pathStyle) {
            return $scheme . '://' . $host . '/' . $bucket . '/' . $key;
        }
        return $scheme . '://' . $bucket . '.' . $host . '/' . $key;
    }

    private function extractS3Error($xml)
    {
        if (preg_match('#<Message>([^<]+)</Message>#i', $xml, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * AWS Signature V4 签名
     */
    private function signRequestV4($config, $method, $url, $host, $key, $mime, $hashedPayload, $body)
    {
        $accessKey = $config['access_key'];
        $secretKey = $config['secret_key'];
        $region = isset($config['region']) ? trim($config['region']) : 'us-east-1';
        $service = 's3';

        $time = time();
        $amzDate = gmdate('Ymd\THis\Z', $time);
        $dateStamp = gmdate('Ymd', $time);

        // 解析 URL 查询参数
        $parsed = parse_url($url);
        $pathQuery = isset($parsed['path']) ? $parsed['path'] : '/';
        $query = isset($parsed['query']) ? $parsed['query'] : '';
        $canonicalUri = $pathQuery;
        if ($canonicalUri === '') {
            $canonicalUri = '/';
        }
        $canonicalQuery = str_replace('&', '&', $query);
        // 排序
        if ($canonicalQuery !== '') {
            $parts = explode('&', $canonicalQuery);
            sort($parts);
            $canonicalQuery = implode('&', $parts);
        }

        // 优先使用调用方流式预计算的哈希，否则按需对内容取哈希
        $payloadHash = ($hashedPayload !== '') ? $hashedPayload : hash('sha256', $body);

        $canonicalHeaders = "host:" . $host . "\n"
            . "x-amz-content-sha256:" . $payloadHash . "\n"
            . "x-amz-date:" . $amzDate . "\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";

        $canonicalRequest = $method . "\n"
            . $canonicalUri . "\n"
            . $canonicalQuery . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        $scope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n"
            . $amzDate . "\n"
            . $scope . "\n"
            . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $headers = array(
            'Authorization: ' . $authHeader,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $amzDate,
            'Host: ' . $host,
        );
        if ($mime !== '') {
            $headers[] = 'Content-Type: ' . $mime;
        }
        return $headers;
    }
}

/**
 * WebDAV 标准
 */
class ShufeiStorageDriverWebDAV extends ShufeiStorageDriver
{
    public function id() { return 'webdav'; }
    public function name() { return 'WebDAV'; }

    public function configFields()
    {
        return array(
            array('name' => 'url', 'label' => 'WebDAV 地址', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 https://dav.example.com/dav', 'help' => 'WebDAV 服务根路径'),
            array('name' => 'username', 'label' => '用户名', 'type' => 'text', 'required' => false, 'default' => ''),
            array('name' => 'password', 'label' => '密码', 'type' => 'password', 'required' => false, 'default' => ''),
            array('name' => 'prefix', 'label' => '存储前缀', 'type' => 'text', 'required' => false, 'default' => 'typecho/', 'placeholder' => '如 typecho/'),
            array('name' => 'custom_url', 'label' => '公开访问域名', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 https://files.example.com/typecho', 'help' => 'WebDAV 上传后通过此域名拼接访问，需保证该域名可公开访问'),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $url = $this->buildUrl($config, $remoteName);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // 流式上传，避免整读文件导致 OOM
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
        $headers = array('Content-Type: ' . $mime);
        if (!empty($config['username'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($config['username'] . ':' . (isset($config['password']) ? $config['password'] : ''));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($err || $code < 200 || $code >= 300) {
            return false;
        }

        $customUrl = rtrim($config['custom_url'], '/');
        $prefix = trim(isset($config['prefix']) ? $config['prefix'] : '', '/');
        $publicUrl = $customUrl . '/' . ($prefix ? $prefix . '/' : '') . $remoteName;

        return array(
            'url' => $publicUrl,
            'key' => $remoteName,
            'size' => filesize($localPath),
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        if (empty($meta['key'])) {
            return true;
        }
        $url = $this->buildUrl($config, $meta['key']);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $headers = array();
        if (!empty($config['username'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($config['username'] . ':' . (isset($config['password']) ? $config['password'] : ''));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, array(
            array('name' => 'url', 'label' => 'WebDAV 地址', 'required' => true),
            array('name' => 'custom_url', 'label' => '公开访问域名', 'required' => true),
        ));
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        $url = $this->normalizeUrl($config['url']);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Depth: 0'));
        if (!empty($config['username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . (isset($config['password']) ? $config['password'] : ''));
        }
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return array('success' => false, 'message' => '连接失败：' . $err);
        }
        if ($code !== 207 && $code !== 200 && $code !== 204) {
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $code . ')，请检查地址与认证');
        }
        return array('success' => true, 'message' => '✓ WebDAV 连接正常');
    }

    private function buildUrl($config, $key)
    {
        $url = $this->normalizeUrl($config['url']);
        $prefix = trim(isset($config['prefix']) ? $config['prefix'] : '', '/');
        $path = ltrim($key, '/');
        if ($prefix) {
            $path = $prefix . '/' . $path;
        }
        return $url . '/' . $path;
    }
}

/**
 * 阿里云 OSS（V1 签名）
 */
class ShufeiStorageDriverAliyunOss extends ShufeiStorageDriver
{
    public function id() { return 'aliyunoss'; }
    public function name() { return '阿里云 OSS'; }

    public function configFields()
    {
        return array(
            array('name' => 'access_key_id', 'label' => 'Access Key ID', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'access_key_secret', 'label' => 'Access Key Secret', 'type' => 'password', 'required' => true, 'default' => ''),
            array('name' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'endpoint', 'label' => 'Endpoint', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 oss-cn-hangzhou.aliyuncs.com', 'help' => '不带 Bucket 名，不带 https://'),
            array('name' => 'custom_url', 'label' => '自定义域名（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 https://cdn.example.com', 'help' => 'CDN 加速域名，留空则使用默认 OSS 域名'),
            array('name' => 'prefix', 'label' => '路径前缀（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 typecho/'),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $key = $this->buildKey($config, $remoteName);
        $url = $this->buildSignedUrl($config, $key, 'PUT', $mime);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // 流式上传，避免整读文件导致 OOM
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: ' . $mime, 'Date: ' . gmdate('D, d M Y H:i:s \G\M\T')));
        $resp = curl_exec($ch);
        fclose($fh);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $code < 200 || $code >= 300) {
            return false;
        }

        return array(
            'url' => $this->buildPublicUrl($config, $key),
            'key' => $key,
            'size' => filesize($localPath),
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        if (empty($meta['key'])) {
            return true;
        }
        $url = $this->buildSignedUrl($config, $meta['key'], 'DELETE', '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, array(
            array('name' => 'access_key_id', 'label' => 'Access Key ID', 'required' => true),
            array('name' => 'access_key_secret', 'label' => 'Access Key Secret', 'required' => true),
            array('name' => 'bucket', 'label' => 'Bucket', 'required' => true),
            array('name' => 'endpoint', 'label' => 'Endpoint', 'required' => true),
        ));
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        // 列举 Bucket（max-keys=1）
        $url = $this->buildSignedUrl($config, '', 'GET', '', '?max-keys=1&prefix=');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return array('success' => false, 'message' => '连接失败：' . $err);
        }
        if ($code !== 200) {
            $msg = '';
            if (preg_match('#<Message>([^<]+)</Message>#i', $resp, $m)) {
                $msg = $m[1];
            }
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $code . ')' . ($msg ? '：' . $msg : ''));
        }
        return array('success' => true, 'message' => '✓ 连接成功，Bucket：' . $config['bucket']);
    }

    private function buildKey($config, $remoteName)
    {
        $key = ltrim($remoteName, '/');
        if (!empty($config['prefix'])) {
            $key = trim($config['prefix'], '/') . '/' . $key;
        }
        return $key;
    }

    private function buildPublicUrl($config, $key)
    {
        $customUrl = isset($config['custom_url']) ? trim($config['custom_url']) : '';
        if ($customUrl !== '') {
            return rtrim($customUrl, '/') . '/' . $key;
        }
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        return $scheme . '://' . $config['bucket'] . '.' . trim($config['endpoint'], '/') . '/' . $key;
    }

    /**
     * OSS V1 签名
     */
    private function buildSignedUrl($config, $key, $method, $mime, $query = '')
    {
        $bucket = $config['bucket'];
        $endpoint = trim($config['endpoint'], '/');
        $accessId = $config['access_key_id'];
        $accessSecret = $config['access_key_secret'];

        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $resource = '/' . $bucket . '/' . $key;
        $strToSign = $method . "\n\n" . ($mime ?: '') . "\n" . $date . "\n" . $resource;
        $signature = base64_encode(hash_hmac('sha1', $strToSign, $accessSecret, true));

        $url = 'https://' . $bucket . '.' . $endpoint . '/' . $key;
        if ($query !== '') {
            $url .= $query;
        }
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'OSSAccessKeyId=' . urlencode($accessId) . '&Expires=0&Signature=' . urlencode($signature);
        return $url;
    }
}

/**
 * 腾讯云 COS（COS V5）
 */
class ShufeiStorageDriverTencentCos extends ShufeiStorageDriver
{
    public function id() { return 'tencentcos'; }
    public function name() { return '腾讯云 COS'; }

    public function configFields()
    {
        return array(
            array('name' => 'secret_id', 'label' => 'SecretId', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'secret_key', 'label' => 'SecretKey', 'type' => 'password', 'required' => true, 'default' => ''),
            array('name' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 name-1250000000', 'help' => '格式：BucketName-APPID'),
            array('name' => 'region', 'label' => 'Region', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 ap-guangzhou'),
            array('name' => 'custom_url', 'label' => '自定义域名（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 https://cdn.example.com'),
            array('name' => 'prefix', 'label' => '路径前缀（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 typecho/'),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $key = $this->buildKey($config, $remoteName);
        $url = $this->buildUrl($config, $key);
        // 流式计算内容哈希用于签名，避免整读大文件导致 OOM
        $payloadHash = $this->hashFileSha256($localPath);
        if ($payloadHash === false) {
            return false;
        }
        $headers = $this->signV5($config, 'put', $key, $mime, $payloadHash);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // 流式上传，避免整读文件导致 OOM
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        fclose($fh);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $code < 200 || $code >= 300) {
            return false;
        }

        return array(
            'url' => $this->buildPublicUrl($config, $key),
            'key' => $key,
            'size' => filesize($localPath),
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        if (empty($meta['key'])) {
            return true;
        }
        $url = $this->buildUrl($config, $meta['key']);
        $headers = $this->signV5($config, 'delete', $meta['key'], '', '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, array(
            array('name' => 'secret_id', 'label' => 'SecretId', 'required' => true),
            array('name' => 'secret_key', 'label' => 'SecretKey', 'required' => true),
            array('name' => 'bucket', 'label' => 'Bucket', 'required' => true),
            array('name' => 'region', 'label' => 'Region', 'required' => true),
        ));
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        $key = '';
        $url = $this->buildUrl($config, $key) . '?max-keys=1';
        $headers = $this->signV5($config, 'get', $key, '', '', '?max-keys=1');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return array('success' => false, 'message' => '连接失败：' . $err);
        }
        if ($code !== 200) {
            $msg = '';
            if (preg_match('#<Message>([^<]+)</Message>#i', $resp, $m)) {
                $msg = $m[1];
            }
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $code . ')' . ($msg ? '：' . $msg : ''));
        }
        return array('success' => true, 'message' => '✓ 连接成功，Bucket：' . $config['bucket']);
    }

    private function buildKey($config, $remoteName)
    {
        $key = ltrim($remoteName, '/');
        if (!empty($config['prefix'])) {
            $key = trim($config['prefix'], '/') . '/' . $key;
        }
        return $key;
    }

    private function buildUrl($config, $key)
    {
        return 'https://' . $config['bucket'] . '.cos.' . $config['region'] . '.myqcloud.com/' . $key;
    }

    private function buildPublicUrl($config, $key)
    {
        $customUrl = isset($config['custom_url']) ? trim($config['custom_url']) : '';
        if ($customUrl !== '') {
            return rtrim($customUrl, '/') . '/' . $key;
        }
        return $this->buildUrl($config, $key);
    }

    /**
     * COS V5 签名（Authorization）
     */
    private function signV5($config, $method, $key, $mime, $body, $query = '')
    {
        $secretId = $config['secret_id'];
        $secretKey = $config['secret_key'];
        $bucket = $config['bucket'];
        $region = $config['region'];

        $service = 'cos';
        $host = $bucket . '.cos.' . $region . '.myqcloud.com';
        $path = '/' . $key;

        $timestamp = time();
        $startTime = $timestamp;
        $expireTime = $startTime + 600;

        // KeyTime
        $keyTime = $startTime . ';' . $expireTime;

        // Step 1: SignKey
        $signKey = hash_hmac('sha1', $keyTime, $secretKey);

        // Step 2: CanonicalRequest
        $httpMethod = strtolower($method);
        // URI 编码处理：COS 规范要求 path 编码
        if ($key !== '') {
            $canonicalUri = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        } else {
            $canonicalUri = '/';
        }
        $canonicalQueryString = '';
        if ($query !== '') {
            $q = ltrim($query, '?');
            $parts = explode('&', $q);
            $kv = array();
            foreach ($parts as $p) {
                if (strpos($p, '=') !== false) {
                    list($k, $v) = explode('=', $p, 2);
                } else {
                    $k = $p; $v = '';
                }
                $kv[] = rawurlencode($k) . '=' . rawurlencode($v);
            }
            sort($kv);
            $canonicalQueryString = implode('&', $kv);
        }
        $headerList = array('host' => $host);
        ksort($headerList);
        $canonicalHeaders = '';
        $signedHeaderList = array();
        foreach ($headerList as $k => $v) {
            $canonicalHeaders .= strtolower($k) . '=' . $v . "\n";
            $signedHeaderList[] = strtolower($k);
        }
        $signedHeaders = implode(';', $signedHeaderList);
        // 调用方传入 64 位十六进制哈希时直接使用（流式预计算），否则按需对内容取哈希
        $hashedPayload = (strlen($body) === 64 && ctype_xdigit($body)) ? strtolower($body) : hash('sha256', $body);

        $canonicalRequest = $httpMethod . "\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $hashedPayload;

        // Step 3: StringToSign
        $stringToSign = "sha1\n" . $keyTime . "\n" . sha1($canonicalRequest) . "\n";

        // Step 4: Signature
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        // Step 5: Authorization
        $auth = 'q-sign-algorithm=sha1&q-ak=' . $secretId
            . '&q-sign-time=' . $keyTime
            . '&q-key-time=' . $keyTime
            . '&q-header-list=' . $signedHeaders
            . '&q-url-param-list=' . ''
            . '&q-signature=' . $signature;

        $headers = array(
            'Host: ' . $host,
            'Authorization: ' . $auth,
        );
        if ($mime !== '') {
            $headers[] = 'Content-Type: ' . $mime;
        }
        return $headers;
    }
}

/**
 * 七牛云 KODO
 */
class ShufeiStorageDriverQiniuKodo extends ShufeiStorageDriver
{
    public function id() { return 'qiniukodo'; }
    public function name() { return '七牛云 KODO'; }

    public function configFields()
    {
        return array(
            array('name' => 'access_key', 'label' => 'AccessKey', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'secret_key', 'label' => 'SecretKey', 'type' => 'password', 'required' => true, 'default' => ''),
            array('name' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'upload_url', 'label' => '上传域名', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 https://up.qiniup.com', 'help' => '华东 z0：https://up.qiniup.com；华东 z1：https://up-z1.qiniup.com；北美 na0：https://up-na0.qiniup.com'),
            array('name' => 'custom_url', 'label' => '绑定域名', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 https://cdn.example.com', 'help' => '七牛空间绑定的访问域名'),
            array('name' => 'prefix', 'label' => '路径前缀（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 typecho/'),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $key = $this->buildKey($config, $remoteName);
        $uploadToken = $this->makeUploadToken($config, $key);

        $url = rtrim($config['upload_url'], '/') . '/';
        $multipart = array(
            'token' => $uploadToken,
            'key' => $key,
            'file' => array(
                'file' => $localPath,
                'mime' => $mime,
                'name' => basename($remoteName),
            ),
        );

        $resp = $this->httpRequest($url, 'POST', array('multipart' => $multipart, 'timeout' => 300));
        if ($resp['error']) {
            return false;
        }
        $data = json_decode($resp['body'], true);
        if (!$data || !isset($data['key']) || !empty($data['error'])) {
            return false;
        }

        $publicUrl = rtrim($config['custom_url'], '/') . '/' . $key;
        return array(
            'url' => $publicUrl,
            'key' => $key,
            'size' => filesize($localPath),
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        if (empty($meta['key'])) {
            return true;
        }
        // POST /delete/<EncodedEntryURI> with Authorization
        $entry = base64_encode($config['bucket'] . ':' . $meta['key']);
        $url = 'https://rs.qiniuapi.com/delete/' . $entry;
        $accessToken = $this->makeAccessToken($config, 'POST', $url, '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: QBox ' . $config['access_key'] . ':' . $accessToken));
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, array(
            array('name' => 'access_key', 'label' => 'AccessKey', 'required' => true),
            array('name' => 'secret_key', 'label' => 'SecretKey', 'required' => true),
            array('name' => 'bucket', 'label' => 'Bucket', 'required' => true),
            array('name' => 'upload_url', 'label' => '上传域名', 'required' => true),
            array('name' => 'custom_url', 'label' => '绑定域名', 'required' => true),
        ));
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        // 调用 bucketinfo 接口
        $entry = base64_encode($config['bucket']);
        $url = 'https://rs.qiniuapi.com/bucketinfo/' . $entry;
        $accessToken = $this->makeAccessToken($config, 'GET', $url, '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: QBox ' . $config['access_key'] . ':' . $accessToken));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return array('success' => false, 'message' => '连接失败：' . $err);
        }
        if ($code !== 200) {
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $code . ')，请检查密钥与 Bucket');
        }
        return array('success' => true, 'message' => '✓ 连接成功，Bucket：' . $config['bucket']);
    }

    private function buildKey($config, $remoteName)
    {
        $key = ltrim($remoteName, '/');
        if (!empty($config['prefix'])) {
            $key = trim($config['prefix'], '/') . '/' . $key;
        }
        return $key;
    }

    /**
     * 生成上传 Token
     */
    private function makeUploadToken($config, $key)
    {
        $deadline = time() + 3600;
        $putPolicy = array(
            'scope' => $config['bucket'] . ':' . $key,
            'deadline' => $deadline,
        );
        $encodedPutPolicy = $this->base64UrlEncode(json_encode($putPolicy));
        $signature = hash_hmac('sha1', $encodedPutPolicy, $config['secret_key'], true);
        $encodedSign = $this->base64UrlEncode($signature);
        return $config['access_key'] . ':' . $encodedSign . ':' . $encodedPutPolicy;
    }

    /**
     * 生成管理 AccessToken
     */
    private function makeAccessToken($config, $method, $url, $body)
    {
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? $parsed['query'] : '';
        $signingStr = $method . ' ' . $path . ($query ? '?' . $query : '') . "\n" . $body;
        return $this->base64UrlEncode(hash_hmac('sha1', $signingStr, $config['secret_key'], true));
    }

    private function base64UrlEncode($data)
    {
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
    }
}

/**
 * 又拍云 USS
 */
class ShufeiStorageDriverUpyun extends ShufeiStorageDriver
{
    public function id() { return 'upyun'; }
    public function name() { return '又拍云 USS'; }

    public function configFields()
    {
        return array(
            array('name' => 'bucket', 'label' => '服务名', 'type' => 'text', 'required' => true, 'default' => '', 'help' => '又拍云云存储服务名'),
            array('name' => 'operator', 'label' => '操作员', 'type' => 'text', 'required' => true, 'default' => ''),
            array('name' => 'password', 'label' => '操作员密码', 'type' => 'password', 'required' => true, 'default' => ''),
            array('name' => 'custom_url', 'label' => '绑定域名', 'type' => 'text', 'required' => true, 'default' => '', 'placeholder' => '如 https://cdn.example.com', 'help' => '又拍云服务绑定的访问域名'),
            array('name' => 'prefix', 'label' => '路径前缀（可选）', 'type' => 'text', 'required' => false, 'default' => '', 'placeholder' => '如 typecho/'),
            array('name' => 'endpoint', 'label' => '上传 API 域名', 'type' => 'select', 'required' => true, 'default' => 'v0.api.upyun.com',
                'options' => array(
                    'v0.api.upyun.com' => '自动（v0）',
                    'v1.api.upyun.com' => '电信（v1）',
                    'v2.api.upyun.com' => '联通（v2）',
                    'v3.api.upyun.com' => '移动（v3）',
                )),
        );
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $key = $this->buildKey($config, $remoteName);
        $endpoint = isset($config['endpoint']) ? $config['endpoint'] : 'v0.api.upyun.com';
        $url = 'https://' . $endpoint . '/' . $config['bucket'] . '/' . $key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // 流式上传，避免整读文件导致 OOM
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) {
            return false;
        }
        $fileSize = filesize($localPath);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
        $auth = 'Basic ' . base64_encode($config['operator'] . ':' . $config['password']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $auth,
            'Content-Type: ' . $mime,
            'Content-Length: ' . $fileSize,
        ));
        $resp = curl_exec($ch);
        fclose($fh);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $code < 200 || $code >= 300) {
            return false;
        }

        $publicUrl = rtrim($config['custom_url'], '/') . '/' . $key;
        return array(
            'url' => $publicUrl,
            'key' => $key,
            'size' => filesize($localPath),
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        if (empty($meta['key'])) {
            return true;
        }
        $endpoint = isset($config['endpoint']) ? $config['endpoint'] : 'v0.api.upyun.com';
        $url = 'https://' . $endpoint . '/' . $config['bucket'] . '/' . $meta['key'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $auth = 'Basic ' . base64_encode($config['operator'] . ':' . $config['password']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $auth));
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, array(
            array('name' => 'bucket', 'label' => '服务名', 'required' => true),
            array('name' => 'operator', 'label' => '操作员', 'required' => true),
            array('name' => 'password', 'label' => '操作员密码', 'required' => true),
            array('name' => 'custom_url', 'label' => '绑定域名', 'required' => true),
        ));
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }
        $endpoint = isset($config['endpoint']) ? $config['endpoint'] : 'v0.api.upyun.com';
        $url = 'https://' . $endpoint . '/' . $config['bucket'] . '/?usage';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $auth = 'Basic ' . base64_encode($config['operator'] . ':' . $config['password']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $auth));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            return array('success' => false, 'message' => '连接失败：' . $err);
        }
        if ($code !== 200) {
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $code . ')，请检查操作员与密码');
        }
        return array('success' => true, 'message' => '✓ 连接成功，服务：' . $config['bucket']);
    }

    private function buildKey($config, $remoteName)
    {
        $key = ltrim($remoteName, '/');
        if (!empty($config['prefix'])) {
            $key = trim($config['prefix'], '/') . '/' . $key;
        }
        return $key;
    }
}

/**
 * 小猫咪图床（catimg）
 * 接口文档：https://img.czzu.cn/api/v1.php
 * 认证：X-API-Key Header
 *
 * 注意：上传接口响应不返回 image_id，但删除接口需要 image_id。
 * 因此删除时先调用 list 接口按 filename 查找对应 image_id，再执行删除。
 * 上传时将 filename 作为 key 保存到 meta，便于后续删除匹配。
 */
class ShufeiStorageDriverCatimg extends ShufeiStorageDriver
{
    public function id() { return 'catimg'; }
    public function name() { return '小猫咪图床'; }

    public function configFields()
    {
        return array(
            array(
                'name' => 'api_url', 'label' => 'API 地址', 'type' => 'text',
                'default' => 'https://img.czzu.cn/api/v1.php',
                'placeholder' => '如 https://img.czzu.cn/api/v1.php',
                'required' => true, 'help' => '图床 API 基础地址，默认为官方地址，可自建',
            ),
            array(
                'name' => 'api_key', 'label' => 'API Key', 'type' => 'password',
                'default' => '', 'placeholder' => 'img_...',
                'required' => true, 'help' => '格式为 img_ + 48位十六进制字符，由管理员在图床后台生成',
            ),
        );
    }

    /**
     * 构造完整请求 URL（自动追加 path 参数）
     */
    private function buildRequestUrl($apiUrl, $path, $query = array())
    {
        $url = $this->normalizeUrl($apiUrl);
        // 兼容用户填入的已有 query
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        $url .= $separator . 'path=' . $path;
        if ($query) {
            foreach ($query as $k => $v) {
                $url .= '&' . $k . '=' . urlencode($v);
            }
        }
        return $url;
    }

    public function upload($localPath, $remoteName, $mime, array $config)
    {
        $apiUrl = isset($config['api_url']) ? $config['api_url'] : 'https://img.czzu.cn/api/v1.php';
        $apiKey = isset($config['api_key']) ? $config['api_key'] : '';
        if (!$apiKey) {
            return false;
        }

        $url = $this->buildRequestUrl($apiUrl, 'upload');
        $multipart = array(
            'file' => array(
                'file' => $localPath,
                'mime' => $mime,
                'name' => basename($remoteName),
            ),
        );

        $resp = $this->httpRequest($url, 'POST', array(
            'multipart' => $multipart,
            'headers' => array('X-API-Key: ' . $apiKey),
            'timeout' => 180,
        ));

        if ($resp['error']) {
            return false;
        }
        if ($resp['code'] !== 200) {
            return false;
        }

        $data = json_decode($resp['body'], true);
        if (!$data || empty($data['success']) || empty($data['data']['url'])) {
            return false;
        }

        $imgUrl = $data['data']['url'];
        $filename = isset($data['data']['filename']) ? $data['data']['filename'] : basename($imgUrl);
        $size = isset($data['data']['size']) ? intval($data['data']['size']) : (is_file($localPath) ? filesize($localPath) : 0);

        return array(
            'url' => $imgUrl,
            'key' => $filename, // 保存 filename，删除时用于在 list 中查找 image_id
            'size' => $size,
            'name' => basename($remoteName),
        );
    }

    public function delete(array $meta, array $config)
    {
        $apiUrl = isset($config['api_url']) ? $config['api_url'] : 'https://img.czzu.cn/api/v1.php';
        $apiKey = isset($config['api_key']) ? $config['api_key'] : '';
        if (!$apiKey) {
            return false;
        }

        // 优先使用直接传入的 image_id（来自图片管理界面）
        $imageId = 0;
        if (isset($meta['id']) && $meta['id'] !== '') {
            $imageId = intval($meta['id']);
        }

        // 否则通过 filename 在 list 中查找 image_id
        if (!$imageId) {
            $filename = isset($meta['key']) ? $meta['key'] : '';
            if (!$filename) {
                // 无 id 也无 filename，跳过
                return true;
            }
            // 最多翻 5 页（每页 50 条），覆盖最近 250 张图
            for ($page = 1; $page <= 5; $page++) {
                $listUrl = $this->buildRequestUrl($apiUrl, 'list', array('page' => $page, 'limit' => 50));
                $resp = $this->httpRequest($listUrl, 'GET', array(
                    'headers' => array('X-API-Key: ' . $apiKey),
                    'timeout' => 20,
                ));
                if ($resp['error'] || $resp['code'] !== 200) {
                    break;
                }
                $data = json_decode($resp['body'], true);
                if (!$data || empty($data['success']) || empty($data['data']['list'])) {
                    break;
                }
                foreach ($data['data']['list'] as $item) {
                    if (isset($item['filename']) && $item['filename'] === $filename) {
                        $imageId = isset($item['id']) ? intval($item['id']) : 0;
                        break 2;
                    }
                }
                if (count($data['data']['list']) < 50) {
                    break;
                }
            }
        }

        if (!$imageId) {
            // 找不到对应记录（可能已被删除），视为成功
            return true;
        }

        // 调用删除接口
        $delUrl = $this->buildRequestUrl($apiUrl, 'delete');
        $resp = $this->httpRequest($delUrl, 'POST', array(
            'headers' => array(
                'X-API-Key: ' . $apiKey,
                'Content-Type: application/x-www-form-urlencoded',
            ),
            'body' => 'image_id=' . $imageId,
            'timeout' => 20,
        ));

        if ($resp['error']) {
            return false;
        }
        $data = json_decode($resp['body'], true);
        return ($data && !empty($data['success']));
    }

    /**
     * 列出当前用户上传的图片
     * 接口：GET ?path=list&page=N&limit=M
     */
    public function listImages(array $config, $page = 1, $limit = 20)
    {
        $apiUrl = isset($config['api_url']) ? $config['api_url'] : 'https://img.czzu.cn/api/v1.php';
        $apiKey = isset($config['api_key']) ? $config['api_key'] : '';
        if (!$apiKey) {
            return array('success' => false, 'message' => 'API Key 未配置');
        }

        $page = max(1, intval($page));
        $limit = max(1, min(100, intval($limit)));
        $url = $this->buildRequestUrl($apiUrl, 'list', array('page' => $page, 'limit' => $limit));
        $resp = $this->httpRequest($url, 'GET', array(
            'headers' => array('X-API-Key: ' . $apiKey),
            'timeout' => 20,
        ));

        if ($resp['error']) {
            return array('success' => false, 'message' => '请求失败: ' . $resp['error']);
        }
        if ($resp['code'] !== 200) {
            return array('success' => false, 'message' => 'HTTP ' . $resp['code'] . ': ' . substr((string)$resp['body'], 0, 200));
        }

        $data = json_decode($resp['body'], true);
        if (!$data || empty($data['success']) || !isset($data['data']['list'])) {
            return array('success' => false, 'message' => isset($data['message']) ? $data['message'] : '解析响应失败');
        }

        $list = array();
        foreach ($data['data']['list'] as $item) {
            $list[] = array(
                'id'   => isset($item['id']) ? strval($item['id']) : '',
                'url'  => isset($item['file_path']) ? $item['file_path'] : (isset($item['url']) ? $item['url'] : ''),
                'name' => isset($item['original_name']) ? $item['original_name'] : (isset($item['filename']) ? $item['filename'] : ''),
                'size' => isset($item['file_size']) ? intval($item['file_size']) : 0,
                'time' => isset($item['upload_time']) ? $item['upload_time'] : '',
                'key'  => isset($item['filename']) ? $item['filename'] : '',
            );
        }

        return array(
            'success' => true,
            'message' => '获取成功',
            'data' => array(
                'total' => isset($data['data']['total']) ? intval($data['data']['total']) : count($list),
                'page'  => $page,
                'limit' => $limit,
                'list'  => $list,
            ),
        );
    }

    public function testConnection(array $config)
    {
        $errors = $this->validateRequired($config, $this->configFields());
        if ($errors) {
            return array('success' => false, 'message' => implode('；', $errors));
        }

        $apiUrl = isset($config['api_url']) ? $config['api_url'] : 'https://img.czzu.cn/api/v1.php';
        $apiKey = $config['api_key'];

        // 通过 info 接口验证 API Key
        $url = $this->buildRequestUrl($apiUrl, 'info');
        $resp = $this->httpRequest($url, 'GET', array(
            'headers' => array('X-API-Key: ' . $apiKey),
            'timeout' => 20,
        ));

        if ($resp['error']) {
            return array('success' => false, 'message' => '连接失败：' . $resp['error']);
        }
        if ($resp['code'] === 401) {
            return array('success' => false, 'message' => '认证失败，API Key 无效');
        }
        if ($resp['code'] !== 200) {
            return array('success' => false, 'message' => '请求失败 (HTTP ' . $resp['code'] . ')');
        }

        $data = json_decode($resp['body'], true);
        if (!$data || empty($data['success'])) {
            $msg = ($data && isset($data['message'])) ? $data['message'] : '响应解析失败';
            return array('success' => false, 'message' => $msg);
        }

        $info = isset($data['data']) ? $data['data'] : array();
        $username = isset($info['username']) ? $info['username'] : '';
        $count = isset($info['image_count']) ? $info['image_count'] : '?';
        $sizeMb = isset($info['total_size_mb']) ? $info['total_size_mb'] : '?';
        return array(
            'success' => true,
            'message' => '✓ 认证成功' . ($username ? '，用户：' . $username : '')
                . '，已上传 ' . $count . ' 张，占用 ' . $sizeMb . ' MB',
        );
    }
}
