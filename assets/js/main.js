/**
 * ShuFeiCat 主题主脚本
 * 包含返回顶部、移动端菜单、夜间模式、代码高亮、代码复制、Ajax加载等功能
 */

/**
 * 轻量级 Toast 通知
 * 替代 alert()，提供一致的用户提示体验
 */
window.showToast = function(message, type) {
    type = type || 'info';
    var existing = document.getElementById('shufei-toast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.id = 'shufei-toast';
    toast.className = 'shufei-toast shufei-toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);

    // 触发动画
    requestAnimationFrame(function() {
        toast.classList.add('shufei-toast-show');
    });

    setTimeout(function() {
        toast.classList.remove('shufei-toast-show');
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
};

/**
 * 工具函数：检查依赖库是否已加载，未加载则重试
 * @param {string} libName - window 上的库名称
 * @param {function} retryFn - 重试时调用的函数
 * @param {number} retryCount - 当前重试次数
 * @param {number} maxRetry - 最大重试次数
 * @returns {boolean} true 表示库未就绪，false 表示库已可用
 */
function checkLib(libName, retryFn, retryCount, maxRetry) {
    maxRetry = maxRetry || 30;
    if (typeof window[libName] === 'undefined') {
        if (retryCount < maxRetry) {
            setTimeout(retryFn, 100);
        }
        return true;
    }
    return false;
}

/**
 * 动态加载脚本（仅加载一次，支持回调队列）
 * - 已加载完成：立即回调
 * - 加载中：排队等待回调
 * - 加载失败：记录状态并触发已排队回调，不再重试（避免无限循环）
 * @param {string} src - 脚本URL
 * @param {function} [callback] - 加载完成回调（成功/失败均触发）
 */
window.loadScriptOnce = function(src, callback) {
    if (!window._loadedScripts) window._loadedScripts = {};
    var state = window._loadedScripts[src];
    if (state === 'loaded') {
        if (callback) { try { callback(); } catch (e) {} }
        return;
    }
    if (state === 'loading') {
        if (callback) {
            (window._loadedScripts[src + '__cbs'] = window._loadedScripts[src + '__cbs'] || []).push(callback);
        }
        return;
    }
    if (state === 'error') {
        // 加载失败后不再重试，避免循环
        return;
    }
    // 开始加载
    window._loadedScripts[src] = 'loading';
    window._loadedScripts[src + '__cbs'] = callback ? [callback] : [];
    var script = document.createElement('script');
    script.src = src;
    script.onload = function() {
        window._loadedScripts[src] = 'loaded';
        var cbs = window._loadedScripts[src + '__cbs'] || [];
        delete window._loadedScripts[src + '__cbs'];
        cbs.forEach(function(cb) { try { cb(); } catch (e) {} });
    };
    script.onerror = function() {
        window._loadedScripts[src] = 'error';
        var cbs = window._loadedScripts[src + '__cbs'] || [];
        delete window._loadedScripts[src + '__cbs'];
        cbs.forEach(function(cb) { try { cb(); } catch (e) {} });
    };
    document.head.appendChild(script);
};

/**
 * 动态加载样式（仅加载一次）
 * @param {string} href - 样式URL
 */
window.loadStyleOnce = function(href) {
    if (!window._loadedStyles) window._loadedStyles = {};
    if (window._loadedStyles[href]) return;
    window._loadedStyles[href] = true;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
};

/**
 * 内容检测 helper：判断页面是否需要加载对应库
 * 只有页面实际包含对应内容时才触发加载，避免首页等无内容页面加载重型库
 */
window.hasPrismContent = function() {
    return document.querySelectorAll('.post-content pre code, .comment-content pre code').length > 0;
};
window.hasMermaidContent = function() {
    return document.querySelectorAll(
        '.post-content pre code.language-mermaid, .post-content pre code.lang-mermaid, ' +
        '.comment-content pre code.language-mermaid, .comment-content pre code.lang-mermaid'
    ).length > 0;
};
window.hasEChartsContent = function() {
    return document.querySelectorAll(
        '.post-content pre code.language-echarts, .post-content pre code.lang-echarts, ' +
        '.comment-content pre code.language-echarts, .comment-content pre code.lang-echarts'
    ).length > 0;
};
window.hasKaTeXContent = function() {
    // PHP 过滤器生成的 .math-tex 元素（主路径）
    if (document.querySelectorAll('.post-content .math-tex, .comment-content .math-tex').length > 0) return true;
    // 回退：检测 auto-render 定界符（当 PHP 过滤器未生成 .math-tex，如评论区）
    var targets = document.querySelectorAll('.post-content, .comment-content');
    for (var i = 0; i < targets.length; i++) {
        var text = targets[i].textContent || '';
        if (text.indexOf('$$') !== -1 || text.indexOf('\\(') !== -1 || text.indexOf('\\[') !== -1) return true;
        // 检测 $...$（排除单独的 $ 货币符号）
        if (/\$[^\s$][^$]*\$/.test(text)) return true;
    }
    return false;
};
window.hasLightboxContent = function() {
    // 仅当文章内容区存在未被链接包裹的图片时才需要 Lightbox
    var imgs = document.querySelectorAll('.post-content img');
    for (var i = 0; i < imgs.length; i++) {
        if (!imgs[i].closest('a')) return true;
    }
    return false;
};

/**
 * 工具函数：节流
 */
function throttle(fn, delay) {
    var lastCall = 0;
    return function() {
        var now = Date.now();
        if (now - lastCall >= delay) {
            lastCall = now;
            fn.apply(this, arguments);
        }
    };
}

/**
 * 工具函数：HTML 转纯文本
 */
function htmlToText(html) {
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    return tempDiv.textContent || tempDiv.innerText;
}

/**
 * 工具函数：剪贴板回退复制（使用 execCommand）
 */
function fallbackCopy(text, copyBtn) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        copyBtn.innerHTML = '<i class="fa fa-check"></i> 已复制';
        copyBtn.classList.add('copied');
        setTimeout(function() {
            copyBtn.innerHTML = '<i class="fa fa-copy"></i> 复制';
            copyBtn.classList.remove('copied');
        }, 2000);
    } catch (err) {
        copyBtn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 失败';
    }
    document.body.removeChild(textarea);
}

/**
 * 夜间模式功能
 * 支持 localStorage 持久化，并检测系统颜色偏好
 */
/**
 * 夜间模式功能（重构版 - 事件委托 + 纯状态同步）
 * 使用事件委托在 document 上单一监听，彻底避免 PJAX / 多重初始化导致的重复绑定或事件丢失。
 * initDarkMode 现在是纯同步函数，仅根据当前 data-theme 修正按钮图标，可随时安全调用。
 */
(function() {
    var html = document.documentElement;

    function getSavedTheme() {
        try { return localStorage.getItem('theme'); } catch (e) { return null; }
    }
    function saveTheme(theme) {
        try { localStorage.setItem('theme', theme); } catch (e) {}
    }
    function applyTheme(theme) {
        var toggleBtn = document.getElementById('dark-mode-toggle');
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="fa fa-sun-o"></i>';
                toggleBtn.setAttribute('title', '切换日间模式');
            }
        } else {
            html.removeAttribute('data-theme');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="fa fa-moon-o"></i>';
                toggleBtn.setAttribute('title', '切换夜间模式');
            }
        }
    }
    function toggleTheme() {
        var newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        saveTheme(newTheme);
        applyTheme(newTheme);
    }

    // 事件委托：在 document 上只监听一次，无论按钮是否被 PJAX 替换都正常工作
    document.addEventListener('click', function(e) {
        if (!e.target.closest) return;
        if (e.target.closest('#dark-mode-toggle')) {
            e.preventDefault();
            toggleTheme();
        }
    });

    // 初次加载时立即恢复或初始化主题
    var savedTheme = getSavedTheme();
    if (savedTheme) {
        applyTheme(savedTheme);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        applyTheme('dark');
        saveTheme('dark');
    }

    // 监听系统颜色偏好变化
    if (window.matchMedia) {
        var colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        var handleChange = function(e) {
            if (!getSavedTheme()) applyTheme(e.matches ? 'dark' : 'light');
        };
        if (colorSchemeQuery.addEventListener) {
            colorSchemeQuery.addEventListener('change', handleChange);
        } else if (colorSchemeQuery.addListener) {
            colorSchemeQuery.addListener(handleChange);
        }
    }
})();

/**
 * PJAX 或 DOM 替换后调用 — 仅同步按钮图标状态
 */
window.initDarkMode = function() {
    var toggleBtn = document.getElementById('dark-mode-toggle');
    if (!toggleBtn) return;
    var theme = document.documentElement.getAttribute('data-theme');
    if (theme === 'dark') {
        toggleBtn.innerHTML = '<i class="fa fa-sun-o"></i>';
        toggleBtn.setAttribute('title', '切换日间模式');
    } else {
        toggleBtn.innerHTML = '<i class="fa fa-moon-o"></i>';
        toggleBtn.setAttribute('title', '切换夜间模式');
    }
};

window.initPrismHighlight = function(retryCount) {
    if (!window.codeHighlightEnabled) return;
    // 仅在页面存在代码块时才加载与高亮，避免首页等无内容页面加载 Prism
    if (!window.hasPrismContent()) return;
    retryCount = retryCount || 0;

    // Prism 未加载时，动态加载库与样式后再高亮
    if (typeof window.Prism === 'undefined') {
        var vs = window.vendorScripts || {};
        if (!vs.prism) return;
        if (window.vendorCssUrls && window.vendorCssUrls.prism) {
            window.loadStyleOnce(window.vendorCssUrls.prism);
        }
        window.loadScriptOnce(vs.prism, function() {
            // Prism 加载完成后加载 autoloader
            if (!vs.prismAutoloader) { window.initPrismHighlight(retryCount); return; }
            window.loadScriptOnce(vs.prismAutoloader, function() {
                // 设置 autoloader 语言组件路径（仅非 CDN 模式需要）
                if (vs.prismAutoloaderPath && window.Prism && Prism.plugins && Prism.plugins.autoloader) {
                    Prism.plugins.autoloader.languages_path = vs.prismAutoloaderPath;
                }
                window.initPrismHighlight(retryCount);
            });
        });
        return;
    }

    const codeBlocks = document.querySelectorAll('.post-content pre code, .comment-content pre code');
    // 收集需要排除的代码块（mermaid/echarts），临时移除其 language-* 类名防止 Prism autoloader 加载不存在的语言组件
    var excludedBlocks = [];
    codeBlocks.forEach(function(code) {
        if (code.classList.contains('language-mermaid') || code.classList.contains('lang-mermaid') ||
            code.classList.contains('language-echarts') || code.classList.contains('lang-echarts')) {
            var removedClasses = [];
            ['language-mermaid', 'lang-mermaid', 'language-echarts', 'lang-echarts'].forEach(function(cls) {
                if (code.classList.contains(cls)) {
                    code.classList.remove(cls);
                    removedClasses.push(cls);
                }
            });
            excludedBlocks.push({ element: code, classes: removedClasses });
            return;
        }
        const className = code.className;
        const langMatch = className.match(/lang-(\w+)/);
        if (langMatch) {
            const language = langMatch[1];
            code.className = 'language-' + language;
            // 注意：必须用 textContent 赋值而非 innerHTML。
            // 代码内容中若包含 <?php、<script>、</body>、</html> 等，
            // 经 innerHTML 重新解析会被当作 HTML 标签/bogus comment 吞掉，导致代码内容被截断。
            code.textContent = htmlToText(code.innerHTML);
        }
    });

    Prism.highlightAll();

    // 恢复被排除代码块的 language-* 类名，以便 echarts/mermaid 渲染器能找到它们
    excludedBlocks.forEach(function(item) {
        item.classes.forEach(function(cls) {
            item.element.classList.add(cls);
        });
    });

    // pjax/动态加载场景下，Prism autoloader 的语言组件是异步加载的，
    // highlightAll 可能在语言组件就绪前执行，导致个别代码块未高亮。
    // 且 autoloader 对一次性加载失败的语言不会自动重试（本会话内永久标记 error），
    // 因此这里校验是否全部完成，未完成则强制重载语言组件并重试（限次避免死循环）。
    var pending = [];
    codeBlocks.forEach(function(code) {
        // 已高亮（含 token）或内容为空的不再处理
        if (code.querySelector('.token')) return;
        if (!code.textContent.trim()) return;
        var m = (code.className || '').match(/language-(\w+)/);
        if (!m) return;
        // 语言组件已加载但仍无 token，说明该块无匹配项，视为已处理
        if (Prism.languages[m[1]]) return;
        pending.push(code);
    });
    if (pending.length && retryCount < 6) {
        setTimeout(function() {
            // 强制重新加载缺失的语言组件（'!' 前缀让 autoloader 忽略 error/loading 状态重新拉取）
            if (Prism.plugins && Prism.plugins.autoloader) {
                pending.forEach(function(code) {
                    var m = (code.className || '').match(/language-(\w+)/);
                    if (m && m[1]) {
                        try {
                            Prism.plugins.autoloader.loadLanguages('!' + m[1]);
                        } catch (e) {}
                    }
                });
            }
            // 语言组件就绪后重新高亮这些代码块
            pending.forEach(function(code) {
                try {
                    Prism.highlightElement(code);
                } catch (e) {}
            });
            window.initPrismHighlight(retryCount + 1);
        }, 300);
    }
};

window.initLightbox = function(retryCount) {
    // 仅在文章内容区存在未链接包裹的图片时才加载 Lightbox
    if (!window.hasLightboxContent()) return;
    retryCount = retryCount || 0;

    var vs = window.vendorScripts || {};
    var vc = window.vendorCssUrls || {};

    // Lightbox3 未加载时，动态加载脚本与样式
    if (typeof window.Lightbox3 === 'undefined' || !window.Lightbox3.Lightbox) {
        if (!vs.lightbox) return;
        if (vc.lightbox) window.loadStyleOnce(vc.lightbox);
        window.loadScriptOnce(vs.lightbox, function() { window.initLightbox(retryCount); });
        return;
    }

    // 包装未被链接包裹的文章图片
    const postContent = document.querySelector('.post-content');
    if (postContent) {
        const images = postContent.querySelectorAll('img');
        let imageIndex = 0;

        images.forEach(function(img) {
            if (img.closest('a')) {
                return;
            }

            const imgSrc = img.getAttribute('src');
            if (!imgSrc) return;

            const link = document.createElement('a');
            link.href = imgSrc;
            link.setAttribute('data-lightbox', 'post-images');
            // 优先使用 figcaption，其次 alt，最后默认标题
            var lightboxTitle = img.getAttribute('alt') || '';
            var figCaption = img.closest('figure');
            if (figCaption) {
                var capEl = figCaption.querySelector('figcaption');
                if (capEl && capEl.textContent) {
                    lightboxTitle = capEl.textContent;
                }
            }
            if (!lightboxTitle) lightboxTitle = '图片 ' + (imageIndex + 1);
            link.setAttribute('data-title', lightboxTitle);

            img.parentNode.insertBefore(link, img);
            link.appendChild(img);

            imageIndex++;
        });
    }

    // 初始化 Lightbox3（事件委托方式，后续新增的 data-lightbox 链接自动生效）
    var Lb = window.Lightbox3.Lightbox;
    var lb = Lb.init({
        loop: true,
        padding: 40
    });

    // 仅增强一次，避免 pjax 切换时重复包装
    if (!window._lb3Enhanced) {
        window._lb3Enhanced = true;
        window._enhanceLightbox(lb);
    }
};

