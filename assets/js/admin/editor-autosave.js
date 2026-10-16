/**
 * 编辑器自动保存与状态增强（2026-10-16 编辑器重构 · 阶段二：方便）
 *
 * 背景：Typecho 原生自动保存依赖 $options->autoSave（write-js.php），但该值读的是
 * 站点选项（user=0），而用户设置里的「自动保存」存在 user=uid 行——原生自动保存
 * 实际永不生效。本组件用 localStorage 兜底，并补齐字数统计/快捷键/焦点状态联动。
 *
 * 功能：
 * - 本地自动保存：停输 3s（+30s 兜底）存 localStorage，按 cid/new 分 key
 * - 恢复横幅：刷新后草稿与线上不同时提示，可「恢复/丢弃」；相同则静默清理
 * - 状态条：未保存更改 / 已自动保存时间 + 字数 + 预计阅读时间
 *   （焦点模式下同步显示到悬浮操作条状态槽）
 * - 快捷键：Ctrl+S 存草稿、Ctrl+Enter 发布、Ctrl+Shift+P 预览
 * - 新文章打开自动聚焦标题
 *
 * 注意：beforeunload 离开提醒沿用 Typecho 原生（write-js.php 已绑定，勿重复）。
 */
(function () {
    'use strict';

    var textarea = document.getElementById('text');
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    var form = document.querySelector('form[name="write_post"]') ||
               document.querySelector('form[name="write_page"]');
    if (!textarea || !form) return;

    /* ===== 草稿 key：按文章区分，新文章用 new ===== */
    var cidInput = form.querySelector('input[name="cid"]');
    var cid = (cidInput && cidInput.value) ? String(cidInput.value) : 'new';
    var DRAFT_KEY = 'shufei_editor_draft_' + cid;
    var DEBOUNCE = 3000;
    var SAFETY_INTERVAL = 30000;

    var dirty = false;
    var saveTimer = null;
    var lastSavedText = '';
    var statusEl = null;

    /* ===== 工具 ===== */
    function nowTime() {
        var d = new Date();
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    function countWords(text) {
        var cjk = (text.match(/[\u4e00-\u9fff\u3400-\u4dbf\u3040-\u30ff\uac00-\ud7af]/g) || []).length;
        var latin = (text.replace(/[\u4e00-\u9fff\u3400-\u4dbf]/g, ' ')
            .match(/[A-Za-z0-9_'%-]+/g) || []).length;
        return cjk + latin;
    }

    function currentSnapshot() {
        return {
            title: titleInput ? titleInput.value : '',
            text: textarea.value,
            slug: slugInput ? slugInput.value : '',
        };
    }

    /* ===== 状态条 ===== */
    function buildStatus() {
        if (statusEl) return;
        statusEl = document.createElement('div');
        statusEl.id = 'shufei-editor-status';
        statusEl.className = 'shufei-editor-status';
        document.body.appendChild(statusEl);
    }

    function setStatus(html) {
        buildStatus();
        // 焦点模式：状态进悬浮操作条槽；普通模式：右下角浮签
        var inFocus = window.shufeiFocus && window.shufeiFocus.isActive();
        var slot = document.getElementById('shufei-focus-status');
        if (inFocus && slot) {
            statusEl.style.display = 'none';
            slot.innerHTML = html;
        } else {
            statusEl.style.display = '';
            statusEl.innerHTML = html;
            if (slot) slot.innerHTML = '';
        }
    }

    function refreshStatus() {
        var words = countWords(textarea.value);
        var minutes = words > 0 ? Math.max(1, Math.ceil(words / 400)) : 0;
        var meta = words.toLocaleString('en-US') + ' 字' +
            (minutes > 0 ? ' · 约 ' + minutes + ' 分钟' : '');
        if (dirty) {
            setStatus('<span class="ses-dot ses-warn"></span>未保存的更改… <span class="ses-meta">' + meta + '</span>');
        } else if (lastSavedText) {
            setStatus('<span class="ses-dot ses-ok"></span>已自动保存 ' + lastSavedText +
                ' <span class="ses-meta">' + meta + '</span>');
        } else {
            setStatus('<span class="ses-dot ses-idle"></span>' + meta);
        }
    }

    // 焦点模式切换时重渲状态（焦点条显隐导致挂载点变化）
    window.shufeiOnFocusChange = function () {
        refreshStatus();
    };

    /* ===== 保存逻辑 ===== */
    function saveDraft(silent) {
        var snap = currentSnapshot();
        if (!snap.text.trim() && !snap.title.trim()) return; // 空内容不存
        var payload = {
            title: snap.title,
            text: snap.text,
            slug: snap.slug,
            savedAt: Date.now(),
        };
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
            dirty = false;
            lastSavedText = nowTime();
            refreshStatus();
        } catch (e) {
            if (!silent) {
                setStatus('<span class="ses-dot ses-warn"></span>自动保存失败（存储空间不足）');
            }
        }
    }

    function scheduleSave() {
        dirty = true;
        refreshStatus();
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () { saveDraft(); }, DEBOUNCE);
    }

    /* ===== 恢复横幅 ===== */
    function readDraft() {
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return null;
            var d = JSON.parse(raw);
            if (!d || typeof d.text !== 'string') return null;
            return d;
        } catch (e) { return null; }
    }

    function removeDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    function dismissBanner() {
        var b = document.getElementById('shufei-draft-banner');
        if (b) b.remove();
    }

    function showBanner(draft) {
        dismissBanner();
        var t = new Date(draft.savedAt || Date.now());
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        var timeStr = p(t.getHours()) + ':' + p(t.getMinutes()) + ':' + p(t.getSeconds());
        var banner = document.createElement('div');
        banner.id = 'shufei-draft-banner';
        banner.className = 'shufei-draft-banner';
        banner.innerHTML =
            '<span class="sdb-icon">💾</span>' +
            '<span class="sdb-text">检测到未恢复的本地草稿（保存于 ' + timeStr + '，' +
            countWords(draft.text).toLocaleString('en-US') + ' 字）</span>' +
            '<button type="button" class="sdb-btn sdb-restore">恢复</button>' +
            '<button type="button" class="sdb-btn sdb-discard">丢弃</button>';
        document.body.appendChild(banner);

        banner.querySelector('.sdb-restore').addEventListener('click', function () {
            if (titleInput) titleInput.value = draft.title || '';
            if (slugInput) slugInput.value = draft.slug || '';
            textarea.value = draft.text || '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            removeDraft();
            dismissBanner();
            lastSavedText = nowTime();
            dirty = true; // 恢复的内容与线上不同 → 视为未保存
            refreshStatus();
        });
        banner.querySelector('.sdb-discard').addEventListener('click', function () {
            removeDraft();
            dismissBanner();
        });
    }

    function checkDraftOnLoad() {
        var draft = readDraft();
        if (!draft) return;
        var snap = currentSnapshot();
        // 草稿与当前内容一致 → 静默清理，不打扰
        if (draft.text === snap.text && draft.title === snap.title &&
            (draft.slug || '') === (snap.slug || '')) {
            removeDraft();
            return;
        }
        showBanner(draft);
    }

    /* ===== 快捷键 ===== */
    /** 以指定原生按钮提交表单（requestSubmit 优先：焦点模式下按钮隐藏也可靠；
     *  Typecho 防重复提交会给按钮加 disabled + form 加 submitting，click 会被挡，
     *  requestSubmit 不受 disabled 影响） */
    function submitWith(btnId) {
        var btn = document.getElementById(btnId);
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

    document.addEventListener('keydown', function (e) {
        var mod = e.ctrlKey || e.metaKey;
        if (!mod) return;
        var key = (e.key || '').toLowerCase();

        // Ctrl+S 保存草稿
        if (key === 's' && !e.shiftKey) {
            e.preventDefault();
            submitWith('btn-save');
            return;
        }
        // Ctrl+Enter 发布
        if (e.key === 'Enter') {
            e.preventDefault();
            submitWith('btn-submit');
            return;
        }
        // Ctrl+Shift+P 预览
        if (key === 'p' && e.shiftKey) {
            e.preventDefault();
            var prev = document.getElementById('btn-preview');
            if (prev) prev.click();
            return;
        }
    });

    /* ===== 事件绑定 ===== */
    ['input', 'change', 'keyup', 'paste', 'cut'].forEach(function (ev) {
        textarea.addEventListener(ev, scheduleSave);
    });
    [titleInput, slugInput].forEach(function (el) {
        if (el) el.addEventListener('input', scheduleSave);
    });

    // 表单真实提交后清除脏标记（提交会整体导航，本地状态随之重置）
    form.addEventListener('submit', function () {
        dirty = false;
        if (saveTimer) clearTimeout(saveTimer);
    });

    // 兜底周期保存（防止 debounce 被持续输入无限推迟）
    setInterval(function () {
        if (dirty) saveDraft();
    }, SAFETY_INTERVAL);

    /* ===== 初始化 ===== */
    buildStatus();
    checkDraftOnLoad();
    refreshStatus();

    // 新文章自动聚焦标题（编辑已有文章不抢焦点，避免打断续写）
    var isNew = !cid || cid === 'new' || cid === '0';
    if (isNew && titleInput) {
        setTimeout(function () { titleInput.focus(); }, 300);
    }

    /* ===== 对外 API ===== */
    window.shufeiAutosave = {
        saveNow: function () { saveDraft(); },
        getDraft: readDraft,
        clearDraft: removeDraft,
        isDirty: function () { return dirty; },
    };
})();
