/* 免保存实时预览（编辑器重构 · 预览优化）
 *
 * 接管后台「预览文章」按钮：点击后把编辑器当前内容 POST 到
 * core/preview-ajax.php（与前台同款渲染管线），以一次性 token 打开
 * core/preview-render.php 整页预览。
 *
 * 相比原生（admin/write-js.php previewData）：
 *  - 不 confirm、不保存草稿、不污染 draft 字段
 *  - 新文章（无 cid 无草稿）也能立即预览（原生三分支落空静默无响应）
 *  - 预览内容 = 编辑器当前内容，所见即所得
 *  - 渲染管线与前台逐字节一致（shufei_render_post_content 假 widget）
 *
 * 注意：原生 btnPreview.click 是 jQuery bubble 绑定，本脚本在捕获阶段
 * stopPropagation 拦截（早于原生 handler），再走新链路。
 */
(function () {
    'use strict';

    var PREVIEW_AJAX = null; // 由 PHP 注入（window.SHUFEI_ADMIN.previewAjaxUrl）
    var PREVIEW_RENDER = null;
    var frame = null;
    var loading = false;

    function getUrls() {
        var a = window.SHUFEI_ADMIN || {};
        PREVIEW_AJAX = a.previewAjaxUrl;
        PREVIEW_RENDER = a.previewRenderUrl;
        return PREVIEW_AJAX && PREVIEW_RENDER;
    }

    /* 全屏预览浮层（复用原生 .preview-frame 样式类） */
    function openFrame(token) {
        closeFrame();
        document.body.classList.add('fullscreen', 'preview');
        frame = document.createElement('iframe');
        frame.className = 'preview-frame preview-loading';
        frame.setAttribute('frameborder', '0');
        frame.setAttribute('sandbox', 'allow-same-origin allow-scripts');
        frame.src = PREVIEW_RENDER + '?token=' + encodeURIComponent(token);
        frame.style.height = (window.innerHeight - 53) + 'px';
        frame.addEventListener('load', function () {
            frame.classList.remove('preview-loading');
        });
        document.body.appendChild(frame);

        // 顶部退出条（原生预览只有一个隐藏的 #btn-cancel-preview，这里给可见入口）
        var bar = document.createElement('div');
        bar.id = 'shufei-preview-exit-bar';
        bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:40px;background:#fff;border-bottom:1px solid #D9D9D6;z-index:100002;display:none;align-items:center;padding:0 14px;gap:10px;font-size:13px;color:#666;';
        bar.innerHTML = '<strong style="color:#333;">实时预览</strong><span>（编辑器当前内容，未保存）</span>' +
            '<button type="button" id="shufei-preview-exit-btn" style="margin-left:auto;border:none;background:#E9E9E6;color:#666;border-radius:2px;height:28px;padding:0 14px;cursor:pointer;font-size:13px;">退出预览</button>';
        document.body.appendChild(bar);
        bar.style.display = 'flex';
        document.getElementById('shufei-preview-exit-btn').addEventListener('click', closeFrame);
        // Esc / 帧内 postMessage 关闭
        document.addEventListener('keydown', onEsc);
        window.addEventListener('message', onMessage);
    }

    function closeFrame() {
        if (frame && frame.parentNode) frame.parentNode.removeChild(frame);
        frame = null;
        var bar = document.getElementById('shufei-preview-exit-bar');
        if (bar && bar.parentNode) bar.parentNode.removeChild(bar);
        document.body.classList.remove('fullscreen', 'preview');
        document.removeEventListener('keydown', onEsc);
        window.removeEventListener('message', onMessage);
    }

    function onEsc(e) {
        if (e.key === 'Escape') { e.preventDefault(); closeFrame(); }
    }
    function onMessage(e) {
        if (e && e.data === 'shufeiPreviewClose') closeFrame();
    }

    /* 收集编辑器内容并请求渲染 */
    function doPreview() {
        if (loading) return;
        if (!getUrls()) {
            alert('预览接口未就绪，请刷新页面后重试');
            return;
        }
        var textEl = document.getElementById('text');
        var titleEl = document.getElementById('title');
        if (!textEl) return;
        var text = textEl.value || '';
        if (text.replace(/\s/g, '') === '') {
            alert('内容为空，无法预览');
            return;
        }
        var cidInput = document.querySelector('form[name=write_post] input[name=cid], form[name=write_page] input[name=cid]');
        var mdInput = document.querySelector('input[name=markdown]');
        loading = true;
        var body = new FormData();
        body.append('action', 'preview_save');
        body.append('text', text);
        body.append('title', titleEl ? (titleEl.value || '') : '');
        body.append('markdown', mdInput ? '1' : '0');
        body.append('cid', cidInput ? (cidInput.value || '') : '');

        fetch(PREVIEW_AJAX, { method: 'POST', credentials: 'include', body: body })
            .then(function (r) { return r.json(); })
            .then(function (o) {
                loading = false;
                if (o && o.success && o.token) {
                    openFrame(o.token);
                } else {
                    alert((o && o.message) || '预览失败，请重试');
                }
            })
            .catch(function () {
                loading = false;
                alert('预览请求失败，请检查网络后重试');
            });
    }

    /* 捕获阶段拦截 #btn-preview（早于原生 jQuery bubble handler） */
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('#btn-preview') : null;
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        doPreview();
    }, true);
})();
