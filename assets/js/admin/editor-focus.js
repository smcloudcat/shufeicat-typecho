/**
 * 编辑器焦点模式（2026-10-16 编辑器重构 · 阶段一：沉浸）
 *
 * 功能：
 * - 焦点模式：隐藏后台 chrome（顶栏/页标题/页脚/侧栏/自定义字段/提交栏），
 *   正文收窄居中、textarea 撑满视口，右侧「选项/附件」变抽屉
 * - 当前段落高亮：textarea 垫底高亮条（canvas 测量软换行，mono 字体精确）
 * - 打字机模式：当前段首行垂直居中
 * - 底部悬浮操作条：高亮/打字机/选项抽屉/发布/草稿/退出
 * - 快捷键：Ctrl+Shift+F 切换焦点，Esc 退出焦点（先关抽屉）
 * - 偏好持久化 localStorage（高亮/打字机开关）
 *
 * 依赖：editor-quick-insert.js 的 SHUFEI_QI_EXTRA 注册机制（本文件后加载，
 *       push 后调 window.shufeiQiApplyExtra() 补挂按钮）。
 * 注意：admin JS 原样部署（无 .min 产物，与同目录其他文件一致）。
 */
(function () {
    'use strict';

    var textarea = document.getElementById('text');
    if (!textarea) return;

    /* ===== 偏好 ===== */
    var PREF_KEY = 'shufei_editor_focus_prefs';
    var prefs = { highlight: true, typewriter: false, preview: false };
    try {
        var saved = JSON.parse(localStorage.getItem(PREF_KEY) || '{}');
        if (typeof saved.highlight === 'boolean') prefs.highlight = saved.highlight;
        if (typeof saved.typewriter === 'boolean') prefs.typewriter = saved.typewriter;
        if (typeof saved.preview === 'boolean') prefs.preview = saved.preview;
    } catch (e) { /* localStorage 不可用时用默认值 */ }

    function savePrefs() {
        try { localStorage.setItem(PREF_KEY, JSON.stringify(prefs)); } catch (e) {}
    }

    /* ===== 状态 ===== */
    var focusActive = false;
    var drawerOpen = false;
    var wrap = null;        // textarea 包裹容器（高亮条定位基准）
    var hlBar = null;       // 段落高亮条
    var bar = null;         // 悬浮操作条
    var previewPane = null; // 实时预览面板（专注模式双栏）
    var previewTimer = 0;   // 输入防抖计时器
    var previewSeq = 0;     // 请求序号（丢弃过期响应）
    var rafId = 0;
    var originalHeight = textarea.style.height || '';

    /* ===== 工具：计算样式 ===== */
    function computed(name) {
        return window.getComputedStyle(textarea).getPropertyValue(name);
    }

    function getLineHeight() {
        var lh = computed('line-height');
        if (lh && lh !== 'normal') {
            var v = parseFloat(lh);
            if (v > 0) return v;
        }
        var fs = parseFloat(computed('font-size')) || 14;
        return fs * 1.5;
    }

    // canvas 测量上下文（与 textarea 同字体，mono 下软换行计算精确）
    var measureCtx = null;
    function getMeasureCtx() {
        if (!measureCtx) {
            measureCtx = document.createElement('canvas').getContext('2d');
        }
        var style = window.getComputedStyle(textarea);
        measureCtx.font = style.fontStyle + ' ' + style.fontWeight + ' ' +
            style.fontSize + ' ' + style.fontFamily;
        return measureCtx;
    }

    /** 一段硬行文本在 textarea 宽度下占几个可视行（贪心逐字累积，mono 精确） */
    function visualLineCount(lineText, maxWidth) {
        if (!lineText) return 1;
        var ctx = getMeasureCtx();
        var lines = 1, cur = 0;
        for (var i = 0; i < lineText.length; i++) {
            var w = ctx.measureText(lineText.charAt(i)).width;
            if (cur + w > maxWidth && cur > 0) { lines++; cur = w; }
            else { cur += w; }
        }
        return lines;
    }

    /* ===== 包裹 textarea ===== */
    function ensureWrap() {
        if (wrap) return;
        wrap = document.createElement('div');
        wrap.className = 'shufei-focus-wrap';
        textarea.parentNode.insertBefore(wrap, textarea);
        wrap.appendChild(textarea);
        hlBar = document.createElement('div');
        hlBar.className = 'shufei-focus-hl';
        hlBar.setAttribute('aria-hidden', 'true');
        wrap.appendChild(hlBar);
    }

    /* ===== 当前段落几何 ===== */
    function currentParagraph() {
        var value = textarea.value;
        var pos = textarea.selectionStart || 0;
        var lines = value.split('\n');
        var lineIdx = 0, col = pos;
        for (var i = 0; i < lines.length; i++) {
            if (col <= lines[i].length) { lineIdx = i; break; }
            col -= lines[i].length + 1; // +1 = \n
            lineIdx = i + 1;
        }
        if (lineIdx >= lines.length) lineIdx = lines.length - 1;

        // 段落 = 当前硬行向上向下延伸的连续非空行
        var start = lineIdx, end = lineIdx;
        while (start > 0 && lines[start - 1].trim() !== '') start--;
        while (end < lines.length - 1 && lines[end + 1].trim() !== '') end++;
        return { lines: lines, start: start, end: end };
    }

    function updateHighlight() {
        if (!hlBar) return;
        if (!focusActive || !prefs.highlight || textarea.offsetParent === null) {
            hlBar.classList.remove('on');
            return;
        }
        hlBar.classList.add('on');

        var lh = getLineHeight();
        var padLeft = parseFloat(computed('padding-left')) || 0;
        var padRight = parseFloat(computed('padding-right')) || 0;
        var padTop = parseFloat(computed('padding-top')) || 0;
        var maxWidth = textarea.clientWidth - padLeft - padRight;
        if (maxWidth <= 0) return;

        var para = currentParagraph();
        var visualBefore = 0;
        for (var i = 0; i < para.start; i++) {
            visualBefore += visualLineCount(para.lines[i], maxWidth);
        }
        var visualCount = 0;
        for (var j = para.start; j <= para.end; j++) {
            visualCount += visualLineCount(para.lines[j], maxWidth);
        }

        hlBar.style.left = padLeft + 'px';
        hlBar.style.right = padRight + 'px';
        hlBar.style.top = (visualBefore * lh + padTop - textarea.scrollTop) + 'px';
        hlBar.style.height = (visualCount * lh) + 'px';
    }

    /* ===== 打字机模式：当前段首行居中 ===== */
    function updateTypewriter() {
        if (!focusActive || !prefs.typewriter || textarea.offsetParent === null) return;
        var lh = getLineHeight();
        var padLeft = parseFloat(computed('padding-left')) || 0;
        var padRight = parseFloat(computed('padding-right')) || 0;
        var maxWidth = textarea.clientWidth - padLeft - padRight;
        if (maxWidth <= 0) return;

        var para = currentParagraph();
        var visualBefore = 0;
        for (var i = 0; i < para.start; i++) {
            visualBefore += visualLineCount(para.lines[i], maxWidth);
        }
        var target = visualBefore * lh - (textarea.clientHeight - lh) / 2;
        var maxScroll = textarea.scrollHeight - textarea.clientHeight;
        textarea.scrollTop = Math.max(0, Math.min(target, maxScroll));
    }

    function scheduleUpdate() {
        if (rafId) return;
        rafId = requestAnimationFrame(function () {
            rafId = 0;
            updateHighlight();
            updateTypewriter();
        });
    }

    /* ===== 悬浮操作条 ===== */
    function buildBar() {
        if (bar) return;
        bar = document.createElement('div');
        bar.className = 'shufei-focus-bar';
        bar.id = 'shufei-focus-bar';
        bar.innerHTML =
            '<button type="button" class="sf-fb-btn" id="sf-fb-preview" title="实时预览：右侧同步显示渲染效果"><i class="fa fa-columns"></i> 预览</button>' +
            '<button type="button" class="sf-fb-btn" id="sf-fb-highlight" title="当前段落高亮"><i class="fa fa-lightbulb-o"></i> 高亮</button>' +
            '<button type="button" class="sf-fb-btn" id="sf-fb-typewriter" title="打字机模式：当前段居中"><i class="fa fa-i-cursor"></i> 居中</button>' +
            '<span class="sf-fb-sep"></span>' +
            '<button type="button" class="sf-fb-btn" id="sf-fb-options" title="发布选项与附件"><i class="fa fa-cog"></i> 选项</button>' +
            '<span class="sf-fb-sep"></span>' +
            '<button type="button" class="sf-fb-btn sf-fb-primary" id="sf-fb-publish" title="发布文章"><i class="fa fa-check"></i> 发布</button>' +
            '<button type="button" class="sf-fb-btn" id="sf-fb-save" title="保存草稿"><i class="fa fa-floppy-o"></i> 草稿</button>' +
            '<span class="sf-fb-sep"></span>' +
            '<button type="button" class="sf-fb-btn" id="sf-fb-exit" title="退出焦点模式 (Esc)"><i class="fa fa-sign-out"></i> 退出</button>' +
            '<span class="sf-fb-status" id="shufei-focus-status"></span>';
        document.body.appendChild(bar);

        bar.querySelector('#sf-fb-preview').addEventListener('click', function () {
            setPreview(!prefs.preview);
        });
        bar.querySelector('#sf-fb-highlight').addEventListener('click', function () {
            prefs.highlight = !prefs.highlight;
            savePrefs();
            syncBarState();
            scheduleUpdate();
        });
        bar.querySelector('#sf-fb-typewriter').addEventListener('click', function () {
            prefs.typewriter = !prefs.typewriter;
            savePrefs();
            syncBarState();
            scheduleUpdate();
        });
        bar.querySelector('#sf-fb-options').addEventListener('click', toggleDrawer);
        bar.querySelector('#sf-fb-save').addEventListener('click', function () {
            submitWith('btn-save');
        });
        bar.querySelector('#sf-fb-publish').addEventListener('click', function () {
            submitWith('btn-submit');
        });
        bar.querySelector('#sf-fb-exit').addEventListener('click', function () {
            setFocus(false);
        });
    }

    function syncBarState() {
        if (!bar) return;
        var pv = bar.querySelector('#sf-fb-preview');
        if (pv) pv.classList.toggle('on', prefs.preview);
        bar.querySelector('#sf-fb-highlight').classList.toggle('on', prefs.highlight);
        bar.querySelector('#sf-fb-typewriter').classList.toggle('on', prefs.typewriter);
    }

    /** 以指定原生按钮提交表单（requestSubmit 优先，隐藏按钮也能可靠触发） */
    function submitWith(btnId) {
        var btn = document.getElementById(btnId);
        var form = document.querySelector('form[name="write_post"]');
        if (!btn || !form) return;
        try {
            if (form.requestSubmit) {
                form.requestSubmit(btn);
            } else {
                btn.click();
            }
        } catch (e) {
            btn.click();
        }
    }

    /* ===== 选项抽屉 ===== */
    var drawerMask = null;
    function showDrawerMask() {
        if (drawerMask) return;
        drawerMask = document.createElement('div');
        drawerMask.id = 'shufei-drawer-mask';
        drawerMask.addEventListener('click', function () { toggleDrawer(); });
        document.body.appendChild(drawerMask);
    }
    function hideDrawerMask() {
        if (drawerMask && drawerMask.parentNode) drawerMask.parentNode.removeChild(drawerMask);
        drawerMask = null;
    }
    function toggleDrawer() {
        var secondary = document.getElementById('edit-secondary');
        if (!secondary) return;
        drawerOpen = !drawerOpen;
        secondary.classList.toggle('shufei-drawer-open', drawerOpen);
        var btn = bar && bar.querySelector('#sf-fb-options');
        if (btn) btn.classList.toggle('on', drawerOpen);
        // 遮罩：抽屉打开时挡住左侧编辑区，防误触（移动端尤其需要）
        if (drawerOpen) showDrawerMask(); else hideDrawerMask();
    }

    /* ===== 焦点模式开关 ===== */
    function resizeTextarea() {
        if (!focusActive) return;
        // 撑满视口：顶部偏移 + 底部留给悬浮操作条
        var top = wrap.getBoundingClientRect().top;
        var h = window.innerHeight - top - 76;
        if (h < 240) h = 240;
        textarea.style.height = h + 'px';
    }

    /* ===== 实时预览面板（专注模式双栏，2026-11-03）=====
     * 用 preview-ajax 的 preview_live（前台同款渲染管线、不落盘不写缓存），
     * 输入防抖 700ms 后刷新右栏。请求带序号，过期响应直接丢弃，
     * 避免慢请求覆盖新内容（打字快时必现）。
     */
    function buildPreviewPane() {
        if (previewPane) return previewPane;
        previewPane = document.createElement('div');
        previewPane.id = 'shufei-focus-preview';
        previewPane.innerHTML =
            '<div class="sfp-head"><span class="sfp-title">实时预览</span>' +
            '<span class="sfp-status" id="sfp-status"></span></div>' +
            '<div class="sfp-body" id="sfp-body"></div>';
        if (prefs.preview) previewPane.style.display = 'flex';
        document.body.appendChild(previewPane);
        return previewPane;
    }

    function renderPreviewNow() {
        var cfg = window.SHUFEI_ADMIN || {};
        var ajax = cfg.previewAjaxUrl;
        var bodyEl = document.getElementById('sfp-body');
        var statusEl = document.getElementById('sfp-status');
        if (!ajax || !bodyEl) {
            if (statusEl) statusEl.textContent = '接口未就绪';
            return;
        }
        var cidInput = document.querySelector('form[name=write_post] input[name=cid], form[name=write_page] input[name=cid]');
        var mdInput = document.querySelector('input[name=markdown]');
        var seq = ++previewSeq;
        if (statusEl) statusEl.textContent = '渲染中…';
        var fd = new FormData();
        fd.append('action', 'preview_live');
        fd.append('text', textarea.value || '');
        fd.append('markdown', mdInput ? '1' : '0');
        fd.append('cid', cidInput ? (cidInput.value || '') : '');
        fetch(ajax, { method: 'POST', credentials: 'include', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (o) {
                if (seq !== previewSeq) return; // 已有更新的请求，丢弃本次结果
                if (!o || !o.success) {
                    if (statusEl) statusEl.textContent = (o && o.message) || '渲染失败';
                    return;
                }
                var top = bodyEl.scrollTop;
                bodyEl.innerHTML = (o.html && o.html !== '')
                    ? o.html
                    : '<p class="sfp-empty">开始输入，这里实时显示渲染效果…</p>';
                bodyEl.scrollTop = top; // 保持阅读位置
                if (statusEl) statusEl.textContent = '';
            })
            .catch(function () {
                if (seq !== previewSeq) return;
                if (statusEl) statusEl.textContent = '请求失败';
            });
    }

    function schedulePreview() {
        if (!focusActive || !prefs.preview) return;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderPreviewNow, 700);
    }

    function setPreview(on) {
        prefs.preview = on;
        savePrefs();
        document.body.classList.toggle('shufei-focus-preview', !!on && focusActive);
        if (on) {
            buildPreviewPane();
            previewPane.style.display = 'flex';
            renderPreviewNow();
        } else if (previewPane) {
            previewPane.style.display = 'none';
        }
        syncBarState();
    }

    function setFocus(on) {
        if (on === focusActive) return;
        focusActive = on;
        document.body.classList.toggle('shufei-focus-mode', on);

        if (on) {
            ensureWrap();
            buildBar();
            resizeTextarea();
            syncBarState();
            scheduleUpdate();
            window.addEventListener('resize', resizeTextarea);
            // 恢复上次的实时预览开关
            document.body.classList.toggle('shufei-focus-preview', prefs.preview);
            if (prefs.preview) {
                buildPreviewPane();
                previewPane.style.display = 'flex';
                renderPreviewNow();
            }
        } else {
            textarea.style.height = originalHeight;
            document.body.classList.remove('shufei-focus-preview');
            if (previewPane) previewPane.style.display = 'none';
            clearTimeout(previewTimer);
            if (hlBar) hlBar.classList.remove('on');
            window.removeEventListener('resize', resizeTextarea);
            // 退出时收起抽屉
            var secondary = document.getElementById('edit-secondary');
            if (secondary) secondary.classList.remove('shufei-drawer-open');
            drawerOpen = false;
            hideDrawerMask();
            if (bar) {
                var optBtn = bar.querySelector('#sf-fb-options');
                if (optBtn) optBtn.classList.remove('on');
            }
        }
        // 通知阶段二自动保存状态条重渲（焦点条状态槽显隐切换）
        if (typeof window.shufeiOnFocusChange === 'function') window.shufeiOnFocusChange(on);
    }

    /* ===== 事件 ===== */
    ['input', 'keyup', 'click', 'focus'].forEach(function (ev) {
        textarea.addEventListener(ev, scheduleUpdate);
    });
    // 实时预览只跟 input（编辑器内容真正变化时才重渲染）
    textarea.addEventListener('input', schedulePreview);
    textarea.addEventListener('compositionend', schedulePreview);
    textarea.addEventListener('compositionend', scheduleUpdate);
    textarea.addEventListener('scroll', function () {
        if (rafId) return;
        rafId = requestAnimationFrame(function () {
            rafId = 0;
            updateHighlight(); // 滚动只重定位高亮条，不触发打字机滚动
        });
    });

    /* 原生全屏快捷键合并（2026-11-03）
     * pagedown 自带 Ctrl+J（全屏）/ Ctrl+E（退出全屏），走的是它自己的
     * doFullScreen → 给 body 加 .fullscreen 走左右分栏。原生「全屏」按钮
     * 已移除，这两个快捷键改为切换专注模式，避免用户误入旧的全屏模式。
     * 必须用捕获阶段：pagedown 的 handler 绑在 textarea 冒泡阶段，晚于本监听。 */
    textarea.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.shiftKey || e.altKey) return;
        var k = (e.key || '').toLowerCase();
        if (k === 'j') {
            e.preventDefault();
            e.stopPropagation();
            setFocus(!focusActive);
        } else if (k === 'e' && focusActive) {
            e.preventDefault();
            e.stopPropagation();
            setFocus(false);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        // Ctrl/Cmd + Shift + F：切换焦点模式
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'F' || e.key === 'f')) {
            e.preventDefault();
            setFocus(!focusActive);
            return;
        }
        // Esc：先关抽屉，再退出焦点
        if (e.key === 'Escape' && focusActive) {
            if (drawerOpen) {
                toggleDrawer();
            } else {
                setFocus(false);
            }
        }
    });

    /* ===== 工具栏按钮注册（SHUFEI_QI_EXTRA 机制）=====
     * 注意：SHUFEI_QI_EXTRA 数组由 editor-quick-insert.js 在 $(document).ready
     * 里初始化，本脚本同步执行时它还是 undefined——必须等 DOM ready 再注册
     * （jQuery ready 先于本原生监听器触发，数组必已就绪；兜底 || [] 双保险）。
     */
    function registerToolbarButton() {
        window.SHUFEI_QI_EXTRA = window.SHUFEI_QI_EXTRA || [];
        window.SHUFEI_QI_ACTIONS = window.SHUFEI_QI_ACTIONS || {};
        window.SHUFEI_QI_EXTRA.push({
            action: 'editor-focus',
            label: '专注',
            // 原生「全屏」已并入本模式（2026-11-03）：专注即全屏沉浸写作
            title: '专注模式（全屏沉浸写作，Ctrl+J 或 Ctrl+Shift+F），Esc 退出',
        });
        window.SHUFEI_QI_ACTIONS['editor-focus'] = function () { setFocus(!focusActive); };
        // 本脚本注册晚于快捷按钮重建时补挂
        if (typeof window.shufeiQiApplyExtra === 'function') window.shufeiQiApplyExtra();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerToolbarButton);
    } else {
        registerToolbarButton();
    }

    /* ===== 对外 API（阶段二自动保存状态条会用到）===== */
    window.shufeiFocus = {
        toggle: function () { setFocus(!focusActive); },
        isActive: function () { return focusActive; },
        prefs: prefs,
    };
})();
