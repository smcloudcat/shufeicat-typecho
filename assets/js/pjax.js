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
        container.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; height: 4px; z-index: 99999; display: none; overflow: hidden;';
        
        var bar = document.createElement('div');
        bar.className = 'pjax-progress-bar';
        bar.style.cssText = 'height: 100%; width: 0%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s ease; position: absolute; left: 0; top: 0;';
        
        container.appendChild(bar);
        return container;
    }
    
    function createCircleStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-circle';
        container.className = 'pjax-loading pjax-circle';
        container.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 99999; display: none;';
        
        var spinner = document.createElement('div');
        spinner.className = 'pjax-circle-spinner';
        spinner.style.cssText = 'width: 50px; height: 50px; border: 3px solid rgba(102, 126, 234, 0.2); border-top-color: #667eea; border-radius: 50%; animation: pjax-spin 0.8s linear infinite;';
        
        var text = document.createElement('div');
        text.className = 'pjax-circle-text';
        text.textContent = '加载中...';
        text.style.cssText = 'text-align: center; margin-top: 15px; font-size: 14px; color: #667eea; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';
        
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'background: rgba(255, 255, 255, 0.95); padding: 30px 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);';
        
        wrapper.appendChild(spinner);
        wrapper.appendChild(text);
        container.appendChild(wrapper);
        
        return container;
    }
    
    function createDotsStyle() {
        var container = document.createElement('div');
        container.id = 'pjax-loading-dots';
        container.className = 'pjax-loading pjax-dots';
        container.style.cssText = 'position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 99999; display: none;';
        
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'background: rgba(255, 255, 255, 0.95); padding: 15px 25px; border-radius: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px;';
        
        var text = document.createElement('span');
        text.textContent = '加载中';
        text.style.cssText = 'font-size: 14px; color: #666; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin-right: 5px;';
        
        var dots = document.createElement('div');
        dots.style.cssText = 'display: flex; gap: 6px;';
        
        for (var i = 0; i < 3; i++) {
            var dot = document.createElement('span');
            dot.style.cssText = 'width: 8px; height: 8px; background: #667eea; border-radius: 50%; animation: pjax-pulse 1.4s ease-in-out infinite; animation-delay: ' + (i * 0.2) + 's;';
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
    
    function addAnimationStyles() {
        if (document.getElementById('pjax-animation-styles')) return;
        
        var style = document.createElement('style');
        style.id = 'pjax-animation-styles';
        style.textContent = '@keyframes pjax-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } @keyframes pjax-pulse { 0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }';
        document.head.appendChild(style);
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
        var icon = btn.querySelector('i');
        if (loading) {
            if (icon) {
                icon.className = 'fa fa-spinner fa-spin';
            }
            btn.setAttribute('data-original-text', btn.innerHTML);
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
        }
    }

    function initAjaxComment() {
        var commentForm = document.getElementById('comment-form');
        if (!commentForm) return;

        var clonedForm = commentForm.cloneNode(true);
        commentForm.parentNode.replaceChild(clonedForm, commentForm);

        clonedForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var textarea = clonedForm.querySelector('#textarea');
            if (!textarea || !textarea.value.trim()) {
                showSubmitTip('请填写评论内容', 'error');
                return;
            }

            var author = clonedForm.querySelector('#author');
            if (author && author.required && !author.value.trim()) {
                showSubmitTip('请填写称呼', 'error');
                return;
            }

            var mail = clonedForm.querySelector('#mail');
            if (mail && mail.required && !mail.value.trim()) {
                showSubmitTip('请填写邮箱', 'error');
                return;
            }

            var tokenInputs = clonedForm.querySelectorAll('input[name="_"]');
            for (var i = 0; i < tokenInputs.length; i++) {
                tokenInputs[i].parentNode.removeChild(tokenInputs[i]);
            }

            var token = clonedForm.getAttribute('data-token');
            if (token) {
                var tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_';
                tokenInput.value = token;
                clonedForm.appendChild(tokenInput);
            }

            var formData = new FormData(clonedForm);

            setSubmitLoading(true);
            showSubmitTip('正在提交...', '');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', clonedForm.getAttribute('action'), true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                setSubmitLoading(false);

                if (xhr.status >= 200 && xhr.status < 300) {
                    var responseUrl = xhr.responseURL || '';
                    var currentUrl = window.location.href.split('#')[0];

                    if (responseUrl && responseUrl !== currentUrl) {
                        onCommentSuccess(clonedForm, textarea);
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
                            }
                        }

                        if (!hasError) {
                            onCommentSuccess(clonedForm, textarea);
                        }
                    }
                } else if (xhr.status === 403) {
                    showSubmitTip('评论被拒绝，请刷新页面后重试', 'error');
                } else {
                    showSubmitTip('提交失败，请稍后重试 (错误: ' + xhr.status + ')', 'error');
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
                    console.warn('[Turnstile] 渲染出错:', e);
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

    function initPjax() {
        if (typeof Pjax === 'undefined') {
            console.error('Pjax库未加载');
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
        
        console.log('Pjax已初始化 [V1.0.3]，样式：' + pjaxLoadStyle);
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
        console.log('pjax:complete 事件触发 [V1.0.3]');
        // 切换前先清理 ECharts 实例，防止内存泄漏
        if (typeof window.destroyECharts === 'function') {
            window.destroyECharts();
        }
        window.reinitPageFunctions();
    });
    
    document.addEventListener('pjax:timeout', function(e) {
        console.warn('Pjax加载超时');
        e.continue();
    });
    
    document.addEventListener('pjax:error', function(e) {
        console.error('Pjax加载失败:', e);
        if (e.requestedUrl) {
            window.location.href = e.requestedUrl;
        } else {
            window.location.reload();
        }
    });
    
    createLoadingIndicator();
    addAnimationStyles();
    initPjax();
    
    window.reinitPageFunctions = function() {
        if (window.reinitTimer) clearTimeout(window.reinitTimer);
        
        window.reinitTimer = setTimeout(function() {
            console.log('开始重新初始化页面功能 [V1.0.3]...');
            
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
        }, 50);
    };
    
    if (document.readyState === 'complete') {
        console.log('页面已加载完成 [V1.0.3]，执行初始化');
        window.reinitPageFunctions();
    } else {
        window.addEventListener('load', function() {
            console.log('页面load事件触发 [V1.0.3]，执行初始化');
            window.reinitPageFunctions();
        });
    }
})();
