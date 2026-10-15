/**
 * 文章引用选择器（后台编辑器）
 *
 * 功能：在后台写文章/写页面时，搜索站内文章并选择引用（上限 5 篇）。
 * 存储：动态维护表单内隐藏域 fields[shufeiCitePosts]（JSON 数组字符串），
 *      由 Typecho 核心 getFields()/applyFields() 自动保存为自定义字段；
 *      保存前由服务端 write 过滤器（shufei_article_cite_write_filter）消毒。
 * 空值策略：无选中时隐藏域保留 "[]"（与「JS 未运行→无此域」区分，
 *      服务端据此 unset 触发核心清理字段行）。
 */
(function ($) {
    $(function () {
        var cfg = window.SHUFEI_ADMIN || {};
        var ajaxUrl = cfg.articleCiteAjaxUrl;
        var MAX = parseInt(cfg.articleCiteMax, 10) || 5;
        var currentCid = parseInt(cfg.articleCiteCid, 10) || 0;

        var $toolbar = $('#shufei-cite-toolbar');
        var $chips = $('#shufei-cite-chips');
        var $mask = $('#shufei-cite-mask');
        var $modal = $('#shufei-cite-modal');
        var $openBtn = $('#shufei-cite-open-btn');
        var $closeBtn = $('#shufei-cite-close');
        var $doneBtn = $('#shufei-cite-done-btn');
        var $searchInput = $('#shufei-cite-search-input');
        var $results = $('#shufei-cite-results');
        var $statusTip = $('#shufei-cite-status-tip');
        if (!$toolbar.length || !$modal.length || !ajaxUrl) return;

        var selected = [];   // [{cid,title,date,status,statusLabel}]
        var articles = [];   // 最近一次搜索结果
        var searchTimer = null;
        var curCtrl = null;

        // 初始回显（PHP 传入，已按选择顺序；防御性去重）
        (cfg.articleCiteCurrent || []).forEach(function (a) {
            if (a && a.cid && !findIdx(a.cid).found) {
                selected.push({
                    cid: parseInt(a.cid, 10),
                    title: a.title || '',
                    date: a.date || '',
                    status: a.status || '',
                    statusLabel: a.statusLabel || ''
                });
            }
        });

        function findIdx(cid) {
            for (var i = 0; i < selected.length; i++) {
                if (selected[i].cid === cid) return { found: true, idx: i };
            }
            return { found: false, idx: -1 };
        }

        /* ---------- 隐藏域维护（核心保存依赖） ----------
         * action 请求不加载主题 functions.php（write 过滤器不可用），保存完全依赖
         * 核心 fields 自动化：隐藏域在 → 存字段；不在 → $exists 清理删字段行。
         * 故空选时移除隐藏域而非留 "[]"，避免 DB 积压空字段行；
         * JS 禁用/失败时由 editor-ui.php 的 vanilla 预填脚本兜底。
         */
        function syncHidden() {
            var $form = $('form[name="write_post"], form[name="write_page"]').first();
            if (!$form.length) return;
            var $input = $form.find('input[name="fields[shufeiCitePosts]"]');
            if (!$input.length) {
                // bottom 钩子在 </form> 之后，隐藏域必须动态挂进表单
                $input = $('<input type="hidden" name="fields[shufeiCitePosts]">');
                $form.append($input);
            }
            var cids = [];
            selected.forEach(function (a) { cids.push(a.cid); });
            if (!cids.length) {
                // 无选中：移除隐藏域 → 核心 $exists 清理删除该自定义字段行
                if ($input.length) $input.remove();
                return;
            }
            if (!$input.length) {
                // bottom 钩子在 </form> 之后，隐藏域必须动态挂进表单
                $input = $('<input type="hidden" name="fields[shufeiCitePosts]"]');
                $form.append($input);
            }
            $input.val(JSON.stringify(cids));
        }

        /* ---------- chips 渲染 ---------- */
        function badgeClass(status) {
            return (status && status !== 'publish') ? ' ' + status : '';
        }

        function renderChips() {
            $chips.empty();
            if (!selected.length) {
                // JS 直接控制显示（内联 display 优先于类，避免与注入前的 display:none 冲突）
                $chips.css('display', 'none').removeClass('has-items');
                $statusTip.text('发布后将在文章末尾显示引用卡片');
                return;
            }
            $chips.addClass('has-items').css('display', 'flex');
            selected.forEach(function (a) {
                var $chip = $('<span class="shufei-cite-chip"></span>');
                var $title = $('<span class="shufei-cite-chip-title"></span>').text(a.title);
                $chip.append($title);
                if (a.status && a.status !== 'publish') {
                    $chip.append($('<span class="shufei-cite-chip-badge"></span>').text(a.statusLabel || a.status));
                }
                var $remove = $('<button type="button" class="shufei-cite-chip-remove" title="移除引用">&times;</button>');
                $remove.on('click', function (e) {
                    e.preventDefault();
                    removeCite(a.cid);
                });
                $chip.append($remove);
                $chips.append($chip);
            });
            $statusTip.text('已选 ' + selected.length + ' / ' + MAX + ' 篇');
        }

        /* ---------- 搜索结果渲染 ---------- */
        function renderResults() {
            $results.empty();
            if (!articles.length) {
                $results.append($('<div class="shufei-cite-empty"></div>')
                    .text($searchInput.val().trim() ? '没有找到相关文章' : '暂无文章'));
                return;
            }
            articles.forEach(function (a) {
                var hit = findIdx(a.cid);
                var $item = $('<div class="shufei-cite-result-item" role="option"></div>');
                if (hit.found) $item.addClass('selected');
                $item.append($('<span class="shufei-cite-result-title"></span>').text(a.title));
                $item.append($('<span class="shufei-cite-result-date"></span>').text(a.date || ''));
                if (a.status && a.status !== 'publish') {
                    $item.append($('<span class="shufei-cite-result-badge' + badgeClass(a.status) + '"></span>')
                        .text(a.statusLabel || a.status));
                }
                $item.append($('<i class="fa fa-check shufei-cite-result-check"></i>'));
                $item.on('click', function () { toggleCite(a); });
                $results.append($item);
            });
        }

        function toggleCite(a) {
            var hit = findIdx(a.cid);
            if (hit.found) {
                selected.splice(hit.idx, 1);
            } else {
                if (selected.length >= MAX) {
                    $statusTip.text('最多只能引用 ' + MAX + ' 篇文章').css('color', '#cf1322');
                    setTimeout(function () { $statusTip.css('color', ''); renderChips(); }, 1500);
                    return;
                }
                selected.push({
                    cid: a.cid,
                    title: a.title || '',
                    date: a.date || '',
                    status: a.status || '',
                    statusLabel: a.statusLabel || ''
                });
            }
            renderChips();
            renderResults();
            syncHidden();
        }

        function removeCite(cid) {
            var hit = findIdx(cid);
            if (hit.found) selected.splice(hit.idx, 1);
            renderChips();
            renderResults();
            syncHidden();
        }

        /* ---------- 搜索（防抖 + 请求中断） ---------- */
        function search(q) {
            if (curCtrl) {
                try { curCtrl.abort(); } catch (e) {}
            }
            curCtrl = new AbortController();
            var fd = new FormData();
            fd.append('action', 'search');
            fd.append('q', q);
            fd.append('exclude', currentCid);
            fd.append('limit', 10);
            fetch(ajaxUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                signal: curCtrl.signal
            }).then(function (resp) {
                return resp.json();
            }).then(function (data) {
                curCtrl = null;
                if (data && data.success) {
                    articles = data.articles || [];
                    renderResults();
                } else {
                    articles = [];
                    renderResults();
                    $results.find('.shufei-cite-empty').text((data && data.message) ? data.message : '搜索失败');
                }
            }).catch(function (err) {
                curCtrl = null;
                if (err && err.name === 'AbortError') return; // 被新请求中断，静默
                articles = [];
                renderResults();
                $results.find('.shufei-cite-empty').text('搜索失败，请稍后重试');
            });
        }

        /* ---------- 弹窗开合 ---------- */
        function openModal() {
            $mask.addClass('show');
            $modal.addClass('show');
            setTimeout(function () { $searchInput.focus(); }, 50);
            if (!articles.length) search($searchInput.val().trim());
        }

        function closeModal() {
            $mask.removeClass('show');
            $modal.removeClass('show');
        }

        $openBtn.on('click', function (e) {
            e.preventDefault();
            if ($modal.hasClass('show')) {
                closeModal();
            } else {
                openModal();
            }
        });
        $closeBtn.add($doneBtn).on('click', function (e) {
            e.preventDefault();
            closeModal();
        });
        $mask.on('click', closeModal);

        $searchInput.on('input', function () {
            if (searchTimer) clearTimeout(searchTimer);
            var q = $searchInput.val().trim();
            searchTimer = setTimeout(function () { search(q); }, 300);
        });

        $searchInput.on('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeModal();
                $openBtn.focus();
            }
        });

        /* ---------- 工具栏注入到编辑器上方（与 AI 写作工具栏一致） ---------- */
        function injectToolbar() {
            if ($toolbar.data('injected')) return;
            var $text = $('#text');
            if (!$text.length) return;
            $toolbar.add($chips).insertBefore($text).show();
            $toolbar.data('injected', true);
        }
        injectToolbar();
        // 重试注入（等待编辑器初始化）
        setTimeout(injectToolbar, 500);
        setTimeout(injectToolbar, 1500);

        /* ---------- 初始化 ---------- */
        renderChips();
        syncHidden();
    });
})(jQuery);
