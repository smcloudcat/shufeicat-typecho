<?php
/**
 * ShuFeiCat 主题 核心函数文件
 * 
 * 包含后台配置、分类 UI、核心业务逻辑钩子
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 检测主题更新
 * 
 * @return array 更新检测结果
 */
function shufei_check_theme_update()
{
    $currentVersion = '1.4.0-rc.5';
    $cacheKey = 'shufei_update_check';
    $cacheTime = 3600; // 缓存1小时

    // 尝试从缓存读取
    $cacheFile = dirname(__FILE__) . '/cache/update_check.json';
    if (file_exists($cacheFile)) {
        $cache = @json_decode(file_get_contents($cacheFile), true);
        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTime) {
            return $cache['result'];
        }
    }

    $blogUrl = '';

    if (defined('__TYPECHO_SITE_URL__')) {
        $blogUrl = constant('__TYPECHO_SITE_URL__');
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $blogUrl = $protocol . $_SERVER['HTTP_HOST'];
    }

    $updateUrl = 'https://githubver.czzu.cn/?owner=smcloudcat&repo=lottery&version=' . $currentVersion . '&blogurl=' . urlencode($blogUrl);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $updateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = array('code' => 0, 'msg' => '检测失败，请稍后重试');
    if ($httpCode == 200 && $response) {
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['code'])) {
            $result = $decoded;
        }
    }

    // 写入缓存
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode(array(
        'timestamp' => time(),
        'result' => $result
    )));

    return $result;
}

/**
 * 获取主题版本号
 * 
 * @return string 主题版本号
 */
function shufei_get_theme_version()
{
    return '1.4.0-rc.5';
}

/**
 * 主题后台配置函数
 */
