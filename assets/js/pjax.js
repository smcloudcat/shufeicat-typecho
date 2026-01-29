/**
 * Pjax加载功能
 * 用于全站无刷新页面切换，提升用户体验
 * 支持三种加载动画样式：progress、circle、dots
 */

(function() {
    // Turnstile 渲染锁，防止重复渲染
    var turnstileRendering = false;
    // 检查是否开启了pjax加载
    var pjaxEnabled = window.pjaxEnabled || false;
    var pjaxLoadStyle = window.pjaxLoadStyle || 'progress';
    var pjaxTimeout = window.pjaxTimeout || 10000;
    
    if (!pjaxEnabled) return;
    
    // 加载动画容器
    var loadingContainer = null;
    
    // 样式1：顶部进度条
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
    
    // 样式2：圆形旋转器
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
    
    // 样式3：底部圆点脉冲
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
    
    // 创建加载动画容器
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
    
    // 添加动画样式
    function addAnimationStyles() {
        if (document.getElementById('pjax-animation-styles')) return;
        
        var style = document.createElement('style');
        style.id = 'pjax-animation-styles';
        style.textContent = '@keyframes pjax-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } @keyframes pjax-pulse { 0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }';
        document.head.appendChild(style);
    }
    
    // 显示加载动画
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
    
    // 更新加载进度
    function updateProgress(percent) {
        if (pjaxLoadStyle === 'progress') {
            var bar = loadingContainer.querySelector('.pjax-progress-bar');
            if (bar) {
                bar.style.width = percent + '%';
            }
        }
    }
    
    // 隐藏加载动画
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
    
    // 需要排除的链接选择器
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
    
    // 初始化Pjax
    function initPjax() {
        if (typeof Pjax === 'undefined') {
            console.error('Pjax库未加载');
            return;
        }
        
        var pjax = new Pjax({
            elements: 'a:not(' + excludeSelectors.join(', ') + ')',
            selectors: [
                '#header',
                '#main',
                '#secondary',
                '#footer',
                'title'
            ],
            cacheBust: false,
            timeout: pjaxTimeout,
            debug: false
        });
        
        console.log('Pjax已初始化，样式：' + pjaxLoadStyle);
        return pjax;
    }
    
    // 页面加载前显示加载动画
    document.addEventListener('pjax:send', function() {
        showLoading();
        updateProgress(50);
    });
    
    // 页面加载成功后更新内容
    document.addEventListener('pjax:success', function() {
        updateProgress(80);
    });
    
    // 页面加载完成（无论成功或失败）
    document.addEventListener('pjax:complete', function() {
        updateProgress(100);
        hideLoading();
    });
    
    // 页面加载超时
    document.addEventListener('pjax:timeout', function(e) {
        console.warn('Pjax加载超时');
        e.continue();
    });
    
    // 页面加载出错
    document.addEventListener('pjax:error', function(e) {
        console.error('Pjax加载失败:', e);
        if (e.requestedUrl) {
            window.location.href = e.requestedUrl;
        } else {
            // 如果无法获取URL，刷新当前页面
            window.location.reload();
        }
    });
    
    // 初始化
    createLoadingIndicator();
    addAnimationStyles();
    initPjax();
    
    // 监听页面切换完成 - 使用多个事件确保触发
    document.addEventListener('pjax:complete', function() {
        console.log('pjax:complete 事件触发');
        window.reinitPageFunctions();
    });
    
    document.addEventListener('pjax:success', function() {
        console.log('pjax:success 事件触发');
        window.reinitPageFunctions();
    });
    
    // 重新初始化页面功能的函数
    window.reinitPageFunctions = function() {
        console.log('开始重新初始化页面功能...');
        
        // 调用全局函数初始化移动端菜单
        if (typeof window.initMobileMenu === 'function') {
            console.log('调用window.initMobileMenu');
            setTimeout(window.initMobileMenu, 100);
        } else {
            console.warn('window.initMobileMenu 函数不存在');
        }
        
        // 调用全局函数初始化代码高亮
        if (typeof window.initPrismHighlight === 'function') {
            console.log('调用window.initPrismHighlight');
            setTimeout(window.initPrismHighlight, 150);
        } else {
            console.warn('window.initPrismHighlight 函数不存在');
        }
        
        // 调用全局函数初始化代码复制按钮
        if (typeof window.initCopyButtons === 'function') {
            console.log('调用window.initCopyButtons');
            setTimeout(window.initCopyButtons, 200);
        } else {
            console.warn('window.initCopyButtons 函数不存在');
        }
        
        // 调用全局函数初始化图片灯箱
        if (typeof window.initLightbox === 'function') {
            console.log('调用window.initLightbox');
            setTimeout(window.initLightbox, 250);
        } else {
            console.warn('window.initLightbox 函数不存在');
        }
        
        // 重新初始化 Turnstile 人机验证
        // 由于使用了 render=explicit 模式，需要手动渲染
        var turnstileContainer = document.getElementById('cf-turnstile');
        if (turnstileContainer && !turnstileRendering) {
            // 检查是否已经渲染过（通过检查容器内是否有 iframe）
            if (turnstileContainer.querySelector('iframe')) {
                console.log('Turnstile widget 已存在，跳过重新渲染');
                return;
            }
            
            // 设置渲染锁，防止重复渲染
            turnstileRendering = true;
            
            // 清空容器内容
            turnstileContainer.innerHTML = '';
            
            // 等待 Turnstile 脚本加载完成
            var checkTurnstile = setInterval(function() {
                if (typeof window.turnstile !== 'undefined') {
                    clearInterval(checkTurnstile);
                    
                    // 再次检查，防止在等待期间已经被渲染
                    if (turnstileContainer.querySelector('iframe')) {
                        console.log('Turnstile widget 已存在，跳过重新渲染');
                        turnstileRendering = false;
                        return;
                    }
                    
                    console.log('手动渲染 Turnstile widget');
                    try {
                        window.turnstile.render('#cf-turnstile');
                    } catch (e) {
                        console.warn('Turnstile 渲染失败:', e);
                    }
                    turnstileRendering = false;
                }
            }, 100);
            
            // 超时保护，最多等待 5 秒
            setTimeout(function() {
                clearInterval(checkTurnstile);
                turnstileRendering = false;
            }, 5000);
        }
    };
    
    // 初始页面加载完成后执行初始化（移除pjax:ready事件监听，因为不是标准Pjax事件）
    if (document.readyState === 'complete') {
        console.log('页面已加载完成，执行初始化');
        window.reinitPageFunctions();
    } else {
        window.addEventListener('load', function() {
            console.log('页面load事件触发，执行初始化');
            window.reinitPageFunctions();
        });
    }
})();