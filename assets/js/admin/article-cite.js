/**
 * 文章引用选择器（后台编辑器）— Markdown 增强语法版
 *
 * 2026-10-15 起改为正文标记语法：在编辑器光标处插入 [cite:cid]（独占一行），
 * 前台由 shufei_process_article_cites()（core/markdown.php）渲染为引用卡片。
 * 上限 SHUFEI_ARTICLE_MAX_CITES 篇（按正文标记计数）。
 *
 * 旧版兼容：articleCiteCurrent（PHP 回显的 fields[shufeiCitePosts] 旧数据）
 * 渲染为「待迁移」chips，一键插入正文；fields 隐藏域由 editor-ui.php 预填保留
 * （旧文章保存不丢字段，正文无标记时文末兜底渲染继续生效）。
 */
(function ($) {
    $(function () {
        var cfg = window.SHUFEI_ADMIN || {};
        var ajaxUrl = cfg.articleCiteAjaxUrl;
        var MAX = parseInt(cfg.articleCiteMax, 10) || 5;
        var currentCid = parseInt(cfg.articleCiteCid, 10) || 0;

        var $chips = $('#shufei-cite-chips');
        var $mask = $('#shufei-cite-mask');
        var $modal = $('#shufei-cite-modal');
        var $closeBtn = $('#shufei-cite-close');
        var $doneBtn = $('#shufei-cite-done-btn');
        var $searchInput = $('#shufei-cite-search-input');
        var $results = $('#shufei-cite-results');
        if (!$chips.length || !$modal.length || !ajaxUrl) return;

        var articles = [];   // 最近一次搜索结果
        var searchTimer = null;
        var curCtrl = null;
        var titleMap = {};   // cid => 文章信息（插入时记录，chips 展示用）

        // 旧版 fields 回显（待迁移，不在正文中）
        var legacy = [];
        (cfg.articleCiteCurrent || []).forEach(function (a) {
            if (a && a.cid && !titleMap[a.cid]) {
                titleMap[a.cid] = {
                    cid: parseInt(a.cid, 10),
                    title: a.title || '',
                    date: a.date || '',
                    status: a.status || '',
                    statusLabel: a.statusLabel || ''
                };
                legacy.push(parseInt(a.cid, 10));
            }
        });

        /* ---------- 正文 textarea 操作 ---------- */
        function $text() {
            return $('#text');
        }

        // 解析正文中的 [cite:N] 标记（保序去重）
        function parseMarkers() {
            var val = $text().val() || '';
            var re = /\[cite:(\d+)\]/g;
            var cids = [];
            var m;
            while ((m = re.exec(val)) !== null) {
                var c = parseInt(m[1], 10);
                if (c > 0 && cids.indexOf(c) === -1) cids.push(c);
            }
            return cids;
        }

        // 在光标处插入 [cite:cid]（独占一段：前后均保证空行，块级渲染）
        function insertMarker(cid) {
            var $t = $text();
            if (!$t.length) return false;
            var el = $t[0];
            var val = el.value || '';
            var start = el.selectionStart;
            var end = el.selectionEnd;
            if (typeof start !== 'number') start = val.length;
            if (typeof end !== 'number') end = start;

            var before = val.slice(0, start);
            var after = val.slice(end);
            // 前缀：before 非空行结尾时补空行（\n → \n\n；行中 → \n\n）
            var prefix = /\n\n$/.test(before) ? '' : (/\n$/.test(before) ? '\n' : '\n\n');
            // 后缀：after 非空行开头时补空行
            var suffix = /^\n\n/.test(after) ? '' : (/^\n/.test(after) ? '\n' : '\n\n');
            var marker = '[cite:' + cid + ']';
            var insertText = prefix + marker + suffix;

            el.value = before + insertText + after;
            var pos = start + insertText.length;
            el.selectionStart = el.selectionEnd = pos;
            $t.trigger('input').trigger('change').trigger('keyup');
            return true;
        }

        // 从正文移除某个 [cite:N] 标记（删除所在行）
        function removeMarker(cid) {
            var $t = $text();
            if (!$t.length) return;
            var el = $t[0];
            var val = el.value || '';
            var re = new RegExp('[ \\t]*\\[cite:' + cid + '\\][ \\t]*\\n?', 'g');
            el.value = val.replace(re, '');
            $t.trigger('input').trigger('change').trigger('keyup');
        }

        /* ---------- chips 渲染（正文标记 + 待迁移旧引用） ---------- */
        function badgeClass(status) {
            return (status && status !== 'publish') ? ' ' + status : '';
        }

        function chipTitle(cid) {
            var info = titleMap[cid];
            return info ? info.title : ('文章 #' + cid);
        }

        function renderChips() {
            $chips.empty();
            var markers = parseMarkers();
            var hasItems = markers.length > 0 || legacy.length > 0;

            if (!hasItems) {
                $chips.css('display', 'none').removeClass('has-items');
                return;
            }
            $chips.addClass('has-items').css('display', 'flex');

            // 1. 正文标记 chips（可移除）
            markers.forEach(function (cid) {
                var info = titleMap[cid] || {};
                var $chip = $('<span class="shufei-cite-chip"></span>');
                $chip.append($('<span class="shufei-cite-chip-title"></span>').text(chipTitle(cid)));
                if (info.status && info.status !== 'publish') {
                    $chip.append($('<span class="shufei-cite-chip-badge"></span>').text(info.statusLabel || info.status));
                }
                var $remove = $('<button type="button" class="shufei-cite-chip-remove" title="从正文移除">&times;</button>');
                $remove.on('click', function (e) {
                    e.preventDefault();
                    removeMarker(cid);
                    refreshAll();
                });
                $chip.append($remove);
                $chips.append($chip);
            });

            // 2. 旧版 fields 引用（待迁移）
            legacy.forEach(function (cid) {
                var info = titleMap[cid] || {};
                var $chip = $('<span class="shufei-cite-chip shufei-cite-chip-legacy"></span>');
                $chip.append($('<span class="shufei-cite-chip-title"></span>').text(chipTitle(cid)));
                $chip.append($('<span class="shufei-cite-chip-badge"></span>').text('旧版'));
                var $migrate = $('<button type="button" class="shufei-cite-chip-migrate" title="插入正文">插入正文</button>');
                $migrate.on('click', function (e) {
                    e.preventDefault();
                    if (parseMarkers().length >= MAX) {
                        alert('正文引用标记已达上限 ' + MAX + ' 篇');
                        return;
                    }
                    insertMarker(cid);
                    legacy.splice(legacy.indexOf(cid), 1);
                    refreshAll();
                });
                $chip.append($migrate);
                $chips.append($chip);
            });
        }

        /* ---------- 搜索结果渲染 ---------- */
        function renderResults() {
            $results.empty();
            if (!articles.length) {
                $results.append($('<div class="shufei-cite-empty"></div>')
                    .text($searchInput.val().trim() ? '没有找到相关文章' : '暂无文章'));
                return;
            }
            var markers = parseMarkers();
            articles.forEach(function (a) {
                var inText = markers.indexOf(a.cid) !== -1;
                var $item = $('<div class="shufei-cite-result-item" role="option"></div>');
                if (inText) $item.addClass('selected');
                $item.append($('<span class="shufei-cite-result-title"></span>').text(a.title));
                $item.append($('<span class="shufei-cite-result-date"></span>').text(a.date || ''));
                if (a.status && a.status !== 'publish') {
                    $item.append($('<span class="shufei-cite-result-badge' + badgeClass(a.status) + '"></span>')
                        .text(a.statusLabel || a.status));
                }
                $item.append($('<i class="fa fa-check shufei-cite-result-check"></i>'));
                $item.on('click', function () { insertCite(a); });
                $results.append($item);
            });
        }

        function insertCite(a) {
            var markers = parseMarkers();
            if (markers.indexOf(a.cid) !== -1) {
                toast('该文章已在正文中引用');
                return;
            }
            if (markers.length >= MAX) {
                toast('最多只能引用 ' + MAX + ' 篇文章');
                return;
            }
            if (!insertMarker(a.cid)) {
                toast('未找到正文编辑器');
                return;
            }
            titleMap[a.cid] = {
                cid: a.cid,
                title: a.title || '',
                date: a.date || '',
                status: a.status || '',
                statusLabel: a.statusLabel || ''
            };
            var li = legacy.indexOf(a.cid);
            if (li !== -1) legacy.splice(li, 1);
            refreshAll();
            toast('已插入 [cite:' + a.cid + ']');
        }

        function toast(msg) {
            alert(msg);
            refreshAll();
        }

        function refreshAll() {
            renderChips();
            renderResults();
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
            }
        });

        /* ---------- chips 注入到编辑器上方（引用标记存在时才显示） ---------- */
        function injectChips() {
            if ($chips.data('injected')) return;
            var $t = $text();
            if (!$t.length) return;
            $chips.insertBefore($t);
            $chips.data('injected', true);
        }
        injectChips();
        // 重试注入（等待编辑器初始化）
        setTimeout(injectChips, 500);
        setTimeout(injectChips, 1500);

        /* ---------- 注册到编辑器快捷按钮行（选择引用文章） ---------- */
        window.SHUFEI_QI_EXTRA = window.SHUFEI_QI_EXTRA || [];
        window.SHUFEI_QI_ACTIONS = window.SHUFEI_QI_ACTIONS || {};
        SHUFEI_QI_EXTRA.push(
            { action: 'article-cite', label: '引用文章', title: '引用站内文章，在光标处插入 [cite:cid] 标记' }
        );
        SHUFEI_QI_ACTIONS['article-cite'] = openModal;
        // 本脚本加载晚于快捷按钮重建时补挂
        if (window.shufeiQiApplyExtra) window.shufeiQiApplyExtra();

        /* ---------- 正文变化时刷新 chips（粘贴/撤销/手动删除标记） ---------- */
        $text().on('input change', function () {
            if (!$modal.hasClass('show')) {
                renderChips();
            }
        });

        /* ---------- 初始化 ---------- */
        renderChips();
    });
})(jQuery);