function themeConfig($form)
{
    $css = '<style>' .
        '.cat-config-container { display: flex; background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 25px; overflow: hidden; font-family: "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif; }' .
        '.cat-config-aside { width: 180px; background: #f9f9f9; border-right: 1px solid #e5e5e5; flex-shrink: 0; padding: 15px 0; }' .
        '.cat-config-logo { padding: 0 20px 15px; font-weight: bold; color: #467B96; font-size: 16px; border-bottom: 1px solid #eee; margin-bottom: 10px; }' .
        '.cat-config-aside ul { list-style: none; margin: 0; padding: 0; }' .
        '.cat-config-aside li { padding: 12px 20px; cursor: pointer; color: #666; font-size: 13px; transition: .2s; border-left: 3px solid transparent; }' .
        '.cat-config-aside li:hover { background: #f0f0f0; color: #467B96; }' .
        '.cat-config-aside li.active { background: #fff; color: #467B96; font-weight: bold; border-left-color: #467B96; }' .
        '.cat-config-main { flex: 1; padding: 10px 30px 30px; min-height: 500px; }' .
        '.typecho-option-list:not(.typecho-option-submit) { display: none !important; }' .
        '.cat-pane { display: none; }' .
        '.cat-pane.active { display: block; animation: catFadeIn .3s ease; }' .
        '@keyframes catFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }' .
        '.cat-config-main .typecho-option { border-bottom: 1px solid #f5f5f5; padding: 25px 0; margin: 0; }' .
        '.cat-config-main .typecho-option:last-child { border-bottom: none; }' .
        '.cat-config-main .typecho-option label.typecho-label { display: block; font-weight: bold; margin-bottom: 10px; color: #333; }' .
        '.cat-config-main .description { color: #999; font-size: 12px; margin-top: 8px; line-height: 1.6; }' .
        '.cat-config-main input[type=text], .cat-config-main textarea, .cat-config-main select, .cat-config-main input[type=number] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa; transition: all .2s; box-sizing: border-box; }' .
        '.cat-config-main input:focus, .cat-config-main textarea:focus { border-color: #467B96; outline: none; background: #fff; box-shadow: 0 0 0 3px rgba(70, 123, 150, 0.1); }' .
        '.typecho-option-submit { background: #fff; padding: 25px; border: 1px solid #e5e5e5; border-radius: 8px; text-align: right; }' .
        '.typecho-option-submit button { background: #467B96 !important; border: none !important; color: #fff !important; padding: 0 40px !important; height: 46px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 6px !important; cursor: pointer !important; font-weight: 600 !important; transition: all .2s !important; vertical-align: middle !important; margin: 0 !important; line-height: 1 !important; text-decoration: none !important; outline: none !important; }' .
        '.typecho-option-submit button:hover { transform: scale(1.02); opacity: 0.9; }' .
        '.api-status-box { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; border: 1px solid transparent; line-height: 1.6; }' .
        '.api-success { background: #f6ffed; border-color: #b7eb8f; color: #389e0d; }' .
        '.api-error { background: #fff2f0; border-color: #ffccc7; color: #cf1322; }' .
        '.cat-data-section { padding: 20px 0; border-bottom: 1px solid #f5f5f5; }' .
        '.cat-data-section:last-child { border-bottom: none; }' .
        '.cat-data-title { font-weight: bold; font-size: 14px; margin-bottom: 8px; color: #333; }' .
        '.cat-data-desc { color: #999; font-size: 12px; margin-bottom: 15px; line-height: 1.8; }' .
        '.cat-data-btn { display: inline-block; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all .2s; text-decoration: none; }' .
        '.cat-data-btn:hover { opacity: 0.85; transform: scale(1.02); }' .
        '.cat-data-btn-primary { background: #467B96; color: #fff; }' .
        '.cat-data-btn-warning { background: #e67e22; color: #fff; }' .
        '.cat-data-btn-danger { background: #e74c3c; color: #fff; }' .
        '.cat-file-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }' .
        '.cat-file-label { display: inline-block; padding: 10px 20px; background: #fafafa; border: 1px dashed #ccc; border-radius: 6px; color: #666; font-size: 13px; cursor: pointer; transition: all .2s; position: relative; overflow: hidden; }' .
        '.cat-file-label:hover { border-color: #467B96; color: #467B96; background: #f0f7fa; }' .
        '.cat-file-label input[type=file] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }' .
        '.cat-file-name { color: #467B96; font-size: 12px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }' .
        '.cat-data-status { margin-top: 12px; padding: 12px 16px; border-radius: 6px; font-size: 13px; display: none; line-height: 1.6; }' .
        '.cat-data-status.show { display: block; }' .
        '.cat-data-status.success { background: #f6ffed; border: 1px solid #b7eb8f; color: #389e0d; }' .
        '.cat-data-status.error { background: #fff2f0; border: 1px solid #ffccc7; color: #cf1322; }' .
        '.cat-data-status.info { background: #e6f7ff; border: 1px solid #91d5ff; color: #096dd9; }' .
        '.cat-data-warning { background: #fffbe6; border: 1px solid #ffe58f; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; color: #d48806; font-size: 12px; line-height: 1.8; }' .
        '.cat-data-warning i { margin-right: 6px; }' .
        '</style>';
    echo $css;

    $html = '<div id="cat-tpl" style="display:none">' .
        '<div class="cat-config-container">' .
            '<div class="cat-config-aside">' .
                '<div class="cat-config-logo">ShuFeiCat 设置</div>' .
                '<ul id="cat-tabs">' .
                    '<li data-id="cat-basic" class="active">基本设置</li>' .
                    '<li data-id="cat-avatar">头像外观</li>' .
                    '<li data-id="cat-appearance">外观设置</li>' .
                    '<li data-id="cat-pjax">Pjax无刷新</li>' .
                    '<li data-id="cat-resource">资源加载</li>' .
                    '<li data-id="cat-article">文章缩略图</li>' .
                    '<li data-id="cat-stats">文章统计</li>' .
                    '<li data-id="cat-seo">SEO 设置</li>' .
                    '<li data-id="cat-mail">评论邮件通知</li>' .
                    '<li data-id="cat-ai">AI 评论审核</li>' .
                    '<li data-id="cat-verify">人机验证</li>' .
                    '<li data-id="cat-enhance">功能增强</li>' .
                    '<li data-id="cat-data">数据管理</li>' .
                '</ul>' .
            '</div>' .
            '<div class="cat-config-main" id="cat-panes"></div>' .
        '</div>' .
    '</div>';
    echo $html;

    $js = '<script>' .
    '(function() {' .
        'window.addEventListener("load", function() {' .
            'var f = document.querySelector(".main form");' .
            'if (!f) return;' .
            'var c = document.getElementById("cat-tpl").querySelector(".cat-config-container");' .
            'var pWrap = c.querySelector("#cat-panes");' .
            'f.insertBefore(c, f.firstChild);' .
            'var ids = ["cat-basic", "cat-avatar", "cat-appearance", "cat-pjax", "cat-resource", "cat-article", "cat-stats", "cat-seo", "cat-mail", "cat-ai", "cat-verify", "cat-enhance", "cat-data"];' .
            'ids.forEach(function(id) {' .
                'var p = document.createElement("div");' .
                'p.id = id; p.className = "cat-pane" + (id === "cat-basic" ? " active" : "");' .
                'pWrap.appendChild(p);' .
            '});' .
            'document.querySelectorAll(".typecho-option:not(.typecho-option-submit)").forEach(function(el) {' .
                'var m = el.className.match(/cat-group-[\w-]+/);' .
                'if (m) {' .
                    'var target = document.getElementById(m[0].replace("cat-group-", "cat-"));' .
                    'if (target) target.appendChild(el);' .
                '} else {' .
                    'var basic = document.getElementById("cat-basic");' .
                    'if (basic) basic.appendChild(el);' .
                '}' .
            '});' .
            'document.querySelectorAll("#cat-tabs li").forEach(function(li) {' .
                'li.onclick = function() {' .
                    'document.querySelectorAll("#cat-tabs li").forEach(function(n) { n.classList.remove("active"); });' .
                    'this.classList.add("active");' .
                    'document.querySelectorAll(".cat-pane").forEach(function(p) { p.classList.remove("active"); });' .
                    'var targetPane = document.getElementById(this.getAttribute("data-id"));' .
                    'if (targetPane) targetPane.classList.add("active");' .
                '};' .
            '});' .
        '});' .
    '})();' .
    '</script>';
    echo $js;

    $dataHtml = '<div class="typecho-option cat-group-data" style="display:none">' .
        '<div class="cat-data-warning">' .
            '<i class="fa fa-exclamation-triangle"></i>数据管理说明：导出功能会将当前主题的所有配置项保存为 JSON 文件；导入功能会从 JSON 文件恢复配置并自动保存。<b>导入操作将覆盖当前所有设置，请谨慎操作！</b>' .
        '</div>' .
        '<div class="cat-data-section">' .
            '<div class="cat-data-title">导出配置</div>' .
            '<div class="cat-data-desc">将当前主题的所有配置项导出为 JSON 文件，可用于备份或迁移到其他站点。</div>' .
            '<button type="button" class="cat-data-btn cat-data-btn-primary" id="cat-export-btn">' .
                '<i class="fa fa-download" style="margin-right:6px"></i>导出配置文件' .
            '</button>' .
        '</div>' .
        '<div class="cat-data-section">' .
            '<div class="cat-data-title">导入配置</div>' .
            '<div class="cat-data-desc">从之前导出的 JSON 文件中恢复主题配置。导入后当前配置将被完全覆盖并自动保存，建议先导出备份。</div>' .
            '<div class="cat-file-row">' .
                '<label class="cat-file-label">' .
                    '<i class="fa fa-file-text-o" style="margin-right:6px"></i>选择配置文件' .
                    '<input type="file" id="cat-import-file" accept=".json">' .
                '</label>' .
                '<span class="cat-file-name" id="cat-file-name">未选择文件</span>' .
                '<button type="button" class="cat-data-btn cat-data-btn-warning" id="cat-import-btn" disabled>' .
                    '<i class="fa fa-upload" style="margin-right:6px"></i>导入配置' .
                '</button>' .
            '</div>' .
            '<div class="cat-data-status" id="cat-import-status"></div>' .
        '</div>' .
    '</div>';
    echo $dataHtml;

    $dataJs = '<script>' .
    '(function() {' .
        'window.addEventListener("load", function() {' .
            'var dataEl = document.querySelector(".cat-group-data");' .
            'if (dataEl) dataEl.style.display = "";' .

            'function collectFormData() {' .
                'var form = document.querySelector(".main form");' .
                'if (!form) return {};' .
                'var data = {};' .
                'var inputs = form.querySelectorAll("input[name], textarea[name], select[name]");' .
                'inputs.forEach(function(el) {' .
                    'var name = el.getAttribute("name");' .
                    'if (!name || name.indexOf("do") === 0) return;' .
                    'if (el.type === "checkbox") {' .
                        'if (el.checked) {' .
                            'if (!data[name]) data[name] = [];' .
                            'data[name].push(el.value);' .
                        '}' .
                    '} else if (el.type === "radio") {' .
                        'if (el.checked) data[name] = el.value;' .
                    '} else {' .
                        'data[name] = el.value;' .
                    '}' .
                '});' .
                'return data;' .
            '}' .

            'var exportBtn = document.getElementById("cat-export-btn");' .
            'if (exportBtn) {' .
                'exportBtn.addEventListener("click", function() {' .
                    'var data = collectFormData();' .
                    'var exportData = {' .
                        'theme: "shufeicat-typecho",' .
                        'version: "' . shufei_get_theme_version() . '",' .
                        'exportTime: new Date().toLocaleString("zh-CN"),' .
                        'options: data' .
                    '};' .
                    'var json = JSON.stringify(exportData, null, 2);' .
                    'var blob = new Blob([json], {type: "application/json"});' .
                    'var url = URL.createObjectURL(blob);' .
                    'var a = document.createElement("a");' .
                    'a.href = url;' .
                    'a.download = "shufeicat-config-" + new Date().toISOString().slice(0,19).replace(/[T:]/g, "") + ".json";' .
                    'document.body.appendChild(a);' .
                    'a.click();' .
                    'document.body.removeChild(a);' .
                    'URL.revokeObjectURL(url);' .
                '});' .
            '}' .

            'var fileInput = document.getElementById("cat-import-file");' .
            'var fileName = document.getElementById("cat-file-name");' .
            'var importBtn = document.getElementById("cat-import-btn");' .
            'var importStatus = document.getElementById("cat-import-status");' .

            'if (fileInput) {' .
                'fileInput.addEventListener("change", function() {' .
                    'if (this.files && this.files[0]) {' .
                        'fileName.textContent = this.files[0].name;' .
                        'importBtn.disabled = false;' .
                        'importStatus.className = "cat-data-status";' .
                        'importStatus.textContent = "";' .
                    '} else {' .
                        'fileName.textContent = "未选择文件";' .
                        'importBtn.disabled = true;' .
                    '}' .
                '});' .
            '}' .

            'if (importBtn) {' .
                'importBtn.addEventListener("click", function() {' .
                    'if (!fileInput || !fileInput.files || !fileInput.files[0]) {' .
                        'importStatus.className = "cat-data-status show error";' .
                        'importStatus.textContent = "请先选择配置文件";' .
                        'return;' .
                    '}' .
                    'if (!confirm("导入配置将覆盖当前所有主题设置并自动保存，确定要继续吗？")) return;' .
                    'var reader = new FileReader();' .
                    'reader.onload = function(e) {' .
                        'try {' .
                            'var importData = JSON.parse(e.target.result);' .
                            'if (!importData || !importData.options || typeof importData.options !== "object") {' .
                                'importStatus.className = "cat-data-status show error";' .
                                'importStatus.textContent = "无效的配置文件格式，请检查文件内容";' .
                                'return;' .
                            '}' .
                            'var form = document.querySelector(".main form");' .
                            'if (!form) {' .
                                'importStatus.className = "cat-data-status show error";' .
                                'importStatus.textContent = "未找到设置表单";' .
                                'return;' .
                            '}' .
                            'var options = importData.options;' .
                            'var formInputs = {};' .
                            'form.querySelectorAll("input[name], textarea[name], select[name]").forEach(function(el) {' .
                                'var n = el.getAttribute("name");' .
                                'if (n && n.indexOf("do") !== 0) {' .
                                    'if (!formInputs[n]) formInputs[n] = [];' .
                                    'formInputs[n].push(el);' .
                                '}' .
                            '});' .
                            'for (var key in options) {' .
                                'if (!formInputs[key]) continue;' .
                                'var els = formInputs[key];' .
                                'var val = options[key];' .
                                'els.forEach(function(el) {' .
                                    'if (el.type === "checkbox") {' .
                                        'var vals = Array.isArray(val) ? val : [val];' .
                                        'el.checked = vals.indexOf(el.value) !== -1;' .
                                    '} else if (el.type === "radio") {' .
                                        'el.checked = (el.value === String(val));' .
                                    '} else if (el.tagName === "SELECT") {' .
                                        'el.value = val;' .
                                    '} else {' .
                                        'el.value = val || "";' .
                                    '}' .
                                '});' .
                            '}' .
                            'importStatus.className = "cat-data-status show success";' .
                            'importStatus.textContent = "配置已填入表单，正在自动保存...";' .
                            'var submitBtn = form.querySelector("button[type=submit], input[type=submit]");' .
                            'if (submitBtn) {' .
                                'setTimeout(function() { submitBtn.click(); }, 800);' .
                            '} else {' .
                                'importStatus.textContent = "配置已填入表单，请手动点击保存设置按钮。";' .
                            '}' .
                        '} catch(err) {' .
                            'importStatus.className = "cat-data-status show error";' .
                            'importStatus.textContent = "解析配置文件失败：" + err.message;' .
                        '}' .
                    '};' .
                    'reader.readAsText(fileInput.files[0]);' .
                '});' .
            '}' .
        '});' .
    '})();' .
    '</script>';
    echo $dataJs;

    $currentVersion = shufei_get_theme_version();
    $updateResult = shufei_check_theme_update();
    if ($updateResult && isset($updateResult['code']) && $updateResult['code'] == 1) {
        $remoteVersion = $updateResult['version'];
        if (version_compare($remoteVersion, $currentVersion, '>')) {
            echo '<div class="message notice"><p>检测到新版本：<b>' . htmlspecialchars($remoteVersion) . '</b></p><p>更新内容：' . htmlspecialchars($updateResult['msg']) . '</p><p>更新链接：<a href="' . htmlspecialchars($updateResult['url']) . '" target="_blank">' . htmlspecialchars($updateResult['url']) . '</a></p></div>';
        } elseif (version_compare($remoteVersion, $currentVersion, '<')) {
            echo '<div class="message notice"><p>该版本为测试版本，如果在使用过程中发现问题，请及时反馈。</p></div>';
        }
    } else {
        $msg = isset($updateResult['msg']) ? $updateResult['msg'] : '检测失败';
        echo '<div class="message notice"><p>' . htmlspecialchars($msg) . '</p></div>';
    }
    
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题前加上一个 LOGO')
    );
    $logoUrl->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的URL地址')));

    $authorAvatar = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorAvatar',
        null,
        'https://q1.qlogo.cn/g?b=qq&nk=3522934828&s=100',
        _t('站长头像'),
        _t('在这里填入站长头像的URL地址，显示在左侧侧边栏顶部<br>默认：QQ头像')
    );
    $authorAvatar->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorAvatar);

    $authorName = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorName',
        null,
        '云猫',
        _t('站长名称'),
        _t('在这里填入站长名称，显示在左侧侧边栏头像下方<br>默认：云猫')
    );
    $authorName->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorName);

    $authorSignature = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorSignature',
        null,
        'Hello,world',
        _t('站长签名'),
        _t('在这里填入站长个性签名，显示在左侧侧边栏名称下方<br>默认：Hello,world')
    );
    $authorSignature->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorSignature);

    $authorEmail = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorEmail',
        null,
        'yuncat@email.lwcat.cn',
        _t('站长邮箱'),
        _t('在这里填入站长邮箱地址，显示在左侧侧边栏底部联系方式中<br>留空则不显示邮箱<br>默认：yuncat@email.lwcat.cn')
    );
    $authorEmail->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorEmail);

    $authorGithub = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorGithub',
        null,
        'https://github.com/smcloudcat/shufeicat-typecho',
        _t('站长GitHub'),
        _t('在这里填入GitHub主页地址，显示在左侧侧边栏底部联系方式中<br>留空则不显示GitHub<br>默认：https://github.com/smcloudcat/shufeicat-typecho')
    );
    $authorGithub->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorGithub);

    $authorQQ = new \Typecho\Widget\Helper\Form\Element\Text(
        'authorQQ',
        null,
        '',
        _t('站长QQ'),
        _t('在这里填入QQ号码，显示在左侧侧边栏底部联系方式中<br>留空则不显示QQ')
    );
    $authorQQ->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($authorQQ);

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        array(
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowRecentComments' => _t('显示最近回复'),
            'ShowCategory'       => _t('显示分类'),
            'ShowArchive'        => _t('显示归档'),
            'ShowOther'          => _t('显示其它杂项'),
            'ShowLinks'          => _t('显示友链')
        ),
        array('ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowOther', 'ShowLinks'),
        _t('侧边栏显示'),
        _t('介绍：选择要在侧边栏展示的功能板块')
    );
    $sidebarBlock->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($sidebarBlock->multiMode());

    $links = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'links',
        null,
        null,
        _t('友链配置'),
        _t('介绍：每行一个友链，格式：链接名称,链接地址<br>例如：<br>CC的小窝,https://lwcat.cn<br>谷歌,https://www.google.com')
    );
    $links->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($links);

    $footerCopyrightEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'footerCopyrightEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('显示底部版权'),
        _t('介绍：是否在网站底部显示版权信息')
    );
    $footerCopyrightEnabled->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerCopyrightEnabled);

    $footerBeianNumber = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerBeianNumber',
        null,
        null,
        _t('网站备案号'),
        _t('介绍：填写网站的备案号（例如：京ICP备12345678号-1）<br>留空则不显示备案信息')
    );
    $footerBeianNumber->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerBeianNumber);

    $footerBeianLink = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerBeianLink',
        null,
        'https://beian.miit.gov.cn/',
        _t('备案号链接'),
        _t('介绍：备案号的跳转链接地址<br>默认：https://beian.miit.gov.cn/')
    );
    $footerBeianLink->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerBeianLink->addRule('url', _t('请填写一个合法的URL地址')));

    $footerGonganNumber = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerGonganNumber',
        null,
        null,
        _t('公安备案号'),
        _t('介绍：填写网站的公安备案号（例如：京公网安备 11010802012345号）<br>留空则不显示公安备案信息')
    );
    $footerGonganNumber->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerGonganNumber);

    $footerCustomText = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'footerCustomText',
        null,
        null,
        _t('自定义版权文本'),
        _t('介绍：自定义底部版权文本，支持HTML代码<br>可以使用以下变量：<br>{year} - 当前年份<br>{sitetitle} - 网站标题<br>{siteurl} - 网站URL')
    );
    $footerCustomText->setAttribute('class', 'typecho-option cat-group-basic');
    $form->addInput($footerCustomText);
    
    $gravatarSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'gravatarSource',
        array(
            'cat' => _t('Cat源'),
            'loli' => _t('Loli源'),
            'weavatar' => _t('weavatar源'),
            'cravatar' => _t('cravatar源'),
            'official' => _t('官方源')
        ),
        'cat',
        _t('Gravatar头像镜像源'),
        _t('介绍：选择Gravatar头像的访问来源<br>Cat源：https://gravatar.luoli.click/avatar/<br>Loli源：https://gravatar.loli.net/avatar/<br>weavatar源：https://weavatar.com/avatar/<br>cravatar源：https://cravatar.cn/avatar/')
    );
    $gravatarSource->setAttribute('class', 'typecho-option cat-group-avatar');
    $form->addInput($gravatarSource);

    $themeColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'themeColor',
        null,
        '#FF6B6B',
        _t('主题颜色'),
        _t('介绍：设置主题的主色调，应用于链接、按钮等元素<br>默认：#FF6B6B（珊瑚红）<br>请填写有效的十六进制颜色值，例如：#FF6B6B、#1E9FFF、#6C5CE7')
    );
    $themeColor->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($themeColor);

    $bgColor = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgColor',
        null,
        '#f8f9fc',
        _t('背景颜色'),
        _t('介绍：设置页面的背景颜色<br>默认：#f8f9fc（浅灰蓝色）<br>请填写有效的十六进制颜色值，例如：#f8f9fc、#ffffff、#f0f0f0')
    );
    $bgColor->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgColor);

    $bgImage = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgImage',
        null,
        null,
        _t('背景图片'),
        _t('介绍：设置页面的背景图片URL，留空则不设置背景图片<br>背景图片将覆盖背景颜色设置，建议使用高分辨率图片<br>示例：https://example.com/background.jpg')
    );
    $bgImage->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($bgImage->addRule('url', _t('请填写一个合法的URL地址')));

    $cardOpacity = new \Typecho\Widget\Helper\Form\Element\Text(
        'cardOpacity',
        null,
        '1',
        _t('盒子透明度'),
        _t('介绍：设置页面中各盒子（导航栏、侧边栏、文章卡片等）的透明度<br>取值范围 0 ~ 1，1 为完全不透明，0 为完全透明<br>默认：1（不透明）<br>设置背景图片后建议调低透明度，例如 0.85，让背景图片透出')
    );
    $cardOpacity->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($cardOpacity);

    $postListStyle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'postListStyle',
        array('card' => _t('卡片模式'), 'classic' => _t('经典模式')),
        'classic',
        _t('文章列表样式'),
        _t('介绍：选择首页文章列表的展示样式<br>卡片模式：缩略图在左侧，标题和摘要在右侧，信息更清晰<br>经典模式：缩略图作为背景覆盖，文字叠加在图片上')
    );
    $postListStyle->setAttribute('class', 'typecho-option cat-group-appearance');
    $form->addInput($postListStyle);

    $pjaxLoad = new \Typecho\Widget\Helper\Form\Element\Radio(
        'pjaxLoad',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('Pjax加载'),
        _t('介绍：开启后，全站页面切换将使用Pjax方式，实现无刷新加载，提升用户体验')
    );
    $pjaxLoad->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxLoad);

    $pjaxLoadStyle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'pjaxLoadStyle',
        array('progress' => _t('顶部进度条'), 'circle' => _t('圆形旋转器'), 'dots' => _t('底部圆点')),
        'progress',
        _t('Pjax加载动画'),
        _t('介绍：选择Pjax加载时的动画效果样式')
    );
    $pjaxLoadStyle->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxLoadStyle);

    $pjaxTimeout = new \Typecho\Widget\Helper\Form\Element\Text(
        'pjaxTimeout',
        null,
        '10000',
        _t('Pjax超时时间（毫秒）'),
        _t('介绍：建议5000-30000毫秒，超时后将使用传统方式加载')
    );
    $pjaxTimeout->setAttribute('class', 'typecho-option cat-group-pjax');
    $form->addInput($pjaxTimeout);
    
    $resourceMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'resourceMode',
        array('local' => _t('本地资源'), 'cdn' => _t('官方CDN'), 'custom' => _t('自建CDN')),
        'local',
        _t('资源加载模式'),
        _t('介绍：选择主题资源的加载方式<br>本地资源：从主题目录加载<br>官方CDN：使用 jsDelivr CDN<br>自建CDN：使用自定义CDN地址')
    );
    $resourceMode->setAttribute('class', 'typecho-option cat-group-resource');
    $form->addInput($resourceMode);

    $customCdn = new \Typecho\Widget\Helper\Form\Element\Text(
        'customCdn',
        null,
        'https://cdn.lwcat.cn/shufeicat',
        _t('自建CDN地址'),
        _t('介绍：当选择"自建CDN"模式时，请填写CDN基础地址（例如https://cdn.lwcat.cn/shufeicat），不要以斜杠结尾<br>把"assets"整个文件夹打包即可（包括assets这个文件夹）')
    );
    $customCdn->setAttribute('class', 'typecho-option cat-group-resource');
    $form->addInput($customCdn->addRule('url', _t('请填写一个合法的URL地址')));
    
    $thumbnailSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'thumbnailSource',
        array('local' => _t('本地图片'), 'remote' => _t('远程图片')),
        'local',
        _t('文章随机缩略图来源'),
        _t('介绍：选择文章随机缩略图的来源方式<br>本地图片：使用主题自带默认图片<br>远程图片：使用自定义远程图片地址')
    );
    $thumbnailSource->setAttribute('class', 'typecho-option cat-group-article');
    $form->addInput($thumbnailSource);

    $statsEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'statsEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('文章统计功能'),
        _t('介绍：开启后，将启用文章浏览量和点赞功能')
    );
    $statsEnabled->setAttribute('class', 'typecho-option cat-group-stats');
    $form->addInput($statsEnabled);

    $rankingEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'rankingEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('排行榜功能'),
        _t('介绍：开启后，将在侧边栏显示文章排行榜')
    );
    $rankingEnabled->setAttribute('class', 'typecho-option cat-group-stats');
    $form->addInput($rankingEnabled);

    $rankingType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'rankingType',
        array('views' => _t('按浏览量'), 'likes' => _t('按点赞数')),
        'views',
        _t('排行榜排序方式'),
        _t('介绍：选择侧边栏排行榜的排序依据')
    );
    $rankingType->setAttribute('class', 'typecho-option cat-group-stats');
    $form->addInput($rankingType);

    $rankingLimit = new \Typecho\Widget\Helper\Form\Element\Text(
        'rankingLimit',
        null,
        '5',
        _t('排行榜显示数量'),
        _t('介绍：设置侧边栏排行榜显示的文章数量，默认为5篇')
    );
    $rankingLimit->setAttribute('class', 'typecho-option cat-group-stats');
    $form->addInput($rankingLimit);

    $seoKeywords = new \Typecho\Widget\Helper\Form\Element\Text(
        'seoKeywords',
        null,
        null,
        _t('全站关键词（SEO）'),
        _t('介绍：作为首页/分类/标签/归档/搜索/404 等没有自定义关键词页面的默认 SEO 关键词<br>多个关键词请用英文逗号 "," 分隔<br>示例：云猫博客,Typecho 主题,个人博客,技术分享<br>留空则使用 Typecho 后台"站点描述"或站点标题作为兜底')
    );
    $seoKeywords->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoKeywords);

    $seoDescription = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'seoDescription',
        null,
        null,
        _t('全站 SEO 描述'),
        _t('介绍：作为首页/分类/标签/归档等页面的 SEO 描述（description）<br>留空则使用 Typecho 后台"站点描述"<br>建议 80-160 字之间')
    );
    $seoDescription->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoDescription);

    $seoOgImage = new \Typecho\Widget\Helper\Form\Element\Text(
        'seoOgImage',
        null,
        null,
        _t('默认社交分享图（OG Image）'),
        _t('介绍：用于 Open Graph / Twitter Card 分享的默认图片，建议 1200x630 像素<br>当文章/页面没有可用图片时使用此图<br>请填写完整的图片URL地址')
    );
    $seoOgImage->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoOgImage->addRule('url', _t('请填写一个合法的URL地址')));

    $seoSiteVerification = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'seoSiteVerification',
        null,
        null,
        _t('搜索引擎站点验证'),
        _t('介绍：用于搜索引擎站长平台验证，可粘贴完整 meta 标签的 content 值或完整 meta 标签<br>每行一个，例如：<br>google-site-verification=xxxxxx<br>baidu-site-verification=xxxxxx<br>也可以直接粘贴：&lt;meta name="google-site-verification" content="xxxxx" /&gt;')
    );
    $seoSiteVerification->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoSiteVerification);

    $seoRobots = new \Typecho\Widget\Helper\Form\Element\Radio(
        'seoRobots',
        array(
            'auto'    => _t('自动（推荐）'),
            'index'   => _t('全部可收录（index,follow）'),
            'noindex' => _t('全部不可收录（noindex,nofollow）')
        ),
        'auto',
        _t('默认 robots 设置'),
        _t('介绍：自动模式下，首页/文章/页面/分类/标签可被收录，搜索结果/404/分页后页会标记为 noindex')
    );
    $seoRobots->setAttribute('class', 'typecho-option cat-group-seo');
    $form->addInput($seoRobots);

    $remoteImages = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'remoteImages',
        null,
        null,
        _t('远程图片地址'),
        _t('介绍：当选择"远程图片"时，请填写图片地址，每行一个地址')
    );
    $remoteImages->setAttribute('class', 'typecho-option cat-group-article');
    $form->addInput($remoteImages);
    
    $commentMailEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentMailEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('是否开启评论邮件通知'),
        _t('介绍：开启后评论内容将会进行邮箱通知，该设置会影响用户发布评论速度')
    );
    $commentMailEnabled->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailEnabled);

    $commentMailHost = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailHost',
        null,
        null,
        '邮箱服务器地址',
        '例如：smtp.qq.com'
    );
    $commentMailHost->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailHost);

    $commentMailSMTPSecure = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentMailSMTPSecure',
        array('ssl' => _t('ssl'), 'tsl' => _t('tsl')),
        'ssl',
        _t('加密方式'),
        _t('介绍：用于选择登录鉴权加密方式')
    );
    $commentMailSMTPSecure->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailSMTPSecure);

    $commentMailPort = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailPort',
        null,
        null,
        '邮箱服务器端口号',
        '例如：465'
    );
    $commentMailPort->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailPort);

    $commentMailFromName = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailFromName',
        null,
        null,
        '发件人昵称',
        '例如：云猫'
    );
    $commentMailFromName->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailFromName);

    $commentMailAccount = new \Typecho\Widget\Helper\Form\Element\Text(
        'commentMailAccount',
        null,
        null,
        '发件人邮箱',
        '例如：123456@qq.com'
    );
    $commentMailAccount->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailAccount);

    $commentMailPassword = new \Typecho\Widget\Helper\Form\Element\Password(
        'commentMailPassword',
        null,
        null,
        '邮箱授权码',
        '介绍：填写邮箱生成的授权码（以QQ邮箱为例：设置 > 账户 > IMAP/SMTP服务）'
    );
    $commentMailPassword->setAttribute('class', 'typecho-option cat-group-mail');
    $form->addInput($commentMailPassword);

    $aiModerationEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiModerationEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('AI评论审核'),
        _t('介绍：开启后将通过AI进行内容安全审核，该设置会影响评论速度')
    );
    $aiModerationEnabled->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiModerationEnabled);
    
    $aiOptions = \Typecho\Widget::widget('Widget_Options');
    $aiEnabled = isset($aiOptions->aiModerationEnabled) ? $aiOptions->aiModerationEnabled : 'off';

    if ($aiEnabled === 'on') {
        $apiType = isset($aiOptions->aiApiType) ? $aiOptions->aiApiType : 'free';
        $apiUrl = isset($aiOptions->aiModerationApiUrl) ? $aiOptions->aiModerationApiUrl : '';
        $apiKey = isset($aiOptions->aiModerationApiKey) ? $aiOptions->aiModerationApiKey : '';
        $model = isset($aiOptions->aiModerationModel) ? $aiOptions->aiModerationModel : '';

        if ($apiType === 'free' && empty($apiUrl)) {
            echo '<div class="typecho-option cat-group-ai"><div class="api-status-box api-error"><b>接口状态检测：</b><br>免费接口已下线，请切换到自定义接口并填写您自己的 API 地址和密钥</div></div>';
        } elseif (empty($apiUrl) || empty($apiKey)) {
            echo '<div class="typecho-option cat-group-ai"><div class="api-status-box api-error"><b>接口状态检测：</b><br>请先填写 AI API 地址和密钥</div></div>';
        } else {
            $targetUrl = $apiUrl;
            $targetKey = $apiKey;
            $targetModel = !empty($model) ? $model : 'gpt-3.5-turbo';

            $apiStatus = '正在检测 AI 接口连通性...';
            $apiClass = 'api-error';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $targetUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
                'model' => $targetModel,
                'messages' => array(array('role' => 'user', 'content' => 'ping')),
                'max_tokens' => 1
            )));
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $targetKey
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) { $apiStatus = '✗ AI API 连接失败: ' . $err; $apiClass = 'api-error'; }
            elseif ($code === 200) { $apiStatus = '✓ AI 接口正常连通 (HTTP 200)'; $apiClass = 'api-success'; }
            else { $apiStatus = '✗ AI API 返回异常: HTTP ' . $code . ' (请检查 API 地址或密钥)'; $apiClass = 'api-error'; }

            echo '<div class="typecho-option cat-group-ai"><div class="api-status-box ' . $apiClass . '"><b>接口状态检测：</b><br>' . $apiStatus . '</div></div>';
        }
    } else {

        echo '<div class="typecho-option cat-group-ai"><div class="api-status-box api-error"><b>接口状态检测：</b><br>AI审核功能已关闭，开启后自动检测接口状态</div></div>';
    }
    $aiApiType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiApiType',
        array('custom' => '自定义接口'),
        'custom',
        _t('AI接口类型'),
        _t('介绍：请配置您自己的兼容 OpenAI 格式的 API 地址和密钥')
    );
    $aiApiType->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiApiType);

    $aiModerationApiUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationApiUrl',
        null,
        null,
        _t('AI API 地址'),
        _t('介绍：兼容OpenAI格式，如：https://api.openai.com/v1/chat/completions')
    );
    $aiModerationApiUrl->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiModerationApiUrl);

    $aiModerationApiKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'aiModerationApiKey',
        null,
        null,
        _t('AI API 密钥'),
        _t('介绍：填写您的 AI 接口 API Key')
    );
    $aiModerationApiKey->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiModerationApiKey);

    $aiModerationModel = new \Typecho\Widget\Helper\Form\Element\Text(
        'aiModerationModel',
        null,
        'gpt-3.5-turbo',
        _t('AI 模型'),
        _t('介绍：填写使用的模型名称，如：gpt-3.5-turbo, gpt-4o 等')
    );
    $aiModerationModel->setAttribute('class', 'typecho-option cat-group-ai');
    $form->addInput($aiModerationModel);

    
    $captchaType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'captchaType',
        array(
            'none' => _t('关闭'),
            'turnstile' => _t('Cloudflare Turnstile'),
            'captcha_number' => _t('图片验证码（纯数字）'),
            'captcha_alpha' => _t('图片验证码（纯字母）'),
            'captcha_alnum' => _t('图片验证码（数字+字母）')
        ),
        'none',
        _t('评论验证方式'),
        _t('介绍：选择评论提交时的人机验证方式。图片验证码无需第三方服务，Turnstile 需要 Cloudflare 账号')
    );
    $captchaType->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($captchaType);

    $turnstileSiteKey = new \Typecho\Widget\Helper\Form\Element\Text(
        'turnstileSiteKey',
        null,
        null,
        _t('Turnstile Site Key'),
        _t('介绍：填写 Cloudflare Turnstile 提供的 Site Key（仅 Turnstile 验证方式需要）')
    );
    $turnstileSiteKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileSiteKey);

    $turnstileSecretKey = new \Typecho\Widget\Helper\Form\Element\Password(
        'turnstileSecretKey',
        null,
        null,
        _t('Turnstile Secret Key'),
        _t('介绍：填写 Cloudflare Turnstile 提供的 Secret Key（仅 Turnstile 验证方式需要）')
    );
    $turnstileSecretKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileSecretKey);

    $captchaLength = new \Typecho\Widget\Helper\Form\Element\Radio(
        'captchaLength',
        array('4' => _t('4位'), '5' => _t('5位'), '6' => _t('6位')),
        '4',
        _t('图片验证码位数'),
        _t('介绍：选择图片验证码的字符位数（仅图片验证码方式有效）')
    );
    $captchaLength->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($captchaLength);

    // ===== 功能增强配置 =====
    $codeHighlightEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'codeHighlightEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('代码高亮（Prism.js）'),
        _t('介绍：开启后，文章中的代码块将使用 Prism.js 进行语法高亮渲染<br>关闭后，代码块将以纯文本形式显示')
    );
    $codeHighlightEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($codeHighlightEnabled);

    $mermaidEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'mermaidEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('Mermaid 图表渲染'),
        _t('介绍：开启后，支持在文章中使用 ```mermaid 代码块渲染流程图、时序图、甘特图等<br>使用方法：在代码块标记后加上 mermaid，例如：<br>```mermaid<br>graph TD<br>&nbsp;&nbsp;&nbsp;&nbsp;A[开始] --> B[结束]<br>```<br>支持所有 Mermaid 官方图表类型')
    );
    $mermaidEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($mermaidEnabled);

    $echartsEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'echartsEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('ECharts 图表渲染'),
        _t('介绍：开启后，支持在文章中使用 ```echarts 代码块渲染 ECharts 图表<br>使用方法：在代码块标记后加上 echarts，代码内容为标准 ECharts option JSON 配置<br>例如：```echarts<br>{"xAxis":{"type":"category","data":["A","B","C"]},"yAxis":{"type":"value"},"series":[{"data":[120,200,150],"type":"bar"}]}<br>```<br>支持所有 ECharts 官方图表类型和配置参数')
    );
    $echartsEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($echartsEnabled);

    $katexEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'katexEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'off',
        _t('KaTeX 数学公式渲染'),
        _t('介绍：开启后，支持在文章中渲染数学公式<br>行内公式：使用 $...$ 包裹，例如 $E=mc^2$<br>块级公式：使用 $$...$$ 包裹，例如：<br>$$<br>\\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}<br>$$<br>支持常见数学符号和公式结构')
    );
    $katexEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($katexEnabled);

    $markdownExtEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'markdownExtEnabled',
        array('on' => _t('开启'), 'off' => _t('关闭')),
        'on',
        _t('Markdown 扩展语法'),
        _t('介绍：开启后，支持以下扩展 Markdown 语法：<br><br>' .
            '<b>1. 图片大小</b>：在 alt 文本后加 |宽x高，例如 <code>![描述|300x200](url)</code> 或 <code>![描述|50%](url)</code><br>' .
            '<b>2. 图片对齐</b>：在 alt 文本后加 #对齐方式，例如 <code>![描述#center](url)</code> 支持 left/center/right<br>' .
            '<b>3. 图片标题</b>：alt 文本自动显示为图片下方标题（figcaption）<br>' .
            '<b>4. 高亮文本</b>：使用 ==包裹==，例如 <code>==高亮内容==</code><br>' .
            '<b>5. 任务列表</b>：<code>- [x] 已完成</code> 和 <code>- [ ] 未完成</code><br>' .
            '<b>6. 提示框</b>：引用块首行写 <code>[!tip]</code>，支持 tip/note/info/warning/danger<br>' .
            '&nbsp;&nbsp;&nbsp;&nbsp;例如：<code>> [!tip] 提示标题</code><br>' .
            '<b>7. 折叠区块</b>：引用块首行写 <code>[details:标题]</code><br>' .
            '&nbsp;&nbsp;&nbsp;&nbsp;例如：<code>> [details:点击展开]</code><br>' .
            '<b>8. 图片懒加载</b>：自动为所有图片添加 loading="lazy"')
    );
    $markdownExtEnabled->setAttribute('class', 'typecho-option cat-group-enhance');
    $form->addInput($markdownExtEnabled);
}

