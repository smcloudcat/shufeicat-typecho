/*!
 * ShuFeiCat · AI 消息 Markdown 轻量渲染器（前台悬浮窗 + 后台设置助手共用）
 *
 * 把 AI 回复的 Markdown 文本渲染成安全的 HTML 片段。
 *
 * 安全设计（AI 输出可能与站内数据拼接，一律按不可信内容处理）：
 *  1. 先把整段文本做 HTML 转义，再套用**我们自己生成**的白名单标签；
 *     原始 HTML（<script>、<img onerror> 等）永远不可能进入输出。
 *  2. 链接只放行 http/https/站内相对路径/#锚点/mailto，javascript:、data: 等一律降级为纯文本。
 *  3. 图片语法只保留 alt 文本（不加载外部资源，避免追踪与布局错乱）。
 *  4. 代码块语言名做字符白名单，防止属性逃逸。
 *
 * 支持语法：标题、粗体、斜体、删除线、行内代码、代码块、有序/无序列表、
 *          引用、分隔线、表格、链接与裸链接。
 *
 * 用法：ShufeiMD.render('**你好**')  →  '<strong>你好</strong>'
 */
(function (root) {
    'use strict';

    var ESC_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ESC_MAP[c]; });
    }

    /** 只放行安全协议；不安全返回空串 */
    function safeUrl(url) {
        var u = String(url == null ? '' : url).trim();
        if (!u) return '';
        if (/^(https?:\/\/|\/|#|mailto:|\?)/i.test(u)) return u;
        // 站内相对路径（不含协议）
        if (/^[\w.-]+(\/|$)/.test(u) && !/^[a-z][a-z0-9+.-]*:/i.test(u)) return u;
        return '';
    }

    function aTag(text, url) {
        var u = safeUrl(url);
        if (!u) return text;
        var ext = /^https?:\/\//i.test(u) ? ' target="_blank" rel="noopener noreferrer"' : '';
        return '<a href="' + esc(u) + '"' + ext + '>' + text + '</a>';
    }

    /** 行内元素（入参已是转义后的纯文本） */
    function inline(s) {
        var holds = [];
        function hold(html) {
            holds.push(html);
            return '\u0001H' + (holds.length - 1) + 'H\u0001';
        }

        // 行内代码 → 占位，内部不再套用任何规则
        s = s.replace(/`([^`\n]+)`/g, function (m, c) { return hold('<code>' + c + '</code>'); });
        // 图片语法只留 alt
        s = s.replace(/!\[([^\]]*)\]\([^)\s]*(?:\s+"[^"]*")?\)/g, '$1');
        // 链接 → 占位（避免其中的 URL 被裸链接规则二次包装）
        s = s.replace(/\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, function (m, t, u) {
            return hold(aTag(t, u));
        });
        // 裸链接
        s = s.replace(/(^|[\s(（])((?:https?:\/\/)[^\s<>"'）)]+)/g, function (m, pre, u) {
            return pre + hold(aTag(u, u));
        });

        s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
        s = s.replace(/(^|[^*\w])\*([^*\n]+)\*/g, '$1<em>$2</em>');
        s = s.replace(/(^|[^_\w])_([^_\n]+)_/g, '$1<em>$2</em>');
        s = s.replace(/~~([^~\n]+)~~/g, '<del>$1</del>');

        // 还原占位
        return s.replace(/\u0001H(\d+)H\u0001/g, function (m, i) { return holds[+i]; });
    }

    /** 判断第 i 行是否为表格起始（下一行是分隔行） */
    function isTableStart(lines, i) {
        if (i + 1 >= lines.length) return false;
        if (!/\|/.test(lines[i])) return false;
        return /^\s*\|?[\s:|-]*-[\s:|-]*\|?\s*$/.test(lines[i + 1]) && /\|/.test(lines[i + 1]);
    }

    function splitRow(line) {
        var t = line.trim().replace(/^\|/, '').replace(/\|$/, '');
        return t.split('|').map(function (c) { return c.trim(); });
    }

    function renderTable(lines, start) {
        var head = splitRow(lines[start]);
        var i = start + 2;
        var rows = [];
        while (i < lines.length && /\|/.test(lines[i]) && !/^\s*$/.test(lines[i])) {
            rows.push(splitRow(lines[i]));
            i++;
        }
        var html = '<div class="md-table-wrap"><table><thead><tr>';
        head.forEach(function (c) { html += '<th>' + inline(esc(c)) + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(function (r) {
            html += '<tr>';
            for (var k = 0; k < head.length; k++) {
                html += '<td>' + inline(esc(r[k] == null ? '' : r[k])) + '</td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return { html: html, next: i };
    }

    /**
     * 渲染 Markdown 为 HTML 片段
     * @param {string} src
     * @returns {string}
     */
    function render(src) {
        if (src == null || src === '') return '';
        // ⚠️ 结构判定必须用**原始行**：先整体转义会把 `>` 变成 `&gt;`，
        // 引用（`>`）、标题（`#` 不受影响）等块级语法就再也匹配不到了。
        // 统一约定：结构用原始行判断，内容先 esc 再套行内规则。
        var lines = String(src).replace(/\r\n?/g, '\n').split('\n');
        var out = [];
        var i = 0;

        while (i < lines.length) {
            var line = lines[i];

            /* 代码块 ```lang */
            var fence = line.match(/^\s{0,3}```+\s*([\w+#.-]*)\s*$/);
            if (fence) {
                var lang = (fence[1] || '').replace(/[^\w+#.-]/g, '');
                var buf = [];
                i++;
                while (i < lines.length && !/^\s{0,3}```+\s*$/.test(lines[i])) { buf.push(esc(lines[i])); i++; }
                i++; // 跳过收尾围栏（未闭合时 i 已到末尾）
                out.push('<pre class="md-pre"><code'
                    + (lang ? ' class="language-' + lang + '"' : '')
                    + '>' + buf.join('\n') + '</code></pre>');
                continue;
            }

            /* 表格 */
            if (isTableStart(lines, i)) {
                var tb = renderTable(lines, i);
                out.push(tb.html);
                i = tb.next;
                continue;
            }

            /* 标题 */
            var h = line.match(/^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/);
            if (h) {
                var lv = h[1].length;
                out.push('<h' + lv + '>' + inline(esc(h[2])) + '</h' + lv + '>');
                i++;
                continue;
            }

            /* 分隔线 */
            if (/^\s{0,3}([-*_])\s*(\1\s*){2,}$/.test(line)) {
                out.push('<hr>');
                i++;
                continue;
            }

            /* 引用（连续行合并） */
            if (/^\s{0,3}>\s?/.test(line)) {
                var quote = [];
                while (i < lines.length && /^\s{0,3}>\s?/.test(lines[i])) {
                    quote.push(lines[i].replace(/^\s{0,3}>\s?/, ''));
                    i++;
                }
                out.push('<blockquote>' + inline(esc(quote.join('\n'))).replace(/\n/g, '<br>') + '</blockquote>');
                continue;
            }

            /* 列表（无序 / 有序，支持同级续行） */
            var li = line.match(/^\s*([-*+]|\d+[.)])\s+(.*)$/);
            if (li) {
                var ordered = /\d/.test(li[1]);
                var items = [];
                while (i < lines.length) {
                    // 松散列表：列表项之间允许空行（AI 回复里极常见）。
                    // 若空行后仍是同类列表项则跳过空行继续同一列表，
                    // 否则才结束——否则会被拆成多个 <ol>，编号全部从 1 重来。
                    if (/^\s*$/.test(lines[i])) {
                        var j = i;
                        while (j < lines.length && /^\s*$/.test(lines[j])) { j++; }
                        var nm = j < lines.length ? lines[j].match(/^\s*([-*+]|\d+[.)])\s+(.*)$/) : null;
                        if (nm && /\d/.test(nm[1]) === ordered) { i = j; continue; }
                        break;
                    }
                    var m = lines[i].match(/^\s*([-*+]|\d+[.)])\s+(.*)$/);
                    if (!m) break;
                    if (/\d/.test(m[1]) !== ordered) break;
                    var item = m[2];
                    i++;
                    // 缩进续行并入当前项（用 \n 占位，渲染前统一转 <br>）
                    while (i < lines.length && /^\s{2,}\S/.test(lines[i]) && !/^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) {
                        item += '\n' + lines[i].trim();
                        i++;
                    }
                    items.push('<li>' + inline(esc(item)).replace(/\n/g, '<br>') + '</li>');
                }
                var tag = ordered ? 'ol' : 'ul';
                out.push('<' + tag + '>' + items.join('') + '</' + tag + '>');
                continue;
            }

            /* 空行 */
            if (/^\s*$/.test(line)) { i++; continue; }

            /* 段落：连续非空行合并，行间换行转 <br> */
            var para = [];
            while (i < lines.length && !/^\s*$/.test(lines[i])
                && !/^\s{0,3}(#{1,6})\s+/.test(lines[i])
                && !/^\s{0,3}>\s?/.test(lines[i])
                && !/^\s*([-*+]|\d+[.)])\s+/.test(lines[i])
                && !/^\s{0,3}```/.test(lines[i])
                && !/^\s{0,3}([-*_])\s*(\1\s*){2,}$/.test(lines[i])
                && !isTableStart(lines, i)) {
                para.push(lines[i]);
                i++;
            }
            if (para.length) {
                out.push('<p>' + inline(esc(para.join('\n'))).replace(/\n/g, '<br>') + '</p>');
            } else {
                i++; // 兜底，防止死循环
            }
        }

        return out.join('');
    }

    root.ShufeiMD = { render: render, escapeHtml: esc };
})(typeof window !== 'undefined' ? window : this);
