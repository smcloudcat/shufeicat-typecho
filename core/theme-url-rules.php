<?php
/**
 * 主题设置 URL 字段校验规则（共享库）
 *
 * 从 theme-save-ajax.php 抽出，供以下端点共用：
 *  - theme-save-ajax.php（后台保存）
 *  - ai-settings-ajax.php（AI 设置助手 update 工具）
 *
 * 校验逻辑与 Typecho Common::safeUrl + Validate::url 保持一致：
 * 非法值先自动清洗（去引号/尖括号/控制字符/空白），中文路径自动 percent-encode，
 * 仍不合法则判定失败（调用方决定忽略或报错）。
 */

if (!defined('SHUFEI_THEME_URL_RULES')) {
    define('SHUFEI_THEME_URL_RULES', 1);

    /**
     * 与 Typecho Common::safeUrl + Validate::url 一致的 URL 校验
     * 返回 [bool 合法, string 清洗后值]
     */
    function shufei_theme_save_check_url($raw)
    {
        $str = (string) $raw;

        // 快速通过：合法原样返回
        if (shufei_theme_save_php_url_valid($str)) {
            return array(true, $str);
        }

        // 清洗：去除引号、尖括号、反引号、反斜杠、控制字符与首尾空白
        $cleaned = str_replace(array('"', "'", '<', '>', '`', '\\'), '', $str);
        $cleaned = preg_replace('/[\x00-\x1f\x7f]/', '', $cleaned);
        $cleaned = preg_replace('/[\x{00A0}\x{3000}]/u', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned !== '' && shufei_theme_save_php_url_valid($cleaned)) {
            return array(true, $cleaned);
        }

        // 中文等非 ASCII 是 FILTER_VALIDATE_URL 的硬限制：尝试对路径/查询部分做 rawurlencode
        $ascii = shufei_theme_save_encode_nonascii($cleaned);
        if ($ascii !== '' && shufei_theme_save_php_url_valid($ascii)) {
            return array(true, $ascii);
        }

        return array(false, '');
    }

    /**
     * 精确复现 Typecho Validate::url 的判定
     */
    function shufei_theme_save_php_url_valid($str)
    {
        if ($str === '') {
            return false;
        }
        $url = shufei_theme_save_safe_url($str);
        return (bool) (filter_var($str, FILTER_VALIDATE_URL) && ($url === $str));
    }

    function shufei_theme_save_safe_url($url)
    {
        $params = parse_url(str_replace(["\r", "\n", "\t", ' '], '', $url));

        if (isset($params['scheme'])) {
            if (!in_array($params['scheme'], array('http', 'https'))) {
                return '/';
            }
        }

        $params = array_map(function ($string) {
            $string = str_replace(array('%0d', '%0a'), '', strip_tags($string));
            $string = preg_replace(
                array("/\(\s*([\"'])/i", "/([\"'])\s*\)/i"),
                '',
                $string
            );
            $string = str_replace(array('"', "'", '<', '>'), '', $string);
            return $string;
        }, $params);

        return shufei_theme_save_build_url($params);
    }

    function shufei_theme_save_build_url(array $params)
    {
        return (isset($params['scheme']) ? $params['scheme'] . '://' : null)
            . (isset($params['user']) ? $params['user']
                . (isset($params['pass']) ? ':' . $params['pass'] : null) . '@' : null)
            . (isset($params['host']) ? $params['host'] : null)
            . (isset($params['port']) ? ':' . $params['port'] : null)
            . (isset($params['path']) ? $params['path'] : null)
            . (isset($params['query']) ? '?' . $params['query'] : null)
            . (isset($params['fragment']) ? '#' . $params['fragment'] : null);
    }

    /**
     * 将主机名之外的部分（路径/查询/锚点）中的非 ASCII 字符 percent-encode，
     * 以兼容 PHP FILTER_VALIDATE_URL 的 ASCII 限制
     */
    function shufei_theme_save_encode_nonascii($url)
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            return '';
        }
        $host = $parts['host'];
        // 主机含非 ASCII（如中文域名）时转 punycode 不可行，直接判失败
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            return '';
        }
        $rebuilt = $parts['scheme'] . '://';
        if (isset($parts['user'])) {
            $rebuilt = rawurlencode($parts['user']);
            $host = $rebuilt . (isset($parts['pass']) ? ':' . rawurlencode($parts['pass']) : '') . '@' . $host;
        }
        $out = $parts['scheme'] . '://' . $host;
        if (isset($parts['port'])) {
            $out .= ':' . $parts['port'];
        }
        foreach (array('path', 'query', 'fragment') as $part) {
            if (!isset($parts[$part])) {
                continue;
            }
            $enc = preg_replace_callback('/[^\x21-\x7e]+/u', function ($m) {
                return rawurlencode($m[0]);
            }, $parts[$part]);
            $out .= ($part === 'path' ? $enc : ($part === 'query' ? '?' . $enc : '#' . $enc));
        }
        return $out;
    }

    /**
     * 需要校验 URL 的字段及其展示名
     */
    function shufei_theme_save_url_fields()
    {
        return array(
            'logoUrl'           => 'Logo 图片地址',
            'shufeiUpdateApiUrl' => '更新接口地址',
            'footerBeianLink'   => '备案链接',
            'footerGonganLink'  => '公安备案链接',
            'bgImage'           => '背景图片',
            'customCdn'         => '自定义 CDN 地址',
            'seoOgImage'        => 'OG 分享图',
        );
    }
}