/**
 * 随机缩略图逻辑
 */
function shufei_get_random_thumbnail()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $thumbnailSource = isset($options->thumbnailSource) ? $options->thumbnailSource : 'local';
    
    if ($thumbnailSource === 'remote') {
        $remoteImages = isset($options->remoteImages) ? $options->remoteImages : '';
        if (!empty($remoteImages)) {
            $imageList = preg_split('/\R/', trim($remoteImages));
            if (!empty($imageList)) {
                return $imageList[array_rand($imageList)];
            }
        }
    }
    
    $siteUrl = isset($options->siteUrl) ? $options->siteUrl : '';
    $themeUrl = \Typecho\Common::url('/usr/themes/ShuFeiCat/', $siteUrl);
    $defaultImages = array('default-1.svg', 'default-2.svg', 'default-3.svg', 'default-4.svg', 'default-5.svg');
    return $themeUrl . 'image/' . $defaultImages[array_rand($defaultImages)];
}

/**
 * 后台自定义字段定义
 */
function themeFields($layout)
{
    $thumbnail = new \Typecho\Widget\Helper\Form\Element\Text('thumbnail', NULL, NULL, _t('文章缩略图'), _t('留空则自动获取文章图片或随机图片'));
    $layout->addItem($thumbnail);
    $excerpt = new \Typecho\Widget\Helper\Form\Element\Text('excerpt', NULL, NULL, _t('文章简介'), _t('留空则自动截取文章内容'));
    $layout->addItem($excerpt);
    $keywords = new \Typecho\Widget\Helper\Form\Element\Text('keywords', NULL, NULL, _t('文章关键词（SEO）'), _t('用于 SEO 关键词 meta 标签，多个关键词请用英文逗号 "," 分隔<br>示例：Typecho 主题,ShuFeiCat,SEO 优化<br>留空则自动使用全站关键词或文章标签'));
    $layout->addItem($keywords);
    $sticky = new \Typecho\Widget\Helper\Form\Element\Radio('sticky', array('0' => _t('普通文章'), '1' => _t('置顶文章')), '0', _t('文章置顶'), _t('选择置顶后，该文章将在首页顶部显示'));
    $layout->addItem($sticky);
}

