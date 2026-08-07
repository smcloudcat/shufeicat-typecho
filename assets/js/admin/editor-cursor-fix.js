
/* ===== 修复编辑器插入图片/媒体后跳转到底部 =====
 * 跳转由多个机制叠加导致：
 *  1. ShuFeiCat insertAtCursor 中 textarea.setSelection() → jQuery.focus() + setSelectionRange()
 *     浏览器自动滚动 textarea 让光标可见
 *  2. textarea.trigger('input') → pagedown 刷新预览 → onPreviewRefresh → reloadScroll(true)
 *     reloadScroll 设置 preview.scrollTop 到光标对应行
 *  3. pagedown 的 input 处理：r.preview.scrollTop = (scrollHeight-clientHeight) * f(r.preview)
 *     重置 preview 滚动位置
 *  4. pagedown 的 window.scrollBy(0, t-n) 窗口滚动补偿
 *  5. scrollableEditor 的 p() 函数用 requestAnimationFrame + a.scrollTo({top: ...})
 *     在 500ms 内平滑滚动 textarea（这是 textarea 跳到末尾的真正根因）
 *
 * 修复策略：
 *  - 临时覆盖 textarea.scrollTo 为 no-op，让 p() 的 a.scrollTo({top: ...}) 失效
 *  - 用 setInterval 持续恢复 textarea/preview/window 三个元素滚动位置作双保险
 *  - 1200ms 后恢复原生 scrollTo 并停止持续恢复
 */
(function () {
    if (window.__shufeiEditorCursorFixBound) return;
    window.__shufeiEditorCursorFixBound = true;

    var ta = document.getElementById('text');
    var toolbar = document.getElementById('wmd-button-bar');
    if (!ta) return;

    // 预览区元素（pagedown 的 #wmd-preview）
    function getPreview() {
        return document.getElementById('wmd-preview');
    }

    // 状态变量
    var savedScrollY = 0;        // 窗口滚动位置
    var savedTaScroll = 0;       // textarea 滚动位置
    var savedPreviewScroll = 0;  // preview 滚动位置
    var pendingInsert = false;   // 有插入操作待处理
    var pendingTimeoutId = null; // pendingInsert 自动清除定时器
    var restoreIntervalId = null;// 持续恢复的 setInterval ID

    /**
     * 保存当前所有滚动位置
     */
    function saveScrollPositions() {
        savedScrollY = window.scrollY;
        savedTaScroll = ta.scrollTop;
        var preview = getPreview();
        savedPreviewScroll = preview ? preview.scrollTop : 0;
    }

    /**
     * 恢复所有滚动位置（窗口 + textarea + preview）
     */
    function restoreScroll() {
        window.scrollTo(0, savedScrollY);
        ta.scrollTop = savedTaScroll;
        var preview = getPreview();
        if (preview) preview.scrollTop = savedPreviewScroll;
    }

    /**
     * 清除 pendingInsert 标志，停止持续恢复
     */
    function clearPending() {
        pendingInsert = false;
        if (pendingTimeoutId) {
            clearTimeout(pendingTimeoutId);
            pendingTimeoutId = null;
        }
        if (restoreIntervalId) {
            clearInterval(restoreIntervalId);
            restoreIntervalId = null;
        }
    }

    /**
     * 设置 pendingInsert 标志，启动 60 秒超时自动清除
     */
    function setPending() {
        pendingInsert = true;
        if (pendingTimeoutId) clearTimeout(pendingTimeoutId);
        pendingTimeoutId = setTimeout(clearPending, 60000);
    }

    /* 1. 监听工具栏按钮点击 - 保存滚动位置
     * （insertAtCursor 中已有完整的保存/恢复逻辑，这里仅作辅助）
     */
    function handleToolbarClick(e) {
        var target = e.target;
        if (!target || !target.closest) return;
        var btn = target.closest('li[id^="wmd-"]') ||
                  target.closest('.shufei-qi-btn') ||
                  target.closest('.shufei-ai-btn');
        if (!btn) return;
        saveScrollPositions();
    }
    if (toolbar) {
        toolbar.addEventListener('click', handleToolbarClick, true);
    }
    document.addEventListener('click', handleToolbarClick, true);

    /* 2. input 事件 - 恢复滚动位置（双保险，覆盖附件插入等情况） */
    ta.addEventListener('input', function () {
        if (!pendingInsert) return;
        restoreScroll();
        // 重置定时器
        if (pendingTimeoutId) clearTimeout(pendingTimeoutId);
        pendingTimeoutId = setTimeout(function () {
            restoreScroll();
            clearPending();
        }, 2000);
    });

    /* 3. 处理附件插入（Typecho.insertFileToEditor / uploadComplete）
     * 这两个函数在 editor-js.php 中被重定义，可能在 bottom 钩子之后
     */
    function wrapTypechoFuncs() {
        if (!window.Typecho) return;
        // 包装 insertFileToEditor
        if (typeof Typecho.insertFileToEditor === 'function' && !Typecho.__shufeiWrapped_insert) {
            var origInsert = Typecho.insertFileToEditor;
            Typecho.insertFileToEditor = function () {
                saveScrollPositions();
                setPending();
                return origInsert.apply(this, arguments);
            };
            Typecho.__shufeiWrapped_insert = true;
        }
        // 包装 uploadComplete
        if (typeof Typecho.uploadComplete === 'function' && !Typecho.__shufeiWrapped_upload) {
            var origUpload = Typecho.uploadComplete;
            Typecho.uploadComplete = function () {
                saveScrollPositions();
                setPending();
                return origUpload.apply(this, arguments);
            };
            Typecho.__shufeiWrapped_upload = true;
        }
    }
    // 立即尝试包装
    wrapTypechoFuncs();
    // editor-js.php 可能在本脚本之后重定义这些函数，延迟再包装一次
    setTimeout(wrapTypechoFuncs, 0);
    setTimeout(wrapTypechoFuncs, 500);
})();

