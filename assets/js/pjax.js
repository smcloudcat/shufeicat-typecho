/**
 * Pjax加载功能
 * 用于全站无刷新页面切换，提升用户体验
 * 支持五种加载动画样式：progress、circle、dots、wave、spin-ring
 * 支持AJAX无刷新提交评论
 */

(function() {
    var pjaxEnabled = window.pjaxEnabled || false;
    var pjaxLoadStyle = window.pjaxLoadStyle || 'progress';
    var pjaxTimeout = window.pjaxTimeout || 10000;
    
    if (!pjaxEnabled) return;
    
    var loadingContainer = null;
    
    function createProgressStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-progress';
        container.className = 'pjax-loading pjax-progress';

        var bar = document.createElement('div');
        bar.className = 'pjax-progress-bar';

        container.appendChild(bar);
        return container;
    }

    function createCircleStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-circle';
        container.className = 'pjax-loading pjax-circle';

        var wrapper = document.createElement('div');
        wrapper.className = 'pjax-circle-wrapper';

        var spinner = document.createElement('div');
        spinner.className = 'pjax-circle-spinner';

        var text = document.createElement('div');
        text.className = 'pjax-circle-text';
        text.textContent = '加载中...';

        wrapper.appendChild(spinner);
        wrapper.appendChild(text);
        container.appendChild(wrapper);

        return container;
    }

    function createDotsStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-dots';
        container.className = 'pjax-loading pjax-dots';

        var wrapper = document.createElement('div');
        wrapper.className = 'pjax-dots-wrapper';

        var text = document.createElement('span');
        text.className = 'pjax-dots-text';
        text.textContent = '加载中';

        var dots = document.createElement('div');
        dots.className = 'pjax-dots-container';

        for (var i = 0; i < 3; i++) {
            var dot = document.createElement('span');
            dot.className = 'pjax-dot';
            dots.appendChild(dot);
        }

        wrapper.appendChild(text);
        wrapper.appendChild(dots);
        container.appendChild(wrapper);

        return container;
    }

    function createWaveStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-wave';
        container.className = 'pjax-loading pjax-wave';

        var wrapper = document.createElement('div');
        wrapper.className = 'pjax-wave-wrapper';

        var text = document.createElement('div');
        text.className = 'pjax-wave-text';

        var chars = '加载中'.split('');
        for (var i = 0; i < chars.length; i++) {
            var c = document.createElement('span');
            c.className = 'pjax-wave-char';
            c.textContent = chars[i];
            text.appendChild(c);
        }

        wrapper.appendChild(text);
        container.appendChild(wrapper);

        return container;
    }

    function createSpinRingStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-spin-ring';
        container.className = 'pjax-loading pjax-spin-ring';

        var wrapper = document.createElement('div');
        wrapper.className = 'pjax-spin-ring-wrapper';

        var ring = document.createElement('div');
        ring.className = 'pjax-spin-ring-ring';

        var text = document.createElement('div');
        text.className = 'pjax-spin-ring-text';
        text.textContent = '加载中...';

        wrapper.appendChild(ring);
        wrapper.appendChild(text);
        container.appendChild(wrapper);

        return container;
    }

    function createLoadingIndicator() {
        switch (pjaxLoadStyle) {
            case 'circle':
                loadingContainer = createCircleStyle();
                break;
            case 'dots':
                loadingContainer = createDotsStyle();
                break;
            case 'wave':
                loadingContainer = createWaveStyle();
                break;
            case 'spin-ring':
                loadingContainer = createSpinRingStyle();
                break;
            default:
                loadingContainer = createProgressStyle();
        }
        document.body.appendChild(loadingContainer);
    }
    
    function showLoading() {
        loadingContainer.style.display = 'block';
        
        if (pjaxLoadStyle === 'progress') {
            var bar = loadingContainer.querySelector('.pjax-progress-bar');
            if (bar) {
                bar.style.width = '30%';
                bar.style.transition = 'width 0.3s ease';
            }
        }
    }
    
    function updateProgress(percent) {
        if (pjaxLoadStyle === 'progress') {
            var bar = loadingContainer.querySelector('.pjax-progress-bar');
            if (bar) {
                bar.style.width = percent + '%';
            }
        }
    }
    
    function hideLoading() {
        if (pjaxLoadStyle === 'progress') {
            var bar = loadingContainer.querySelector('.pjax-progress-bar');
            if (bar) {
                bar.style.width = '100%';
                bar.style.transition = 'width 0.2s ease';
            }
            setTimeout(function() {
                loadingContainer.style.display = 'none';
                bar.style.width = '0%';
            }, 200);
        } else {
            loadingContainer.style.display = 'none';
        }
    }
    
    var excludeSelectors = [
        'a[href^="javascript:"]',
        'a[href^="#"]',
        'a[target="_blank"]',
        'a[download]',
        '.no-pjax',
        // 后台/登录/登出：精确匹配 admin 路径，避免 a[href*="admin"] 误伤普通链接
        'a[href*="/admin"]',
        'a[href*="/login"]',
        'a[href*="/logout"]',
        'a[href*="/action/logout"]',
        'a[href*="/feed"]',
        '.password-protection a',
        'a[href*="/action/"]'
    ];

    function showSubmitTip(msg, type) {
        var tip = document.getElementById('comment-submit-tip');
        if (!tip) return;
        tip.textContent = msg;
        tip.className = 'comment-submit-tip ' + (type || '');
        if (type === 'success' || type === 'error') {
            setTimeout(function() {
                tip.textContent = '';
                tip.className = 'comment-submit-tip';
            }, 5000);
        }
    }

    function setSubmitLoading(loading) {
        var btn = document.getElementById('comment-submit-btn');
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            if (!btn.getAttribute('data-original-text')) {
                btn.setAttribute('data-original-text', btn.innerHTML);
            }
            var textNode = document.createTextNode(' 提交中...');
            while (btn.firstChild) btn.removeChild(btn.firstChild);
            var newIcon = document.createElement('i');
            newIcon.className = 'fa fa-spinner fa-spin';
            btn.appendChild(newIcon);
            btn.appendChild(textNode);
        } else {
            var original = btn.getAttribute('data-original-text');
            if (original) {
                btn.innerHTML = original;
            }
            btn.removeAttribute('data-original-text');
        }
    }

    function initAjaxComment() {
        var commentForm = document.getElementById('comment-form');
        if (!commentForm) return;

        // 如果已经绑定过事件委托，跳过
        if (commentForm.getAttribute('data-ajax-bound')) return;
        commentForm.setAttribute('data-ajax-bound', 'true');

        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var textarea = commentForm.querySelector('#textarea');
            if (!textarea || !textarea.value.trim()) {
                showSubmitTip('请填写评论内容', 'error');
                return;
            }

            var author = commentForm.querySelector('#author');
            if (author && author.required && !author.value.trim()) {
                showSubmitTip('请填写称呼', 'error');
                return;
            }

            var mail = commentForm.querySelector('#mail');
            if (mail && mail.required && !mail.value.trim()) {
                showSubmitTip('请填写邮箱', 'error');
                return;
            }

            // 极验 Geetest v4：未验证则弹出验证码，验证成功后会重新触发 submit
            var geetestContainer = document.getElementById('geetest-captcha');
            if (geetestContainer) {
                var geetestValid = (window.geetestCaptchaObj && typeof window.geetestCaptchaObj.getValidate === 'function')
                    ? window.geetestCaptchaObj.getValidate()
                    : null;
                if (!geetestValid) {
                    if (window.geetestCaptchaObj) {
                        setSubmitLoading(true);
                        showSubmitTip('请完成人机验证', '');
                        try { window.geetestCaptchaObj.showCaptcha(); } catch (e) {
                            setSubmitLoading(false);
                            showSubmitTip('人机验证加载中，请稍后重试', 'error');
                        }
                    } else {
                        showSubmitTip('人机验证正在加载，请稍后重试', 'error');
                    }
                    return;
                }
                // 将极验验证结果写入隐藏字段，随表单一起提交
                var geetestFields = ['lot_number', 'captcha_output', 'pass_token', 'gen_time'];
                for (var gi = 0; gi < geetestFields.length; gi++) {
                    var gInput = commentForm.querySelector('input[name="geetest_' + geetestFields[gi] + '"]');
                    if (!gInput) {
                        gInput = document.createElement('input');
                        gInput.type = 'hidden';
                        gInput.name = 'geetest_' + geetestFields[gi];
                        commentForm.appendChild(gInput);
                    }
                    gInput.value = geetestValid[geetestFields[gi]] || '';
                }
            }

            // Cat-Captcha：未验证则弹出验证码，验证成功后会重新触发 submit
            var catcaptchaContainer = document.getElementById('catcaptcha-box');
            if (catcaptchaContainer) {
                var catTicket = (window.catCaptchaBox && typeof window.catCaptchaBox.getTicket === 'function')
                    ? window.catCaptchaBox.getTicket()
                    : '';
                if (!catTicket) {
                    if (window.catCaptchaBox) {
                        setSubmitLoading(true);
                        showSubmitTip('请完成人机验证', '');
                        try { window.catCaptchaBox.open(); } catch (e) {
                            setSubmitLoading(false);
                            showSubmitTip('人机验证加载中，请稍后重试', 'error');
                        }
                    } else {
                        showSubmitTip('人机验证正在加载，请稍后重试', 'error');
                    }
                    return;
                }
                // 将票据写入隐藏字段，随表单一起提交
                var catInput = commentForm.querySelector('input[name="catcaptcha_ticket"]');
                if (!catInput) {
                    catInput = document.createElement('input');
                    catInput.type = 'hidden';
                    catInput.name = 'catcaptcha_ticket';
                    commentForm.appendChild(catInput);
                }
                catInput.value = catTicket;
            }

            var tokenInputs = commentForm.querySelectorAll('input[name="_"]');
            for (var i = 0; i < tokenInputs.length; i++) {
                tokenInputs[i].parentNode.removeChild(tokenInputs[i]);
            }

            var token = commentForm.getAttribute('data-token');
            if (token) {
                var tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_';
                tokenInput.value = token;
                commentForm.appendChild(tokenInput);
            }

            var formData = new FormData(commentForm);

            setSubmitLoading(true);
            showSubmitTip('正在提交...', '');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', commentForm.getAttribute('action'), true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                setSubmitLoading(false);

                if (xhr.status >= 200 && xhr.status < 300) {
                    var responseUrl = xhr.responseURL || '';
                    var currentUrl = window.location.href.split('#')[0];

                    if (responseUrl && responseUrl !== currentUrl) {
                        onCommentSuccess(commentForm, textarea);
                    } else {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(xhr.responseText, 'text/html');
                        var newComments = doc.querySelector('#comments');
                        var currentComments = document.querySelector('#comments');
                        var hasError = false;

                        if (newComments && currentComments) {
                            var errorMsg = doc.querySelector('.message.error, .error-message, .alert-error');
                            if (errorMsg) {
                                hasError = true;
                                showSubmitTip(errorMsg.textContent.trim() || '评论提交失败', 'error');
                                initCaptcha();
                                resetGeetest();
                                resetCatCaptcha();
                            }
                        }

                        if (!hasError) {
                            onCommentSuccess(commentForm, textarea);
                        }
                    }
                } else if (xhr.status === 403) {
                    showSubmitTip('评论被拒绝，请刷新页面后重试', 'error');
                    initCaptcha();
                    resetGeetest();
                    resetCatCaptcha();
                } else {
                    var errorMsg = '提交失败，请稍后重试';
                    try {
                        var errParser = new DOMParser();
                        var doc = errParser.parseFromString(xhr.responseText, 'text/html');
                        var errorEl = doc.querySelector('.message.error, .error-message, .alert-error, .error-content, h2');
                        if (errorEl) {
                            var msg = errorEl.textContent.trim();
                            if (msg) errorMsg = msg;
                        }
                    } catch(e) {}
                    showSubmitTip(errorMsg, 'error');
                    initCaptcha();
                    resetGeetest();
                    resetCatCaptcha();
                }
            };

            xhr.onerror = function() {
                setSubmitLoading(false);
                showSubmitTip('网络错误，请检查网络连接', 'error');
            };

            xhr.timeout = 15000;
            xhr.ontimeout = function() {
                setSubmitLoading(false);
                showSubmitTip('请求超时，请稍后重试', 'error');
            };

            xhr.send(formData);
        });
    }

    function onCommentSuccess(form, textarea) {
        showSubmitTip('评论提交成功！', 'success');

        // 提交成功后重置 Cat-Captcha 触发框（一次性票据已使用，需重新验证）
        resetCatCaptcha();

        if (textarea) {
            textarea.value = '';
        }

        // 清空验证码输入
        var captchaInput = document.getElementById('captcha-code');
        if (captchaInput) {
            captchaInput.value = '';
        }

        var parentInput = form.querySelector('input[name="parent"]');
        var parentId = parentInput ? parentInput.value : '';

        var currentUrl = window.location.href.split('#')[0];
        // 加时间戳缓存破坏参数，避免命中旧缓存导致看不到刚提交的新评论
        var refreshUrl = currentUrl + (currentUrl.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now();
        var commentAnchor = '#comments';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', refreshUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(xhr.responseText, 'text/html');
                var newComments = doc.querySelector('#comments');
                var currentComments = document.querySelector('#comments');
                if (newComments && currentComments) {
                    currentComments.innerHTML = newComments.innerHTML;
                    initAjaxComment();
                    initTurnstile();
                    initCaptcha();
                    initGeetest();
                    initCatCaptcha();
                    // 重新绑定新评论的点赞按钮和排序控件
                    if (typeof window.initCommentLike === 'function') {
                        window.initCommentLike();
                    }
                    if (typeof window.initCommentSort === 'function') {
                        window.initCommentSort();
                    }
                    // 重新加载新评论的 IP 归属地
                    if (typeof window.initCommentIpRegions === 'function') {
                        window.initCommentIpRegions();
                    }
                    // 重新渲染评论区中的扩展内容
                    if (typeof window.initMermaid === 'function') {
                        window.initMermaid();
                    }
                    if (typeof window.initECharts === 'function') {
                        window.initECharts();
                    }
                    if (typeof window.initKaTeX === 'function') {
                        window.initKaTeX();
                    }
                    if (typeof window.initEmojiPanel === 'function') {
                        window.initEmojiPanel();
                    }
                    var targetEl = document.querySelector(commentAnchor);
                    if (targetEl) {
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
        };
        xhr.send();
    }

    function initTurnstile() {
        var turnstileContainer = document.getElementById('cf-turnstile');
        if (!turnstileContainer) return;

        if (turnstileContainer.querySelector('iframe')) {
            return;
        }

        if (turnstileContainer.getAttribute('data-turnstile-rendered')) {
            var widgetId = turnstileContainer.getAttribute('data-turnstile-widget-id');
            if (widgetId && typeof window.turnstile !== 'undefined') {
                window.turnstile.reset(widgetId);
                return;
            }
        }

        if (window.turnstileIsRendering) return;
        window.turnstileIsRendering = true;

        var checkTurnstile = setInterval(function() {
            if (typeof window.turnstile !== 'undefined') {
                clearInterval(checkTurnstile);

                if (turnstileContainer.querySelector('iframe')) {
                    window.turnstileIsRendering = false;
                    return;
                }

                try {
                    var widgetId = window.turnstile.render('#cf-turnstile');
                    if (widgetId) {
                        turnstileContainer.setAttribute('data-turnstile-rendered', 'true');
                        turnstileContainer.setAttribute('data-turnstile-widget-id', widgetId);
                    }
                } catch (e) {
                }
                window.turnstileIsRendering = false;
            }
        }, 50);

        setTimeout(function() {
            if (window.turnstileIsRendering) {
                clearInterval(checkTurnstile);
                window.turnstileIsRendering = false;
            }
        }, 3000);
    }

    function initCaptcha() {
        var captchaImg = document.getElementById('captcha-img');
        if (!captchaImg) return;
        // 刷新验证码图片
        var src = captchaImg.src;
        if (src.indexOf('t=') !== -1) {
            src = src.replace(/t=\d+/, 't=' + Date.now());
        } else {
            src += (src.indexOf('?') !== -1 ? '&' : '?') + 't=' + Date.now();
        }
        captchaImg.src = src;
        // 清空输入框
        var captchaInput = document.getElementById('captcha-code');
        if (captchaInput) {
            captchaInput.value = '';
        }
    }

    // ===== 极验 Geetest v4 =====
    window.geetestCaptchaObj = null;
    window.geetestResult = null;
    var geetestInitTimer = null;

    // ===== Cat-Captcha（触发框模式）=====
    window.catCaptchaBox = null;
    window.catCaptchaTicket = null;
    var catCaptchaInitTimer = null;

    function initGeetest() {
        var geetestContainer = document.getElementById('geetest-captcha');
        if (!geetestContainer) return;
        // 已初始化过则跳过（避免重复绑定）
        if (geetestContainer.getAttribute('data-geetest-init')) return;

        var captchaId = geetestContainer.getAttribute('data-captcha-id');
        if (!captchaId) return;

        // SDK 尚未加载完成，稍后重试
        if (typeof window.initGeetest4 === 'undefined') {
            if (geetestInitTimer) clearTimeout(geetestInitTimer);
            geetestInitTimer = setTimeout(initGeetest, 200);
            return;
        }

        // 销毁旧实例（评论区重新渲染后旧实例已失效）
        if (window.geetestCaptchaObj) {
            try { window.geetestCaptchaObj.destroy(); } catch (e) {}
            window.geetestCaptchaObj = null;
        }
        window.geetestResult = null;

        geetestContainer.setAttribute('data-geetest-init', 'true');

        try {
            window.initGeetest4({
                captchaId: captchaId,
                product: 'bind'
            }, function (captcha) {
                captcha.onReady(function () {});
                captcha.onSuccess(function () {
                    var result = captcha.getValidate();
                    if (result) {
                        window.geetestResult = result;
                        // 验证成功后重新触发表单提交
                        var form = document.getElementById('comment-form');
                        if (form) {
                            form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
                        }
                    }
                });
                captcha.onError(function () {
                    window.geetestResult = null;
                    setSubmitLoading(false);
                    showSubmitTip('人机验证失败，请重试', 'error');
                });
                captcha.onClose(function () {
                    setSubmitLoading(false);
                    showSubmitTip('请完成人机验证', '');
                });
                window.geetestCaptchaObj = captcha;
            });
        } catch (e) {
            window.geetestResult = null;
        }
    }

    function resetGeetest() {
        window.geetestResult = null;
        if (window.geetestCaptchaObj) {
            try { window.geetestCaptchaObj.reset(); } catch (e) {}
        }
        // 清空隐藏字段，避免残留失效的验证结果
        var form = document.getElementById('comment-form');
        if (form) {
            var fields = ['lot_number', 'captcha_output', 'pass_token', 'gen_time'];
            for (var i = 0; i < fields.length; i++) {
                var input = form.querySelector('input[name="geetest_' + fields[i] + '"]');
                if (input) input.value = '';
            }
        }
    }

    // ===== Cat-Captcha =====
    function initCatCaptcha() {
        var catcaptchaContainer = document.getElementById('catcaptcha-box');
        if (!catcaptchaContainer) return;
        // 已初始化过则跳过（避免重复绑定）
        if (catcaptchaContainer.getAttribute('data-catcaptcha-init')) return;

        var catApiBase = catcaptchaContainer.getAttribute('data-api-base') || 'https://captcha.lwcat.cn';
        var catSiteKey = catcaptchaContainer.getAttribute('data-site-key') || '';
        var catAction = catcaptchaContainer.getAttribute('data-action') || 'comment';
        if (!catSiteKey) return;

        // SDK 尚未加载完成，稍后重试
        if (typeof window.LwCaptcha === 'undefined') {
            if (catCaptchaInitTimer) clearTimeout(catCaptchaInitTimer);
            catCaptchaInitTimer = setTimeout(initCatCaptcha, 200);
            return;
        }

        // 销毁旧实例（评论区重新渲染后旧实例已失效）
        if (window.catCaptchaBox) {
            try { window.catCaptchaBox.destroy(); } catch (e) {}
            window.catCaptchaBox = null;
        }
        window.catCaptchaTicket = null;

        catcaptchaContainer.setAttribute('data-catcaptcha-init', 'true');

        try {
            window.catCaptchaBox = window.LwCaptcha.trigger({
                el: '#catcaptcha-box',
                type: 'auto',
                algVersion: 1,
                apiBase: catApiBase,
                siteKey: catSiteKey,
                action: catAction,
                onSuccess: function(ticket) {
                    window.catCaptchaTicket = ticket;
                    var ticketInput = document.getElementById('catcaptcha-ticket');
                    if (ticketInput) ticketInput.value = ticket;
                    // 验证成功后重新触发表单提交
                    var form = document.getElementById('comment-form');
                    if (form) {
                        form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
                    }
                },
                onFail: function(info) {
                    window.catCaptchaTicket = null;
                    setSubmitLoading(false);
                    showSubmitTip((info && info.message) || '人机验证失败，请重试', 'error');
                },
                onClose: function() {
                    setSubmitLoading(false);
                    showSubmitTip('请完成人机验证', '');
                }
            });
            // 移除触发框按钮，仅由提交按钮触发验证弹窗
            var pjaxTriggerBtn = catcaptchaContainer.querySelector('.lwcap-trigger');
            if (pjaxTriggerBtn && pjaxTriggerBtn.parentNode) {
                pjaxTriggerBtn.parentNode.removeChild(pjaxTriggerBtn);
            }
        } catch (e) {
            window.catCaptchaTicket = null;
        }
    }

    function resetCatCaptcha() {
        window.catCaptchaTicket = null;
        if (window.catCaptchaBox) {
            try { window.catCaptchaBox.reset(); } catch (e) {}
        }
        // 清空隐藏字段，避免残留失效的票据
        var ticketInput = document.getElementById('catcaptcha-ticket');
        if (ticketInput) ticketInput.value = '';
    }

    function initPjax() {
        if (typeof Pjax === 'undefined') {
            return;
        }

        var pjax = new Pjax({
            elements: 'a:not(' + excludeSelectors.join(', ') + ')',
            selectors: [
                '#header',
                '#left-sidebar',
                '#main',
                '#secondary',
                '#footer',
                'title'
            ],
            cacheBust: false,
            timeout: pjaxTimeout,
            debug: false
        });

        // 暴露 pjax 实例，供动态生成的链接（如收藏列表）手动触发 pjax 加载
        window.shufeiPjax = pjax;

        initAjaxComment();

        return pjax;
    }
    
    document.addEventListener('pjax:send', function() {
        showLoading();
        updateProgress(50);
    });
    
    document.addEventListener('pjax:success', function() {
        updateProgress(80);
    });
    
    document.addEventListener('pjax:complete', function() {
        updateProgress(100);
        hideLoading();
        // 切换前先清理 ECharts 实例，防止内存泄漏
        if (typeof window.destroyECharts === 'function') {
            window.destroyECharts();
        }
        // 清理文章目录滚动监听
        if (typeof window.destroyTableOfContents === 'function') {
            window.destroyTableOfContents();
        }
        window.reinitPageFunctions();
    });
    
    document.addEventListener('pjax:error', function(e) {
        // MoOx/pjax 0.2.8 不触发 pjax:timeout，超时走 pjax:error 路径
        // e.requestedUrl 在该库中不存在，用 e.triggerElement.href 获取目标 URL
        var targetUrl = null;
        if (e.triggerElement && e.triggerElement.href) {
            targetUrl = e.triggerElement.href;
        } else if (e.request && e.request.responseURL) {
            targetUrl = e.request.responseURL;
        }
        if (targetUrl) {
            window.location.href = targetUrl;
        } else {
            window.location.reload();
        }
    });
    
    createLoadingIndicator();
    initPjax();
    
    window.reinitPageFunctions = function() {
        if (window.reinitTimer) clearTimeout(window.reinitTimer);

        window.reinitTimer = setTimeout(function() {
            // 收起悬浮操作组（避免 pjax 切换后仍处于展开状态）
            var floatActions = document.getElementById('float-actions');
            if (floatActions) {
                floatActions.classList.remove('expanded');
                floatActions.classList.remove('show');
            }

            // 清理浮动收藏按钮（在 #footer 外，pjax 不会替换，需手动重置）
            // 用克隆节点替换彻底清除旧的 click 监听器，避免 pjax 切换后
            // 旧监听器仍用旧 cid 触发收藏，导致收藏错乱
            var floatFavBtn = document.getElementById('float-fav-btn');
            if (floatFavBtn) {
                var newFloatBtn = floatFavBtn.cloneNode(true);
                newFloatBtn.classList.remove('show', 'favorited', 'has-toc');
                newFloatBtn.style.display = 'none';
                newFloatBtn.removeAttribute('data-float-fav-bound');
                var fIcon = newFloatBtn.querySelector('i');
                if (fIcon) fIcon.className = 'fa fa-heart-o';
                floatFavBtn.parentNode.replaceChild(newFloatBtn, floatFavBtn);
                // 清掉 article 上的初始化标记，让 initPostReadingFav 重新绑定并显示按钮
                var rfArticle = document.querySelector('article.post-single');
                if (rfArticle) rfArticle.removeAttribute('data-rf-bound');
                window._rfContext = null;
            }

            // AI 摘要悬浮弹球同样在 #footer 外，pjax 不会替换，需重置 cid 并清理弹窗
            var aiBall = document.getElementById('ai-summary-ball');
            var curArticle = document.querySelector('article.post-single');
            var aiBox = document.getElementById('ai-summary-box');
            var oldAiPopup = document.querySelector('.ai-summary-popup');
            if (oldAiPopup && oldAiPopup.parentNode) {
                oldAiPopup.parentNode.removeChild(oldAiPopup);
            }
            if (aiBall) {
                // 仅当新文章已开启 AI 摘要时显示悬浮球
                var aiEnabled = curArticle && curArticle.getAttribute('data-ai-summary') === '1';
                if (aiEnabled) {
                    aiBall.setAttribute('data-cid', curArticle.getAttribute('data-cid') || aiBall.getAttribute('data-cid'));
                    aiBall.style.display = '';
                    aiBall.classList.remove('has-toc');
                } else {
                    aiBall.style.display = 'none';
                }
                // 重新绑定，避免残留旧事件监听
                aiBall.removeAttribute('data-ai-summary-bound');
                if (window.__aiSummaryTocObserver) {
                    window.__aiSummaryTocObserver.disconnect();
                    window.__aiSummaryTocObserver = null;
                }
            }

            if (typeof window.initDarkMode === 'function') {
                window.initDarkMode();
            }
            
            if (typeof window.initMobileMenu === 'function') {
                window.initMobileMenu();
            }

            if (typeof window.initCollapsibleSidebar === 'function') {
                window.initCollapsibleSidebar();
            }

            if (typeof window.initCategoryCollapse === 'function') {
                window.initCategoryCollapse();
            }
            
            if (typeof window.initPrismHighlight === 'function') {
                window.initPrismHighlight();
            }
            
            if (typeof window.initCopyButtons === 'function') {
                window.initCopyButtons();
            }
            
            if (typeof window.initLightbox === 'function') {
                window.initLightbox();
            }
            
            initAjaxComment();
            initTurnstile();
            initGeetest();
            initCatCaptcha();

            // 阅读进度 & 收藏（pjax 切换页面后重新初始化）
            if (typeof window.initPostReadingFav === 'function') {
                window.initPostReadingFav();
            }
            if (typeof window.initListReadingMarks === 'function') {
                window.initListReadingMarks();
            }
            if (typeof window.initFavDropdown === 'function') {
                window.initFavDropdown();
            }

            if (typeof window.initSearchToggle === 'function') {
                window.initSearchToggle();
            }

            if (typeof window.initPostLike === 'function') {
                window.initPostLike();
            }

            if (typeof window.initPasswordForms === 'function') {
                window.initPasswordForms();
            }

            if (typeof window.initPostVote === 'function') {
                window.initPostVote();
            }

            if (typeof window.initPostViews === 'function') {
                window.initPostViews();
            }

            if (typeof window.initPostThumbFallback === 'function') {
                window.initPostThumbFallback();
            }

            if (typeof window.initCommentLike === 'function') {
                window.initCommentLike();
            }

            if (typeof window.initCommentSort === 'function') {
                window.initCommentSort();
            }

            if (typeof window.initCommentIpRegions === 'function') {
                window.initCommentIpRegions();
            }

            if (typeof window.initMermaid === 'function') {
                window.initMermaid();
            }

            if (typeof window.initECharts === 'function') {
                window.initECharts();
            }

            if (typeof window.initKaTeX === 'function') {
                window.initKaTeX();
            }

            if (typeof window.initMarkdownExt === 'function') {
                window.initMarkdownExt();
            }

            if (typeof window.initTableOfContents === 'function') {
                window.initTableOfContents();
            }

            if (typeof window.initArticleAlert === 'function') {
                window.initArticleAlert();
            }

            if (typeof window.initAiSummary === 'function') {
                window.initAiSummary();
            }

            if (typeof window.initEmojiPanel === 'function') {
                window.initEmojiPanel();
            }

            if (typeof window.initVideoPlayer === 'function') {
                window.initVideoPlayer();
            }

            if (typeof window.initMusicPlayer === 'function') {
                window.initMusicPlayer();
            }

            if (typeof window.initQuoteComment === 'function') {
                window.initQuoteComment();
            }

            if (typeof window.initWeather === 'function') {
                window.initWeather();
            }
        }, 50);
    };
    
    if (document.readyState === 'complete') {
        window.reinitPageFunctions();
    } else {
        window.addEventListener('load', function() {
            window.reinitPageFunctions();
        });
    }
})();