/* 加载核心逻辑库 */
@require_once dirname(__FILE__) . '/core/mail.php';
@require_once dirname(__FILE__) . '/core/ai-moderation.php';
@require_once dirname(__FILE__) . '/core/post-stats.php';

/**
 * 检查当前用户是否已评论指定文章
 *
 * @param int $cid 文章ID
 * @return bool
 */
function shufei_has_commented($cid)
{
    $db = \Typecho\Db::get();
    $hasCommented = false;

    if (\Typecho\Widget::widget('Widget_User')->hasLogin()) {
        $user = \Typecho\Widget::widget('Widget_User');
        $count = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))
            ->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('authorId = ?', $user->uid)
            ->where('status = ?', 'approved'))->num;
        $hasCommented = $count > 0;
    } else {
        $cookieMail = \Typecho\Cookie::get('__typecho_remember_mail');
        if (!empty($cookieMail)) {
            $count = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))
                ->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('mail = ?', $cookieMail)
                ->where('status = ?', 'approved'))->num;
            $hasCommented = $count > 0;
        }
    }

    return $hasCommented;
}

/**
 * 解析文章内容中的 [reply] 短代码
 * 已评论用户可见，未评论用户显示提示
 *
 * @param string $content 文章内容
 * @param int $cid 文章ID
 * @return string
 */
