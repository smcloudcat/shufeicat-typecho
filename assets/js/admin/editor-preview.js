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

    /* 全屏预览浮层
     *
     * 不用原生 .preview / body.fullscreen class：那套规则会把 #text/.submit
     * 改成 position:absolute + width:50%，导致编辑器区域宽度跳变、右侧内容
     * 被挤开（2026-11-03 主人反馈「点预览编辑框变宽」）。这里纯 fixed 自绘。
     */
    function openFrame(token) {
        closeFrame();
        frame = document.createElement('iframe');
        // 保留 preview-frame 类名（既有样式/测试选择器用），但它只在
        // body.preview 下才有定位规则，本实现不再加该类，故不会生效
        frame.className = 'preview-frame shufei-preview-frame';
        frame.setAttribute('frameborder', '0');
        frame.setAttribute('sandbox', 'allow-same-origin allow-scripts');
        frame.src = PREVIEW_RENDER + '?token=' + encodeURIComponent(token);
        frame.style.cssText = 'position:fixed;top:40px;left:0;width:100vw;height:calc(100vh - 40px);'
            + 'border:0;background:#fff;z-index:100001;';
        document.body.appendChild(frame);

        // 加载提示（iframe 白屏期间给个反馈，load 后移除）
        var tip = document.createElement('div');
        tip.id = 'shufei-preview-loading';
        tip.textContent = '正在生成预览…';
        tip.style.cssText = 'position:fixed;top:40px;left:0;right:0;bottom:0;background:#fff;'
            + 'display:flex;align-items:center;justify-content:center;font-size:14px;color:#999;z-index:100000;';
        document.body.appendChild(tip);
        frame.addEventListener('load', function () {
            var t = document.getElementById('shufei-preview-loading');
            if (t && t.parentNode) t.parentNode.removeChild(t);
        });

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
        var tip = document.getElementById('shufei-preview-loading');
        if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
        // 清掉历史版本可能残留的原生预览 class（旧版曾添加）
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
