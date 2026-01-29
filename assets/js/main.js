/**
 * ShuFeiCat 主题主脚本
 * 包含返回顶部、移动端菜单、代码高亮、代码复制、Ajax加载等功能
 */
window.initPrismHighlight = function() {
    if (typeof Prism === 'undefined') {
        setTimeout(window.initPrismHighlight, 100);
        return;
    }
    
    const codeBlocks = document.querySelectorAll('.post-content pre code');
    codeBlocks.forEach(function(code) {
        const className = code.className;
        const langMatch = className.match(/lang-(\w+)/);
        if (langMatch) {
            const language = langMatch[1];
            code.className = 'language-' + language;
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = code.innerHTML;
            code.innerHTML = tempDiv.textContent || tempDiv.innerText;
        }
    });
    
    Prism.highlightAll();
};

window.initLightbox = function() {
    if (typeof jQuery === 'undefined' || typeof lightbox === 'undefined') {
        setTimeout(window.initLightbox, 100);
        return;
    }
    
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
            link.setAttribute('data-title', img.getAttribute('alt') || '图片 ' + (imageIndex + 1));
            
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
        copyBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; padding: 5px 10px; background: rgba(102, 126, 234, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; z-index: 10;';
        
        copyBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const code = pre.querySelector('code');
            if (code) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = code.innerHTML;
                const text = tempDiv.textContent || tempDiv.innerText || '';
                
                navigator.clipboard.writeText(text).then(function() {
                    copyBtn.innerHTML = '<i class="fa fa-check"></i> 已复制';
                    copyBtn.classList.add('copied');
                    
                    setTimeout(function() {
                        copyBtn.innerHTML = '<i class="fa fa-copy"></i> 复制';
                        copyBtn.classList.remove('copied');
                    }, 2000);
                }).catch(function(err) {
                    console.error('复制失败:', err);
                    copyBtn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 失败';
                });
            }
        });
        
        pre.style.position = 'relative';
        pre.appendChild(copyBtn);
    });
};

// 初始化移动端菜单功能 - 控制左侧边栏
window.initMobileMenu = function() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const leftSidebar = document.getElementById('left-sidebar');
    const bodyShade = document.getElementById('body-shade');
    
    if (!mobileMenuBtn || !leftSidebar) {
        console.warn('移动端菜单元素未找到');
        return;
    }
    
    // 移除旧的事件监听器（通过克隆节点）
    const newBtn = mobileMenuBtn.cloneNode(true);
    mobileMenuBtn.parentNode.replaceChild(newBtn, mobileMenuBtn);
    
    const newShade = bodyShade ? bodyShade.cloneNode(true) : null;
    if (bodyShade && newShade) {
        bodyShade.parentNode.replaceChild(newShade, bodyShade);
    }
    
    // 重新获取元素引用
    const btn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('left-sidebar');
    const shade = document.getElementById('body-shade');
    
    if (btn && sidebar) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            sidebar.classList.toggle('admin-side-show');
            
            if (shade) {
                shade.classList.toggle('active');
            }
            
            // 切换按钮图标
            if (this.classList.contains('active')) {
                this.innerHTML = '<span class="hamburger-line" style="transform: rotate(45deg) translate(5px, 5px);"></span><span class="hamburger-line" style="opacity: 0;"></span><span class="hamburger-line" style="transform: rotate(-45deg) translate(5px, -5px);"></span>';
                this.title = '关闭菜单';
            } else {
                this.innerHTML = '<span class="hamburger-line"></span><span class="hamburger-line"></span><span class="hamburger-line"></span>';
                this.title = '展开菜单';
            }
        });
        
        // 点击遮罩层关闭菜单
        if (shade) {
            shade.addEventListener('click', function() {
                btn.classList.remove('active');
                sidebar.classList.remove('admin-side-show');
                this.classList.remove('active');
                
                // 恢复按钮图标
                btn.innerHTML = '<span class="hamburger-line"></span><span class="hamburger-line"></span><span class="hamburger-line"></span>';
                btn.title = '展开菜单';
            });
        }
        
        // 点击侧边栏链接后关闭菜单
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    btn.classList.remove('active');
                    sidebar.classList.remove('admin-side-show');
                    
                    if (shade) {
                        shade.classList.remove('active');
                    }
                    
                    // 恢复按钮图标
                    btn.innerHTML = '<span class="hamburger-line"></span><span class="hamburger-line"></span><span class="hamburger-line"></span>';
                    btn.title = '展开菜单';
                }
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // 返回顶部功能
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // 滚动显示/隐藏
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });
    }
    
    // 初始化移动端菜单
    window.initMobileMenu();
    
    // 延迟执行以确保Prism完全加载
    setTimeout(window.initPrismHighlight, 200);
    
    // 为代码块添加复制功能
    setTimeout(window.initCopyButtons, 250);
    
    // 延迟执行以确保 jQuery 和 Lightbox 完全加载
    setTimeout(window.initLightbox, 300);
    
    // 初始化 Turnstile 人机验证（使用 explicit 模式需要手动渲染）
    var turnstileContainer = document.getElementById('cf-turnstile');
    if (turnstileContainer) {
        // 检查是否已经渲染过（通过检查容器内是否有 iframe）
        if (turnstileContainer.querySelector('iframe')) {
            console.log('Turnstile widget 已存在，跳过初始渲染');
        } else if (typeof window.turnstile !== 'undefined') {
            console.log('初始页面加载，手动渲染 Turnstile');
            try {
                window.turnstile.render('#cf-turnstile');
            } catch (e) {
                console.warn('Turnstile 初始渲染失败:', e);
            }
        } else {
            // 如果 Turnstile 脚本还未加载，等待加载完成
            var checkTurnstile = setInterval(function() {
                if (typeof window.turnstile !== 'undefined') {
                    clearInterval(checkTurnstile);
                    
                    // 再次检查，防止在等待期间已经被渲染
                    if (turnstileContainer.querySelector('iframe')) {
                        console.log('Turnstile widget 已存在，跳过初始渲染');
                        return;
                    }
                    
                    console.log('Turnstile 脚本加载完成，手动渲染');
                    try {
                        window.turnstile.render('#cf-turnstile');
                    } catch (e) {
                        console.warn('Turnstile 渲染失败:', e);
                    }
                }
            }, 100);
            
            setTimeout(function() {
                clearInterval(checkTurnstile);
            }, 5000);
        }
    }
});