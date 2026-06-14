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
    retryCount = retryCount || 0;
    if (checkLib('Prism', function() { window.initPrismHighlight(retryCount + 1); }, retryCount)) return;

    const codeBlocks = document.querySelectorAll('.post-content pre code');
    codeBlocks.forEach(function(code) {
        // 跳过 mermaid 和 echarts 代码块，由各自的渲染器处理
        // Typecho 生成 lang-xxx 格式，需同时检查两种格式
        if (code.classList.contains('language-mermaid') || code.classList.contains('lang-mermaid') ||
            code.classList.contains('language-echarts') || code.classList.contains('lang-echarts')) {
            return;
        }
        const className = code.className;
        const langMatch = className.match(/lang-(\w+)/);
        if (langMatch) {
            const language = langMatch[1];
            code.className = 'language-' + language;
            code.innerHTML = htmlToText(code.innerHTML);
        }
    });

    Prism.highlightAll();
};

window.initLightbox = function(retryCount) {
    retryCount = retryCount || 0;
    if (checkLib('jQuery', function() { window.initLightbox(retryCount + 1); }, retryCount)) return;
    if (checkLib('lightbox', function() { window.initLightbox(retryCount + 1); }, retryCount)) return;
    
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
    
    if (typeof lightbox !== 'undefined' && typeof lightbox.option === 'function') {
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'fadeDuration': 200,
            'imageFadeDuration': 200,
            'disableScrolling': true,
            'fitImagesInViewport': true,
            'positionFromTop': 50
        });
    }
};

window.initCopyButtons = function() {
    const preBlocks = document.querySelectorAll('.post-content pre');
    preBlocks.forEach(function(pre) {
        pre.setAttribute('tabindex', '0');
        
        var existingBtn = pre.querySelector('.copy-code-btn');
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
        
        pre.style.position = 'relative';
        pre.appendChild(copyBtn);
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

                            // 更新点赞盒子中的点赞数
                            var metaLikes = document.querySelector('.post-likes-count[data-cid="' + cid + '"]');
                            if (metaLikes) metaLikes.textContent = data.likes;
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

        xhr.send('cid=' + encodeURIComponent(cid));
    });
};

/**
 * 文章浏览量统计
 */
window.initPostViews = function() {
    var viewsCount = document.querySelector('.post-views-count');
    if (!viewsCount) return;

    var cid = viewsCount.getAttribute('data-cid');
    if (!cid) return;

    var themeUrl = window.themeUrl || '';

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

        xhr.send('cid=' + encodeURIComponent(cid));
    }, 1500);
};

/**
 * Mermaid 图表渲染功能
 */
window.initMermaid = function(retryCount) {
    if (!window.mermaidEnabled) return;
    retryCount = retryCount || 0;
    if (checkLib('mermaid', function() { window.initMermaid(retryCount + 1); }, retryCount)) return;

    // 初始化 Mermaid 配置（仅首次）
    if (!window._mermaidInitialized) {
        mermaid.initialize({
            startOnLoad: false,
            theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default',
            securityLevel: 'loose',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
        });
        window._mermaidInitialized = true;
    }

    // 查找所有 mermaid 代码块并渲染
    var mermaidBlocks = document.querySelectorAll('.post-content pre code.language-mermaid, .post-content pre code.lang-mermaid');
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
    retryCount = retryCount || 0;
    if (checkLib('echarts', function() { window.initECharts(retryCount + 1); }, retryCount)) return;

    var echartsBlocks = document.querySelectorAll('.post-content pre code.language-echarts, .post-content pre code.lang-echarts');
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
    retryCount = retryCount || 0;
    if (checkLib('katex', function() { window.initKaTeX(retryCount + 1); }, retryCount)) return;

    // 优先处理 PHP 过滤器生成的 .math-tex 元素（已修复 Markdown 副作用）
    var mathElements = document.querySelectorAll('.post-content .math-tex');
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

    // 回退：使用 auto-render 扫描定界符（当 PHP 过滤器未生效时）
    if (checkLib('renderMathInElement', function() { window.initKaTeX(retryCount + 1); }, retryCount)) return;

    var postContent = document.querySelector('.post-content');
    if (!postContent) return;

    renderMathInElement(postContent, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true }
        ],
        throwOnError: false,
        output: 'html'
    });
};

/**
 * Markdown 扩展功能初始化
 * 处理任务列表交互、折叠区块、提示框等前端增强
 */
window.initMarkdownExt = function() {
    var postContent = document.querySelector('.post-content');
    if (!postContent) return;

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

document.addEventListener('DOMContentLoaded', function() {
    // 初始化夜间模式（优先执行，避免页面闪烁）
    window.initDarkMode();

    // 初始化侧边栏折叠功能
    window.initCollapsibleSidebar();

    // 返回顶部功能
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // 滚动显示/隐藏（节流）
        window.addEventListener('scroll', throttle(function() {
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        }, 150));
    }
    
    // 初始化移动端菜单
    window.initMobileMenu();
    
    // 延迟执行以确保Prism完全加载
    setTimeout(window.initPrismHighlight, 200);
    
    // 为代码块添加复制功能
    setTimeout(window.initCopyButtons, 250);
    
    // 延迟执行以确保 jQuery 和 Lightbox 完全加载
    setTimeout(window.initLightbox, 300);
    
    // 初始化点赞功能
    window.initPostLike();
    
    // 初始化浏览量统计
    window.initPostViews();
    
    // 初始化 Mermaid 图表渲染
    setTimeout(window.initMermaid, 350);
    
    // 初始化 ECharts 图表渲染
    setTimeout(window.initECharts, 400);
    
    // 初始化 KaTeX 数学公式渲染
    setTimeout(window.initKaTeX, 450);

    // 初始化 Markdown 扩展功能
    setTimeout(window.initMarkdownExt, 500);

});