function shufei_parse_reply_content($content, $cid)
{
    if ($content === null) {
        $content = '';
    }
    $hasCommented = shufei_has_commented($cid);

    return preg_replace_callback('/\[reply\](.*?)\[\/reply\]/is', function ($matches) use ($hasCommented, $cid) {
        if ($hasCommented) {
            return '<div class="reply-visible-content">' . $matches[1] . '</div>';
        } else {
            return '<div class="reply-hidden-box">
                <div class="reply-hidden-inner">
                    <div class="reply-hidden-icon">
                        <i class="fa fa-lock"></i>
                    </div>
                    <div class="reply-hidden-title">回复可见</div>
                    <div class="reply-hidden-desc">此处内容需要评论后才可查看，快来参与讨论吧！</div>
                    <a href="#comments" class="reply-hidden-btn">
                        <i class="fa fa-commenting"></i> 去评论
                    </a>
                </div>
            </div>';
        }
    }, $content);
}

/**
 * 核心逻辑钩子：评论安全性校验（包含验证码校验 + AI审核）
 */
function shufei_comment_check($comment, $post) {
    $options = \Typecho\Widget::widget('Widget_Options');
    $captchaType = shufei_get_captcha_type();

    // 1. 验证码校验
    if ($captchaType === 'turnstile') {
        // Turnstile 验证
        $token = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
        if (empty($token)) {
            throw new \Typecho\Widget\Exception(_t('请先完成人机验证'));
        }
        // 调用 Cloudflare Siteverify API 验证 token
        $secretKey = isset($options->turnstileSecretKey) ? $options->turnstileSecretKey : '';
        if (!empty($secretKey)) {
            $verifyResult = shufei_turnstile_verify_curl($secretKey, $token);
            if ($verifyResult !== null && isset($verifyResult['success']) && !$verifyResult['success']) {
                throw new \Typecho\Widget\Exception(_t('人机验证未通过，请重试'));
            }
        }
    } elseif (shufei_is_captcha_enabled()) {
        // 图片验证码验证
        session_start();
        $captchaInput = isset($_POST['captcha_code']) ? strtolower(trim($_POST['captcha_code'])) : '';
        $captchaSession = isset($_SESSION['shufei_captcha_code']) ? $_SESSION['shufei_captcha_code'] : '';

        // 验证后立即清除，防止重复使用
        unset($_SESSION['shufei_captcha_code']);
        unset($_SESSION['shufei_captcha_time']);

        if (empty($captchaInput)) {
            throw new \Typecho\Widget\Exception(_t('请填写验证码'));
        }
        if (empty($captchaSession)) {
            throw new \Typecho\Widget\Exception(_t('验证码已过期，请刷新验证码后重试'));
        }
        if ($captchaInput !== $captchaSession) {
            throw new \Typecho\Widget\Exception(_t('验证码错误，请重新输入'));
        }
    }

    // 2. AI评论审核
    if (function_exists('processAiModeration')) {
        $comment = processAiModeration($comment);
    }

    return $comment;
}

/**
 * 使用 cURL 验证 Turnstile token
 */
function shufei_turnstile_verify_curl($secretKey, $token) {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
    )));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

// 注册钩子（整合AI审核功能）
\Typecho\Plugin::factory('Widget_Feedback')->comment = 'shufei_comment_check';

// 注册 KaTeX 内容过滤器（防止 Markdown 破坏数学公式语法）
// handle 使用 Widget\Base\Contents，因为 ___content() 中 Contents::pluginHandle() 的 static::class 解析为该类
// nativeClassName() 会将反斜杠转为下划线，最终查找键为 Widget_Base_Contents:content
\Typecho\Plugin::factory('Widget\Base\Contents')->content = 'shufei_katex_content_filter';

// 注册 Markdown 扩展内容过滤器（在 Markdown 解析后处理扩展语法）
\Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = 'shufei_markdown_ext_filter';

/**
 * 提取数学公式并替换为占位符
 * 在 Markdown 解析前调用，防止 _ 等符号被 Markdown 解析器破坏
 *
 * @param string $text 原始 Markdown 文本
 * @param array &$mathBlocks 用于存储提取的公式 HTML
 * @return string 替换后的文本
 */
function shufei_extract_math_placeholders($text, &$mathBlocks)
{
    if ($text === null) {
        $text = '';
    }

    // 先保护代码块，避免代码块中的 $...$ 等被误提取为公式
    $codeBlocks = array();

    // 保护围栏代码块（支持 3+ 个反引号或波浪号的围栏）
    // 要求起始标记在行首（允许最多3个前导空格），避免匹配缩进代码块中的反引号文本
    // 结束标记也必须在行首（允许前导空格），反引号/波浪号数量必须 >= 起始标记数量
    $text = preg_replace_callback('/^( {0,3})(`{3,})([\s\S]*?)^\1`{3,}/m', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);
    $text = preg_replace_callback('/^( {0,3})(~{3,})([\s\S]*?)^\1~{3,}/m', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);

    // 保护缩进代码块（4+ 空格缩进的连续行）
    // 匹配连续的 4+ 空格缩进行，避免其中的 $ 等被误提取为公式
    $text = preg_replace_callback('/^( {4,}.*(?:\n {4,}.*)*)/m', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);

    // 保护行内代码 `...`
    $text = preg_replace_callback('/`[^`]+`/', function ($m) use (&$codeBlocks) {
        $key = '<!--CODE' . count($codeBlocks) . '-->';
        $codeBlocks[] = $m[0];
        return $key;
    }, $text);

    // 处理块级公式 $$...$$（优先处理，避免与行内公式冲突）
    $text = preg_replace_callback('/\$\$([\s\S]+?)\$\$/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="display" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 处理块级公式 \[...\]
    $text = preg_replace_callback('/\\\\\[([\s\S]+?)\\\\\]/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="display" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 处理行内公式 $...$（排除 $$ 和货币金额）
    $text = preg_replace_callback('/(?<!\$)\$(?!\$)([^\$\n]+?)(?<!\$)\$(?!\$)/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="inline" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 处理行内公式 \(...\)
    $text = preg_replace_callback('/\\\\\(([\s\S]+?)\\\\\)/', function ($matches) use (&$mathBlocks) {
        $math = shufei_fix_markdown_in_math($matches[1]);
        $placeholder = '<!--MATH' . count($mathBlocks) . '-->';
        $mathBlocks[] = '<span class="math-tex" data-mode="inline" data-math="' . htmlspecialchars($math, ENT_QUOTES, 'UTF-8') . '"></span>';
        return $placeholder;
    }, $text);

    // 还原代码块
    foreach ($codeBlocks as $i => $code) {
        $text = str_replace('<!--CODE' . $i . '-->', $code, $text);
    }

    return $text;
}

/**
 * 还原数学公式占位符
 * 在 Markdown 解析后调用，将占位符替换回公式 HTML
 *
 * @param string $content Markdown 解析后的 HTML 内容
 * @param array &$mathBlocks 提取的公式 HTML 数组
 * @return string 还原后的 HTML 内容
 */
function shufei_restore_math_placeholders($content, &$mathBlocks)
{
    if ($content === null) {
        $content = '';
    }
    foreach ($mathBlocks as $i => $mathHtml) {
        $content = str_replace('<!--MATH' . $i . '-->', $mathHtml, $content);
        // Markdown 可能在占位符外面包了 <p> 标签
        $content = str_replace('<p><!--MATH' . $i . '--></p>', $mathHtml, $content);
    }
    return $content;
}

/**
 * KaTeX 内容过滤器
 * 在 Markdown 解析前提取数学公式，防止 Markdown 解析器将 _ 转为 <em> 等标签破坏公式语法
 * 将公式内容提取并包装在占位符中，Markdown 解析后再还原
 */
function shufei_katex_content_filter($content, $widget, $lastResult)
{
    $content = $lastResult ?: $content;
    if ($content === null) {
        $content = '';
    }

    // 保护行内代码中包含反引号序列的模式（如 ` ```mermaid `）
    $icodeBlocks = array();
    if ($widget->isMarkdown) {
        $content = shufei_protect_inline_code_backticks($content, $icodeBlocks);
    }

    $options = \Typecho\Widget::widget('Widget_Options');
    if (empty($options->katexEnabled) || $options->katexEnabled !== 'on') {
        // KaTeX 未开启，直接走默认 Markdown 解析
        $result = $widget->isMarkdown ? $widget->markdown($content) : $widget->autoP($content);

        // 还原行内代码占位符
        $result = shufei_restore_inline_code_backticks($result ?? '', $icodeBlocks);

        return $result ?? '';
    }

    // 用占位符保护数学公式，避免 Markdown 解析器破坏
    $mathBlocks = array();
    $content = shufei_extract_math_placeholders($content, $mathBlocks);

    // 执行 Markdown 解析
    $content = $widget->isMarkdown ? $widget->markdown($content) : $widget->autoP($content);

    // 防止 markdown/autoP 返回 null
    if ($content === null) {
        $content = '';
    }

    // 还原行内代码占位符
    $content = shufei_restore_inline_code_backticks($content, $icodeBlocks);

    // 还原数学公式占位符
    $content = shufei_restore_math_placeholders($content, $mathBlocks);

    return $content;
}

/**
 * 修复 Markdown 在数学公式中产生的副作用
 * 将 Markdown 生成的 HTML 标签还原为原始符号
 */
function shufei_fix_markdown_in_math($text)
{
    // 将 <em> 还原为下划线（Markdown 将 _ 转为 <em>）
    $text = preg_replace('/<em>(.*?)<\/em>/s', '_$1_', $text);
    // 将 <strong> 还原为星号
    $text = preg_replace('/<strong>(.*?)<\/strong>/s', '**$1**', $text);
    // 将 <del> 还原为波浪号
    $text = preg_replace('/<del>(.*?)<\/del>/s', '~~$1~~', $text);
    // 移除 <br> 标签
    $text = preg_replace('/<br\s*\/?>/i', '', $text);
    // 解码 HTML 实体（如 &amp; → &）
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($text);
}

/**
 * 获取当前验证码类型
 *
 * @return string none|turnstile|captcha_number|captcha_alpha|captcha_alnum
 */
function shufei_get_captcha_type()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->captchaType) ? $options->captchaType : 'none';
}

/**
 * 检查Turnstile人机验证是否启用
 *
 * @return bool
 */
function shufei_is_turnstile_enabled()
{
    return shufei_get_captcha_type() === 'turnstile';
}

/**
 * 检查图片验证码是否启用
 *
 * @return bool
 */
function shufei_is_captcha_enabled()
{
    $type = shufei_get_captcha_type();
    return in_array($type, array('captcha_number', 'captcha_alpha', 'captcha_alnum'));
}

/**
 * 获取验证码字符类型（用于captcha.php的type参数）
 *
 * @return string number|alpha|alnum
 */
function shufei_get_captcha_char_type()
{
    $type = shufei_get_captcha_type();
    if ($type === 'captcha_number') return 'number';
    if ($type === 'captcha_alpha') return 'alpha';
    return 'alnum';
}

/**
 * 获取验证码位数
 *
 * @return int
 */
function shufei_get_captcha_length()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $length = isset($options->captchaLength) ? intval($options->captchaLength) : 4;
    return max(4, min(6, $length));
}

/**
 * 获取Turnstile Site Key
 *
 * @return string
 */
function shufei_get_turnstile_site_key()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->turnstileSiteKey) ? $options->turnstileSiteKey : '';
}

/**
 * 获取所有置顶文章的CID
 *
 * @return array 置顶文章CID数组
 */
function shufei_get_sticky_cids()
{
    $db = \Typecho\Db::get();
    $rows = $db->fetchAll($db->select('cid')->from('table.fields')
        ->where('name = ?', 'sticky')
        ->where('str_value = ?', '1'));

    $cids = [];
    foreach ($rows as $row) {
        $cids[] = $row['cid'];
    }
    return $cids;
}

