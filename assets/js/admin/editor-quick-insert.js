/**
 * 编辑器快捷插入工具栏
 * 从 functions.php shufei_quick_insert_js() 抽取
 * 依赖: jQuery, jQuery selection plugin (Typecho 内置)
 */
(function ($) {
    $(document).ready(function () {
        var textarea = $('#text');
        if (textarea.length === 0) return;

        // 在光标位置插入文本，并触发预览刷新
        // 修复：保存/恢复 scrollTop，防止 setSelectionRange 和 pagedown 的 scrollableEditor
        // 导致 textarea 跳转到文章末尾
        function insertAtCursor(text) {
            var sel = textarea.getSelection();
            var offset = (sel ? sel.start : 0) + text.length;
            // 保存插入前的滚动位置
            var savedScrollTop = textarea[0].scrollTop;
            var savedWindowScroll = window.scrollY;
            textarea.replaceSelection(text);
            textarea.setSelection(offset, offset);
            // setSelectionRange 会触发浏览器自动滚动，立即恢复
            textarea[0].scrollTop = savedScrollTop;
            textarea.trigger('input');
            // pagedown input 处理可能修改 scrollTop，再次恢复
            textarea[0].scrollTop = savedScrollTop;
            textarea.focus();
            // focus 可能再次触发滚动，再次恢复
            textarea[0].scrollTop = savedScrollTop;
            window.scrollTo(0, savedWindowScroll);
            // 用 setInterval 持续恢复，覆盖 scrollableEditor 的 p() 500ms 平滑滚动
            // 8ms 频率高于 p() 的 rAF(16ms)，600ms 覆盖 p() 的 500ms 动画 + 余量
            var restoreCount = 0;
            var restoreInterval = setInterval(function () {
                textarea[0].scrollTop = savedScrollTop;
                window.scrollTo(0, savedWindowScroll);
                restoreCount++;
                if (restoreCount >= 75) {  // 约 600ms（75次 * 8ms）
                    clearInterval(restoreInterval);
                }
            }, 8);
        }

        // 在选中文本两侧包裹标记（如 ==高亮==），无选中时插入占位
        // 修复：同样保存/恢复 scrollTop
        function wrapSelection(before, after, placeholder) {
            var sel = textarea.getSelection();
            var text = sel && sel.text ? sel.text : (placeholder || '');
            var replacement = before + text + (after || before);
            var start = sel ? sel.start : 0;
            // 保存滚动位置
            var savedScrollTop = textarea[0].scrollTop;
            var savedWindowScroll = window.scrollY;
            textarea.replaceSelection(replacement);
            // 选中插入的文本部分（不含包裹标记）
            textarea.setSelection(start + before.length, start + before.length + text.length);
            textarea[0].scrollTop = savedScrollTop;
            textarea.trigger('input');
            textarea[0].scrollTop = savedScrollTop;
            textarea.focus();
            textarea[0].scrollTop = savedScrollTop;
            window.scrollTo(0, savedWindowScroll);
            // 持续恢复
            var restoreCount = 0;
            var restoreInterval = setInterval(function () {
                textarea[0].scrollTop = savedScrollTop;
                window.scrollTo(0, savedWindowScroll);
                restoreCount++;
                if (restoreCount >= 75) {
                    clearInterval(restoreInterval);
                }
            }, 8);
        }

        // 创建并显示模态对话框
        function showModal(title, fields, callback) {
            $('#shufei-modal-mask, #shufei-modal').remove();

            var mask = $('<div class="shufei-modal-mask" id="shufei-modal-mask"></div>');
            var modal = $('<div class="shufei-modal" id="shufei-modal"></div>');
            var header = $('<div class="shufei-modal-header"></div>').text(title);
            var body = $('<div class="shufei-modal-body"></div>');
            var footer = $('<div class="shufei-modal-footer"></div>');
            var btnOk = $('<button type="button" class="btn btn-xs btn-primary">确定</button>');
            var btnCancel = $('<button type="button" class="btn btn-xs btn-cancel">取消</button>');

            var inputs = {};
            var currentRow = null;

            fields.forEach(function (f) {
                if (f.half && !currentRow) {
                    currentRow = $('<div class="field-row"></div>');
                    body.append(currentRow);
                } else if (!f.half) {
                    currentRow = null;
                }

                var fieldWrap = $('<div class="field"></div>');
                var label = $('<label></label>').attr('for', 'shufei-field-' + f.name).text(f.label);

                var input;
                if (f.type === 'select') {
                    input = $('<select></select>').attr('id', 'shufei-field-' + f.name);
                    (f.options || []).forEach(function (opt) {
                        var val = typeof opt === 'object' ? opt.value : opt;
                        var text = typeof opt === 'object' ? opt.text : opt;
                        var option = $('<option></option>').attr('value', val).text(text);
                        if (val === f.value) option.attr('selected', 'selected');
                        input.append(option);
                    });
                } else {
                    input = $('<input type="text" />')
                        .attr('id', 'shufei-field-' + f.name)
                        .attr('placeholder', f.placeholder || '');
                    if (f.value) input.val(f.value);
                }

                fieldWrap.append(label).append(input);
                inputs[f.name] = input;

                if (currentRow) {
                    currentRow.append(fieldWrap);
                    if (currentRow.children().length >= 2) currentRow = null;
                } else {
                    body.append(fieldWrap);
                }
            });

            footer.append(btnCancel).append(btnOk);
            modal.append(header).append(body).append(footer);
            $('body').append(mask).append(modal);
            mask.addClass('active');
            modal.addClass('active');

            var firstInput = body.find('input, select').first();
            if (firstInput.length) firstInput.focus();

            function closeModal() {
                mask.removeClass('active').remove();
                modal.removeClass('active').remove();
            }

            btnOk.on('click', function () {
                var values = {};
                var hasValue = false;
                for (var name in inputs) {
                    values[name] = $.trim(inputs[name].val());
                    if (values[name]) hasValue = true;
                }
                if (hasValue) callback(values);
                closeModal();
                textarea.focus();
            });

            btnCancel.on('click', function () { closeModal(); textarea.focus(); });
            mask.on('click', function () { closeModal(); textarea.focus(); });

            modal.on('keydown', function (e) {
                if (e.keyCode === 13) { e.preventDefault(); btnOk.trigger('click'); }
                else if (e.keyCode === 27) { e.preventDefault(); btnCancel.trigger('click'); }
            });
        }

        // ===== 直接插入类（无需弹窗）=====

        // 高亮文本：==高亮内容==
        function insertHighlight() {
            wrapSelection('==', '==', '高亮内容');
        }

        // 回复可见：[reply]内容[/reply]
        function insertReply() {
            wrapSelection('[reply]', '[/reply]', '此处内容需要回复后才可查看');
        }

        // 行内代码：`code`（包裹选中文本）
        function insertCode() {
            wrapSelection('`', '`', 'code');
        }

        // 代码块：```\ncode\n```
        function insertCodeBlock() {
            insertAtCursor('\n```\ncode\n```\n');
        }

        // 数学公式：$$\n公式\n$$
        function insertMath() {
            insertAtCursor('\n$$\nE = mc^2\n$$\n');
        }

        // 任务列表：- [ ] 任务项
        function insertTask() {
            insertAtCursor('\n- [ ] 任务项\n- [ ] 任务项\n- [x] 已完成项\n');
        }

        // 提示框：> [!tip] 标题
        function insertTip() {
            insertAtCursor('\n> [!tip] 提示标题\n> 提示内容写在这里\n> 可以换行继续写\n');
        }

        // 折叠区块：> [details:标题]
        function insertDetails() {
            insertAtCursor('\n> [details:点击展开查看]\n> 折叠的内容写在这里\n> 可以换行继续写\n');
        }

        // Mermaid 流程图
        function insertMermaid() {
            insertAtCursor('\n```mermaid\ngraph TD\n    A[开始] --> B[步骤一]\n    B --> C[步骤二]\n    C --> D[结束]\n```\n');
        }

        // ECharts 图表
        function insertEcharts() {
            insertAtCursor('\n```echarts\n{\n  "xAxis": { "type": "category", "data": ["A", "B", "C"] },\n  "yAxis": { "type": "value" },\n  "series": [{ "data": [120, 200, 150], "type": "bar" }]\n}\n```\n');
        }

        // ===== 弹窗插入类 =====

        // 图片插入：![描述|宽x高#对齐](url)
        function insertImage() {
            showModal('插入图片', [
                { name: 'url', label: '图片地址 *', placeholder: 'https://example.com/image.jpg' },
                { name: 'alt', label: '图片描述', placeholder: '图片说明文字（可选，会显示为标题）' },
                { name: 'width', label: '宽度', placeholder: '如 300 或 50%', half: true },
                { name: 'height', label: '高度', placeholder: '如 200（可选）', half: true },
                {
                    name: 'align', label: '对齐方式', type: 'select', value: '',
                    options: [
                        { value: '', text: '默认' },
                        { value: 'center', text: '居中' },
                        { value: 'left', text: '左对齐' },
                        { value: 'right', text: '右对齐' }
                    ]
                }
            ], function (v) {
                if (!v.url) return;
                var altParts = [];
                if (v.alt) altParts.push(v.alt);
                var sizeStr = '';
                if (v.width && v.height) sizeStr = v.width + 'x' + v.height;
                else if (v.width) sizeStr = v.width;
                if (sizeStr) altParts.push(sizeStr);
                var altText = altParts.join('|');
                if (v.align) altText += '#' + v.align;
                insertAtCursor('\n![' + altText + '](' + v.url + ')\n');
            });
        }

        // 视频插入：[video src="url" poster="..." autoplay="true"]
        function insertVideo() {
            showModal('插入视频', [
                { name: 'src', label: '视频地址 *', placeholder: 'https://example.com/video.mp4' },
                { name: 'poster', label: '封面图地址', placeholder: 'https://example.com/poster.jpg（可选）' },
                {
                    name: 'autoplay', label: '自动播放', type: 'select', value: 'false',
                    options: [
                        { value: 'false', text: '否' },
                        { value: 'true', text: '是' }
                    ]
                }
            ], function (v) {
                if (!v.src) return;
                var attrs = 'src="' + v.src + '"';
                if (v.poster) attrs += ' poster="' + v.poster + '"';
                if (v.autoplay === 'true') attrs += ' autoplay="true"';
                insertAtCursor('\n[video ' + attrs + ']\n');
            });
        }

        // 音乐插入：[music src="url" title="..." artist="..." cover="..."]
        function insertMusic() {
            showModal('插入音乐', [
                { name: 'src', label: '音乐地址 *', placeholder: 'https://example.com/song.mp3' },
                { name: 'title', label: '歌曲名', placeholder: '如：晴天（可选）', half: true },
                { name: 'artist', label: '艺术家', placeholder: '如：周杰伦（可选）', half: true },
                { name: 'cover', label: '封面图地址', placeholder: 'https://example.com/cover.jpg（可选）' }
            ], function (v) {
                if (!v.src) return;
                var attrs = 'src="' + v.src + '"';
                if (v.title) attrs += ' title="' + v.title + '"';
                if (v.artist) attrs += ' artist="' + v.artist + '"';
                if (v.cover) attrs += ' cover="' + v.cover + '"';
                insertAtCursor('\n[music ' + attrs + ']\n');
            });
        }

        // ===== 重建工具栏：原按钮文字化 + 新按钮 + 统一排序 =====

        // 原编辑器按钮 ID → 文字标签
        var origLabels = {
            'wmd-bold-button': '加粗',
            'wmd-italic-button': '斜体',
            'wmd-link-button': '链接',
            'wmd-quote-button': '引用',
            'wmd-olist-button': '有序列表',
            'wmd-ulist-button': '无序列表',
            'wmd-heading-button': '标题',
            'wmd-hr-button': '分割线',
            'wmd-more-button': '摘要',
            'wmd-undo-button': '撤销',
            'wmd-redo-button': '重做',
            'wmd-fullscreen-button': '全屏',
            'wmd-exit-fullscreen-button': '退出全屏',
            'wmd-help-button': '帮助'
        };

        // 将原 sprite 按钮的文字标签写入 span，移除背景图
        function textifyOrig(li, label) {
            var span = li.find('span');
            if (span.length) {
                span.css('background-image', 'none').width('auto').text(label);
            }
            return li;
        }

        // 重建整个工具栏，按功能分组排列
        function rebuildToolbar() {
            var buttonRow = $('.wmd-button-row');
            if (buttonRow.length === 0) return false;
            if (buttonRow.data('shufei-rebuilt')) return true;

            // 收集并 detach 所有原按钮（保留事件绑定）
            var origBtns = {};
            buttonRow.find('li').each(function () {
                var li = $(this);
                var id = li.attr('id');
                if (id) {
                    origBtns[id] = li.detach();
                } else {
                    li.remove();
                }
            });

            // 删除原图片和代码按钮（由新按钮替代）
            delete origBtns['wmd-image-button'];
            delete origBtns['wmd-code-button'];

            // 辅助函数
            function appendOrig(id) {
                if (origBtns[id]) {
                    buttonRow.append(textifyOrig(origBtns[id], origLabels[id] || ''));
                }
            }
            function appendNew(action, label, title) {
                buttonRow.append('<li class="shufei-qi-btn" data-action="' + action + '" title="' + title + '"><span>' + label + '</span></li>');
            }
            function appendSep() {
                buttonRow.append('<li class="shufei-qi-sep"></li>');
            }

            // === 第1组：文字格式 ===
            appendOrig('wmd-bold-button');       // 加粗
            appendOrig('wmd-italic-button');      // 斜体
            appendNew('highlight', '高亮', '高亮文本 ==高亮==');
            appendNew('code', '代码', '行内代码 `code`');
            appendSep();

            // === 第2组：结构 ===
            appendOrig('wmd-heading-button');     // 标题
            appendOrig('wmd-quote-button');       // 引用
            appendOrig('wmd-olist-button');       // 有序列表
            appendOrig('wmd-ulist-button');       // 无序列表
            appendNew('task', '任务', '任务列表 - [ ]');
            appendOrig('wmd-hr-button');          // 分割线
            appendSep();

            // === 第3组：插入 ===
            appendOrig('wmd-link-button');        // 链接
            appendNew('image', '图片', '插入图片（支持大小/对齐）');
            appendNew('video', '视频', '插入视频（支持封面）');
            appendNew('music', '音乐', '插入音乐');
            appendNew('codeblock', '代码块', '代码块 ```code```');
            appendSep();

            // === 第4组：高级 ===
            appendNew('math', '公式', '数学公式 $$...$$');
            appendNew('tip', '提示', '提示框 > [!tip]');
            appendNew('details', '折叠', '折叠区块 > [details:]');
            appendNew('reply', '回复可见', '回复可见 [reply]内容[/reply]');
            appendNew('mermaid', '流程图', 'Mermaid 流程图/时序图/甘特图');
            appendNew('echarts', '图表', 'ECharts 数据图表');
            appendSep();

            // === 第5组：工具 ===
            appendOrig('wmd-more-button');        // 摘要
            appendOrig('wmd-undo-button');        // 撤销
            appendOrig('wmd-redo-button');        // 重做
            appendOrig('wmd-fullscreen-button');  // 全屏
            if (origBtns['wmd-exit-fullscreen-button']) {
                appendOrig('wmd-exit-fullscreen-button'); // 退出全屏
            }
            appendOrig('wmd-help-button');        // 帮助

            buttonRow.data('shufei-rebuilt', true);

            return true;
        }

        // 尝试立即重建，若工具栏尚未创建则监听 DOM 变化
        if (!rebuildToolbar()) {
            var observer = new MutationObserver(function (mutations, obs) {
                if (rebuildToolbar()) obs.disconnect();
            });
            observer.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () { observer.disconnect(); }, 30000);
        }

        // 全屏模式：动态调整 #text 和 #wmd-preview 的 top 位置，避免被工具栏遮挡
        function adjustFullscreenLayout() {
            var isFs = $('#text').css('position') === 'absolute';
            if (isFs) {
                var barHeight = $('#wmd-button-bar').outerHeight(true) || 53;
                $('#text').css('top', barHeight + 'px');
                $('#wmd-preview').css('top', barHeight + 'px');
                // 让 .submit 覆盖到预览区顶部，避免工具栏换行后露出下方内容
                $('.submit').css('height', barHeight + 'px');
            } else {
                $('#text').css('top', '');
                $('#wmd-preview').css('top', '');
                $('.submit').css('height', '');
            }
        }

        // 全屏切换时处理 exit-fullscreen 按钮和布局调整
        $(document).on('click', '#wmd-fullscreen-button, #wmd-exit-fullscreen-button', function () {
            setTimeout(function () {
                // 文字化 exit-fullscreen 按钮
                var exitBtn = $('#wmd-exit-fullscreen-button');
                if (exitBtn.length) {
                    var span = exitBtn.find('span');
                    if (span.length && !span.text()) {
                        span.css('background-image', 'none').width('auto').text('退出全屏');
                    }
                }
                // 全屏可能重建工具栏，重新文字化所有 sprite 按钮
                $('.wmd-button-row li[id]').each(function () {
                    var li = $(this);
                    var id = li.attr('id');
                    if (origLabels[id]) {
                        var span = li.find('span');
                        if (span.length && !span.text()) {
                            span.css('background-image', 'none').width('auto').text(origLabels[id]);
                        }
                    }
                });
                // 调整内容区位置
                adjustFullscreenLayout();
            }, 100);
        });

        // 绑定按钮点击事件（事件委托，支持动态注入）
        $(document).on('click', '.shufei-qi-btn', function (e) {
            e.preventDefault();
            var action = $(this).data('action');
            switch (action) {
                case 'highlight': insertHighlight(); break;
                case 'reply': insertReply(); break;
                case 'code': insertCode(); break;
                case 'codeblock': insertCodeBlock(); break;
                case 'math': insertMath(); break;
                case 'task': insertTask(); break;
                case 'tip': insertTip(); break;
                case 'details': insertDetails(); break;
                case 'mermaid': insertMermaid(); break;
                case 'echarts': insertEcharts(); break;
                case 'image': insertImage(); break;
                case 'video': insertVideo(); break;
                case 'music': insertMusic(); break;
            }
        });
    });
})(jQuery);