/**
 * 灯箱增强：自定义工具栏（旋转、缩放、复位、全屏、下载）、键盘快捷键
 * 仅在 Lightbox3 已加载后调用一次
 */
window._enhanceLightbox = function(lb) {
    var Lb = window.Lightbox3 && window.Lightbox3.Lightbox;
    if (!lb) {
        if (!Lb) return;
        lb = Lb.init();
    }

    // 灯箱状态
    var state = {
        rotation: 0,     // 旋转角度（度）
        scale: 1,        // 缩放倍数
        currentSrc: ''   // 当前图片地址
    };

    // 获取当前灯箱中的图片元素
    function getImage() {
        var overlay = document.querySelector('.lightbox3-overlay');
        return overlay ? overlay.querySelector('.lightbox3-image') : null;
    }

    // 应用旋转/缩放：使用独立的 rotate/scale 属性，
    // 与 Lightbox3 内部基于 transform 的缩放/拖动互不冲突
    function applyTransform() {
        var img = getImage();
        if (!img) return;
        img.style.rotate = state.rotation ? state.rotation + 'deg' : '';
        img.style.scale = state.scale !== 1 ? state.scale : '';
    }

    function resetTransform() {
        state.rotation = 0;
        state.scale = 1;
        applyTransform();
    }

    // ---------- 注入自定义工具栏（只注入一次） ----------
    function injectToolbar() {
        if (document.getElementById('sf-lb-toolbar')) return;

        var toolbar = document.createElement('div');
        toolbar.id = 'sf-lb-toolbar';
        toolbar.className = 'sf-lb-toolbar';
        toolbar.innerHTML =
            '<button type="button" class="sf-lb-btn" data-action="rotate-left" title="左旋 (Shift+R)" aria-label="左旋">' +
              '<i class="fa fa-rotate-left"></i>' +
            '</button>' +
            '<button type="button" class="sf-lb-btn" data-action="rotate-right" title="右旋 (R)" aria-label="右旋">' +
              '<i class="fa fa-rotate-right"></i>' +
            '</button>' +
            '<button type="button" class="sf-lb-btn" data-action="zoom-out" title="缩小 (-)" aria-label="缩小">' +
              '<i class="fa fa-search-minus"></i>' +
            '</button>' +
            '<button type="button" class="sf-lb-btn" data-action="zoom-in" title="放大 (+)" aria-label="放大">' +
              '<i class="fa fa-search-plus"></i>' +
            '</button>' +
            '<button type="button" class="sf-lb-btn" data-action="reset" title="复位 (0)" aria-label="复位">' +
              '<i class="fa fa-compress"></i>' +
            '</button>' +
            '<button type="button" class="sf-lb-btn" data-action="fullscreen" title="全屏 (F)" aria-label="全屏">' +
              '<i class="fa fa-expand"></i>' +
            '</button>' +
            '<button type="button" class="sf-lb-btn" data-action="download" title="下载 (D)" aria-label="下载">' +
              '<i class="fa fa-download"></i>' +
            '</button>';
        document.body.appendChild(toolbar);

        toolbar.addEventListener('click', function(e) {
            var btn = e.target.closest('.sf-lb-btn');
            if (!btn) return;
            e.stopPropagation();
            handleAction(btn.getAttribute('data-action'));
        });
    }

    function showToolbar() {
        var t = document.getElementById('sf-lb-toolbar');
        if (t) t.classList.add('sf-lb-visible');
    }

    function hideToolbar() {
        var t = document.getElementById('sf-lb-toolbar');
        if (t) t.classList.remove('sf-lb-visible');
    }

    // ---------- 下载 ----------
    function downloadImage() {
        if (!state.currentSrc) return;
        var url = state.currentSrc;
        var filename = url.split('/').pop().split('?')[0] || 'image';

        function saveBlob(blob) {
            var blobUrl = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function() { URL.revokeObjectURL(blobUrl); }, 5000);
        }

        // 同源：直接 a[download] 下载
        if (url.indexOf(location.origin) === 0) {
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            return;
        }

        // 拉取目标为 Blob，且必须是图片内容才算成功
        // same-origin：同源（主题下载代理）会携带 session cookie，保证 CSRF 校验通过；跨源仍不带凭证
        function fetchBlob(target) {
            return fetch(target, { credentials: 'same-origin' }).then(function(r) {
                if (!r.ok) throw new Error('http ' + r.status);
                return r.blob();
            }).then(function(blob) {
                if (!blob || !blob.size || blob.type.indexOf('image/') !== 0) {
                    throw new Error('not image');
                }
                return blob;
            });
        }

        // 主题服务器端下载代理（同源，可中转跨域图片）
        function proxyUrl(target) {
            return (window.themeUrl || '') + 'core/download-proxy.php?url=' + encodeURIComponent(target) + '&_=' + encodeURIComponent(window.csrfToken || '');
        }

        // 公共 CORS 代理（兜底，部分源可用）
        function corsProxyUrl(target) {
            return 'https://api.allorigins.win/raw?url=' + encodeURIComponent(target);
        }

        // 跨域：依次尝试 直连Blob(需CORS) → 服务器代理 → 公共CORS代理 → 新窗口打开
        var attempts = [url, proxyUrl(url), corsProxyUrl(url)];

        function tryNext(i) {
            if (i >= attempts.length) {
                window.open(url, '_blank', 'noopener');
                return;
            }
            fetchBlob(attempts[i]).then(function(blob) {
                saveBlob(blob);
            }).catch(function() {
                tryNext(i + 1);
            });
        }
        tryNext(0);
    }

    // ---------- 全屏 ----------
    function toggleFullscreen() {
        var img = getImage();
        if (!img) return;
        if (!document.fullscreenElement) {
            if (img.requestFullscreen) {
                img.requestFullscreen().catch(function() {});
            } else if (img.webkitRequestFullscreen) {
                img.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }
    }

    // ---------- 处理工具栏动作 ----------
    function handleAction(action) {
        switch (action) {
            case 'rotate-left':
                state.rotation = (state.rotation - 90 + 360) % 360;
                applyTransform();
                break;
            case 'rotate-right':
                state.rotation = (state.rotation + 90) % 360;
                applyTransform();
                break;
            case 'zoom-in':
                state.scale = Math.min(5, state.scale + 0.25);
                applyTransform();
                break;
            case 'zoom-out':
                state.scale = Math.max(0.5, state.scale - 0.25);
                applyTransform();
                break;
            case 'reset':
                resetTransform();
                break;
            case 'fullscreen':
                toggleFullscreen();
                break;
            case 'download':
                downloadImage();
                break;
        }
    }

    // ---------- 键盘快捷键（与 Lightbox3 自带的 Esc/方向键不冲突） ----------
    function onKeydown(e) {
        if (!document.querySelector('.lightbox3-overlay')) return;
        var tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA') return;
        var key = e.key;
        switch (key) {
            case 'r':
                handleAction(e.shiftKey ? 'rotate-left' : 'rotate-right');
                e.preventDefault();
                break;
            case 'R':
                handleAction('rotate-left');
                e.preventDefault();
                break;
            case '+':
            case '=':
                handleAction('zoom-in');
                e.preventDefault();
                break;
            case '-':
            case '_':
                handleAction('zoom-out');
                e.preventDefault();
                break;
            case '0':
                handleAction('reset');
                e.preventDefault();
                break;
            case 'f':
            case 'F':
                handleAction('fullscreen');
                e.preventDefault();
                break;
            case 'd':
            case 'D':
                handleAction('download');
                e.preventDefault();
                break;
        }
    }

    // ---------- 监听 Lightbox3 事件，同步 UI ----------
    lb.on('open', function(detail) {
        if (detail && detail.src) state.currentSrc = detail.src;
        injectToolbar();
        resetTransform();
        showToolbar();
    });
    lb.on('opened', function(detail) {
        if (detail && detail.src) state.currentSrc = detail.src;
        showToolbar();
    });
    lb.on('navigate', function(detail) {
        if (detail && detail.src) state.currentSrc = detail.src;
        resetTransform();
    });
    lb.on('closed', function() {
        hideToolbar();
        resetTransform();
        state.currentSrc = '';
    });

    document.addEventListener('keydown', onKeydown);
};

window.initCopyButtons = function() {
    const preBlocks = document.querySelectorAll('.post-content pre');
    preBlocks.forEach(function(pre) {
        pre.setAttribute('tabindex', '0');

        // 将 pre 包一层容器，复制按钮定位到外层容器，避免随代码横向滚动
        let box = pre.parentNode;
        if (!box || box.nodeType !== 1 || !box.classList.contains('code-box')) {
            box = document.createElement('div');
            box.className = 'code-box';
            pre.parentNode.insertBefore(box, pre);
            box.appendChild(pre);
        }
        box.style.position = 'relative';

        const existingBtn = box.querySelector('.copy-code-btn');
        if (existingBtn) {
            existingBtn.remove();
        }

        const copyBtn = document.createElement('button');
        copyBtn.className = 'copy-code-btn';
        copyBtn.innerHTML = '<i class="fa fa-copy"></i> 复制';
        copyBtn.title = '复制代码';
        
        copyBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const code = pre.querySelector('code');
            if (code) {
                const text = htmlToText(code.innerHTML) || '';

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        copyBtn.innerHTML = '<i class="fa fa-check"></i> 已复制';
                        copyBtn.classList.add('copied');

                        setTimeout(function() {
                            copyBtn.innerHTML = '<i class="fa fa-copy"></i> 复制';
                            copyBtn.classList.remove('copied');
                        }, 2000);
                    }).catch(function() {
                        // clipboard API 失败，回退到 execCommand
                        fallbackCopy(text, copyBtn);
                    });
                } else {
                    // clipboard API 不可用，直接使用 execCommand
                    fallbackCopy(text, copyBtn);
                }
            }
        });
        
        box.appendChild(copyBtn);
    });
};

window.initCollapsibleSidebar = function() {
    var sidebar = document.getElementById('left-sidebar');
    if (!sidebar) return;

    function getWidgetKey(widget) {
        var specificClass = '';
        var classes = widget.className.split(' ');
        for (var i = 0; i < classes.length; i++) {
            if (classes[i] !== 'widget' && classes[i] !== 'collapsible-widget' && classes[i] !== 'collapsed') {
                specificClass = classes[i];
                break;
            }
        }
        return 'sidebar_state_' + (specificClass || 'unknown');
    }

    // 使用 document 级别事件委托，只绑定一次，避免 PJAX / 多重初始化导致重复绑定
    if (!window._collapsibleSidebarBound) {
        window._collapsibleSidebarBound = true;
        document.addEventListener('click', function(e) {
            var toggle = e.target.closest('.collapsible-toggle');
            if (!toggle) return;
            e.preventDefault();

            var widget = toggle.closest('.collapsible-widget');
            if (!widget) return;

            var isCollapsed = widget.classList.contains('collapsed');
            if (isCollapsed) {
                widget.classList.remove('collapsed');
                try { localStorage.setItem(getWidgetKey(widget), 'expanded'); } catch (e) {}
            } else {
                widget.classList.add('collapsed');
                try { localStorage.setItem(getWidgetKey(widget), 'collapsed'); } catch (e) {}
            }
        });
    }

    // 恢复折叠状态
    var toggles = sidebar.querySelectorAll('.collapsible-toggle');
    toggles.forEach(function(toggle) {
        var widget = toggle.closest('.collapsible-widget');
        if (!widget) return;
        try {
            var key = getWidgetKey(widget);
            var state = localStorage.getItem(key);
            if (state === 'expanded') {
                widget.classList.remove('collapsed');
            } else {
                widget.classList.add('collapsed');
            }
        } catch (e) {
            widget.classList.add('collapsed');
        }
    });
};

window.initCategoryCollapse = function() {
    var sidebar = document.getElementById('left-sidebar');
    if (!sidebar) return;

    function getCatKey(slug) {
        return 'shufei_cat_collapsed_' + slug;
    }

    // 使用 document 捕获阶段事件委托，只绑定一次；捕获阶段先于 PJAX 处理，避免点击箭头触发跳转
    if (!window._categoryCollapseBound) {
        window._categoryCollapseBound = true;
        document.addEventListener('click', function(e) {
            var toggle = e.target.closest('.cat-toggle');
            if (!toggle) return;
            e.preventDefault();
            e.stopImmediatePropagation();

            var li = toggle.closest('.category-nav-item');
            if (!li) return;
            var isCollapsed = li.classList.contains('collapsed');
            if (isCollapsed) {
                li.classList.remove('collapsed');
                try { localStorage.setItem(getCatKey(toggle.getAttribute('data-slug')), '0'); } catch (e) {}
            } else {
                li.classList.add('collapsed');
                try { localStorage.setItem(getCatKey(toggle.getAttribute('data-slug')), '1'); } catch (e) {}
            }
        }, true);
    }

    // 恢复折叠状态
    var toggles = sidebar.querySelectorAll('.cat-toggle');
    toggles.forEach(function(toggle) {
        var li = toggle.closest('.category-nav-item');
        if (!li) return;
        try {
            if (localStorage.getItem(getCatKey(toggle.getAttribute('data-slug'))) === '1') {
                li.classList.add('collapsed');
            } else {
                li.classList.remove('collapsed');
            }
        } catch (e) {
            li.classList.remove('collapsed');
        }
    });
};

// 初始化移动端菜单功能 - 控制左侧边栏
// 使用 document 级别事件委托 + 实时 DOM 查询，避免 PJAX 替换元素后闭包引用失效
(function() {
    // 设置侧边栏状态
    function setSidebarState(isOpen) {
        var btn = document.getElementById('mobile-menu-btn');
        var sidebar = document.getElementById('left-sidebar');
        var shade = document.getElementById('body-shade');
        if (btn) {
            if (isOpen) {
                btn.classList.add('active');
                btn.title = '关闭菜单';
            } else {
                btn.classList.remove('active');
                btn.title = '展开菜单';
            }
        }
        if (sidebar) {
            if (isOpen) {
                sidebar.classList.add('admin-side-show');
            } else {
                sidebar.classList.remove('admin-side-show');
            }
        }
        if (shade) {
            if (isOpen) {
                shade.classList.add('active');
            } else {
                shade.classList.remove('active');
            }
        }
    }

    // 只绑定一次事件委托
    if (!window._mobileMenuDocBound) {
        window._mobileMenuDocBound = true;

        document.addEventListener('click', function(e) {
            // 遮罩层点击关闭
            if (e.target.closest('#body-shade')) {
                e.preventDefault();
                setSidebarState(false);
                return;
            }

            // 点击侧边栏外部关闭菜单
            var sidebar = document.getElementById('left-sidebar');
            var btn = document.getElementById('mobile-menu-btn');
            if (window.innerWidth <= 992 && sidebar && sidebar.classList.contains('admin-side-show')) {
                if (!sidebar.contains(e.target) && !(btn && btn.contains(e.target))) {
                    setSidebarState(false);
                }
            }

            // 点击侧边栏链接后关闭菜单
            if (e.target.closest('#left-sidebar a') && window.innerWidth <= 992) {
                setSidebarState(false);
            }
        });

        // 触摸事件关闭遮罩
        document.addEventListener('touchstart', function(e) {
            if (e.target.closest('#body-shade')) {
                e.preventDefault();
                setSidebarState(false);
            }
        }, { passive: false });

        // ESC键关闭菜单
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var sidebar = document.getElementById('left-sidebar');
                if (sidebar && sidebar.classList.contains('admin-side-show')) {
                    setSidebarState(false);
                }
            }
        });
    }

    // 按钮点击：直接绑定到元素，每次调用都重新绑定（PJAX 后元素会被替换）
    window.initMobileMenu = function() {
        var btn = document.getElementById('mobile-menu-btn');
        if (!btn) return;

        // 移除旧处理器（防止重复绑定）
        if (btn._mobileMenuHandler) {
            btn.removeEventListener('click', btn._mobileMenuHandler);
        }

        btn._mobileMenuHandler = function(e) {
            e.preventDefault();
            e.stopPropagation();
            var sidebar = document.getElementById('left-sidebar');
            var isOpen = sidebar && sidebar.classList.contains('admin-side-show');
            setSidebarState(!isOpen);
        };

        btn.addEventListener('click', btn._mobileMenuHandler);
    };
})();