/**
 * 获取文章缩略图
 *
 * @param object $post 文章对象
 * @return string 缩略图URL
 */
function shufei_get_post_thumbnail($post)
{
    // 1. 首先检查文章自定义字段中的缩略图
    $thumbnail = $post->fields->thumbnail;
    if (!empty($thumbnail)) {
        return $thumbnail;
    }
    
    // 2. 从文章内容中提取第一张图片（支持HTML img标签）
    $content = $post->content ?? '';
    preg_match_all('/<img.*?src=["\'](.*?)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        return $matches[1][0];
    }
    
    // 3. 从文章原始文本中提取第一张图片（支持Markdown格式 ![alt](url)）
    if (!empty($post->text)) {
        // 先去掉 Typecho 的 <!--markdown--> 前缀
        $rawText = preg_replace('/^<!--markdown-->/', '', $post->text);
        // 剔除行内代码块，避免匹配到示例语法如 `![desc](url)` 中的 url
        $cleanText = preg_replace('/`[^`]*`/', '', $rawText);
        preg_match_all('/!\[.*?\]\(([^)]+)\)/', $cleanText, $mdMatches);
        if (!empty($mdMatches[1][0])) {
            return $mdMatches[1][0];
        }
    }
    
    // 4. 使用随机缩略图
    return shufei_get_random_thumbnail();
}

/**
 * 获取Gravatar头像URL
 * 
 * @param string $email 邮箱地址
 * @param int $size 头像尺寸
 * @return string 头像URL
 */
function shufei_get_gravatar_url($email, $size = 80)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $gravatarSource = isset($options->gravatarSource) ? $options->gravatarSource : 'cat';
    
    // 根据配置选择镜像源
    switch ($gravatarSource) {
        case 'loli':
            $baseUrl = 'https://gravatar.loli.net/avatar/';
            break;
        case 'weavatar':
            $baseUrl = 'https://weavatar.com/avatar/';
            break;
        case 'cravatar':
            $baseUrl = 'https://cravatar.cn/avatar/';
            break;
        case 'official':
            $baseUrl = 'https://secure.gravatar.com/avatar/';
            break;
        case 'cat':
        default:
            $baseUrl = 'https://gravatar.luoli.click/avatar/';
            break;
    }
    
    $hash = md5(strtolower(trim($email)));
    return $baseUrl . $hash . '?s=' . $size . '&d=identicon&r=g';
}

/**
 * 获取当前 Archive widget（用于检测当前页面类型与获取内容）
 *
 * @return \Typecho\Widget\Archive|null
 */
function shufei_get_archive()
{
    static $archive = null;
    if ($archive === null) {
        try {
            $archive = \Typecho\Widget::widget('Widget_Archive');
        } catch (\Exception $e) {
            $archive = false;
        }
    }
    return $archive ?: null;
}

/**
 * 当前页面是否为文章页
 */
function shufei_is_post()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('post');
}

/**
 * 当前页面是否为独立页面
 */
function shufei_is_page()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('page');
}

/**
 * 当前页面是否为分类页
 */
function shufei_is_category()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('category');
}

/**
 * 当前页面是否为标签页
 */
function shufei_is_tag()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('tag');
}

/**
 * 当前页面是否为搜索结果页
 */
function shufei_is_search()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('search');
}

/**
 * 当前页面是否为作者归档页
 */
function shufei_is_author()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('author');
}

/**
 * 当前页面是否为日期归档页
 */
function shufei_is_archive()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('archive');
}

/**
 * 当前页面是否为 404
 */
function shufei_is_404()
{
    return \Typecho\Widget::widget('Widget_Options')->template != '404.php'
        && !shufei_is_post() && !shufei_is_page() && !shufei_is_category()
        && !shufei_is_tag() && !shufei_is_search() && !shufei_is_author();
}

/**
 * 截取并清理文本作为 SEO 描述
 *
 * @param string $text 原始文本
 * @param int $length 截取长度
 * @return string
 */
function shufei_seo_trim($text, $length = 120)
{
    if ($text === null) {
        $text = '';
    }
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    return htmlspecialchars($text);
}

/**
 * 获取当前页面的 SEO 关键词
 *
 * 优先级：文章自定义关键词 > 文章标签 > 全站 SEO 关键词 > 站点标题
 *
 * @return string
 */
function shufei_get_seo_keywords()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $keywords = '';

    if (shufei_is_post() || shufei_is_page()) {
        $post = shufei_get_archive();
        if ($post && !empty($post->fields->keywords)) {
            $keywords = $post->fields->keywords;
        } elseif ($post && !empty($post->tags) && is_array($post->tags)) {
            $tagNames = array();
            foreach ($post->tags as $tag) {
                if (isset($tag['name'])) {
                    $tagNames[] = $tag['name'];
                }
            }
            $keywords = implode(',', $tagNames);
        }
    }

    if (empty($keywords) && !empty($options->seoKeywords)) {
        $keywords = $options->seoKeywords;
    }

    if (empty($keywords)) {
        $keywords = $options->title;
    }

    return htmlspecialchars(trim($keywords, ' ,'));
}

/**
 * 获取当前页面的 SEO 描述
 *
 * 优先级：文章自定义 excerpt > 文章内容截取 > 全站 SEO 描述 > Typecho 站点描述
 *
 * @return string
 */
function shufei_get_seo_description()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $description = '';

    if (shufei_is_post()) {
        $post = shufei_get_archive();
        if ($post && !empty($post->fields->excerpt)) {
            $description = shufei_seo_trim($post->fields->excerpt, 160);
        } elseif ($post && !empty($post->content)) {
            $description = shufei_seo_trim($post->content, 160);
        }
    } elseif (shufei_is_page()) {
        $page = shufei_get_archive();
        if ($page && !empty($page->content)) {
            $description = shufei_seo_trim($page->content, 160);
        }
    }

    if (empty($description) && !empty($options->seoDescription)) {
        $description = shufei_seo_trim($options->seoDescription, 160);
    }

    if (empty($description) && !empty($options->description)) {
        $description = shufei_seo_trim($options->description, 160);
    }

    if (empty($description)) {
        $description = $options->title;
    }

    return $description;
}

/**
 * 获取当前页面的 SEO 标题
 *
 * @return string
 */
function shufei_get_seo_title()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $archive = shufei_get_archive();

    if (shufei_is_post() && $archive) {
        return htmlspecialchars($archive->title) . ' - ' . $options->title;
    }

    if (shufei_is_page() && $archive) {
        return htmlspecialchars($archive->title) . ' - ' . $options->title;
    }

    if (shufei_is_category() && $archive) {
        return sprintf(_t('分类 %s 下的文章'), $archive->name) . ' - ' . $options->title;
    }

    if (shufei_is_tag() && $archive) {
        return sprintf(_t('标签 %s 下的文章'), $archive->name) . ' - ' . $options->title;
    }

    if (shufei_is_search()) {
        $s = isset($_GET['s']) ? htmlspecialchars(trim($_GET['s'])) : '';
        if (empty($s) && $archive && !empty($archive->archiveTitle)) {
            $s = htmlspecialchars($archive->archiveTitle);
        }
        return sprintf(_t('包含关键字 %s 的文章'), $s) . ' - ' . $options->title;
    }

    if (shufei_is_author() && $archive) {
        $name = !empty($archive->screenName) ? $archive->screenName : (!empty($archive->name) ? $archive->name : '');
        return sprintf(_t('%s 发布的文章'), $name) . ' - ' . $options->title;
    }

    if (shufei_is_archive()) {
        return _t('文章归档') . ' - ' . $options->title;
    }

    return $options->title;
}

/**
 * 获取社交分享图（OG / Twitter Card）
 *
 * @param object|null $archive 当前内容对象（文章/页面）
 * @return string
 */
function shufei_get_seo_og_image($archive = null)
{
    $options = \Typecho\Widget::widget('Widget_Options');

    if ($archive && !empty($archive->fields->thumbnail)) {
        return $archive->fields->thumbnail;
    }

    if ($archive && !empty($archive->content)) {
        preg_match_all('/<img.*?src=["\'](.*?)["\']/', $archive->content, $matches);
        if (!empty($matches[1][0])) {
            return $matches[1][0];
        }
    }

    if (!empty($options->seoOgImage)) {
        return $options->seoOgImage;
    }

    return '';
}

/**
 * 获取当前页面的 canonical URL
 *
 * @return string
 */
function shufei_get_canonical_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');

    if (shufei_is_post() || shufei_is_page()) {
        $archive = shufei_get_archive();
        if ($archive && !empty($archive->permalink)) {
            return $archive->permalink;
        }
    }

    return $options->siteUrl;
}

/**
 * 输出当前页面的 robots meta 值
 *
 * @return string
 */
function shufei_get_robots_content()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $mode = !empty($options->seoRobots) ? $options->seoRobots : 'auto';

    if ($mode === 'noindex') {
        return 'noindex,nofollow';
    }
    if ($mode === 'index') {
        return 'index,follow';
    }

    // auto 模式
    if (shufei_is_search() || shufei_is_404()) {
        return 'noindex,nofollow';
    }

    // 分页大于 1 标记为 noindex
    if (!empty($_GET['page']) && intval($_GET['page']) > 1) {
        return 'noindex,follow';
    }

    return 'index,follow';
}

/**
 * 渲染文章内容（模板调用入口）
 * 整合预处理、Markdown 解析、回复可见、扩展处理
 *
 * @param Widget\Base\Contents $widget 文章 widget 实例
 * @return string 处理后的 HTML 内容
 */
function shufei_render_post_content($widget)
{
    if ($widget->isMarkdown) {
        // 获取原始 Markdown 文本（___text() 已剥离 <!--markdown--> 前缀）
        $rawText = $widget->text;
        // 预处理：修复表格中行内代码内的 | 等问题
        $rawText = shufei_pre_markdown_process($rawText);

        // 保护行内代码中包含反引号序列的模式（如 ` ```mermaid `）
        $icodeBlocks = array();
        $rawText = shufei_protect_inline_code_backticks($rawText, $icodeBlocks);

        // KaTeX 公式保护：在 Markdown 解析前提取数学公式，防止 _ 等被破坏
        $mathBlocks = array();
        $katexEnabled = false;
        $options = \Typecho\Widget::widget('Widget_Options');
        if (!empty($options->katexEnabled) && $options->katexEnabled === 'on') {
            $katexEnabled = true;
            $rawText = shufei_extract_math_placeholders($rawText, $mathBlocks);
        }

        // 手动 Markdown 解析
        $html = \Utils\Markdown::convert($rawText);

        // 还原行内代码占位符
        $html = shufei_restore_inline_code_backticks($html, $icodeBlocks);

        // 还原数学公式占位符
        if ($katexEnabled) {
            $html = shufei_restore_math_placeholders($html, $mathBlocks);
        }
    } else {
        // 非 Markdown 内容，使用默认解析
        $html = $widget->content;
    }

    // 处理回复可见
    $html = shufei_parse_reply_content($html, $widget->cid);

    // 应用 Markdown 扩展
    $html = shufei_apply_markdown_ext($html);

    return $html;
}

/**
 * 保护行内代码中包含反引号序列的模式
 * 处理 ` ```mermaid ` 这类 HyperDown 无法正确解析的模式
 * 在 Markdown 解析前将整段行内代码替换为占位符，解析后还原为正确的 HTML
 *
 * @param string $text Markdown 原始文本
 * @param array &$codeBlocks 存储替换的 HTML 内容
 * @return string 替换后的文本
 */
