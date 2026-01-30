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
    $currentVersion = '1.1.1';
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $result = json_decode($response, true);
        if ($result && isset($result['code'])) {
            return $result;
        }
    }
    
    return array('code' => 0, 'msg' => '检测失败，请稍后重试');
}

/**
 * 获取主题版本号
 * 
 * @return string 主题版本号
 */
function shufei_get_theme_version()
{
    return '1.1.1';
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
        '</style>';
    echo $css;

    $html = '<div id="cat-tpl" style="display:none">' .
        '<div class="cat-config-container">' .
            '<div class="cat-config-aside">' .
                '<div class="cat-config-logo">ShuFeiCat 设置</div>' .
                '<ul id="cat-tabs">' .
                    '<li data-id="cat-basic" class="active">基本设置</li>' .
                    '<li data-id="cat-avatar">头像外观</li>' .
                    '<li data-id="cat-pjax">Pjax无刷新</li>' .
                    '<li data-id="cat-resource">资源加载</li>' .
                    '<li data-id="cat-article">文章缩略图</li>' .
                    '<li data-id="cat-mail">评论邮件通知</li>' .
                    '<li data-id="cat-ai">AI 评论审核</li>' .
                    '<li data-id="cat-verify">人机验证</li>' .
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
            'var ids = ["cat-basic", "cat-avatar", "cat-pjax", "cat-resource", "cat-article", "cat-mail", "cat-ai", "cat-verify"];' .
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

    $commentMailPassword = new \Typecho\Widget\Helper\Form\Element\Text(
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
        
        $freeApiUrl = 'https://newapi.nki.pw/v1/chat/completions';
        $freeApiKey = 'sk-Db7pjBREaWCmkcZ0kTgDlILjMAsrfJRYFUpiRrH5SOlnpDtS';
        $freeModel = '[低价沉浸式翻译]GPT-4o';
        
        $targetUrl = ($apiType === 'free' || empty($apiUrl)) ? $freeApiUrl : $apiUrl;
        $targetKey = ($apiType === 'free' || empty($apiKey)) ? $freeApiKey : $apiKey;
        $targetModel = ($apiType === 'free' || empty($model)) ? $freeModel : $model;

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) { $apiStatus = '✗ AI API 连接失败: ' . $err; $apiClass = 'api-error'; }
        elseif ($code === 200) { $apiStatus = '✓ AI 接口正常连通 (HTTP 200)'; $apiClass = 'api-success'; }
        else { $apiStatus = '✗ AI API 返回异常: HTTP ' . $code . ' (请检查 API 地址或密钥)'; $apiClass = 'api-error'; }

        echo '<div class="typecho-option cat-group-ai"><div class="api-status-box ' . $apiClass . '"><b>接口状态检测：</b><br>' . $apiStatus . '</div></div>';
    } else {

        echo '<div class="typecho-option cat-group-ai"><div class="api-status-box api-error"><b>接口状态检测：</b><br>AI审核功能已关闭，开启后自动检测接口状态</div></div>';
    }
    $aiApiType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aiApiType',
        array('free' => '免费接口', 'custom' => '自定义接口'),
        'free',
        _t('AI接口类型'),
        _t('介绍：免费接口由主题内置提供；自定义接口可配置您自己的API地址')
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

    $aiModerationApiKey = new \Typecho\Widget\Helper\Form\Element\Text(
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

    
    $turnstileEnabled = new \Typecho\Widget\Helper\Form\Element\Radio(
        'turnstileEnabled',
        array('off' => _t('关闭'), 'on' => _t('开启')),
        'off',
        _t('Turnstile人机验证'),
        _t('介绍：开启后评论提交将进行 Cloudflare Turnstile 人机验证，有效防止机器人灌水')
    );
    $turnstileEnabled->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileEnabled);

    $turnstileSiteKey = new \Typecho\Widget\Helper\Form\Element\Text(
        'turnstileSiteKey',
        null,
        null,
        _t('Site Key'),
        _t('介绍：填写 Cloudflare Turnstile 提供的 Site Key')
    );
    $turnstileSiteKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileSiteKey);

    $turnstileSecretKey = new \Typecho\Widget\Helper\Form\Element\Text(
        'turnstileSecretKey',
        null,
        null,
        _t('Secret Key'),
        _t('介绍：填写 Cloudflare Turnstile 提供的 Secret Key')
    );
    $turnstileSecretKey->setAttribute('class', 'typecho-option cat-group-verify');
    $form->addInput($turnstileSecretKey);
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
}

/* 加载核心逻辑库 */
@require_once dirname(__FILE__) . '/core/mail.php';
@require_once dirname(__FILE__) . '/core/ai-moderation.php';

/**
 * 核心逻辑钩子：评论安全性校验（包含AI审核）
 */
function shufei_comment_check($comment, $post) {
    $options = \Typecho\Widget::widget('Widget_Options');
    
    // 1. 人机验证校验
    if (isset($options->turnstileEnabled) && $options->turnstileEnabled === 'on') {
        $token = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
        if (empty($token)) {
            throw new \Typecho\Widget\Exception(_t('请先完成人机验证'));
        }
    }
    
    // 2. AI评论审核
    if (function_exists('processAiModeration')) {
        $comment = processAiModeration($comment);
    }
    
    return $comment;
}

// 注册钩子（整合AI审核功能）
\Typecho\Plugin::factory('Widget_Feedback')->comment = 'shufei_comment_check';

/**
 * 检查Turnstile人机验证是否启用
 * 
 * @return bool
 */
function shufei_is_turnstile_enabled()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    return isset($options->turnstileEnabled) && $options->turnstileEnabled === 'on';
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
    
    // 2. 从文章内容中提取第一张图片
    $content = $post->content;
    preg_match_all('/<img.*?src=["\'](.*?)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        return $matches[1][0];
    }
    
    // 3. 使用随机缩略图
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