/**
 * 文章点赞功能
 */
window.initPostLike = function() {
    var likeBtn = document.querySelector('.post-like-btn');
    if (!likeBtn) return;

    // 防重复绑定：F5 刷新时 main.js(DOMContentLoaded) 与 pjax.js(load) 都会调用
    if (likeBtn.getAttribute('data-like-bound')) return;
    likeBtn.setAttribute('data-like-bound', '1');

    likeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (this.classList.contains('liked') || this.disabled) {
            return;
        }

        var cid = this.getAttribute('data-cid');
        if (!cid) return;

        var btn = this;
        var likeText = btn.querySelector('.like-text');

        // 获取当前主题URL用于ajax请求
        var themeUrl = window.themeUrl || '';
        var csrfToken = window.csrfToken || '';

        // 使用 XMLHttpRequest 替代 fetch 以兼容更多浏览器
        var xhr = new XMLHttpRequest();
        xhr.open('POST', themeUrl + 'core/ajax-handler.php?action=like', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            btn.classList.add('liked');
                            btn.disabled = true;
                            if (likeText) likeText.textContent = '已点赞';

                            // 更新所有点赞数显示（文章meta和点赞盒子）
                            var metaLikesList = document.querySelectorAll('.post-likes-count[data-cid="' + cid + '"]');
                            metaLikesList.forEach(function(el) {
                                el.textContent = data.likes;
                            });
                        } else {
                            window.showToast(data.message || '点赞失败', 'error');
                        }
                    } catch (err) {
                        window.showToast('点赞请求异常', 'error');
                    }
                } else {
                    window.showToast('网络请求失败', 'error');
                }
            }
        };

        xhr.onerror = function() {
            window.showToast('网络连接失败', 'error');
        };

        xhr.send('cid=' + encodeURIComponent(cid) + '&_=' + encodeURIComponent(csrfToken));
    });
};

/**
 * 密码文章/页面表单 AJAX 提交（无刷新验证）
 * - 密码正确：写入 cookie 后通过 Pjax 局部加载文章内容（未开启 Pjax 时整页跳转）
 * - 密码错误：密码卡片内直接显示错误提示，页面不刷新
 * 表单 action 保留指向 password-verify.php 作为无 JS 时的兜底。
 */
window.initPasswordForms = function() {
    var forms = document.querySelectorAll('form.protected[data-ajax="1"]');
    if (!forms.length) return;

    for (var i = 0; i < forms.length; i++) {
        var form = forms[i];
        if (form.getAttribute('data-pwd-bound')) continue;
        form.setAttribute('data-pwd-bound', '1');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var currentForm = this;
            var cidInput = this.querySelector('input[name="cid"]');
            var pwdInput = this.querySelector('input[name="password"]');
            var returnInput = this.querySelector('input[name="return"]');
            var submitBtn = this.querySelector('input[type="submit"]');
            var password = pwdInput ? pwdInput.value : '';
            var cid = cidInput ? cidInput.value : '';
            var returnUrl = returnInput ? returnInput.value : window.location.href;

            if (!password) {
                showPwdError(currentForm, '请输入访问密码');
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.value = '验证中...';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', (window.themeUrl || '') + 'core/ajax-handler.php?action=password_verify', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return;

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.value = '提交';
                }

                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            // 密码正确：优先 Pjax 无刷新加载文章内容
                            if (window.shufeiPjax && typeof window.shufeiPjax.loadUrl === 'function') {
                                window.shufeiPjax.loadUrl(returnUrl);
                            } else {
                                window.location.href = returnUrl;
                            }
                        } else {
                            showPwdError(currentForm, data.message || '密码错误，请重新输入');
                        }
                    } catch (err) {
                        showPwdError(currentForm, '密码验证异常，请重试');
                    }
                } else {
                    showPwdError(currentForm, '网络请求失败，请重试');
                }
            };

            xhr.onerror = function() {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.value = '提交';
                }
                showPwdError(currentForm, '网络连接失败，请重试');
            };

            xhr.send('cid=' + encodeURIComponent(cid) +
                '&password=' + encodeURIComponent(password) +
                '&_=' + encodeURIComponent(window.csrfToken || ''));
        });
    }
};

/**
 * 在密码卡片内显示/更新错误提示
 */
function showPwdError(form, msg) {
    var card = (typeof form.closest === 'function') ? form.closest('.password-protection') : null;
    var errBox = card ? card.querySelector('.password-error') : null;

    if (!errBox) {
        errBox = document.createElement('p');
        errBox.className = 'password-error';
        var anchor = card || form.parentNode;
        anchor.insertBefore(errBox, form);
    }
    errBox.innerHTML = '<i class="fa fa-times-circle"></i> ' + msg;
    errBox.style.display = 'flex';
}

/**
 * 文章投票功能
 * - 未投票时：点击选项提交投票
 * - 已投票/已截止：显示结果
 */
window.initPostVote = function() {
    var card = document.getElementById('post-vote-card');
    if (!card) return;
    if (card.getAttribute('data-vote-bound')) return;
    card.setAttribute('data-vote-bound', '1');

    var cid = card.getAttribute('data-cid');
    var isVoted = card.getAttribute('data-voted') === '1';
    var isExpired = card.getAttribute('data-expired') === '1';

    // 已投票或已截止时不可点击
    if (isVoted || isExpired) return;

    var options = card.querySelectorAll('.vote-option');
    if (!options.length) return;

    for (var i = 0; i < options.length; i++) {
        (function(opt) {
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                if (opt.classList.contains('vote-option-readonly')) return;
                if (opt.classList.contains('vote-option-loading')) return;

                var optionIndex = parseInt(opt.getAttribute('data-index'), 10);
                if (isNaN(optionIndex)) return;

                var themeUrl = window.themeUrl || '';
                var csrfToken = window.csrfToken || '';

                opt.classList.add('vote-option-loading');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', themeUrl + 'core/ajax-handler.php?action=vote', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        opt.classList.remove('vote-option-loading');
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (data.success) {
                                    // 渲染投票结果
                                    renderVoteResults(card, data.option, data.counts, data.total);
                                } else {
                                    window.showToast(data.message || '投票失败', 'error');
                                }
                            } catch (err) {
                                window.showToast('投票请求异常', 'error');
                            }
                        } else {
                            window.showToast('网络请求失败', 'error');
                        }
                    }
                };

                xhr.onerror = function() {
                    opt.classList.remove('vote-option-loading');
                    window.showToast('网络连接失败', 'error');
                };

                xhr.send('cid=' + encodeURIComponent(cid) +
                         '&option=' + encodeURIComponent(optionIndex) +
                         '&_=' + encodeURIComponent(csrfToken));
            });
        })(options[i]);
    }

    // 渲染投票结果
    function renderVoteResults(card, votedOption, counts, total) {
        var optionEls = card.querySelectorAll('.vote-option');
        for (var i = 0; i < optionEls.length; i++) {
            var el = optionEls[i];
            var idx = parseInt(el.getAttribute('data-index'), 10);
            var cnt = counts[idx] || 0;
            var pct = total > 0 ? Math.round((cnt / total) * 100) : 0;
            var isMine = (idx === votedOption);

            el.classList.add('vote-option-readonly');
            if (isMine) el.classList.add('vote-option-mine');

            // 进度条动画
            var bar = el.querySelector('.vote-option-bar');
            if (bar) bar.style.width = pct + '%';

            // 更新内容
            var content = el.querySelector('.vote-option-content');
            if (content) {
                var labelHtml = (isMine ? '<i class="fa fa-check-circle"></i>' : '') +
                    el.querySelector('.vote-option-label').textContent.trim();
                content.innerHTML = '<span class="vote-option-label">' + labelHtml + '</span>' +
                    '<span class="vote-option-stats">' +
                        '<span class="vote-pct">' + pct + '%</span>' +
                        '<span class="vote-cnt">(' + cnt + '票)</span>' +
                    '</span>';
            }
        }

        // 更新底部信息
        var footer = card.querySelector('.vote-card-footer');
        if (footer) {
            var totalEl = footer.querySelector('.vote-total strong');
            if (totalEl) totalEl.textContent = total;
            var hintEl = footer.querySelector('.vote-hint');
            if (hintEl) hintEl.textContent = '您已投票，感谢参与';
        }

        card.setAttribute('data-voted', '1');
        card.setAttribute('data-option', String(votedOption));
    }
};

/**
 * 文章浏览量统计
 */
window.initPostThumbFallback = function() {
    var cards = document.querySelectorAll('.post.has-thumbnail[data-thumb]:not([data-thumb-done])');
    if (!cards.length) return;
    for (var i = 0; i < cards.length; i++) {
        (function(card) {
            card.setAttribute('data-thumb-done', '1');
            var src = card.getAttribute('data-thumb');
            var fallback = card.getAttribute('data-thumb-fallback');
            if (!src) return;
            var img = new Image();
            img.onerror = function() {
                if (!fallback) return;
                card.style.backgroundImage = 'url(' + fallback + ')';
                var imgEl = card.querySelector('img.thumbnail-img-side');
                if (imgEl) imgEl.src = fallback;
            };
            img.src = src;
        })(cards[i]);
    }
};

window.initPostViews = function() {
    var viewsCount = document.querySelector('.post-views-count');
    if (!viewsCount) return;

    var cid = viewsCount.getAttribute('data-cid');
    if (!cid) return;

    var themeUrl = window.themeUrl || '';
    var csrfToken = window.csrfToken || '';

    // 使用延迟确保页面完全加载后再统计
    setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', themeUrl + 'core/ajax-handler.php?action=view', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        viewsCount.textContent = data.views;
                    }
                } catch (err) {
                    // 浏览量统计失败静默处理，不影响用户体验
                }
            }
        };

        xhr.onerror = function() {
            // 浏览量统计网络失败静默处理
        };

        xhr.send('cid=' + encodeURIComponent(cid) + '&_=' + encodeURIComponent(csrfToken));
    }, 1500);
};

/**
 * 评论点赞功能
 */
window.initCommentIpRegions = function() {
    var spans = document.querySelectorAll('.comment-ip-region:not([data-region-done])');
    if (!spans.length) return;
    for (var i = 0; i < spans.length; i++) {
        (function(span) {
            span.setAttribute('data-region-done', '1');
            var ip = span.getAttribute('data-ip');
            if (!ip) return;
            var textEl = span.querySelector('.ip-region-text');
            if (!textEl) return;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'https://api.lwcat.cn/api/ip/?ip=' + encodeURIComponent(ip), true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return;
                var region = '';
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data && data.code === 0 && data.data) {
                            var d = data.data;
                            var parts = [];
                            if (d.country && d.country !== '中国') parts.push(d.country);
                            if (d.province && d.province !== '0') parts.push(d.province);
                            if (d.city && d.city !== '0' && d.city !== d.province) parts.push(d.city);
                            region = parts.join(d.country === '中国' ? '' : ' ');
                        }
                    } catch (err) {}
                }
                textEl.textContent = region || '未知';
            };
            xhr.send();
        })(spans[i]);
    }
};

window.initCommentLike = function() {
    var likeBtns = document.querySelectorAll('.comment-like-btn:not([data-like-bound])');
    if (!likeBtns.length) return;

    var themeUrl = window.themeUrl || '';
    var csrfToken = window.csrfToken || '';

    for (var i = 0; i < likeBtns.length; i++) {
        (function(btn) {
            btn.setAttribute('data-like-bound', '1');

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (btn.classList.contains('liked')) return;

                var coid = btn.getAttribute('data-coid');
                if (!coid) return;

                var countEl = btn.querySelector('.like-count');
                var iconEl = btn.querySelector('i');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', themeUrl + 'core/ajax-handler.php?action=comment_like', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (data.success) {
                                    btn.classList.add('liked');
                                    if (iconEl) iconEl.className = 'fa fa-heart';
                                    if (countEl) countEl.textContent = data.likes;
                                    var li = btn.closest('li');
                                    if (li) li.setAttribute('data-likes', data.likes);
                                } else {
                                    if (window.showToast) window.showToast(data.message || '点赞失败', 'error');
                                }
                            } catch (err) {
                                if (window.showToast) window.showToast('点赞请求异常', 'error');
                            }
                        } else {
                            if (window.showToast) window.showToast('网络请求失败', 'error');
                        }
                    }
                };

                xhr.send('coid=' + encodeURIComponent(coid) + '&_=' + encodeURIComponent(csrfToken));
            });
        })(likeBtns[i]);
    }
};

/**
 * 评论排序功能
 */
window.initCommentSort = function() {
    var sortOptions = document.getElementById('comment-sort-options');
    if (!sortOptions) return;

    if (!sortOptions.getAttribute('data-sort-bound')) {
        sortOptions.setAttribute('data-sort-bound', '1');

        sortOptions.addEventListener('click', function(e) {
            var btn = e.target.closest('.sort-btn');
            if (!btn) return;

            var sortType = btn.getAttribute('data-sort');
            if (!sortType) return;

            var allBtns = sortOptions.querySelectorAll('.sort-btn');
            for (var i = 0; i < allBtns.length; i++) {
                allBtns[i].classList.remove('active');
            }
            btn.classList.add('active');

            try { localStorage.setItem('shufei_comment_sort', sortType); } catch (e) {}

            window.applyCommentSort(sortType);
        });
    }

    // 恢复用户偏好
    window.applyCommentSort();
};

window.applyCommentSort = function(sortType) {
    var commentList = document.getElementById('comment-list');
    if (!commentList) return;

    if (!sortType) {
        try { sortType = localStorage.getItem('shufei_comment_sort') || 'default'; } catch (e) { sortType = 'default'; }
        var sortOptions = document.getElementById('comment-sort-options');
        if (sortOptions) {
            var btns = sortOptions.querySelectorAll('.sort-btn');
            for (var i = 0; i < btns.length; i++) {
                btns[i].classList.toggle('active', btns[i].getAttribute('data-sort') === sortType);
            }
        }
    }

    var items = commentList.children;
    if (!items.length) return;

    // 保存原始顺序（仅首次设置，pjax 切换后新 DOM 会重新设置）
    for (var i = 0; i < items.length; i++) {
        if (!items[i].getAttribute('data-original-index')) {
            items[i].setAttribute('data-original-index', i);
        }
    }

    // author 模式：只过滤不排序
    if (sortType === 'author') {
        for (var i = 0; i < items.length; i++) {
            var isAuthor = items[i].getAttribute('data-is-author') === '1';
            items[i].style.display = isAuthor ? '' : 'none';
        }
        return;
    }

    // 恢复所有评论显示
    for (var i = 0; i < items.length; i++) {
        items[i].style.display = '';
    }

    // 先恢复 default 顺序，再按目标排序（避免上次排序结果干扰）
    var arr = Array.prototype.slice.call(items);
    arr.sort(function(a, b) {
        return parseInt(a.getAttribute('data-original-index')) - parseInt(b.getAttribute('data-original-index'));
    });

    if (sortType === 'default') {
        for (var i = 0; i < arr.length; i++) {
            commentList.appendChild(arr[i]);
        }
        return;
    }

    // 按目标排序，相同主键时用原始顺序作为二级排序（保证稳定）
    arr.sort(function(a, b) {
        var va = 0, vb = 0, result = 0;
        if (sortType === 'time_asc') {
            va = parseInt(a.getAttribute('data-created')) || 0;
            vb = parseInt(b.getAttribute('data-created')) || 0;
            result = va - vb;
        } else if (sortType === 'time_desc') {
            va = parseInt(a.getAttribute('data-created')) || 0;
            vb = parseInt(b.getAttribute('data-created')) || 0;
            result = vb - va;
        } else if (sortType === 'likes') {
            va = parseInt(a.getAttribute('data-likes')) || 0;
            vb = parseInt(b.getAttribute('data-likes')) || 0;
            result = vb - va;
            // 点赞数相同时，按时间降序（新的在前）
            if (result === 0) {
                var ta = parseInt(a.getAttribute('data-created')) || 0;
                var tb = parseInt(b.getAttribute('data-created')) || 0;
                result = tb - ta;
            }
        }
        // 二级排序：仍然相同时保持原始顺序
        if (result === 0) {
            result = parseInt(a.getAttribute('data-original-index')) - parseInt(b.getAttribute('data-original-index'));
        }
        return result;
    });

    for (var i = 0; i < arr.length; i++) {
        commentList.appendChild(arr[i]);
    }
};