function shufei_protect_inline_code_backticks($text, &$codeBlocks)
{
    if (empty($text) || !is_string($text)) {
        return $text ?? '';
    }

    // 先临时保护围栏代码块，避免匹配到代码块内部的相同模式
    $fencedBlocks = array();
    $text = preg_replace_callback('/^( {0,3})(`{3,})([\s\S]*?)^\1`{3,}/m', function ($m) use (&$fencedBlocks) {
        $key = '<!--FENCED' . count($fencedBlocks) . '-->';
        $fencedBlocks[] = $m[0];
        return $key;
    }, $text);
    $text = preg_replace_callback('/^( {0,3})(~{3,})([\s\S]*?)^\1~{3,}/m', function ($m) use (&$fencedBlocks) {
        $key = '<!--FENCED' . count($fencedBlocks) . '-->';
        $fencedBlocks[] = $m[0];
        return $key;
    }, $text);
    // 保护缩进代码块
    $text = preg_replace_callback('/^( {4,}.*(?:\n {4,}.*)*)/m', function ($m) use (&$fencedBlocks) {
        $key = '<!--FENCED' . count($fencedBlocks) . '-->';
        $fencedBlocks[] = $m[0];
        return $key;
    }, $text);

    // 匹配行内代码中内容以反引号序列开头的模式
    // 例如 ` ```mermaid ` → 开头反引号 + 空格 + 反引号序列 + 文字 + 空格 + 结尾反引号
    $text = preg_replace_callback('/`[ \t]*(`+)([^`\n]+?)[ \t]*`/', function ($m) use (&$codeBlocks) {
        $key = '<!--ICODE' . count($codeBlocks) . '-->';
        $innerBackticks = $m[1];
        $rest = $m[2];
        $codeBlocks[] = '<code>' . htmlspecialchars($innerBackticks . $rest) . '</code>';
        return $key;
    }, $text);

    // 还原围栏代码块
    foreach ($fencedBlocks as $i => $fenced) {
        $text = str_replace('<!--FENCED' . $i . '-->', $fenced, $text);
    }

    return $text;
}

/**
 * 还原行内代码占位符
 * 在 Markdown 解析后，将占位符替换回正确的 HTML
 *
 * @param string $content Markdown 解析后的 HTML 内容
 * @param array &$codeBlocks 存储的 HTML 内容数组
 * @return string 还原后的 HTML 内容
 */
function shufei_restore_inline_code_backticks($content, &$codeBlocks)
{
    if (empty($codeBlocks)) {
        return $content;
    }
    foreach ($codeBlocks as $i => $html) {
        $content = str_replace('<!--ICODE' . $i . '-->', $html, $content);
        // Markdown 可能在占位符外面包了 <p> 标签
        $content = str_replace('<p><!--ICODE' . $i . '--></p>', $html, $content);
    }
    return $content;
}

/**
 * 预处理 Markdown 文本，修复 HyperDown 解析器的已知问题
 * 1. 表格中行内代码内的 | 会被误当作列分隔符，替换为 &#124;
 * 2. 表格中 \| 转义在 HyperDown 中无效，也需替换
 */
function shufei_pre_markdown_process($text)
{
    if (empty($text) || !is_string($text) || strpos($text, '|') === false) {
        return $text ?? '';
    }

    // 检查是否包含表格（含 | 分隔的行，允许行尾空白）
    if (!preg_match('/^\|.+\|\s*$/m', $text)) {
        return $text;
    }

    // 逐行处理：只处理表格行
    $lines = explode("\n", $text);
    foreach ($lines as &$line) {
        // 跳过非表格行
        if (strpos($line, '|') === false) {
            continue;
        }

        // 1. 保护行内代码中的 | 和 \| 字符
        $line = preg_replace_callback('/`[^`]+`/', function ($m) {
            // 先处理 \| （去掉无意义的反斜杠转义），再处理 |
            $result = str_replace('\\|', '&#124;', $m[0]);
            $result = str_replace('|', '&#124;', $result);
            return $result;
        }, $line);

        // 2. 保护表格行中非代码的 \| 转义（HyperDown 不支持 \| 转义）
        // 只在表格行中处理，避免影响普通段落
        $line = preg_replace('/\\\\\|/', '&#124;', $line);
    }

    return implode("\n", $lines);
}

/**
 * 还原 <code> 和 <pre> 内的 | 占位符
 * 由于 shufei_pre_markdown_process 在预处理阶段将 | 转为 &#124; 避免破坏表格列分隔，
 * Markdown 解析后 &#124; 会被 HTML 实体化为 &amp;#124;，导致在代码块中显示为字面量 &#124;
 * 此函数在最终输出前将 &amp;#124; / &#124; 还原为 |
 */
function shufei_fix_inline_code_pipe($content)
{
    if (empty($content) || !is_string($content) || (strpos($content, '&#124;') === false && strpos($content, '&amp;#124;') === false)) {
        return $content ?? '';
    }

    return preg_replace_callback(
        '/<(code|pre)[^>]*>.*?<\/\1>/si',
        function ($matches) {
            $fixed = str_replace('&amp;#124;', '|', $matches[0]);
            $fixed = str_replace('&#124;', '|', $fixed);
            return $fixed;
        },
        $content
    );
}

/**
 * 直接应用 Markdown 扩展处理（模板调用入口）
 * 绕过钩子系统，直接在模板中处理内容
 *
 * @param string $content 已解析的 HTML 内容
 * @return string 处理后的 HTML 内容
 */
function shufei_apply_markdown_ext($content)
{
    if (empty($content)) {
        return $content;
    }

    // 检查是否开启 Markdown 扩展
    $options = \Typecho\Widget::widget('Widget_Options');
    if (!empty($options->markdownExtEnabled) && $options->markdownExtEnabled === 'off') {
        return $content;
    }

    // 清理可能残留的 <!--markdown--> 标记
    $content = preg_replace('/<p><!--markdown--><\/p>/', '', $content);
    $content = str_replace('<!--markdown-->', '', $content);

    // 1. 处理图片扩展语法（大小、对齐、标题、懒加载）
    $content = shufei_process_images($content);

    // 2. 处理高亮文本 ==text==
    $content = shufei_process_mark($content);

    // 3. 处理任务列表 - [x] / - [ ]
    $content = shufei_process_task_lists($content);

    // 4. 处理 blockquote 中的提示框和折叠区块（统一处理，避免嵌套）
    $content = shufei_process_blockquote_extensions($content);

    // 5. 还原 <code>/<pre> 内的 | 占位符（表格预处理产生）
    $content = shufei_fix_inline_code_pipe($content);

    // 6. 清理残留的空 blockquote 标签
    $content = preg_replace('/<blockquote>\s*<\/blockquote>/s', '', $content);
    // 清理仅包含 admonition/details div 的 blockquote（合并提示框处理后残留）
    $content = shufei_match_outer_blockquotes($content, function ($inner, $fullMatch) {
        $trimmedInner = trim($inner);
        // 如果内部只包含 div 元素（admonition/details），移除 blockquote 包裹
        if (preg_match('/^(<div[^>]*>.*<\/div>)\s*$/s', $trimmedInner) ||
            preg_match('/^(<div[^>]*>.*<\/div>\s*)+$/s', $trimmedInner)) {
            return $inner;
        }
        return $fullMatch;
    });

    return $content;
}

/**
 * Markdown 扩展内容过滤器（钩子版本）
 * 在 Markdown 解析后处理扩展语法，增强文章内容显示
 *
 * 支持的扩展语法：
 * 1. 图片大小：![alt|300x200](url) 或 ![alt|50%](url)
 * 2. 图片对齐：![alt#center](url)、![alt#left](url)、![alt#right](url)
 * 3. 图片带标题：![这是标题](url) 自动转为 <figure>+<figcaption>
 * 4. 高亮文本：==高亮内容== → <mark>高亮内容</mark>
 * 5. 任务列表：- [x] 已完成 / - [ ] 未完成 → 复选框
 * 6. 提示框：> [!tip] 内容 / > [!warning] 内容 / > [!note] 内容 等
 * 7. 折叠区块：> [details:标题] 内容 → <details><summary>标题</summary>内容</details>
 * 8. 图片懒加载：自动为图片添加 loading="lazy"
 *
 * @param string $content 已解析的 HTML 内容
 * @param object $widget Contents widget 实例
 * @param string $lastResult 上一个钩子的返回值
 * @return string 处理后的 HTML 内容
 */
function shufei_markdown_ext_filter($content, $widget, $lastResult)
{
    $content = $lastResult ?: $content;

    // 仅在文章/页面内容中处理
    if (empty($content)) {
        return $content;
    }

    return shufei_apply_markdown_ext($content);
}

/**
 * 处理图片扩展语法
 * - 图片大小：![alt|300x200](url) 或 ![alt|50%](url)
 * - 图片对齐：![alt#center](url)、![alt#left](url)、![alt#right](url)
 * - 图片标题：alt 文本自动作为 figcaption
 * - 图片懒加载：自动添加 loading="lazy"
 */
function shufei_process_images($content)
{
    if ($content === null) {
        $content = '';
    }
    // 匹配 Markdown 生成的 <img> 标签，包括被 <a> 标签包裹的情况
    // HyperDown 生成格式：<img src="URL" alt="ALT" title="TITLE">
    // 链接图片格式：<a href="LINK"><img src="URL" alt="ALT" title="TITLE"></a>
    $content = preg_replace_callback(
        '/(<a\s+href="[^"]*"[^>]*>)?\s*(<img\s+src="([^"]+)"\s+alt="([^"]*)"(?:\s+title="([^"]*)")?\s*>)\s*(<\/a>)?/s',
        function ($matches) {
            $hasLink = !empty($matches[1]);
            $openLink = $hasLink ? $matches[1] : '';
            $closeLink = !empty($matches[5]) ? $matches[5] : '';
            $src = $matches[3];
            $alt = $matches[4];
            $title = isset($matches[5]) && !$closeLink ? $matches[5] : (isset($matches[6]) ? '' : '');
            // 修正：title 在 matches[5]（当无链接时）或 matches[5] 是 </a>
            // 重新从 img 标签中提取 title
            $imgTag = $matches[2];
            $titleVal = '';
            if (preg_match('/title="([^"]*)"/', $imgTag, $titleMatch)) {
                $titleVal = $titleMatch[1];
            }

            $width = '';
            $height = '';
            $align = '';
            $caption = '';
            $cleanAlt = $alt;

            // 解析 alt 中的扩展语法
            // 格式：alt文本|宽x高#对齐
            // 例如：图片描述|300x200#center

            // 提取对齐方式 #left / #center / #right
            if (preg_match('/#(left|center|right)$/i', $cleanAlt, $alignMatch)) {
                $align = strtolower($alignMatch[1]);
                $cleanAlt = preg_replace('/#(left|center|right)$/i', '', $cleanAlt);
            }

            // 提取尺寸 |300x200 或 |50% 或 |300 或 |x200
            if (preg_match('/\|(\d*%?)(?:x(\d*%?))?$/i', $cleanAlt, $sizeMatch)) {
                if (!empty($sizeMatch[1])) {
                    $width = $sizeMatch[1];
                }
                if (!empty($sizeMatch[2])) {
                    $height = $sizeMatch[2];
                }
                $cleanAlt = preg_replace('/\|\d*%?(?:x\d*%?)?$/i', '', $cleanAlt);
            }

            // 构建 img 属性
            $imgAttrs = 'src="' . htmlspecialchars($src) . '"';
            $imgAttrs .= ' alt="' . htmlspecialchars($cleanAlt) . '"';
            if (!empty($titleVal) && $titleVal !== $cleanAlt) {
                $imgAttrs .= ' title="' . htmlspecialchars($titleVal) . '"';
            }
            // 收集百分比样式，合并到同一个 style 属性中
            $styleParts = array();
            if (!empty($width)) {
                if (substr($width, -1) === '%') {
                    $styleParts[] = 'width:' . intval($width) . '%';
                } else {
                    $imgAttrs .= ' width="' . intval($width) . '"';
                }
            }
            if (!empty($height)) {
                if (substr($height, -1) === '%') {
                    $styleParts[] = 'height:' . intval($height) . '%';
                } else {
                    $imgAttrs .= ' height="' . intval($height) . '"';
                }
            }
            if (!empty($styleParts)) {
                $imgAttrs .= ' style="' . implode(';', $styleParts) . '"';
            }
            // 懒加载
            $imgAttrs .= ' loading="lazy"';

            $newImgTag = '<img ' . $imgAttrs . '>';

            // 如果有对齐方式或 alt 文本（作为标题），包裹在 figure 中
            if (!empty($align) || !empty($cleanAlt)) {
                $figureClass = 'post-figure';
                if (!empty($align)) {
                    $figureClass .= ' post-figure-' . $align;
                }
                $html = '<figure class="' . $figureClass . '">';
                // 如果图片在链接内，将链接保留在 figure 内部
                if ($hasLink) {
                    $html .= $openLink . $newImgTag . $closeLink;
                } else {
                    $html .= $newImgTag;
                }
                if (!empty($cleanAlt)) {
                    $html .= '<figcaption>' . htmlspecialchars($cleanAlt) . '</figcaption>';
                }
                $html .= '</figure>';
                return $html;
            }

            // 无需 figure 包裹，只替换 img 标签属性
            if ($hasLink) {
                return $openLink . $newImgTag . $closeLink;
            }
            return $newImgTag;
        },
        $content
    );

    return $content;
}

