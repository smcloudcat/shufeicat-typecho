/* ShuFeiCat 加载更多 */
(function () {
    'use strict';

    var WRAP_SELECTOR = '#load-more-wrap';
    var BTN_SELECTOR = '#load-more-btn';

    function getWrap() {
        return document.querySelector(WRAP_SELECTOR);
    }

    function parsePage(html) {
        return new DOMParser().parseFromString(html, 'text/html');
    }

    // 安全解析 HTML 字符串并返回经过净化的 DocumentFragment
    // 移除 <script>、<iframe>、on* 事件属性、javascript: 协议等危险内容
    function sanitizeHtml(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var dangerousTags = ['script', 'iframe', 'object', 'embed', 'base', 'form'];
        dangerousTags.forEach(function(tagName) {
            var nodes = doc.querySelectorAll(tagName);
            nodes.forEach(function(node) { node.remove(); });
        });
        var allElements = doc.querySelectorAll('*');
        allElements.forEach(function(el) {
            var attrs = el.attributes;
            for (var i = attrs.length - 1; i >= 0; i--) {
                var attrName = attrs[i].name.toLowerCase();
                var attrValue = attrs[i].value;
                if (attrName.indexOf('on') === 0) {
                    el.removeAttribute(attrs[i].name);
                } else if ((attrName === 'href' || attrName === 'src') &&
                    /^\s*javascript:/i.test(attrValue)) {
                    el.removeAttribute(attrs[i].name);
                }
            }
        });
        var fragment = document.createDocumentFragment();
        while (doc.body.firstChild) {
            fragment.appendChild(doc.body.firstChild);
        }
        return fragment;
    }

    // 将目标页面中的文章卡片追加到当前列表
    function appendPosts(doc) {
        var targetList = document.getElementById('ajax-post-list');
        var newList = doc.getElementById('ajax-post-list');
        if (!targetList || !newList) return false;
        var fragment = sanitizeHtml(newList.innerHTML);
        while (fragment.firstChild) {
            targetList.appendChild(fragment.firstChild);
        }
        return true;
    }

    // 用目标页面的加载更多区块替换当前区块，保持 data-next 与进度文案同步
    function updateWrap(doc) {
        var oldWrap = getWrap();
        if (!oldWrap) return;

        var newWrap = doc.getElementById('load-more-wrap');
        if (newWrap) {
            oldWrap.parentNode.replaceChild(newWrap, oldWrap);
        } else {
            var end = document.createElement('div');
            end.className = 'load-more-end';
            end.textContent = '没有更多内容了';
            oldWrap.parentNode.replaceChild(end, oldWrap);
        }
    }

    // 重新初始化新增内容相关的交互
    function reinitLoadedContent() {
        if (typeof window.reinitPageFunctions === 'function') {
            window.reinitPageFunctions();
            return;
        }

        var fns = [
            'initPostViews',
            'initPostThumbFallback',
            'initListReadingMarks',
            'initPostLike',
            'initLightbox',
            'initPrismHighlight',
            'initCopyButtons',
            'initMermaid',
            'initECharts',
            'initKaTeX',
            'initMarkdownExt',
            'initVideoPlayer',
            'initMusicPlayer'
        ];
        for (var i = 0; i < fns.length; i++) {
            if (typeof window[fns[i]] === 'function') {
                window[fns[i]]();
            }
        }
    }

    function loadNextPage(btn) {
        var wrap = btn.closest ? btn.closest(WRAP_SELECTOR) : getWrap();
        var next = wrap && wrap.getAttribute('data-next');
        if (!next) return;

        btn.classList.add('loading');
        btn.disabled = true;
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-pulse"></i> 加载中...';

        fetch(next, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                var doc = parsePage(html);
                if (!appendPosts(doc)) {
                    throw new Error('empty list');
                }
                updateWrap(doc);
                reinitLoadedContent();
            })
            .catch(function () {
                btn.innerHTML = originalHtml;
                btn.classList.remove('loading');
                btn.disabled = false;
            });
    }

    // 事件委托：Pjax 切换页面后按钮被整体替换，委托监听始终生效
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest(BTN_SELECTOR) : null;
        if (!btn) return;
        e.preventDefault();
        loadNextPage(btn);
    });
})();