/**
 * Mermaid 图表渲染功能
 */
window.initMermaid = function(retryCount) {
    if (!window.mermaidEnabled) return;
    // 仅在页面存在 mermaid 代码块时才加载与渲染
    if (!window.hasMermaidContent()) return;
    retryCount = retryCount || 0;

    // mermaid 未加载时，动态加载库后再渲染
    if (typeof window.mermaid === 'undefined') {
        var src = (window.vendorScripts || {}).mermaid;
        if (!src) return;
        window.loadScriptOnce(src, function() { window.initMermaid(retryCount); });
        return;
    }

    // 初始化 Mermaid 配置（仅首次）
    if (!window._mermaidInitialized) {
        mermaid.initialize({
            startOnLoad: false,
            theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default',
            securityLevel: 'strict',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
        });
        window._mermaidInitialized = true;
    }

    // 查找所有 mermaid 代码块并渲染（包括评论区）
    var mermaidBlocks = document.querySelectorAll('.post-content pre code.language-mermaid, .post-content pre code.lang-mermaid, .comment-content pre code.language-mermaid, .comment-content pre code.lang-mermaid');
    mermaidBlocks.forEach(function(codeBlock) {
        var pre = codeBlock.parentElement;
        if (!pre || pre.getAttribute('data-mermaid-processed')) return;
        // 标记为已处理，防止重复渲染
        pre.setAttribute('data-mermaid-processed', 'true');

        var id = 'mermaid-' + Math.random().toString(36).substr(2, 9);
        var source = codeBlock.textContent || codeBlock.innerText;

        var container = document.createElement('div');
        container.className = 'mermaid-container';
        container.setAttribute('data-mermaid-id', id);

        pre.parentNode.replaceChild(container, pre);

        try {
            mermaid.render(id, source).then(function(result) {
                container.innerHTML = result.svg;
            }).catch(function(err) {
                container.innerHTML = '<div class="mermaid-error">Mermaid 渲染错误: ' + err.message + '</div>';
            });
        } catch (err) {
            container.innerHTML = '<div class="mermaid-error">Mermaid 渲染错误: ' + err.message + '</div>';
        }
    });
};

/**
 * ECharts 图表渲染功能
 */
window.initECharts = function(retryCount) {
    if (!window.echartsEnabled) return;
    // 仅在页面存在 echarts 代码块时才加载与渲染
    if (!window.hasEChartsContent()) return;
    retryCount = retryCount || 0;

    // echarts 未加载时，动态加载库后再渲染
    if (typeof window.echarts === 'undefined') {
        var src = (window.vendorScripts || {}).echarts;
        if (!src) return;
        window.loadScriptOnce(src, function() { window.initECharts(retryCount); });
        return;
    }

    var echartsBlocks = document.querySelectorAll('.post-content pre code.language-echarts, .post-content pre code.lang-echarts, .comment-content pre code.language-echarts, .comment-content pre code.lang-echarts');
    echartsBlocks.forEach(function(codeBlock) {
        var pre = codeBlock.parentElement;
        if (!pre || pre.getAttribute('data-echarts-processed')) return;
        // 标记为已处理，防止重复渲染
        pre.setAttribute('data-echarts-processed', 'true');

        var source = codeBlock.textContent || codeBlock.innerText;
        source = source.trim();

        var container = document.createElement('div');
        container.className = 'echarts-container';

        pre.parentNode.replaceChild(container, pre);

        try {
            var option = JSON.parse(source);
            var chart = echarts.init(container);
            chart.setOption(option);

            // 响应式：窗口大小变化时自动调整（节流）
            var resizeHandler = throttle(function() {
                chart.resize();
            }, 150);
            window.addEventListener('resize', resizeHandler);

            // 保存引用以便 PJAX 切换时清理
            if (!window._echartsInstances) window._echartsInstances = [];
            window._echartsInstances.push({ chart: chart, resizeHandler: resizeHandler });
        } catch (err) {
            container.innerHTML = '<div class="echarts-error">ECharts 配置解析错误: ' + err.message + '</div>';
            container.style.height = 'auto';
        }
    });
};

/**
 * 清理 ECharts 实例（PJAX 切换前调用）
 */
window.destroyECharts = function() {
    if (window._echartsInstances) {
        window._echartsInstances.forEach(function(item) {
            window.removeEventListener('resize', item.resizeHandler);
            item.chart.dispose();
        });
        window._echartsInstances = [];
    }
};

/**
 * KaTeX 数学公式渲染功能
 */
window.initKaTeX = function(retryCount) {
    if (!window.katexEnabled) return;
    // 仅在页面存在数学公式内容时才加载与渲染
    if (!window.hasKaTeXContent()) return;
    retryCount = retryCount || 0;

    // katex 未加载时，动态加载库、样式与 auto-render 后再渲染
    if (typeof window.katex === 'undefined') {
        var vs = window.vendorScripts || {};
        if (!vs.katex) return;
        if (window.vendorCssUrls && window.vendorCssUrls.katex) {
            window.loadStyleOnce(window.vendorCssUrls.katex);
        }
        window.loadScriptOnce(vs.katex, function() {
            // 加载 auto-render（用于扫描定界符的回退路径）
            if (!vs.katexAutoRender) { window.initKaTeX(retryCount); return; }
            window.loadScriptOnce(vs.katexAutoRender, function() {
                window.initKaTeX(retryCount);
            });
        });
        return;
    }

    // 优先处理 PHP 过滤器生成的 .math-tex 元素（已修复 Markdown 副作用），包括评论区
    var mathElements = document.querySelectorAll('.post-content .math-tex, .comment-content .math-tex');
    if (mathElements.length > 0) {
        mathElements.forEach(function(el) {
            if (el.getAttribute('data-katex-processed')) return;
            el.setAttribute('data-katex-processed', 'true');

            var math = el.getAttribute('data-math');
            var mode = el.getAttribute('data-mode');

            if (!math) return;

            try {
                katex.render(math, el, {
                    displayMode: mode === 'display',
                    throwOnError: false,
                    output: 'html'
                });
            } catch (err) {
                el.textContent = math;
                el.className = 'math-error';
            }
        });
        return;
    }

    // 回退：使用 auto-render 扫描定界符（当 PHP 过滤器未生成 .math-tex 时），包括评论区
    // auto-render 已在动态加载 katex 时一并加载，此处直接使用
    if (typeof window.renderMathInElement === 'undefined') return;

    var renderTargets = document.querySelectorAll('.post-content, .comment-content');
    renderTargets.forEach(function(target) {
        renderMathInElement(target, {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$', right: '$', display: false },
                { left: '\\(', right: '\\)', display: false },
                { left: '\\[', right: '\\]', display: true }
            ],
            throwOnError: false,
            output: 'html'
        });
    });
};

/**
 * 文章目录（Table of Contents）功能
 * 自动提取文章中的 h2/h3 标题，生成目录导航
 * 支持滚动高亮当前目录项，点击平滑滚动定位
 */
