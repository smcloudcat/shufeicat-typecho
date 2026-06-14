/**
 * Pjax加载功能
 * 用于全站无刷新页面切换，提升用户体验
 * 支持三种加载动画样式：progress、circle、dots
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
    
    function createLoadingIndicator() {
        if (pjaxLoadStyle === 'circle') {
            loadingContainer = createCircleStyle();
        } else if (pjaxLoadStyle === 'dots') {
            loadingContainer = createDotsStyle();
        } else {
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
        'form',
        '.no-pjax',
        'a[href*="admin"]',
        'a[href*="login"]',
        'a[href*="logout"]',
        'a[href*="feed"]',
        '.password-protection a',
        'a[href*="password"]'
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
                            }
                        }

                        if (!hasError) {
                            onCommentSuccess(commentForm, textarea);
                        }
                    }
                } else if (xhr.status === 403) {
                    showSubmitTip('评论被拒绝，请刷新页面后重试', 'error');
                    initCaptcha();
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
        var commentAnchor = '#comments';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', currentUrl, true);
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
                    if (typeof window.initKaomojiPanel === 'function') {
                        window.initKaomojiPanel();
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
        window.reinitPageFunctions();
    });
    
    document.addEventListener('pjax:timeout', function(e) {
        e.continue();
    });
    
    document.addEventListener('pjax:error', function(e) {
        if (e.requestedUrl) {
            window.location.href = e.requestedUrl;
        } else {
            window.location.reload();
        }
    });
    
    createLoadingIndicator();
    initPjax();
    
    window.reinitPageFunctions = function() {
        if (window.reinitTimer) clearTimeout(window.reinitTimer);
        
        window.reinitTimer = setTimeout(function() {
            if (typeof window.initDarkMode === 'function') {
                window.initDarkMode();
            }
            
            if (typeof window.initMobileMenu === 'function') {
                window.initMobileMenu();
            }

            if (typeof window.initCollapsibleSidebar === 'function') {
                window.initCollapsibleSidebar();
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

            if (typeof window.initPostLike === 'function') {
                window.initPostLike();
            }

            if (typeof window.initPostViews === 'function') {
                window.initPostViews();
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

            if (typeof window.initArticleAlert === 'function') {
                window.initArticleAlert();
            }

            if (typeof window.initKaomojiPanel === 'function') {
                window.initKaomojiPanel();
            }

            if (typeof window.initVideoPlayer === 'function') {
                window.initVideoPlayer();
            }

            if (typeof window.initMusicPlayer === 'function') {
                window.initMusicPlayer();
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
