<?php
/**
 * 文章投票功能核心文件
 * 支持文章编辑页自定义投票问题、选项和截止时间
 * IP + Cookie 双重防刷
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 确保投票记录表存在
 */
function shufei_ensure_vote_table()
{
    static $checked = false;
    if ($checked) return;

    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $adapterName = $db->getAdapterName();
    $tableName = $prefix . 'post_votes';
    $quotedTable = $db->getAdapter()->quoteColumn($tableName);

    try {
        $db->query("SELECT 1 FROM " . $quotedTable . " LIMIT 1");
    } catch (\Exception $e) {
        $isPgsql = ($adapterName === 'Pdo_Pgsql' || $adapterName === 'Pgsql');
        $isSqlite = ($adapterName === 'Pdo_SQLite' || $adapterName === 'SQLite');

        if ($isPgsql) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                \"id\" serial NOT NULL,
                \"cid\" integer NOT NULL DEFAULT 0,
                \"option_index\" integer NOT NULL DEFAULT 0,
                \"voter_ip\" varchar(64) NOT NULL DEFAULT '',
                \"voter_ua\" varchar(255) NOT NULL DEFAULT '',
                \"voted_at\" integer NOT NULL DEFAULT 0,
                PRIMARY KEY (\"id\")
            )";
        } elseif ($isSqlite) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                \"id\" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
                \"cid\" integer NOT NULL DEFAULT 0,
                \"option_index\" integer NOT NULL DEFAULT 0,
                \"voter_ip\" varchar(64) NOT NULL DEFAULT '',
                \"voter_ua\" varchar(255) NOT NULL DEFAULT '',
                \"voted_at\" integer NOT NULL DEFAULT 0
            )";
        } else {
            // MySQL / MariaDB
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `cid` int(10) unsigned NOT NULL DEFAULT '0',
                `option_index` int(10) unsigned NOT NULL DEFAULT '0',
                `voter_ip` varchar(64) NOT NULL DEFAULT '',
                `voter_ua` varchar(255) NOT NULL DEFAULT '',
                `voted_at` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `idx_cid` (`cid`),
                KEY `idx_cid_ip` (`cid`, `voter_ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        $db->query($sql);
    }

    $checked = true;
}

/**
 * 获取客户端真实 IP（兼容代理环境）
 * @return string
 */
function shufei_get_voter_ip()
{
    $remote = trim(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

    // 判断是否为内网/保留/环回地址（代理自身）
    $isPrivate = function ($ip) {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) &&
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    };

    // 客户端直连为公网地址时，X-Forwarded-For/X-Real-IP 完全可由客户端伪造，直接忽略，防止刷票
    if (!empty($remote) && !$isPrivate($remote)) {
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    // 处于反向代理/CDN 之后：从 XFF 最右侧（最近代理追加）向左取第一个公网 IP
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
        foreach ($ips as $cand) {
            if ($cand !== '' && filter_var($cand, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $cand;
            }
        }
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $xrip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($xrip, FILTER_VALIDATE_IP)) {
            return $xrip;
        }
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * 获取文章投票配置（从文章自定义字段读取）
 * @param int $cid
 * @return array|null 配置数组，无投票则返回 null
 */
function shufei_get_vote_config($cid)
{
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();

    // 读取文章字段（Typecho fields 表使用 str_value 列存储字符串值）
    $fields = $db->fetchAll($db->select('name', 'str_value')
        ->from($prefix . 'fields')
        ->where('cid = ?', $cid));

    $fieldMap = array();
    foreach ($fields as $f) {
        $fieldMap[$f['name']] = $f['str_value'];
    }

    // 投票问题
    $question = isset($fieldMap['voteQuestion']) ? trim($fieldMap['voteQuestion']) : '';
    // 选项（每行一个）
    $optionsRaw = isset($fieldMap['voteOptions']) ? trim($fieldMap['voteOptions']) : '';
    // 截止时间（YYYY-MM-DD 或 YYYY-MM-DD HH:MM）
    $deadline = isset($fieldMap['voteDeadline']) ? trim($fieldMap['voteDeadline']) : '';

    if ($question === '' || $optionsRaw === '') {
        return null;
    }

    // 解析选项
    $options = array();
    $lines = preg_split('/\r\n|\r|\n/', $optionsRaw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $options[] = $line;
        }
    }

    if (count($options) < 2) {
        return null;
    }

    // 解析截止时间戳
    $deadlineTs = 0;
    if ($deadline !== '') {
        $ts = strtotime($deadline);
        if ($ts !== false) {
            $deadlineTs = $ts;
        }
    }

    return array(
        'question'  => $question,
        'options'   => $options,
        'deadline'  => $deadlineTs,
        'deadlineText' => $deadline
    );
}

/**
 * 检查当前用户是否已投票
 * @param int $cid
 * @return array ['voted' => bool, 'option' => int|null]
 */
function shufei_check_voted($cid)
{
    $ip = shufei_get_voter_ip();
    $cookieKey = 'shufei_voted_' . $cid;
    $cookieVal = isset($_COOKIE[$cookieKey]) ? $_COOKIE[$cookieKey] : '';

    // Cookie 优先：快速判断
    if ($cookieVal !== '') {
        return array('voted' => true, 'option' => intval($cookieVal));
    }

    // 数据库 IP 查询
    shufei_ensure_vote_table();
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $tableName = $prefix . 'post_votes';

    try {
        $row = $db->fetchRow($db->select('option_index')
            ->from($tableName)
            ->where('cid = ?', $cid)
            ->where('voter_ip = ?', $ip)
            ->limit(1));
        if ($row) {
            return array('voted' => true, 'option' => intval($row['option_index']));
        }
    } catch (\Exception $e) {}

    return array('voted' => false, 'option' => null);
}

/**
 * 获取投票结果统计
 * @param int $cid
 * @param array $options 选项列表
 * @return array ['counts' => [option_index => count], 'total' => int]
 */
function shufei_get_vote_counts($cid, $options)
{
    shufei_ensure_vote_table();
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $tableName = $prefix . 'post_votes';

    $counts = array();
    $total = 0;
    try {
        $rows = $db->fetchAll($db->select('option_index', array('COUNT(*)' => 'cnt'))
            ->from($tableName)
            ->where('cid = ?', $cid)
            ->group('option_index'));
        foreach ($rows as $r) {
            $counts[intval($r['option_index'])] = intval($r['cnt']);
            $total += intval($r['cnt']);
        }
    } catch (\Exception $e) {}

    // 确保所有选项都有计数值
    $result = array();
    for ($i = 0; $i < count($options); $i++) {
        $result[$i] = isset($counts[$i]) ? $counts[$i] : 0;
    }

    return array('counts' => $result, 'total' => $total);
}

/**
 * 提交投票
 * @param int $cid
 * @param int $optionIndex
 * @return array ['success' => bool, 'message' => string]
 */
function shufei_submit_vote($cid, $optionIndex)
{
    shufei_ensure_vote_table();

    $config = shufei_get_vote_config($cid);
    if (!$config) {
        return array('success' => false, 'message' => '该文章未开启投票');
    }

    // 校验选项范围
    if ($optionIndex < 0 || $optionIndex >= count($config['options'])) {
        return array('success' => false, 'message' => '无效的投票选项');
    }

    // 检查截止时间
    if ($config['deadline'] > 0 && time() > $config['deadline']) {
        return array('success' => false, 'message' => '投票已截止');
    }

    // 防重复投票
    $check = shufei_check_voted($cid);
    if ($check['voted']) {
        return array('success' => false, 'message' => '您已经投过票了');
    }

    $ip = shufei_get_voter_ip();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    $time = time();

    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $tableName = $prefix . 'post_votes';

    try {
        $db->query($db->insert($tableName)->rows(array(
            'cid'          => $cid,
            'option_index' => $optionIndex,
            'voter_ip'     => $ip,
            'voter_ua'     => $ua,
            'voted_at'     => $time
        )));
    } catch (\Exception $e) {
        return array('success' => false, 'message' => '投票失败，请稍后重试');
    }

    // 写入 Cookie（30天）
    setcookie('shufei_voted_' . $cid, strval($optionIndex), $time + 30 * 86400, '/');

    return array('success' => true, 'message' => '投票成功');
}