window.initTableOfContents = function() {
    var tocWidget = document.getElementById('toc-widget');
    var tocNav = document.getElementById('toc-nav');
    var mobileTocNav = document.getElementById('mobile-toc-nav');
    var mobileTocBtn = document.getElementById('mobile-toc-btn');
    if (!tocWidget || !tocNav) {
        // 非文章页：清理手机端目录状态
        if (mobileTocNav) mobileTocNav.innerHTML = '';
        if (mobileTocBtn) mobileTocBtn.classList.remove('has-toc');
        return;
    }

    var postContent = document.querySelector('.post-content');
    if (!postContent) {
        tocWidget.style.display = 'none';
        if (mobileTocNav) mobileTocNav.innerHTML = '';
        if (mobileTocBtn) mobileTocBtn.classList.remove('has-toc');
        return;
    }

    var headings = postContent.querySelectorAll('h2, h3');
    if (headings.length === 0) {
        tocWidget.style.display = 'none';
        if (mobileTocNav) mobileTocNav.innerHTML = '';
        if (mobileTocBtn) mobileTocBtn.classList.remove('has-toc');
        return;
    }

    // 为标题添加 id（如果没有的话）
    var headingList = [];
    var idCounter = 0;
    headings.forEach(function(heading) {
        if (!heading.id) {
            heading.id = 'toc-heading-' + (++idCounter);
        }
        headingList.push(heading);
    });

    // 构建目录 HTML
    var html = '<ul class="toc-list">';
    headingList.forEach(function(heading, index) {
        var level = heading.tagName.toLowerCase() === 'h2' ? 2 : 3;
        var indent = level === 3 ? ' toc-item-h3' : '';
        html += '<li class="toc-item' + indent + '">';
        html += '<a class="toc-link" href="#' + heading.id + '" data-target="' + heading.id + '">';
        html += heading.textContent.trim();
        html += '</a></li>';
    });
    html += '</ul>';

    tocNav.innerHTML = html;
    if (mobileTocNav) mobileTocNav.innerHTML = html;
    tocWidget.style.display = '';
    if (mobileTocBtn) mobileTocBtn.classList.add('has-toc');

    // 点击目录项平滑滚动（桌面端）
    tocNav.addEventListener('click', function(e) {
        var link = e.target.closest('.toc-link');
        if (!link) return;
        e.preventDefault();
        var targetId = link.getAttribute('data-target');
        var target = document.getElementById(targetId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // 手机端目录项点击通过 document 事件委托处理（见 initMobileToc IIFE），避免 PJAX 重复绑定

    // 滚动高亮当前目录项（同时更新桌面端和手机端）
    var tocLinks = document.querySelectorAll('#toc-nav .toc-link, #mobile-toc-nav .toc-link');
    var scrollHandler = throttle(function() {
        var scrollTop = window.scrollY;
        var currentId = '';
        headingList.forEach(function(heading) {
            if (heading.offsetTop - 80 <= scrollTop) {
                currentId = heading.id;
            }
        });
        tocLinks.forEach(function(link) {
            if (link.getAttribute('data-target') === currentId) {
                link.classList.add('toc-active');
            } else {
                link.classList.remove('toc-active');
            }
        });
    }, 100);

    window.addEventListener('scroll', scrollHandler);
    // 初始触发一次
    scrollHandler();

    // 保存清理函数，PJAX 切换时移除滚动监听
    window._tocScrollHandler = scrollHandler;
};

/**
 * 清理文章目录滚动监听（PJAX 切换前调用）
 */
window.destroyTableOfContents = function() {
    if (window._tocScrollHandler) {
        window.removeEventListener('scroll', window._tocScrollHandler);
        window._tocScrollHandler = null;
    }
    // PJAX 切换前关闭手机端目录侧边栏
    if (typeof window.closeMobileToc === 'function') {
        window.closeMobileToc();
    }
};

/**
 * Markdown 扩展功能初始化
 * 处理任务列表交互、折叠区块、提示框等前端增强
 */
window.initMarkdownExt = function() {
    var postContent = document.querySelector('.post-content');
    if (!postContent) return;
    // 防重复绑定：F5 刷新时 DOMContentLoaded 与 load 都会调用
    if (postContent.getAttribute('data-mdext-bound')) return;
    postContent.setAttribute('data-mdext-bound', '1');

    // 任务列表：允许点击切换勾选状态（视觉反馈，实际 disabled 但添加交互样式）
    var taskCheckboxes = postContent.querySelectorAll('.task-list-checkbox');
    taskCheckboxes.forEach(function(checkbox) {
        checkbox.removeAttribute('disabled');
        checkbox.addEventListener('change', function() {
            var li = this.closest('.task-list-item');
            if (li) {
                if (this.checked) {
                    li.classList.add('task-done');
                } else {
                    li.classList.remove('task-done');
                }
            }
        });
        // 初始化已完成项的样式
        if (checkbox.checked) {
            var li = checkbox.closest('.task-list-item');
            if (li) li.classList.add('task-done');
        }
    });

    // 折叠区块：添加动画支持
    var detailsElements = postContent.querySelectorAll('.post-details');
    detailsElements.forEach(function(details) {
        details.addEventListener('toggle', function() {
            if (this.open) {
                this.classList.add('details-open');
            } else {
                this.classList.remove('details-open');
            }
        });
    });
};

/**
 * 文章提示弹窗关闭功能
 */
window.initArticleAlert = function() {
    var alertBox = document.getElementById('article-alert-box');
    if (!alertBox) return;
    // 防重复绑定：F5 刷新时 DOMContentLoaded 与 load 都会调用
    if (alertBox.getAttribute('data-alert-bound')) return;
    alertBox.setAttribute('data-alert-bound', '1');

    var closeBtn = document.getElementById('article-alert-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            alertBox.style.animation = 'alertSlideOut 0.3s ease forwards';
            setTimeout(function() {
                alertBox.style.display = 'none';
            }, 300);
        });
    }
};

/**
 * 统一表情面板功能（纯自定义实现，不依赖 jQuery-emoji 插件）
 * 包含：颜文字、阿鲁、QQ、微博、贴吧表情
 * 懒加载：点击表情按钮时才加载 emoji.list.js
 */
window.initEmojiPanel = function() {
    var emojiToggle = document.getElementById('emoji-toggle');
    var textarea = document.getElementById('textarea');
    if (!emojiToggle || !textarea) return;

    // 清理 PJAX 切换前残留的旧面板
    var oldPanel = document.getElementById('sf-emoji-container');
    if (oldPanel) oldPanel.remove();

    // 避免重复绑定（PJAX 切换后 emojiToggle 是新元素，不会触发此判断）
    if (emojiToggle._emojiBound) return;
    emojiToggle._emojiBound = true;

    var panel = null;

    // 点击表情按钮
    emojiToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (!panel) {
            // 首次点击，创建面板
            panel = document.createElement('div');
            panel.className = 'sf-emoji-container';
            panel.id = 'sf-emoji-container';
            panel.innerHTML = '<div class="sf-emoji-loading">加载中...</div>';
            document.body.appendChild(panel);
            _showPanel();

            // 懒加载 emoji.list.js
            _loadEmojiList(function() {
                _buildPanelContent();
                _showPanel();
            });
        } else {
            // 切换显示/隐藏
            if (panel.style.display === 'block') {
                panel.style.display = 'none';
            } else {
                _showPanel();
            }
        }
    });

    function _showPanel() {
        if (!panel) return;
        var rect = emojiToggle.getBoundingClientRect();
        panel.style.display = 'block';
        panel.style.top = (window.scrollY + rect.bottom + 4) + 'px';
        panel.style.left = (window.scrollX + rect.left) + 'px';
    }

    // 点击外部关闭面板：document 监听器只绑定一次，避免 pjax 切换累积
    // 通过实时查询当前面板元素，避免持有旧游离节点引用
    if (!window._emojiDocClickBound) {
        window._emojiDocClickBound = true;
        document.addEventListener('click', function(e) {
            var curPanel = document.getElementById('sf-emoji-container');
            var curToggle = document.getElementById('emoji-toggle');
            if (curPanel && curPanel.style.display === 'block'
                && !curPanel.contains(e.target) && e.target !== curToggle) {
                curPanel.style.display = 'none';
            }
        });
    }

    // 动态加载 emoji.list.js
    function _loadEmojiList(callback) {
        if (typeof emojiLists !== 'undefined') {
            callback();
            return;
        }
        var script = document.createElement('script');
        script.src = (window.emojiAssetBase || (window.themeUrl || '') + 'assets/vendor/jquery-emoji') + '/js/emoji.list.js';
        script.onload = callback;
        script.onerror = function() {
            if (panel) panel.innerHTML = '<div class="sf-emoji-loading">表情数据加载失败</div>';
        };
        document.head.appendChild(script);
    }

    // 构建面板内容
    function _buildPanelContent() {
        var basePath = (window.emojiAssetBase || (window.themeUrl || '') + 'assets/vendor/jquery-emoji') + '/images/emoji/';

        var tabs = [
            { id: 'kaomoji', name: '颜文字' },
            { id: 'aru', name: '阿鲁' },
            { id: 'qq', name: 'QQ' },
            { id: 'weibo', name: '微博' },
            { id: 'tieba', name: '贴吧' }
        ];

        var html = '<div class="sf-emoji-tabs"><ul>';
        for (var i = 0; i < tabs.length; i++) {
            html += '<li data-tab="' + tabs[i].id + '"' + (i === 0 ? ' class="active"' : '') + '>' + tabs[i].name + '</li>';
        }
        html += '</ul></div><div class="sf-emoji-content">';

        // 颜文字
        html += '<div class="sf-emoji-tab" data-tab="kaomoji">';
        if (typeof kaomojiLists !== 'undefined') {
            for (var category in kaomojiLists) {
                html += '<div class="sf-kaomoji-label">' + category + '</div>';
                var items = kaomojiLists[category];
                for (var j = 0; j < items.length; j++) {
                    var k = items[j].replace(/"/g, '&quot;');
                    html += '<span class="sf-kaomoji-item" data-insert="' + k + '">' + items[j] + '</span>';
                }
            }
        }
        html += '</div>';

        // 阿鲁
        html += '<div class="sf-emoji-tab" data-tab="aru" style="display:none;">';
        if (typeof emojiLists !== 'undefined') {
            for (var ai = 0; ai < emojiLists.length; ai++) {
                if (emojiLists[ai].name !== '阿鲁') continue;
                var aruCfg = emojiLists[ai];
                var aruPath = basePath + aruCfg.path;
                for (var n = 1; n <= aruCfg.maxNum; n++) {
                    if (aruCfg.excludeNums && aruCfg.excludeNums.indexOf(n) >= 0) continue;
                    html += '<img class="sf-emoji-item" data-src="' + aruPath + n + aruCfg.file + '" data-insert="[aru_' + n + ']" alt="aru' + n + '" />';
                }
                break;
            }
        }
        html += '</div>';

        // QQ
        html += '<div class="sf-emoji-tab" data-tab="qq" style="display:none;">';
        if (typeof emojiLists !== 'undefined') {
            for (var qi = 0; qi < emojiLists.length; qi++) {
                if (emojiLists[qi].name !== 'QQ') continue;
                var qqCfg = emojiLists[qi];
                var qqPath = basePath + qqCfg.path;
                for (var qqKey in qqCfg.emoji) {
                    var qqFile = qqCfg.emoji[qqKey];
                    html += '<img class="sf-emoji-item" data-src="' + qqPath + encodeURIComponent(qqFile) + qqCfg.file + '" data-insert="[qq:' + qqKey + ']" alt="' + qqKey + '" title="' + qqKey + '" />';
                }
                break;
            }
        }
        html += '</div>';

        // 微博
        html += '<div class="sf-emoji-tab" data-tab="weibo" style="display:none;">';
        if (typeof emojiLists !== 'undefined') {
            for (var wi = 0; wi < emojiLists.length; wi++) {
                if (emojiLists[wi].name !== '微博') continue;
                var wbCfg = emojiLists[wi];
                var wbPath = basePath + wbCfg.path;
                for (var wbKey in wbCfg.emoji) {
                    var wbFile = wbCfg.emoji[wbKey];
                    html += '<img class="sf-emoji-item" data-src="' + wbPath + encodeURIComponent(wbFile) + wbCfg.file + '" data-insert="[wb:' + wbKey + ']" alt="' + wbKey + '" title="' + wbKey + '" />';
                }
                break;
            }
        }
        html += '</div>';

        // 贴吧
        html += '<div class="sf-emoji-tab" data-tab="tieba" style="display:none;">';
        if (typeof emojiLists !== 'undefined') {
            for (var ti = 0; ti < emojiLists.length; ti++) {
                if (emojiLists[ti].name !== '贴吧') continue;
                var tbCfg = emojiLists[ti];
                var tbPath = basePath + tbCfg.path;
                for (var tbKey in tbCfg.emoji) {
                    var tbFile = tbCfg.emoji[tbKey];
                    html += '<img class="sf-emoji-item" data-src="' + tbPath + encodeURIComponent(tbFile) + tbCfg.file + '" data-insert="[tb:' + tbKey + ']" alt="' + tbKey + '" title="' + tbKey + '" />';
                }
                break;
            }
        }
        html += '</div>';

        html += '</div>';
        panel.innerHTML = html;

        // 批量加载表情图片，每次5个
        _startBatchLoad(panel);

        // 绑定标签切换
        var tabLis = panel.querySelectorAll('.sf-emoji-tabs li');
        tabLis.forEach(function(li) {
            li.addEventListener('click', function(e) {
                e.stopPropagation();
                var tab = this.getAttribute('data-tab');
                tabLis.forEach(function(l) { l.classList.remove('active'); });
                this.classList.add('active');
                panel.querySelectorAll('.sf-emoji-tab').forEach(function(c) {
                    c.style.display = (c.getAttribute('data-tab') === tab) ? 'block' : 'none';
                });
                // 切换标签时优先加载当前标签的图片
                _startBatchLoad(panel, tab);
            });
        });

        // 绑定表情/颜文字点击
        panel.addEventListener('click', function(e) {
            var target = e.target;
            if (target.classList.contains('sf-emoji-item') || target.classList.contains('sf-kaomoji-item')) {
                e.stopPropagation();
                var text = target.getAttribute('data-insert');
                if (text) {
                    var start = textarea.selectionStart;
                    var end = textarea.selectionEnd;
                    textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
                    textarea.selectionStart = textarea.selectionEnd = start + text.length;
                    textarea.focus();
                }
                panel.style.display = 'none';
            }
        });
    }
};

/**
 * 批量加载表情图片，每次加载5个，完成后再加载下一批
 * @param {Element} container 面板容器
 * @param {string} priorityTab 优先加载的标签ID
 */
function _startBatchLoad(container, priorityTab) {
    var batchSize = 5;

    // 收集所有未加载的图片，优先加载指定标签的
    var allImgs = Array.prototype.slice.call(container.querySelectorAll('img[data-src]'));
    if (allImgs.length === 0) return;

    var pending;
    if (priorityTab) {
        // 将优先标签的图片排到前面
        pending = [];
        var rest = [];
        allImgs.forEach(function(img) {
            var tabDiv = img.closest('.sf-emoji-tab');
            if (tabDiv && tabDiv.getAttribute('data-tab') === priorityTab) {
                pending.push(img);
            } else {
                rest.push(img);
            }
        });
        pending = pending.concat(rest);
    } else {
        pending = allImgs;
    }

    var index = 0;

    function loadNextBatch() {
        if (index >= pending.length) return;

        var batch = pending.slice(index, index + batchSize);
        index += batchSize;

        var loadedCount = 0;
        batch.forEach(function(img) {
            function onDone() {
                loadedCount++;
                img.onload = null;
                img.onerror = null;
                if (loadedCount >= batch.length) {
                    // 下一批稍微延迟，避免阻塞UI
                    setTimeout(loadNextBatch, 50);
                }
            }
            // 检查 data-src 是否存在，避免多个批处理任务并发时
            // 重复处理同一图片导致 src 被设置为 "null"
            var src = img.getAttribute('data-src');
            if (!src) {
                // 已被其他批处理任务处理过，直接跳过
                onDone();
                return;
            }
            img.onload = onDone;
            img.onerror = onDone;
            img.src = src;
            img.removeAttribute('data-src');
        });
    }

    loadNextBatch();
}

/**
 * 视频播放器增强功能
 * 响应式视频容器，支持全屏等控制
 */
window.initVideoPlayer = function() {
    var videoPlayers = document.querySelectorAll('.post-video-player');
    videoPlayers.forEach(function(video) {
        // 防重复绑定：F5 刷新时 DOMContentLoaded 与 load 都会调用
        if (video.getAttribute('data-video-bound')) return;
        video.setAttribute('data-video-bound', '1');
        // 双击全屏
        video.addEventListener('dblclick', function() {
            if (this.requestFullscreen) {
                this.requestFullscreen();
            } else if (this.webkitRequestFullscreen) {
                this.webkitRequestFullscreen();
            } else if (this.msRequestFullscreen) {
                this.msRequestFullscreen();
            }
        });

        // 键盘控制
        video.setAttribute('tabindex', '0');
        video.addEventListener('keydown', function(e) {
            switch(e.key) {
                case ' ':
                case 'k':
                    e.preventDefault();
                    if (this.paused) { this.play(); } else { this.pause(); }
                    break;
                case 'f':
                    e.preventDefault();
                    if (this.requestFullscreen) { this.requestFullscreen(); }
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.currentTime = Math.max(0, this.currentTime - 5);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.currentTime = Math.min(this.duration, this.currentTime + 5);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.volume = Math.min(1, this.volume + 0.1);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    this.volume = Math.max(0, this.volume - 0.1);
                    break;
                case 'm':
                    e.preventDefault();
                    this.muted = !this.muted;
                    break;
            }
        });
    });
};

/**
 * 音乐播放器增强功能
 * 播放时旋转唱片动画
 */
window.initMusicPlayer = function() {
    var musicPlayers = document.querySelectorAll('.post-music-player');
    musicPlayers.forEach(function(player) {
        var audio = player.querySelector('.music-audio');
        var disc = player.querySelector('.music-disc');
        if (!audio || !disc) return;
        // 防重复绑定：F5 刷新时 DOMContentLoaded 与 load 都会调用
        if (audio.getAttribute('data-music-bound')) return;
        audio.setAttribute('data-music-bound', '1');

        // 播放时旋转唱片
        audio.addEventListener('play', function() {
            disc.classList.add('music-disc-spinning');
        });

        audio.addEventListener('pause', function() {
            disc.classList.remove('music-disc-spinning');
        });

        audio.addEventListener('ended', function() {
            disc.classList.remove('music-disc-spinning');
        });
    });
};

/**
 * 手机端文章目录侧边栏开关功能
 * 使用 document 级别事件委托，兼容 PJAX
 */
(function() {
    function openMobileToc() {
        var sidebar = document.getElementById('mobile-toc-sidebar');
        var shade = document.getElementById('mobile-toc-shade');
        if (sidebar) sidebar.classList.add('open');
        if (shade) shade.classList.add('active');
    }

    window.closeMobileToc = function() {
        var sidebar = document.getElementById('mobile-toc-sidebar');
        var shade = document.getElementById('mobile-toc-shade');
        if (sidebar) sidebar.classList.remove('open');
        if (shade) shade.classList.remove('active');
    };

    window.openMobileToc = openMobileToc;

    if (!window._mobileTocDocBound) {
        window._mobileTocDocBound = true;

        document.addEventListener('click', function(e) {
            // 点击触发按钮打开
            if (e.target.closest('#mobile-toc-btn')) {
                e.preventDefault();
                openMobileToc();
                return;
            }
            // 点击关闭按钮关闭
            if (e.target.closest('#mobile-toc-close')) {
                e.preventDefault();
                window.closeMobileToc();
                return;
            }
            // 点击遮罩层关闭
            if (e.target.closest('#mobile-toc-shade')) {
                e.preventDefault();
                window.closeMobileToc();
                return;
            }
            // 点击手机端目录项：平滑滚动并关闭侧边栏
            var mobileTocLink = e.target.closest('#mobile-toc-nav .toc-link');
            if (mobileTocLink) {
                e.preventDefault();
                var targetId = mobileTocLink.getAttribute('data-target');
                var target = document.getElementById(targetId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                window.closeMobileToc();
                return;
            }
        });

        // ESC 键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var sidebar = document.getElementById('mobile-toc-sidebar');
                if (sidebar && sidebar.classList.contains('open')) {
                    window.closeMobileToc();
                }
            }
        });
    }
})();

/**
 * 评论引用文章内容功能
 * 在文章内容区选中文字后，弹出"引用并评论"浮动按钮
 * 点击后将选中内容以 Markdown 引用语法插入到评论框
 * 使用 document 级别事件委托，兼容 PJAX
 */
