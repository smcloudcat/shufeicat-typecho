/**
 * 编辑器图片粘贴/拖拽直传（2026-10-16 编辑器重构 · 阶段三：高效）
 *
 * 功能：
 * - Ctrl+V 粘贴剪贴板图片 → 直接走存储上传 → 光标处插入 Markdown
 * - 拖拽图片到 textarea → 同上（dragover 时高亮编辑器边框）
 * - 多图并行上传，逐张插入（各占一段）
 * - 浮动 toast 反馈上传进度/结果
 *
 * Profile 选择：优先读上传弹窗里的 profile 下拉（尊重用户选择）；
 * 下拉尚未就绪时回退 list_profiles 取 activeProfileId。
 * 自包含实现，不依赖 storage-editor.js 内部状态。
 */
(function () {
    'use strict';

    var textarea = document.getElementById('text');
    if (!textarea) return;

    var AJAX_URL = (window.SHUFEI_ADMIN && window.SHUFEI_ADMIN.ajaxUrl) || '';
    var IMAGE_RE = /^image\//;

    /* ===== toast ===== */
    var toastEl = null;
    var toastTimer = null;
    function toast(msg, duration) {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.id = 'shufei-paste-toast';
            toastEl.className = 'shufei-paste-toast';
            document.body.appendChild(toastEl);
        }
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('show');
        }, duration || 2200);
    }

    /* ===== 光标插入（保存滚动位置，防跳到底部）===== */
    function insertAtCursor(text) {
        var startPos = textarea.selectionStart || 0;
        var endPos = textarea.selectionEnd || 0;
        var scrollTop = textarea.scrollTop;
        textarea.value = textarea.value.substring(0, startPos) + text +
            textarea.value.substring(endPos, textarea.value.length);
        textarea.focus();
        var pos = startPos + text.length;
        textarea.selectionStart = pos;
        textarea.selectionEnd = pos;
        textarea.scrollTop = scrollTop;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /* ===== Profile 解析 ===== */
    function getProfileId() {
        var sel = document.getElementById('shufei-storage-profile-select');
        if (sel && sel.value) return sel.value;
        return null; // 需要回退查询
    }

    function fetchActiveProfileId() {
        if (!AJAX_URL) return Promise.reject(new Error('ajaxUrl 未配置'));
        var fd = new FormData();
        fd.append('action', 'list_profiles');
        return fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || '读取 Profile 失败');
                if (!d.enabled || !d.profiles || d.profiles.length === 0) {
                    throw new Error('尚未配置存储 Profile');
                }
                return d.activeProfileId || d.profiles[0].id;
            });
    }

    /* ===== 上传单张 ===== */
    function uploadImage(file, profileId) {
        var fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('profile_id', profileId);
        fd.append('file', file, file.name || 'paste.png');
        return fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || '上传失败');
                return d.url;
            });
    }

    /* ===== 批量处理 ===== */
    function handleFiles(fileList) {
        var files = [];
        for (var i = 0; i < fileList.length; i++) {
            if (IMAGE_RE.test(fileList[i].type || '')) files.push(fileList[i]);
        }
        if (!files.length) return false;

        toast('图片上传中 ' + files.length + ' 张…', 60000);
        var profilePromise = getProfileId() ?
            Promise.resolve(getProfileId()) : fetchActiveProfileId();

        profilePromise.then(function (profileId) {
            var done = 0, failed = 0;
            var first = true;
            files.forEach(function (file) {
                uploadImage(file, profileId).then(function (url) {
                    insertAtCursor((first ? '' : '\n\n') + '![](' + url + ')');
                    first = false;
                }).catch(function (e) {
                    failed++;
                    console.warn('[ShuFeiCat] 图片上传失败:', e);
                }).then(function () {
                    done++;
                    if (done === files.length) {
                        toast(failed > 0 ?
                            '上传完成：成功 ' + (files.length - failed) + ' 张，失败 ' + failed + ' 张' :
                            '已插入 ' + files.length + ' 张图片', failed > 0 ? 4000 : 2200);
                    }
                });
            });
        }).catch(function (e) {
            toast('上传失败：' + e.message, 4000);
        });
        return true;
    }

    /* ===== 事件：粘贴 ===== */
    textarea.addEventListener('paste', function (e) {
        var cd = e.clipboardData;
        if (!cd || !cd.files || cd.files.length === 0) return;
        if (handleFiles(cd.files)) {
            e.preventDefault();
        }
    });

    /* ===== 事件：拖拽 ===== */
    textarea.addEventListener('dragover', function (e) {
        if (e.dataTransfer && Array.prototype.some.call(e.dataTransfer.types || [], function (t) { return t === 'Files'; })) {
            e.preventDefault();
            textarea.classList.add('shufei-drag-over');
        }
    });
    textarea.addEventListener('dragleave', function () {
        textarea.classList.remove('shufei-drag-over');
    });
    textarea.addEventListener('drop', function (e) {
        textarea.classList.remove('shufei-drag-over');
        var dt = e.dataTransfer;
        if (!dt || !dt.files || dt.files.length === 0) return;
        if (handleFiles(dt.files)) {
            e.preventDefault();
        }
    });
})();
