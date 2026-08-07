<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * GitHub Repos 缓存
 * 从 functions.php 分层迁移
 */

/**
 * 获取 GitHub 项目列表（带缓存）
 * 优化：缓存过期时返回旧缓存并异步刷新，避免前台同步阻塞最长 50 秒
 *
 * @return array 项目列表数组
 */
function shufei_get_github_repos()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $username = isset($options->githubUsername) ? trim($options->githubUsername) : '';

    if (empty($username)) {
        return array();
    }

    // 获取选定的项目列表
    $selectedRepos = array();
    $selectedReposRaw = isset($options->githubSelectedRepos) ? trim($options->githubSelectedRepos) : '';
    if (!empty($selectedReposRaw)) {
        $decoded = @json_decode($selectedReposRaw, true);
        if (is_array($decoded)) {
            $selectedRepos = $decoded;
        }
    }

    $cacheTime = isset($options->githubCacheTime) ? intval($options->githubCacheTime) : 3600;
    $cacheFile = dirname(__FILE__) . '/cache/github_repos.json';
    $refreshingFlag = dirname(__FILE__) . '/cache/github_repos.refreshing';

    // 按选定项目过滤的闭包
    $filterSelected = function($repos) use ($selectedRepos) {
        if (!empty($selectedRepos)) {
            $repos = array_filter($repos, function($repo) use ($selectedRepos) {
                return in_array($repo['name'], $selectedRepos);
            });
            $repos = array_values($repos);
        }
        return $repos;
    };

    // 尝试从缓存读取
    $cachedRepos = null;
    $cacheIsFresh = false;
    if (file_exists($cacheFile)) {
        $cache = @json_decode(@file_get_contents($cacheFile), true);
        if ($cache && isset($cache['timestamp'], $cache['repos'])) {
            $cachedRepos = $cache['repos'];
            $cacheIsFresh = (time() - $cache['timestamp']) < $cacheTime;
            if ($cacheIsFresh) {
                // 缓存新鲜，直接返回
                return $filterSelected($cachedRepos);
            }
        }
    }

    // 缓存过期或不存在时：
    // 1. 若存在旧缓存，立即返回旧数据，避免前台阻塞
    // 2. 通过标志文件避免并发刷新
    if ($cachedRepos !== null) {
        // 触发后台刷新（仅当没有正在进行的刷新时）
        if (!file_exists($refreshingFlag) || (time() - @filemtime($refreshingFlag)) > 300) {
            @touch($refreshingFlag);
            shufei_refresh_github_repos_cache($username, $cacheFile);
            @unlink($refreshingFlag);
        }
        return $filterSelected($cachedRepos);
    }

    // 完全无缓存时，仅同步获取第一页（最多 10 秒）以快速填充缓存
    $allRepos = shufei_fetch_github_repos_page($username, 1);
    // 若第一页已满 100 条，再异步获取剩余页（不阻塞当前请求）
    if (count($allRepos) >= 100) {
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode(array(
            'timestamp' => time(),
            'repos' => $allRepos
        )));
        // 后台补全剩余页
        if (!file_exists($refreshingFlag) || (time() - @filemtime($refreshingFlag)) > 300) {
            @touch($refreshingFlag);
            shufei_refresh_github_repos_cache($username, $cacheFile);
            @unlink($refreshingFlag);
        }
        return $filterSelected($allRepos);
    }

    // 写入缓存
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode(array(
        'timestamp' => time(),
        'repos' => $allRepos
    )));

    return $filterSelected($allRepos);
}

/**
 * 抓取 GitHub API 单页数据
 *
 * @param string $username GitHub 用户名
 * @param int $page 页码
 * @return array 该页项目列表
 */
function shufei_fetch_github_repos_page($username, $page)
{
    $apiUrl = 'https://api.github.com/users/' . urlencode($username) . '/repos?sort=stars&per_page=100&page=' . $page;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ShuFeiCat-Typecho-Theme');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200 || !$response) {
        return array();
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return array();
    }

    $repos = array();
    foreach ($data as $repo) {
        $repos[] = array(
            'name' => isset($repo['name']) ? $repo['name'] : '',
            'full_name' => isset($repo['full_name']) ? $repo['full_name'] : '',
            'description' => isset($repo['description']) ? $repo['description'] : '',
            'url' => isset($repo['html_url']) ? $repo['html_url'] : '',
            'stars' => isset($repo['stargazers_count']) ? $repo['stargazers_count'] : 0,
            'forks' => isset($repo['forks_count']) ? $repo['forks_count'] : 0,
            'language' => isset($repo['language']) ? $repo['language'] : '',
            'updated_at' => isset($repo['updated_at']) ? $repo['updated_at'] : ''
        );
    }
    return $repos;
}

/**
 * 后台刷新 GitHub 仓库缓存（获取全部页）
 * 单页超时 10s，最多 5 页，但仅在缓存过期或不存在时调用
 *
 * @param string $username GitHub 用户名
 * @param string $cacheFile 缓存文件路径
 */
function shufei_refresh_github_repos_cache($username, $cacheFile)
{
    $allRepos = array();
    $maxPages = 5;

    for ($page = 1; $page <= $maxPages; $page++) {
        $pageRepos = shufei_fetch_github_repos_page($username, $page);
        if (empty($pageRepos)) {
            break;
        }
        $allRepos = array_merge($allRepos, $pageRepos);
        if (count($pageRepos) < 100) {
            break;
        }
    }

    if (!empty($allRepos)) {
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode(array(
            'timestamp' => time(),
            'repos' => $allRepos
        )));
    }
}