window.initQuoteComment = function() {
    // 仅绑定一次全局事件监听
    if (window._quoteCommentBound) return;
    window._quoteCommentBound = true;

    var btn = null;
    var MAX_QUOTE_LENGTH = 500; // 引用内容最大字符数，避免过长

    // 创建或获取浮动按钮
    function getBtn() {
        if (btn) return btn;
        btn = document.createElement('div');
        btn.id = 'sf-quote-comment-btn';
        btn.className = 'sf-quote-comment-btn';
        btn.innerHTML = '<i class="fa fa-quote-right"></i> 引用并评论';
        btn.style.display = 'none';
        document.body.appendChild(btn);
        btn.addEventListener('mousedown', function(e) {
            // 阻止点击按钮时清除选区
            e.preventDefault();
        });
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            insertQuote();
        });
        return btn;
    }

    // 隐藏按钮
    function hideBtn() {
        if (btn) btn.style.display = 'none';
    }

    // 检查选区是否在 .post-content 内
    function getQuoteText() {
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return '';
        var range = sel.getRangeAt(0);
        if (range.collapsed) return '';
        var text = sel.toString();
        if (!text) return '';
        text = text.replace(/\s+/g, ' ').trim();
        if (!text) return '';

        // 检查选区是否位于文章内容区
        var container = range.commonAncestorContainer;
        var postContent = null;
        if (container.nodeType === 1) {
            postContent = container.closest('.post-content');
        } else if (container.parentNode) {
            postContent = container.parentNode.closest('.post-content');
        }
        if (!postContent) return '';

        // 排除密码保护框内的选区
        if (postContent.querySelector('.password-protection') && range.intersectsNode) {
            var pwdBox = postContent.querySelector('.password-protection');
            if (pwdBox && range.intersectsNode(pwdBox)) return '';
        }

        return text;
    }

    // 定位并显示按钮
    function showBtnForSelection() {
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) {
            hideBtn();
            return;
        }
        var text = getQuoteText();
        if (!text) {
            hideBtn();
            return;
        }

        var range = sel.getRangeAt(0);
        var rect = range.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            hideBtn();
            return;
        }

        var b = getBtn();
        // 先显示再计算尺寸（offsetWidth 需要元素在文档流中）
        b.style.display = 'block';
        var btnWidth = b.offsetWidth;
        var btnHeight = b.offsetHeight;

        // 定位到选区上方居中
        var top = window.scrollY + rect.top - btnHeight - 8;
        var left = window.scrollX + rect.left + (rect.width / 2) - (btnWidth / 2);
        var arrowOnTop = false; // 箭头默认在底部（按钮位于选区上方）

        // 边界处理
        if (top < window.scrollY) {
            // 选区上方空间不足，放到选区下方，箭头改为在顶部
            top = window.scrollY + rect.bottom + 8;
            arrowOnTop = true;
        }
        if (left < 8) left = 8;
        var maxLeft = window.scrollX + window.innerWidth - btnWidth - 8;
        if (left > maxLeft) left = maxLeft;

        b.style.top = top + 'px';
        b.style.left = left + 'px';
        if (arrowOnTop) {
            b.classList.add('sf-arrow-top');
        } else {
            b.classList.remove('sf-arrow-top');
        }
    }

    // 将引用文本插入到评论框
    function insertQuote() {
        var text = getQuoteText();
        if (!text) {
            hideBtn();
            return;
        }

        // 截断过长引用
        if (text.length > MAX_QUOTE_LENGTH) {
            text = text.substring(0, MAX_QUOTE_LENGTH) + '...';
        }

        // 使用自定义 [quote] 标记，由 PHP 端统一解析为 <blockquote>
        // 不依赖 Markdown 是否开启，避免 > 被转义为 &gt;
        var insertText = '[quote]' + text + '[/quote]\n\n';

        var textarea = document.getElementById('textarea');
        if (!textarea) {
            // 没有评论框（评论可能已关闭），直接返回
            hideBtn();
            // 保留选区，不清除
            return;
        }

        // 在当前光标位置插入
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;

        // 如果当前已有内容，且光标不在行首，前面补一个换行
        var prefix = '';
        if (start > 0 && value.charAt(start - 1) !== '\n') {
            prefix = '\n';
        }

        var newText = value.substring(0, start) + prefix + insertText + value.substring(end);
        textarea.value = newText;

        // 光标移动到引用块之后，方便用户继续输入评论
        var cursorPos = start + prefix.length + insertText.length;
        textarea.selectionStart = textarea.selectionEnd = cursorPos;

        // 清除选区
        window.getSelection().removeAllRanges();
        hideBtn();

        // 触发 input 事件，兼容其他监听 textarea 变化的逻辑
        try {
            var evt = new Event('input', { bubbles: true });
            textarea.dispatchEvent(evt);
        } catch (err) {
            // 旧浏览器回退
            var evt2 = document.createEvent('HTMLEvents');
            evt2.initEvent('input', true, false);
            textarea.dispatchEvent(evt2);
        }

        // 滚动到评论区并聚焦
        var commentsEl = document.getElementById('comments');
        if (commentsEl) {
            commentsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        setTimeout(function() {
            textarea.focus();
        }, 300);
    }

    // 监听 mouseup（在 mouseup 后选区才确定）
    document.addEventListener('mouseup', function(e) {
        // 忽略点击按钮自身的 mouseup
        if (e.target.closest && e.target.closest('#sf-quote-comment-btn')) return;
        // 延迟一帧，确保选区已更新
        setTimeout(showBtnForSelection, 10);
    });

    // 监听选区变化（键盘选择、点击空白处清除等）
    document.addEventListener('selectionchange', function() {
        var text = getQuoteText();
        if (!text) {
            hideBtn();
        }
    });

    // 滚动或窗口大小变化时隐藏按钮（位置会失效）
    window.addEventListener('scroll', throttle(hideBtn, 100), { passive: true });
    window.addEventListener('resize', hideBtn);

    // 切换页面（PJAX）时隐藏按钮
    document.addEventListener('pjax:send', hideBtn);

    // ===== 评论引用块"定位到原文"功能 =====
    // 监听评论中引用块的"定位到原文"按钮点击
    document.addEventListener('click', function(e) {
        var locateBtn = e.target.closest('.quote-locate-btn');
        if (!locateBtn) return;
        e.preventDefault();
        e.stopPropagation();

        var blockquote = locateBtn.closest('.article-quote');
        if (!blockquote) return;

        var quoteText = blockquote.getAttribute('data-quote-text');
        if (!quoteText) {
            if (typeof window.showToast === 'function') {
                window.showToast('无法获取引用内容', 'info');
            }
            return;
        }

        var postContent = document.querySelector('.post-content');
        if (!postContent) {
            if (typeof window.showToast === 'function') {
                window.showToast('未找到文章内容', 'info');
            }
            return;
        }

        // 在文章内容中查找引用文本
        var range = findTextInElement(postContent, quoteText);
        if (range) {
            // 滚动到匹配位置（留出顶部空间）
            var rect = range.getBoundingClientRect();
            window.scrollTo({
                top: window.scrollY + rect.top - 80,
                behavior: 'smooth'
            });
            // 临时高亮匹配的文本
            highlightRange(range);
        } else {
            if (typeof window.showToast === 'function') {
                window.showToast('未在文章中找到对应内容', 'info');
            }
        }
    });

    // 在指定元素内查找文本，返回 Range 对象
    function findTextInElement(root, searchText) {
        if (!searchText) return null;
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        var fullText = '';
        var textNodes = [];

        while (walker.nextNode()) {
            var node = walker.currentNode;
            // 跳过 script/style 内的文本
            var parent = node.parentNode;
            if (parent && (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE')) continue;
            textNodes.push({ node: node, start: fullText.length });
            fullText += node.textContent;
        }

        // 尝试完整匹配
        var index = fullText.indexOf(searchText);

        // 如果完整匹配失败，尝试前 50 个字符（处理引用被截断的情况）
        if (index === -1 && searchText.length > 50) {
            searchText = searchText.substring(0, 50);
            index = fullText.indexOf(searchText);
        }

        // 仍然失败，尝试前 30 个字符
        if (index === -1 && searchText.length > 30) {
            searchText = searchText.substring(0, 30);
            index = fullText.indexOf(searchText);
        }

        if (index === -1) return null;

        // 找到包含起始位置的文本节点
        for (var i = 0; i < textNodes.length; i++) {
            var nodeInfo = textNodes[i];
            var nodeLength = nodeInfo.node.textContent.length;
            var nodeEnd = nodeInfo.start + nodeLength;

            if (nodeInfo.start <= index && index < nodeEnd) {
                var offsetInNode = index - nodeInfo.start;
                var range = document.createRange();
                range.setStart(nodeInfo.node, offsetInNode);

                // 尝试设置结束位置（可能跨越多个节点）
                var remainingLength = searchText.length;
                for (var j = i; j < textNodes.length && remainingLength > 0; j++) {
                    var currentNode = textNodes[j].node;
                    var currentLength = currentNode.textContent.length;
                    var startOffset = (j === i) ? offsetInNode : 0;
                    var availableLength = currentLength - startOffset;

                    if (remainingLength <= availableLength) {
                        range.setEnd(currentNode, startOffset + remainingLength);
                        return range;
                    } else {
                        remainingLength -= availableLength;
                    }
                }
                // 跨节点未完全匹配，只返回起始位置所在节点
                range.setEnd(nodeInfo.node, nodeLength);
                return range;
            }
        }
        return null;
    }

    // 临时高亮 Range 对应的文本
    function highlightRange(range) {
        // 使用 Selection 高亮（兼容跨节点情况）
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);

        // 2 秒后清除高亮
        setTimeout(function() {
            sel.removeAllRanges();
        }, 2000);
    }
};

/**
 * 阅读进度 & 收藏管理（基于 localStorage）
 * 存储 key 设计：
 *   shufei_reading_progress  -> { [cid]: { percent: 0-100, ts: 时间戳 } }  percent=100 表示已看完
 *   shufei_favorites         -> { [cid]: { title, url, ts } }
 */
window.ReadingFav = (function() {
    var PROGRESS_KEY = 'shufei_reading_progress';
    var FAV_KEY = 'shufei_favorites';
    var MAX_FAV = 100;

    function safeParse(key, def) {
        try {
            var raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : def;
        } catch (e) { return def; }
    }
    function safeWrite(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    function getProgress(cid) {
        var data = safeParse(PROGRESS_KEY, {});
        return data[cid] || null;
    }
    function setProgress(cid, percent) {
        if (!cid) return;
        var data = safeParse(PROGRESS_KEY, {});
        // 进度只增不减，避免上下滚动导致已读状态倒退
        // 但 100% 可能是内容未渲染时的误判，允许向下修正
        var prev = data[cid] ? data[cid].percent : 0;
        if (percent < prev && prev < 100) percent = prev;
        data[cid] = { percent: percent, ts: Date.now() };
        safeWrite(PROGRESS_KEY, data);
    }
    function markRead(cid) {
        if (!cid) return;
        var data = safeParse(PROGRESS_KEY, {});
        data[cid] = { percent: 100, ts: Date.now() };
        safeWrite(PROGRESS_KEY, data);
    }

    function isFav(cid) {
        var data = safeParse(FAV_KEY, {});
        return !!data[cid];
    }
    function addFav(cid, info) {
        if (!cid) return false;
        var data = safeParse(FAV_KEY, {});
        if (data[cid]) return false;
        data[cid] = { title: info.title || '', url: info.url || '', ts: Date.now() };
        safeWrite(FAV_KEY, data);
        return true;
    }
    function removeFav(cid) {
        if (!cid) return false;
        var data = safeParse(FAV_KEY, {});
        if (!data[cid]) return false;
        delete data[cid];
        safeWrite(FAV_KEY, data);
        return true;
    }
    function toggleFav(cid, info) {
        if (isFav(cid)) { removeFav(cid); return false; }
        addFav(cid, info); return true;
    }
    function listFav() {
        var data = safeParse(FAV_KEY, {});
        var arr = [];
        for (var k in data) { if (data.hasOwnProperty(k)) arr.push({ cid: k, title: data[k].title, url: data[k].url, ts: data[k].ts }); }
        arr.sort(function(a, b) { return b.ts - a.ts; });
        return arr;
    }

    return {
        getProgress: getProgress,
        setProgress: setProgress,
        markRead: markRead,
        isFav: isFav,
        addFav: addFav,
        removeFav: removeFav,
        toggleFav: toggleFav,
        listFav: listFav
    };
})();

/**
 * 文章详情页：记录滚动阅读进度 + 收藏按钮交互
 */
window.initPostReadingFav = function() {
    var article = document.querySelector('article.post-single');
    var postContent = document.querySelector('.post-single .post-content');
    if (!article || !postContent) return;

    // 防重复绑定：F5 刷新时 main.js(DOMContentLoaded) 与 pjax.js(load) 都会调用
    if (article.getAttribute('data-rf-bound')) return;
    article.setAttribute('data-rf-bound', '1');

    // 从页面获取 cid：优先 meta 上的 data-cid，回退到 body data-cid
    var cid = document.body.getAttribute('data-cid') || article.getAttribute('data-cid');
    if (!cid) return;

    // pjax 模式下强制滚动到顶部
    // pjax 替换 DOM 后可能保留原滚动位置，导致进度计算错误
    if (window.pjaxEnabled && !window.location.hash) {
        window.scrollTo(0, 0);
    }

    // 滚动监听：仅在用户实际滚动时计算并记录进度
    var ticking = false;
    function updateProgress() {
        ticking = false;
        var rect = postContent.getBoundingClientRect();
        var winH = window.innerHeight || document.documentElement.clientHeight;
        var totalH = postContent.offsetHeight;
        // 内容高度为 0 时跳过（DOM 尚未渲染完成）
        if (totalH === 0) return;
        var top = rect.top;
        var scrolled = Math.max(0, -top);
        var readable = Math.max(1, totalH - winH);
        var percent = Math.min(100, Math.max(0, Math.round((scrolled / readable) * 100)));
        if (percent > 0) {
            window.ReadingFav.setProgress(cid, percent);
        }
    }
    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(updateProgress);
            ticking = true;
        }
    }, { passive: true });

    // 短文自动标记已读：延迟检查，确保 pjax 替换后布局已稳定
    // 仅当内容确实较短（高度 > 0 且 <= 视口 80%）时才标记，避免刚进入就误判
    function checkShortArticle() {
        var totalH = postContent.offsetHeight;
        var winH = window.innerHeight || document.documentElement.clientHeight;
        if (totalH > 0 && totalH <= winH * 0.8) {
            window.ReadingFav.setProgress(cid, 100);
        }
    }
    function deferredShortCheck() {
        var imgs = postContent.querySelectorAll('img');
        var unloaded = 0;
        for (var i = 0; i < imgs.length; i++) {
            if (!imgs[i].complete) unloaded++;
        }
        if (unloaded > 0) {
            var pending = unloaded;
            var done = function() {
                pending--;
                if (pending <= 0) setTimeout(checkShortArticle, 200);
            };
            for (var j = 0; j < imgs.length; j++) {
                if (!imgs[j].complete) {
                    imgs[j].addEventListener('load', done);
                    imgs[j].addEventListener('error', done);
                }
            }
            setTimeout(function() { if (pending > 0) checkShortArticle(); }, 3000);
        } else {
            setTimeout(checkShortArticle, 500);
        }
    }
    if (document.readyState === 'complete') {
        deferredShortCheck();
    } else {
        window.addEventListener('load', deferredShortCheck);
    }

    // 收藏按钮：页面内按钮 + 浮动按钮同步状态
    var favBtn = document.getElementById('post-fav-btn');
    var floatFavBtn = document.getElementById('float-fav-btn');

    function syncFavBtn() {
        var fav = window.ReadingFav.isFav(cid);
        if (favBtn) {
            favBtn.classList.toggle('favorited', fav);
            var icon = favBtn.querySelector('i');
            var text = favBtn.querySelector('.fav-text');
            if (icon) icon.className = fav ? 'fa fa-heart' : 'fa fa-heart-o';
            if (text) text.textContent = fav ? '已收藏' : '收藏';
        }
        if (floatFavBtn) {
            floatFavBtn.classList.toggle('favorited', fav);
            var fIcon = floatFavBtn.querySelector('i');
            if (fIcon) fIcon.className = fav ? 'fa fa-heart' : 'fa fa-heart-o';
            floatFavBtn.title = fav ? '取消收藏' : '收藏文章';
        }
    }

    function doToggleFav() {
        var title = document.title || '';
        var url = window.location.href.split('#')[0];
        var nowFav = window.ReadingFav.toggleFav(cid, { title: title, url: url });
        syncFavBtn();
        if (window.showToast) {
            window.showToast(nowFav ? '已加入收藏' : '已取消收藏', nowFav ? 'success' : 'info');
        }
        // 通知导航栏收藏下拉同步
        document.dispatchEvent(new CustomEvent('shufei:favchange'));
    }

    syncFavBtn();
    if (favBtn) favBtn.addEventListener('click', doToggleFav);

    // 浮动收藏按钮：文章页常驻显示，位于返回顶部/手机目录按钮上方
    if (floatFavBtn) {
        if (floatFavBtn.getAttribute('data-float-fav-bound')) {
            // pjax 切换后元素被替换，无需重复绑定；但需重新显示
        }
        floatFavBtn.setAttribute('data-float-fav-bound', '1');
        floatFavBtn.addEventListener('click', function(e) {
            e.preventDefault();
            doToggleFav();
        });
        // 显示浮动收藏按钮
        floatFavBtn.style.display = '';
        // 同步 has-toc 状态：手机端有目录时上移到目录按钮上方
        var mobileTocBtn = document.getElementById('mobile-toc-btn');
        if (mobileTocBtn && mobileTocBtn.classList.contains('has-toc')) {
            floatFavBtn.classList.add('has-toc');
        }
        // 监听 has-toc 变化（目录初始化可能在 initPostReadingFav 之后）
        // 断开旧 observer 避免内存泄漏（pjax 切换 N 次会累积 N 个 observer）
        if (window._tocObserver) {
            window._tocObserver.disconnect();
            window._tocObserver = null;
        }
        window._tocObserver = new MutationObserver(function() {
            var curFloatBtn = document.getElementById('float-fav-btn');
            var curTocBtn = document.getElementById('mobile-toc-btn');
            if (!curFloatBtn || !curTocBtn) return;
            if (curTocBtn.classList.contains('has-toc')) {
                curFloatBtn.classList.add('has-toc');
            } else {
                curFloatBtn.classList.remove('has-toc');
            }
        });
        if (mobileTocBtn) {
            window._tocObserver.observe(mobileTocBtn, { attributes: true, attributeFilter: ['class'] });
        }
        // 延迟显示动画
        setTimeout(function() { floatFavBtn.classList.add('show'); }, 100);
        // 滚动时保持显示（仅绑定一次到 window，避免 pjax 切换累积监听器）
        if (!window.__floatFavScrollBound) {
            window.__floatFavScrollBound = true;
            window.addEventListener('scroll', function() {
                var fb = document.getElementById('float-fav-btn');
                if (fb) fb.classList.add('show');
            }, { passive: true });
        }
    }

    // 继续上次阅读：若有进度记录（0 < percent < 100），显示提示条
    var prevProgress = window.ReadingFav.getProgress(cid);
    if (prevProgress && prevProgress.percent > 0 && prevProgress.percent < 100) {
        // 等待图片加载后再插入，避免布局抖动
        function insertResumeBar() {
            // 避免重复插入
            if (article.querySelector('.resume-reading')) return;
            var bar = document.createElement('div');
            bar.className = 'resume-reading';
            bar.innerHTML = '<i class="fa fa-bookmark"></i>'
                + '<span class="resume-text">继续上次阅读（<span class="resume-percent">' + prevProgress.percent + '%</span>）</span>'
                + '<i class="fa fa-arrow-right"></i>'
                + '<i class="fa fa-times resume-close" title="关闭"></i>';
            // 插入到文章内容前
            postContent.parentNode.insertBefore(bar, postContent);
            bar.addEventListener('click', function(e) {
                if (e.target.classList.contains('resume-close')) {
                    bar.remove();
                    return;
                }
                // 根据保存的百分比反推滚动位置
                var totalH = postContent.offsetHeight;
                var winH = window.innerHeight || document.documentElement.clientHeight;
                var readable = Math.max(1, totalH - winH);
                var scrolled = Math.round(prevProgress.percent / 100 * readable);
                var target = postContent.getBoundingClientRect().top + window.scrollY + scrolled - 40;
                window.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
                bar.remove();
            });
        }
        if (document.readyState === 'complete') {
            setTimeout(insertResumeBar, 300);
        } else {
            window.addEventListener('load', function() { setTimeout(insertResumeBar, 300); });
        }
    }
};