/**
 * 处理高亮文本 ==text== → <mark>text</mark>
 */
function shufei_process_mark($content)
{
    if ($content === null) {
        $content = '';
    }
    // 先保护 <code>、<pre> 内的内容和 HTML 标签本身（含属性值），避免误处理
    $protected = array();
    // 保护 <pre>...</pre> 和 <code>...</code>
    $content = preg_replace_callback('/<(code|pre)[^>]*>.*?<\/\1>/si', function ($m) use (&$protected) {
        $key = '<!--PROTECT' . count($protected) . '-->';
        $protected[] = $m[0];
        return $key;
    }, $content);
    // 保护 HTML 标签（含属性值中的 ==），避免 style="color:==red==" 被误匹配
    $content = preg_replace_callback('/<[a-zA-Z][^>]*>/s', function ($m) use (&$protected) {
        $key = '<!--PROTECT' . count($protected) . '-->';
        $protected[] = $m[0];
        return $key;
    }, $content);

    // 匹配 ==text==，此时已无 HTML 标签干扰
    $content = preg_replace_callback(
        '/==(?!==)(.+?)(?<!<\/)==(?!==)/s',
        function ($matches) {
            return '<mark>' . $matches[1] . '</mark>';
        },
        $content
    );

    // 还原保护的内容
    foreach ($protected as $i => $html) {
        $content = str_replace('<!--PROTECT' . $i . '-->', $html, $content);
    }

    return $content;
}

/**
 * 处理任务列表
 * - [x] 已完成 → <li class="task-list-item"><input type="checkbox" checked disabled>
 * - [ ] 未完成 → <li class="task-list-item"><input type="checkbox" disabled>
 */
function shufei_process_task_lists($content)
{
    if ($content === null) {
        $content = '';
    }
    // 匹配 <li>- [x] 或 <li>[x] 等变体
    $content = preg_replace_callback(
        '/<li>(\s*)\[([ xX])\]\s*/s',
        function ($matches) {
            $checked = strtolower($matches[2]) === 'x';
            $checkbox = '<input type="checkbox" class="task-list-checkbox"' .
                ($checked ? ' checked' : '') .
                ' disabled>';
            return '<li class="task-list-item">' . $matches[1] . $checkbox . ' ';
        },
        $content
    );

    return $content;
}

/**
 * 匹配最外层 blockquote 标签（正确处理嵌套）
 * 使用递归方式匹配，避免非贪婪匹配在嵌套时错误匹配内层结束标签
 *
 * @param string $content HTML 内容
 * @param callable $callback 对每个匹配的 blockquote 调用，参数为 (innerHtml, fullMatch)
 * @return string 处理后的内容
 */
function shufei_match_outer_blockquotes($content, $callback)
{
    $result = '';
    $offset = 0;
    $len = strlen($content);
    $openTagLen = 12;   // strlen('<blockquote>')
    $closeTagLen = 13;  // strlen('</blockquote>')

    while ($offset < $len) {
        // 查找下一个 <blockquote>
        $start = strpos($content, '<blockquote>', $offset);
        if ($start === false) {
            $result .= substr($content, $offset);
            break;
        }

        // 输出 <blockquote> 之前的内容
        $result .= substr($content, $offset, $start - $offset);

        // 使用计数器找到匹配的 </blockquote>
        $depth = 1;
        $searchPos = $start + $openTagLen;
        while ($depth > 0 && $searchPos < $len) {
            $nextOpen = strpos($content, '<blockquote>', $searchPos);
            $nextClose = strpos($content, '</blockquote>', $searchPos);

            if ($nextClose === false) {
                // 没有匹配的关闭标签，原样保留
                $searchPos = $len;
                break;
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $searchPos = $nextOpen + $openTagLen;
            } else {
                $depth--;
                if ($depth === 0) {
                    $end = $nextClose + $closeTagLen;
                    $inner = substr($content, $start + $openTagLen, $nextClose - $start - $openTagLen);
                    $fullMatch = substr($content, $start, $end - $start);
                    $result .= $callback($inner, $fullMatch);
                    $offset = $end;
                    break;
                }
                $searchPos = $nextClose + $closeTagLen;
            }
        }

        // 如果没找到匹配的关闭标签
        if ($depth > 0) {
            $result .= substr($content, $start);
            break;
        }
    }

    return $result;
}

/**
 * 统一处理 blockquote 中的提示框和折叠区块
 * 逐段解析，避免合并 blockquote 时的嵌套问题
 *
 * 支持语法：
 * > [!tip] 标题          → 提示框
 * > [details:标题]       → 折叠区块
 */
function shufei_process_blockquote_extensions($content)
{
    if ($content === null) {
        $content = '';
    }
    $content = shufei_match_outer_blockquotes($content, function ($inner, $fullMatch) {
            // 检查是否包含扩展标记（支持 <p> 包裹和直接文本两种情况）
            if (!preg_match('/(<p>)?\[!(tip|note|info|warning|danger)\]|(<p>)?\[details:/i', $inner)) {
                return $fullMatch; // 无标记，原样返回
            }

            // 先保护 <pre> 块，避免段落拆分时破坏代码块
            $preBlocks = array();
            $inner = preg_replace_callback('/<pre[^>]*>.*?<\/pre>/si', function ($m) use (&$preBlocks) {
                $key = '<!--PRE' . count($preBlocks) . '-->';
                $preBlocks[] = $m[0];
                return $key;
            }, $inner);

            // 按段落拆分（保留分隔符）
            $parts = preg_split('/(<p>.*?<\/p>)/si', $inner, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            $result = '';
            $currentBlock = null; // 当前正在构建的块

            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (empty($trimmed)) continue;

                // 检查是否为提示框标记段落（<p> 包裹）
                if (preg_match('/^<p>\[!(tip|note|info|warning|danger)\](.*?)<\/p>$/si', $trimmed, $m)) {
                    // 先输出之前的块
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $type = strtolower($m[1]);
                    $titleAndBody = trim($m[2]);
                    $title = '';
                    $body = '';
                    if (preg_match('/^(.*?)(?:<br\s*\/?>)(.*)$/s', $titleAndBody, $titleParts)) {
                        $title = trim($titleParts[1]);
                        $body = trim($titleParts[2]);
                    } else {
                        $title = $titleAndBody;
                    }

                    $currentBlock = array('type' => 'admonition', 'subtype' => $type, 'title' => $title, 'body' => $body);
                }
                // 检查是否为折叠区块标记段落（<p> 包裹）
                elseif (preg_match('/^<p>\[details:(.*?)\](.*?)<\/p>$/si', $trimmed, $m)) {
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $summary = trim($m[1]);
                    $inlineBody = trim($m[2]);
                    $body = '';
                    if (preg_match('/^(?:<br\s*\/?>)?(.*)$/s', $inlineBody, $bodyMatch)) {
                        $body = trim($bodyMatch[1]);
                    }

                    $currentBlock = array('type' => 'details', 'summary' => $summary, 'body' => $body);
                }
                // 检查无 <p> 包裹的提示框标记（HyperDown 有时不生成 <p> 标签）
                elseif (preg_match('/^\[!(tip|note|info|warning|danger)\](.*)$/si', $trimmed, $m)) {
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $type = strtolower($m[1]);
                    $titleAndBody = trim($m[2]);
                    $title = '';
                    $body = '';
                    // 无 <p> 包裹时用 <br> 分割标题和内容
                    if (preg_match('/^(.*?)(?:<br\s*\/?>)(.*)$/s', $titleAndBody, $titleParts)) {
                        $title = trim($titleParts[1]);
                        $body = trim($titleParts[2]);
                    } else {
                        $title = $titleAndBody;
                    }

                    $currentBlock = array('type' => 'admonition', 'subtype' => $type, 'title' => $title, 'body' => $body);
                }
                // 检查无 <p> 包裹的折叠区块标记
                elseif (preg_match('/^\[details:(.*?)\](.*)$/si', $trimmed, $m)) {
                    if ($currentBlock !== null) {
                        $result .= shufei_build_ext_block_html($currentBlock);
                    }

                    $summary = trim($m[1]);
                    $inlineBody = trim($m[2]);
                    $body = '';
                    if (preg_match('/^(?:<br\s*\/?>)?(.*)$/s', $inlineBody, $bodyMatch)) {
                        $body = trim($bodyMatch[1]);
                    }

                    $currentBlock = array('type' => 'details', 'summary' => $summary, 'body' => $body);
                }
                // 内容段落：归属当前块，或作为独立内容
                elseif ($currentBlock !== null) {
                    $currentBlock['body'] .= (empty($currentBlock['body']) ? '' : ' ') . $trimmed;
                } else {
                    $result .= $part;
                }
            }

            // 输出最后一个块
            if ($currentBlock !== null) {
                $result .= shufei_build_ext_block_html($currentBlock);
            }

            // 还原 <pre> 块
            foreach ($preBlocks as $i => $preHtml) {
                $result = str_replace('<!--PRE' . $i . '-->', $preHtml, $result);
            }

            return $result;
        });

    return $content;
}

/**
 * 构建扩展块的 HTML（提示框或折叠区块）
 */
function shufei_build_ext_block_html($block)
{
    if ($block['type'] === 'admonition') {
        $type = $block['subtype'];
        $title = $block['title'];
        $body = $block['body'];

        $typeNames = array(
            'tip'    => '提示',
            'note'   => '备注',
            'info'   => '信息',
            'warning' => '警告',
            'danger' => '危险'
        );
        $displayTitle = !empty($title) ? $title : (isset($typeNames[$type]) ? $typeNames[$type] : ucfirst($type));

        $html = '<div class="admonition admonition-' . $type . '">';
        $html .= '<div class="admonition-title">' . htmlspecialchars($displayTitle) . '</div>';
        if (!empty($body)) {
            $html .= '<div class="admonition-content">' . $body . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    if ($block['type'] === 'details') {
        $summary = $block['summary'];
        $body = $block['body'];

        $html = '<details class="post-details">';
        $html .= '<summary>' . htmlspecialchars($summary) . '</summary>';
        $html .= '<div class="details-content">' . $body . '</div>';
        $html .= '</details>';
        return $html;
    }

    return '';
}