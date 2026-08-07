
(function ($) {
    $(function () {
        var ajaxUrl = window.SHUFEI_ADMIN.aiAjaxUrl;
        var styles = window.SHUFEI_ADMIN.styles;
        var styleDescs = {
            'literary': '用词优美典雅，善用比喻修辞',
            'professional': '严谨准确，逻辑清晰',
            'vivid': '生动活泼，富有画面感',
            'concise': '简练精炼，直击要点',
            'humorous': '幽默有趣，不失分寸',
            'warm': '温暖抒情，富有感染力'
        };
        var currentStyle = 'literary';
        var lastResult = '';
        var lastAction = '';

        // 将工具栏注入到编辑器上方
        function injectToolbar() {
            if ($('#shufei-ai-toolbar').data('injected')) return;
            var $text = $('#text');
            if (!$text.length) return;
            $('#shufei-ai-toolbar').insertBefore($text).show().data('injected', true);
        }
        injectToolbar();
        // 重试注入（等待编辑器初始化）
        setTimeout(injectToolbar, 500);

        // 获取编辑器内容（选区优先）
        function getContent() {
            var $text = $('#text');
            var ta = $text[0];
            if (!ta) return '';
            var start = ta.selectionStart;
            var end = ta.selectionEnd;
            if (start !== end) {
                return ta.value.substring(start, end);
            }
            return ta.value;
        }

        // 替换编辑器内容（选区优先替换选区，否则追加）
        function applyResult(result) {
            var $text = $('#text');
            var ta = $text[0];
            if (!ta) return;
            var start = ta.selectionStart;
            var end = ta.selectionEnd;
            if (start !== end) {
                // 有选区：替换选区
                var newVal = ta.value.substring(0, start) + result + ta.value.substring(end);
                ta.value = newVal;
                ta.selectionStart = start;
                ta.selectionEnd = start + result.length;
            } else if (lastAction === 'continue') {
                // 续写：追加到末尾
                ta.value = ta.value + '\n\n' + result;
                ta.scrollTop = ta.scrollHeight;
            } else {
                // 美化：替换全部内容
                ta.value = result;
            }
            // 触发 change 让预览更新
            $text.trigger('change').trigger('input');
        }

        // 显示浮窗
        function showModal(title, bodyHtml, footerHtml) {
            $('#shufei-ai-modal-title').text(title);
            $('#shufei-ai-modal-body').html(bodyHtml);
            $('#shufei-ai-modal-footer').html(footerHtml || '');
            $('#shufei-ai-mask, #shufei-ai-modal').addClass('show');
        }

        function closeModal() {
            $('#shufei-ai-mask, #shufei-ai-modal').removeClass('show');
        }

        function setStatus(msg, type) {
            $('#shufei-ai-status').removeClass('info success error').addClass(type || 'info').html(msg).show();
        }

        // 显示加载
        function showLoading(text) {
            $('#shufei-ai-modal-body').html(
                '<div class="shufei-ai-loading"><div class="spinner"></div><div>' + (text || 'AI 正在思考中...') + '</div></div>'
            );
        }

        // 调用 AI 接口
        function callAi(action, data, onDone) {
            var postData = 'action=' + encodeURIComponent(action);
            for (var k in data) {
                if (data.hasOwnProperty(k)) {
                    postData += '&' + k + '=' + encodeURIComponent(data[k]);
                }
            }
            showLoading(action === 'beautify' ? 'AI 正在美化文章...' :
                        action === 'continue' ? 'AI 正在续写文章...' :
                        action === 'check' ? 'AI 正在检查文章...' : 'AI 处理中...');
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: postData,
                dataType: 'json',
                timeout: 90000
            }).done(function (resp) {
                if (resp && resp.success) {
                    lastResult = resp.content || '';
                    onDone(resp);
                } else {
                    $('#shufei-ai-modal-body').html(
                        '<div class="shufei-ai-status error">✗ ' + (resp && resp.message ? resp.message : 'AI 请求失败') + '</div>' +
                        '<div style="text-align:right;margin-top:12px;"><button type="button" class="shufei-ai-btn" onclick="jQuery(\'#shufei-ai-mask, #shufei-ai-modal\').removeClass(\'show\');">关闭</button></div>'
                    );
                }
            }).fail(function (xhr) {
                var msg = '请求失败';
                try { var r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch (e) {}
                $('#shufei-ai-modal-body').html(
                    '<div class="shufei-ai-status error">✗ ' + msg + ' (HTTP ' + xhr.status + ')</div>' +
                    '<div style="text-align:right;margin-top:12px;"><button type="button" class="shufei-ai-btn" onclick="jQuery(\'#shufei-ai-mask, #shufei-ai-modal\').removeClass(\'show\');">关闭</button></div>'
                );
            });
        }

        // 美化弹窗
        function openBeautify() {
            var content = getContent();
            if (!content.trim()) { alert('请先输入文章内容，或选中要美化的段落'); return; }
            lastAction = 'beautify';
            var styleHtml = '<div style="margin-bottom:8px;font-weight:bold;">选择美化风格：</div><div class="shufei-ai-style-grid">';
            for (var k in styles) {
                if (!styles.hasOwnProperty(k)) continue;
                var cls = k === currentStyle ? ' active' : '';
                styleHtml += '<div class="shufei-ai-style-opt' + cls + '" data-style="' + k + '">'
                    + '<div>' + styles[k] + '</div>'
                    + '<div class="shufei-ai-style-desc">' + (styleDescs[k] || '') + '</div>'
                    + '</div>';
            }
            styleHtml += '</div>';
            styleHtml += '<div class="shufei-ai-status" id="shufei-ai-status"></div>';
            showModal('AI 美化文章', styleHtml,
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">取消</button>' +
                '<button type="button" class="shufei-ai-btn beautify" id="shufei-ai-run">开始美化</button>');

            // 风格选择
            $('.shufei-ai-style-opt').on('click', function () {
                $('.shufei-ai-style-opt').removeClass('active');
                $(this).addClass('active');
                currentStyle = $(this).data('style');
            });
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-run').on('click', function () {
                callAi('beautify', { content: content, style: currentStyle }, function (resp) {
                    showResultEditor(resp.content, '美化结果', true);
                });
            });
        }

        // 续写弹窗
        function openContinue() {
            var content = getContent();
            if (!content.trim()) { alert('请先输入文章内容，AI 将基于已有内容续写'); return; }
            lastAction = 'continue';
            var html = '<div class="shufei-ai-select-wrap">续写字数：' +
                '<input type="number" class="shufei-ai-len-input" id="shufei-ai-len" value="300" min="100" max="2000" step="50"> 字（建议 100-2000）</div>' +
                '<div class="shufei-ai-meta">AI 将基于当前内容（或选区）自然续写，续写内容将追加到原文末尾。</div>' +
                '<div class="shufei-ai-status" id="shufei-ai-status"></div>';
            showModal('AI 续写文章', html,
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">取消</button>' +
                '<button type="button" class="shufei-ai-btn continue" id="shufei-ai-run">开始续写</button>');
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-run').on('click', function () {
                var len = parseInt($('#shufei-ai-len').val(), 10) || 300;
                callAi('continue', { content: content, length: len }, function (resp) {
                    showResultEditor(resp.content, '续写结果', true);
                });
            });
        }

        // 检查弹窗
        function openCheck() {
            var content = getContent();
            if (!content.trim()) { alert('请先输入文章内容'); return; }
            lastAction = 'check';
            showModal('AI 文章检查', '<div class="shufei-ai-meta">AI 将检查文章的错别字、语法、逻辑等问题并给出修改建议。</div><div class="shufei-ai-status" id="shufei-ai-status"></div>',
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">取消</button>' +
                '<button type="button" class="shufei-ai-btn check" id="shufei-ai-run">开始检查</button>');
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-run').on('click', function () {
                callAi('check', { content: content }, function (resp) {
                    // 检查结果只读展示
                    showResultViewer(resp.content, '检查结果');
                });
            });
        }

        // 显示可编辑结果（美化/续写）
        function showResultEditor(result, title, canApply) {
            var footer = '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">关闭</button>';
            if (canApply) {
                footer = '<button type="button" class="shufei-ai-btn" id="shufei-ai-copy">复制结果</button>' +
                    '<button type="button" class="shufei-ai-btn beautify" id="shufei-ai-apply">应用到文章</button>' +
                    '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">关闭</button>';
            }
            var body = '<div class="shufei-ai-meta">可在此预览/编辑 AI 生成结果，确认后点击「应用到文章」。</div>' +
                '<textarea class="shufei-ai-result" id="shufei-ai-result-text">' + $('<div>').text(result).html() + '</textarea>' +
                '<div class="shufei-ai-meta">字数：' + result.length + '</div>';
            showModal(title, body, footer);
            $('#shufei-ai-cancel').on('click', closeModal);
            if (canApply) {
                $('#shufei-ai-copy').on('click', function () {
                    var txt = $('#shufei-ai-result-text').val();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(txt);
                    } else {
                        var $tmp = $('<textarea>').val(txt).appendTo('body').select();
                        document.execCommand('copy'); $tmp.remove();
                    }
                    $(this).text('已复制').prop('disabled', true);
                });
                $('#shufei-ai-apply').on('click', function () {
                    applyResult($('#shufei-ai-result-text').val());
                    closeModal();
                });
            }
        }

        // 显示只读结果（检查）
        function showResultViewer(result, title) {
            // 简单 Markdown 渲染（标题、列表、加粗、代码）
            function esc(s) { return $('<div>').text(s).html(); }
            var html = esc(result);
            html = html.replace(/^### (.+)$/gm, '<h4>$1</h4>');
            html = html.replace(/^## (.+)$/gm, '<h3>$1</h3>');
            html = html.replace(/^# (.+)$/gm, '<h2>$1</h2>');
            html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
            html = html.replace(/(<li>[\s\S]+?<\/li>)/g, '<ul>$1</ul>');
            html = html.replace(/\n\n/g, '</p><p>');
            html = '<div style="font-size:14px;line-height:1.8;color:#333;"><p>' + html + '</p></div>';
            var footer = '<button type="button" class="shufei-ai-btn" id="shufei-ai-copy">复制结果</button>' +
                '<button type="button" class="shufei-ai-btn" id="shufei-ai-cancel">关闭</button>';
            showModal(title, html, footer);
            $('#shufei-ai-cancel').on('click', closeModal);
            $('#shufei-ai-copy').on('click', function () {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(result);
                } else {
                    var $tmp = $('<textarea>').val(result).appendTo('body').select();
                    document.execCommand('copy'); $tmp.remove();
                }
                $(this).text('已复制').prop('disabled', true);
            });
        }

        // 工具栏按钮事件
        $(document).on('click', '.shufei-ai-btn[data-ai-action]', function (e) {
            e.preventDefault();
            var action = $(this).data('ai-action');
            if (action === 'beautify') openBeautify();
            else if (action === 'continue') openContinue();
            else if (action === 'check') openCheck();
        });

        // 关闭事件
        $('#shufei-ai-close, #shufei-ai-mask').on('click', closeModal);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    });
})(jQuery);