/**
 * 文章列表：在每张卡片上标记阅读状态/进度 + 收藏标记
 */
window.initListReadingMarks = function() {
    // 列表容器类名为 post-list-classic / post-list-card 等（不含 .post-list）
    var cards = document.querySelectorAll('[class*="post-list-"] article.post[data-cid]');
    if (!cards.length) return;
    for (var i = 0; i < cards.length; i++) {
        var card = cards[i];
        // 避免重复处理
        if (card.getAttribute('data-reading-mark')) continue;
        card.setAttribute('data-reading-mark', '1');

        var cid = card.getAttribute('data-cid');
        if (!cid) continue;

        var hasReading = false;
        var p = window.ReadingFav.getProgress(cid);
        if (p) {
            hasReading = true;
            var badge = document.createElement('div');
            badge.className = 'reading-badge';
            if (p.percent >= 100) {
                badge.className += ' read-done';
                badge.innerHTML = '<i class="fa fa-check-circle"></i> 已看';
            } else {
                badge.className += ' reading';
                badge.innerHTML = '<i class="fa fa-bookmark-o"></i> 已看 ' + p.percent + '%';
            }
            card.appendChild(badge);
        }

        // 已收藏的文章显示收藏标记
        if (window.ReadingFav.isFav(cid)) {
            var favBadge = document.createElement('div');
            favBadge.className = 'fav-badge';
            if (hasReading) favBadge.className += ' has-reading';
            favBadge.innerHTML = '<i class="fa fa-heart"></i>';
            favBadge.title = '已收藏';
            card.appendChild(favBadge);
            // 极简模式下：添加 class 用于 CSS 留白控制
            if (card.classList.contains('minimal-item')) {
                card.classList.add('has-fav');
                // 极简模式：把收藏徽章移入右侧 flex 容器，避免绝对定位与标题重叠
                var minimalRow = card.querySelector('.minimal-row');
                if (minimalRow && !minimalRow.querySelector('.minimal-badges')) {
                    var badgesWrap = document.createElement('div');
                    badgesWrap.className = 'minimal-badges';
                    minimalRow.appendChild(badgesWrap);
                }
                var targetWrap = minimalRow ? minimalRow.querySelector('.minimal-badges') : null;
                if (targetWrap && favBadge.parentNode === card) {
                    targetWrap.appendChild(favBadge);
                }
            }
        }
    }
};

/**
 * 顶部导航栏：收藏下拉
 */
window.initFavDropdown = function() {
    var nav = document.getElementById('header-nav-fav');
    if (!nav) return;

    var btn = nav.querySelector('.nav-fav-btn');
    var panel = nav.querySelector('.nav-fav-panel');
    if (!btn || !panel) return;

    // 防重复绑定：F5 刷新时 main.js(DOMContentLoaded) 与 pjax.js(load) 都会调用
    // pjax 切换页面时 header 被替换为新元素，新元素无此标记会正常绑定
    if (btn.getAttribute('data-fav-bound')) return;
    btn.setAttribute('data-fav-bound', '1');

    function render() {
        var list = window.ReadingFav.listFav();
        var listEl = panel.querySelector('.fav-list');
        if (!listEl) return;
        if (!list.length) {
            listEl.innerHTML = '<li class="fav-empty"><i class="fa fa-heart-o"></i> 暂无收藏</li>';
            return;
        }
        var html = '';
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            var safeTitle = (item.title || '').replace(/[<>&"]/g, function(c) {
                return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c];
            });
            html += '<li class="fav-item" data-cid="' + item.cid + '">'
                + '<a href="' + item.url + '" class="fav-link" title="' + safeTitle + '">'
                + '<i class="fa fa-heart"></i>'
                + '<span class="fav-title">' + safeTitle + '</span>'
                + '</a>'
                + '<button class="fav-remove" title="取消收藏" data-cid="' + item.cid + '"><i class="fa fa-times"></i></button>'
                + '</li>';
        }
        listEl.innerHTML = html;

        // 绑定移除按钮
        var removes = listEl.querySelectorAll('.fav-remove');
        for (var j = 0; j < removes.length; j++) {
            removes[j].addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var cid = this.getAttribute('data-cid');
                window.ReadingFav.removeFav(cid);
                render();
                document.dispatchEvent(new CustomEvent('shufei:favchange'));
            });
        }

        // 为收藏链接绑定 pjax 加载（动态生成的链接未被 Pjax 自动绑定）
        var links = listEl.querySelectorAll('.fav-link');
        for (var k = 0; k < links.length; k++) {
            links[k].addEventListener('click', function(e) {
                if (window.shufeiPjax) {
                    e.preventDefault();
                    panel.classList.remove('fav-panel-open');
                    window.shufeiPjax.loadUrl(this.href);
                }
            });
        }
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var isOpen = panel.classList.toggle('fav-panel-open');
        if (isOpen) render();
    });

    // document 级监听器只绑定一次，避免 pjax 切换累积
    // 回调内实时查询当前 nav/panel，避免持有旧游离节点引用
    if (!window._favDropdownDocBound) {
        window._favDropdownDocBound = true;
        // 点击外部关闭
        document.addEventListener('click', function(e) {
            var curNav = document.getElementById('header-nav-fav');
            if (!curNav) return;
            var curPanel = curNav.querySelector('.nav-fav-panel');
            if (curPanel && !curNav.contains(e.target)) {
                curPanel.classList.remove('fav-panel-open');
            }
        });
        // 收藏变化时若面板打开则刷新
        document.addEventListener('shufei:favchange', function() {
            var curNav = document.getElementById('header-nav-fav');
            if (!curNav) return;
            var curPanel = curNav.querySelector('.nav-fav-panel');
            if (curPanel && curPanel.classList.contains('fav-panel-open')) {
                var curList = curPanel.querySelector('.fav-list');
                if (curList) {
                    // 复用 render 逻辑：重新渲染当前面板
                    var list = window.ReadingFav.listFav();
                    if (!list.length) {
                        curList.innerHTML = '<li class="fav-empty"><i class="fa fa-heart-o"></i> 暂无收藏</li>';
                        return;
                    }
                    var html = '';
                    for (var i = 0; i < list.length; i++) {
                        var item = list[i];
                        var safeTitle = (item.title || '').replace(/[<>&"]/g, function(c) {
                            return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c];
                        });
                        html += '<li class="fav-item" data-cid="' + item.cid + '">'
                            + '<a href="' + item.url + '" class="fav-link" title="' + safeTitle + '">'
                            + '<i class="fa fa-heart"></i>'
                            + '<span class="fav-title">' + safeTitle + '</span>'
                            + '</a>'
                            + '<button class="fav-remove" title="取消收藏" data-cid="' + item.cid + '"><i class="fa fa-times"></i></button>'
                            + '</li>';
                    }
                    curList.innerHTML = html;
                    // 重新绑定移除按钮
                    var removes = curList.querySelectorAll('.fav-remove');
                    for (var j = 0; j < removes.length; j++) {
                        removes[j].addEventListener('click', function(ev) {
                            ev.preventDefault();
                            ev.stopPropagation();
                            var cid = this.getAttribute('data-cid');
                            window.ReadingFav.removeFav(cid);
                            document.dispatchEvent(new CustomEvent('shufei:favchange'));
                        });
                    }
                    // 重新绑定 pjax 加载链接
                    var links = curList.querySelectorAll('.fav-link');
                    for (var k = 0; k < links.length; k++) {
                        links[k].addEventListener('click', function(ev) {
                            if (window.shufeiPjax) {
                                ev.preventDefault();
                                if (curPanel) curPanel.classList.remove('fav-panel-open');
                                window.shufeiPjax.loadUrl(this.href);
                            }
                        });
                    }
                }
            }
        });
    }

    render();
};

/**
 * 顶部搜索框：默认收起，点击搜索图标展开输入框
 * 输入框为空失焦时自动收起，按 Escape 收起
 */
window.initSearchToggle = function() {
    var form = document.getElementById('search');
    if (!form) return;
    // 防重复绑定（pjax 切换后 header 被替换，新元素无此标记会正常绑定）
    if (form.getAttribute('data-search-bound')) return;
    form.setAttribute('data-search-bound', '1');

    var input = form.querySelector('input#s');
    var btn = form.querySelector('button.submit');
    if (!input || !btn) return;

    function expand() {
        form.classList.add('search-expanded');
        setTimeout(function() { input.focus(); }, 120);
    }
    function collapse() {
        if (input.value.trim() === '') {
            form.classList.remove('search-expanded');
        }
    }

    // 点击搜索按钮：未展开时展开并阻止提交；已展开则正常提交
    btn.addEventListener('click', function(e) {
        if (!form.classList.contains('search-expanded')) {
            e.preventDefault();
            expand();
        }
    });

    // 输入框失焦：内容为空则收起
    input.addEventListener('blur', function() {
        setTimeout(collapse, 150);
    });

    // Escape 键收起
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            input.value = '';
            form.classList.remove('search-expanded');
            input.blur();
        }
    });
};

/**
 * 验证码组件初始化（非 Pjax 模式）
 * Pjax 模式由 pjax.js 负责初始化；未开启 Pjax 时由本函数渲染 Turnstile / 极验组件
 */
window.initCaptchaWidgets = function() {
    if (window.pjaxEnabled) return;

    // ===== Cloudflare Turnstile（render=explicit 模式需手动渲染）=====
    var turnstileContainer = document.getElementById('cf-turnstile');
    if (turnstileContainer && !turnstileContainer.getAttribute('data-turnstile-rendered')) {
        var turnstileTimer = setInterval(function() {
            if (typeof window.turnstile !== 'undefined') {
                clearInterval(turnstileTimer);
                if (turnstileContainer.querySelector('iframe')) return;
                try {
                    var widgetId = window.turnstile.render('#cf-turnstile');
                    if (widgetId) {
                        turnstileContainer.setAttribute('data-turnstile-rendered', 'true');
                        turnstileContainer.setAttribute('data-turnstile-widget-id', widgetId);
                    }
                } catch (e) {}
            }
        }, 100);
        setTimeout(function() { clearInterval(turnstileTimer); }, 10000);
    }

    // ===== 极验 Geetest v4 =====
    var geetestContainer = document.getElementById('geetest-captcha');
    if (!geetestContainer || geetestContainer.getAttribute('data-geetest-init')) return;

    var captchaId = geetestContainer.getAttribute('data-captcha-id');
    if (!captchaId) return;

    window.geetestCaptchaObj = null;
    window.geetestResult = null;

    // SDK 异步加载，轮询等待就绪后初始化
    var geetestTimer = setInterval(function() {
        if (typeof window.initGeetest4 !== 'undefined') {
            clearInterval(geetestTimer);
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
                            var form = document.getElementById('comment-form');
                            if (form) {
                                // 写入隐藏字段，随表单提交
                                var fields = ['lot_number', 'captcha_output', 'pass_token', 'gen_time'];
                                for (var i = 0; i < fields.length; i++) {
                                    var input = form.querySelector('input[name="geetest_' + fields[i] + '"]');
                                    if (!input) {
                                        input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'geetest_' + fields[i];
                                        form.appendChild(input);
                                    }
                                    input.value = result[fields[i]] || '';
                                }
                                // 原生 submit() 不触发事件监听，避免循环拦截
                                form.submit();
                            }
                        }
                    });
                    captcha.onError(function () {
                        window.geetestResult = null;
                        if (window.showToast) window.showToast('人机验证失败，请重试', 'error');
                    });
                    captcha.onClose(function () {
                        if (window.showToast) window.showToast('请完成人机验证', 'info');
                    });
                    window.geetestCaptchaObj = captcha;
                });
            } catch (e) {}
        }
    }, 200);
    setTimeout(function() { clearInterval(geetestTimer); }, 15000);

    // 拦截表单提交：未完成极验验证时弹出验证码
    var commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            var geetestValid = (window.geetestCaptchaObj && typeof window.geetestCaptchaObj.getValidate === 'function')
                ? window.geetestCaptchaObj.getValidate()
                : null;
            if (!geetestValid) {
                e.preventDefault();
                if (window.geetestCaptchaObj) {
                    try { window.geetestCaptchaObj.showCaptcha(); } catch (err) {
                        if (window.showToast) window.showToast('人机验证加载中，请稍后重试', 'error');
                    }
                } else {
                    if (window.showToast) window.showToast('人机验证正在加载，请稍后重试', 'error');
                }
                return;
            }
            // 验证通过，写入隐藏字段（onSuccess 中已写入，此处为兜底）
            var fields = ['lot_number', 'captcha_output', 'pass_token', 'gen_time'];
            for (var i = 0; i < fields.length; i++) {
                var input = commentForm.querySelector('input[name="geetest_' + fields[i] + '"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'geetest_' + fields[i];
                    commentForm.appendChild(input);
                }
                input.value = geetestValid[fields[i]] || '';
            }
        });
    }
};

// ==================== 天气卡片 ====================
window.initWeather = function(force) {
    var card = document.getElementById('weather-card');
    if (!card) return;
    // 防重复绑定：pjax 切换或 F5 刷新时避免重复请求（force=true 时强制刷新）
    if (!force && card.getAttribute('data-weather-bound')) return;
    card.setAttribute('data-weather-bound', '1');

    // 缓存 key（10 分钟有效）
    var CACHE_KEY = 'sf_weather_cache';
    var CACHE_TTL = 10 * 60 * 1000;

    function getWeatherIcon(code, isDay) {
        // 根据 WMO 天气代码返回 FontAwesome 图标
        if (code === 0) return isDay ? 'fa-sun-o' : 'fa-moon-o';
        if (code === 1) return isDay ? 'fa-cloud' : 'fa-moon-o';
        if (code === 2) return isDay ? 'fa-sun-o' : 'fa-moon-o';
        if (code === 3) return 'fa-cloud';
        if (code === 45 || code === 48) return 'fa-smog';
        if (code >= 51 && code <= 57) return 'fa-tint';
        if (code >= 56 && code <= 57) return 'fa-tint';
        if (code >= 61 && code <= 65) return 'fa-umbrella';
        if (code >= 66 && code <= 67) return 'fa-umbrella';
        if (code >= 71 && code <= 77) return 'fa-snowflake-o';
        if (code >= 80 && code <= 82) return 'fa-umbrella';
        if (code >= 85 && code <= 86) return 'fa-snowflake-o';
        if (code >= 95 && code <= 99) return 'fa-bolt';
        return isDay ? 'fa-sun-o' : 'fa-moon-o';
    }

    // 返回天气类型字符串（用于 CSS 动画 class）
    function getWeatherType(code, isDay) {
        if (code === 0) return isDay ? 'sunny' : 'night';
        if (code === 1 || code === 2) return isDay ? 'cloudy' : 'night';
        if (code === 3) return 'overcast';
        if (code === 45 || code === 48) return 'fog';
        if (code >= 51 && code <= 67) return 'rain';
        if (code >= 71 && code <= 86) return 'snow';
        if (code >= 95) return 'thunder';
        return isDay ? 'sunny' : 'night';
    }

    function getWeatherGradient(code, isDay) {
        // 根据天气代码和昼夜返回渐变背景（柔化处理，避免过亮）
        if (!isDay) {
            // 夜间：深沉色调，营造月夜氛围
            if (code === 0) return 'linear-gradient(135deg, #1a2151, #2c3e7a)'; // 晴夜 - 深蓝
            if (code === 1 || code === 2) return 'linear-gradient(135deg, #1c2545, #2e385f)'; // 多云夜
            if (code === 3) return 'linear-gradient(135deg, #1f2937, #374151)'; // 阴夜
            if (code === 45 || code === 48) return 'linear-gradient(135deg, #2a3447, #3d4a5f)'; // 雾夜
            if (code >= 51 && code <= 67) return 'linear-gradient(135deg, #16212e, #283446)'; // 雨夜
            if (code >= 71 && code <= 86) return 'linear-gradient(135deg, #1f2d45, #3a4f73)'; // 雪夜
            if (code >= 95) return 'linear-gradient(135deg, #0d1421, #1f2937)'; // 雷暴夜
            return 'linear-gradient(135deg, #1a2151, #2c3e7a)';
        }
        // 白天：柔化色调，避免过亮
        if (code === 0) return 'linear-gradient(135deg, #F3986A, #E15B6B)'; // 晴 - 柔暖橙红
        if (code === 1 || code === 2) return 'linear-gradient(135deg, #5BA8E8, #5DC8E8)'; // 多云 - 柔天蓝
        if (code === 3) return 'linear-gradient(135deg, #7B8794, #5A6473)'; // 阴 - 中灰
        if (code === 45 || code === 48) return 'linear-gradient(135deg, #8E9BAC, #6B7A8F)'; // 雾 - 中灰蓝
        if (code >= 51 && code <= 57) return 'linear-gradient(135deg, #5A7A9E, #3F5878)'; // 毛毛雨
        if (code >= 61 && code <= 67) return 'linear-gradient(135deg, #4A6B8A, #2C3E50)'; // 雨
        if (code >= 71 && code <= 77) return 'linear-gradient(135deg, #7AA8D6, #9CC2E2)'; // 雪 - 柔雪蓝
        if (code >= 80 && code <= 82) return 'linear-gradient(135deg, #4A6B8A, #2C3E50)'; // 阵雨
        if (code >= 85 && code <= 86) return 'linear-gradient(135deg, #7AA8D6, #9CC2E2)'; // 阵雪
        if (code >= 95) return 'linear-gradient(135deg, #3D4A5F, #1F2937)'; // 雷暴
        return 'linear-gradient(135deg, #F3986A, #E15B6B)';
    }

    function renderWeather(data) {
        var w = data.weather;
        var hasWeather = w && !Array.isArray(w) && w.weather_desc;
        var location = data.city && data.city !== '0' ? data.city : (data.province && data.province !== '0' ? data.province : data.country);

        if (!hasWeather) {
            card.innerHTML = '<div class="weather-error">' +
                '<i class="fa fa-map-marker"></i>' +
                '<span>暂无天气数据</span>' +
                '<small>' + escapeHtml(location || '未知地区') + '</small>' +
            '</div>';
            return;
        }

        var tempRaw = w.temperature !== null && w.temperature !== undefined ? Math.round(w.temperature) : null;
        var temp = tempRaw !== null ? tempRaw : '--';
        var feelsLike = w.feels_like !== null && w.feels_like !== undefined ? Math.round(w.feels_like) : null;
        var icon = getWeatherIcon(w.weather_code, w.is_day);
        var gradient = getWeatherGradient(w.weather_code, w.is_day);
        var weatherType = getWeatherType(w.weather_code, w.is_day);
        var isDay = w.is_day;
        var updateTime = new Date().toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });

        // 生成天气详情条项目（仅保留核心两项，保持卡片紧凑）
        var detailItems = [];
        if (w.humidity !== null && w.humidity !== undefined) {
            detailItems.push('<div class="weather-detail-chip"><i class="fa fa-tint"></i><span>湿度 ' + w.humidity + '%</span></div>');
        }
        if (w.wind_speed !== null && w.wind_speed !== undefined) {
            var windText = w.wind_direction ? (w.wind_direction + ' ' + w.wind_speed + 'km/h') : (w.wind_speed + 'km/h');
            detailItems.push('<div class="weather-detail-chip"><i class="fa fa-flag"></i><span>' + windText + '</span></div>');
        }

        // 生成动态粒子
        var particles = generateParticles(weatherType);

        var timeClass = isDay ? 'weather-time-day' : 'weather-time-night';

        var html = '<div class="weather-glass weather-type-' + weatherType + ' ' + timeClass + ' weather-enter">' +
            '<div class="weather-glass-bg" style="background: ' + gradient + ';"></div>' +
            '<div class="weather-particles">' + particles + '</div>' +
            '<div class="weather-glass-shine"></div>' +
            '<div class="weather-glass-shine-2"></div>' +
            '<div class="weather-glass-shine-3"></div>' +
            '<div class="weather-glass-sweep"></div>' +
            '<div class="weather-top">' +
                '<div class="weather-location">' +
                    '<i class="fa fa-map-marker"></i>' +
                    '<span>' + escapeHtml(location || '未知') + '</span>' +
                '</div>' +
                '<div class="weather-desc">' + escapeHtml(w.weather_desc || '') + '</div>' +
            '</div>' +
            '<div class="weather-body">' +
                '<div class="weather-icon-wrap">' +
                    '<div class="weather-icon"><i class="fa ' + icon + '"></i></div>' +
                '</div>' +
                '<div class="weather-info">' +
                    '<div class="weather-temp weather-temp-enter" data-target="' + (tempRaw !== null ? tempRaw : '') + '">' + temp + '<sup>°</sup></div>' +
                    '<div class="weather-feels">' + (feelsLike !== null ? '体感 ' + feelsLike + '°' : '') + '</div>' +
                '</div>' +
            '</div>' +
            (detailItems.length > 0 ? '<div class="weather-details-bar">' + detailItems.join('') + '</div>' : '') +
            '<div class="weather-footer">' +
                '<div class="weather-update">' +
                    '<i class="fa fa-clock-o"></i>' +
                    '<span>' + updateTime + ' 更新</span>' +
                '</div>' +
                '<div class="weather-refresh" onclick="window.initWeather(true)" title="刷新天气">' +
                    '<i class="fa fa-refresh"></i>' +
                '</div>' +
            '</div>' +
        '</div>';

        card.innerHTML = html;
        card.setAttribute('data-rendered', '1');

        // 温度数字递增动画
        setTimeout(function() {
            animateTempNumber(card);
        }, 50);
    }

    function generateParticles(type) {
        var count = 0;
        var particleClass = '';
        if (type === 'rain') { count = 12; particleClass = 'weather-particle-rain'; }
        else if (type === 'snow') { count = 10; particleClass = 'weather-particle-snow'; }
        else if (type === 'sunny') { count = 4; particleClass = 'weather-particle-ray'; }
        else if (type === 'night') { count = 8; particleClass = 'weather-particle-star'; }
        else if (type === 'cloudy' || type === 'overcast') { count = 3; particleClass = 'weather-particle-cloud'; }
        else if (type === 'thunder') { count = 14; particleClass = 'weather-particle-rain'; }

        if (count === 0) return '';
        var html = '';
        for (var i = 0; i < count; i++) {
            var left = Math.random() * 100;
            var delay = Math.random() * 3;
            var duration = 2 + Math.random() * 3;
            var size = 0.5 + Math.random() * 1.5;
            html += '<span class="' + particleClass + '" style="left:' + left + '%;animation-delay:' + delay + 's;animation-duration:' + duration + 's;--particle-size:' + size + 'px;"></span>';
        }
        return html;
    }

    function animateTempNumber(card) {
        var tempEl = card.querySelector('.weather-temp');
        if (!tempEl) return;
        var target = tempEl.getAttribute('data-target');
        if (!target || target === '') return;
        target = parseInt(target, 10);
        if (isNaN(target)) return;

        var start = 0;
        var duration = 800;
        var startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            var current = Math.round(start + (target - start) * ease);
            tempEl.innerHTML = current + '<sup>°</sup>';
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }
        requestAnimationFrame(step);
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }

    function renderError(msg) {
        card.innerHTML = '<div class="weather-error">' +
            '<i class="fa fa-exclamation-circle"></i>' +
            '<span>' + escapeHtml(msg || '天气获取失败') + '</span>' +
            '<small><a href="javascript:void(0)" onclick="window.initWeather(true)">点击重试</a></small>' +
        '</div>';
    }

    // 尝试读取缓存（force 模式跳过缓存）
    if (!force) {
        try {
            var cached = localStorage.getItem(CACHE_KEY);
            if (cached) {
                var parsed = JSON.parse(cached);
                if (Date.now() - parsed.ts < CACHE_TTL) {
                    renderWeather(parsed.data);
                    return;
                }
            }
        } catch (e) {}
    }

    // 请求接口
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'https://api.lwcat.cn/api/ip/', true);
    xhr.timeout = 8000;
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.code === 0 && resp.data) {
                    // 写入缓存
                    try {
                        localStorage.setItem(CACHE_KEY, JSON.stringify({ ts: Date.now(), data: resp.data }));
                    } catch (e) {}
                    renderWeather(resp.data);
                } else {
                    renderError(resp.message || '天气数据异常');
                }
            } catch (e) {
                renderError('解析天气数据失败');
            }
        } else {
            renderError('天气服务暂不可用');
        }
    };
    xhr.ontimeout = function() {
        renderError('请求超时，请稍后重试');
    };
    xhr.onerror = function() {
        renderError('网络异常，请稍后重试');
    };
    xhr.send();
};

document.addEventListener('DOMContentLoaded', function() {
    // 初始化夜间模式（优先执行，避免页面闪烁）
    window.initDarkMode();

    // 初始化验证码组件（非 Pjax 模式；Pjax 模式由 pjax.js 负责）
    window.initCaptchaWidgets();

    // 初始化阅读进度 & 收藏功能
    window.initPostReadingFav();
    window.initListReadingMarks();
    window.initFavDropdown();

    // 初始化搜索框展开/收起
    window.initSearchToggle();

    // 初始化侧边栏折叠功能
    window.initCollapsibleSidebar();

    // 初始化分类子分类折叠功能
    window.initCategoryCollapse();

    // 返回顶部功能
    const backToTop = document.getElementById('back-to-top');
    const mobileTocBtn = document.getElementById('mobile-toc-btn');
    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // 滚动显示/隐藏（节流）— 同时控制返回顶部和手机端目录按钮
        window.addEventListener('scroll', throttle(function() {
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
                if (mobileTocBtn) mobileTocBtn.classList.add('show');
            } else {
                backToTop.classList.remove('show');
                if (mobileTocBtn) mobileTocBtn.classList.remove('show');
            }
        }, 150));
    }
    
    // 初始化移动端菜单
    window.initMobileMenu();
    
    // 初始化密码文章表单（AJAX 验证）
    window.initPasswordForms();
    
    // 延迟执行以确保Prism完全加载
    setTimeout(window.initPrismHighlight, 200);
    
    // 为代码块添加复制功能
    setTimeout(window.initCopyButtons, 250);
    
    // 延迟执行以确保 Lightbox3 完全加载
    setTimeout(window.initLightbox, 300);
    
    // 初始化点赞功能
    window.initPostLike();

    // 初始化文章投票功能
    window.initPostVote();

    // 初始化浏览量统计
    window.initPostViews();

    // 初始化缩略图失效回退（图片失效时替换为随机图片）
    window.initPostThumbFallback();

    // 初始化评论点赞和排序
    window.initCommentLike();
    window.initCommentSort();

    // 初始化评论 IP 归属地显示
    window.initCommentIpRegions();
    
    // 初始化 Mermaid 图表渲染
    setTimeout(window.initMermaid, 350);
    
    // 初始化 ECharts 图表渲染
    setTimeout(window.initECharts, 400);
    
    // 初始化 KaTeX 数学公式渲染
    setTimeout(window.initKaTeX, 450);

    // 初始化 Markdown 扩展功能
    setTimeout(window.initMarkdownExt, 500);

    // 初始化文章目录
    setTimeout(window.initTableOfContents, 520);

    // 初始化文章提示弹窗关闭功能
    window.initArticleAlert();

    // 初始化统一表情面板（包含颜文字）
    setTimeout(window.initEmojiPanel, 600);

    // 初始化视频播放器增强
    window.initVideoPlayer();

    // 初始化音乐播放器增强
    window.initMusicPlayer();

    // 初始化评论引用文章内容功能
    window.initQuoteComment();

    // 初始化天气卡片
    window.initWeather();

});
