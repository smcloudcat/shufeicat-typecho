
(function(){
    if (window.__shufeiStorageEditorInit) return;
    window.__shufeiStorageEditorInit = true;

    var AJAX_URL = window.SHUFEI_ADMIN.ajaxUrl;
    var pendingFiles = [];
    var uploadResults = [];

    function $(id){ return document.getElementById(id); }

    function insertTextToEditor(text){
        var ta = document.getElementById('text') || document.querySelector('textarea[name=text]');
        if (!ta) { alert('未找到编辑器文本框'); return; }
        if (document.selection) {
            ta.focus();
            var sel = document.selection.createRange();
            sel.text = text;
            ta.focus();
        } else if (ta.selectionStart || ta.selectionStart === 0) {
            var startPos = ta.selectionStart;
            var endPos = ta.selectionEnd;
            var scrollTop = ta.scrollTop;
            ta.value = ta.value.substring(0, startPos) + text + ta.value.substring(endPos, ta.value.length);
            ta.focus();
            ta.selectionStart = startPos + text.length;
            ta.selectionEnd = startPos + text.length;
            ta.scrollTop = scrollTop;
        } else {
            ta.value += text;
            ta.focus();
        }
        // 触发 input 事件以便编辑器预览同步
        if (typeof Event !== 'undefined') {
            var ev = new Event('input', { bubbles: true });
            ta.dispatchEvent(ev);
        }
    }

    // 加载 Profile 列表
    function loadProfiles(){
        var fd = new FormData();
        fd.append('action', 'list_profiles');
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.success) {
                    $('shufei-storage-status-tip').textContent = d.message || '加载失败';
                    return;
                }
                if (!d.enabled) { return; }
                if (!d.profiles || d.profiles.length === 0) {
                    $('shufei-storage-status-tip').textContent = '尚未配置任何 Profile，请到主题设置中添加';
                    $('shufei-storage-editor-toolbar').style.display = 'flex';
                    return;
                }
                var sel = $('shufei-storage-profile-select');
                sel.innerHTML = '';
                d.profiles.forEach(function(p){
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name + '（' + (p.driverName || p.driver) + '）';
                    if (p.id === d.activeProfileId) opt.selected = true;
                    sel.appendChild(opt);
                });
                // 处理选项提示
                var tips = [];
                if (d.processing && d.processing.compress === 'on') tips.push('压缩');
                if (d.processing && d.processing.webp === 'on') tips.push('WebP');
                if (d.processing && d.processing.watermark === 'on') tips.push('水印');
                $('shufei-storage-status-tip').textContent = tips.length ? '已启用：' + tips.join(' / ') : '';
                $('shufei-storage-editor-toolbar').style.display = 'flex';
            })
            .catch(function(e){
                $('shufei-storage-status-tip').textContent = '加载失败: ' + e.message;
            });
    }

    // 切换 Profile
    $('shufei-storage-profile-select').addEventListener('change', function(){
        var pid = this.value;
        if (!pid) return;
        var fd = new FormData();
        fd.append('action', 'switch_profile');
        fd.append('profile_id', pid);
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                $('shufei-storage-status-tip').textContent = d.message || (d.success ? '已切换' : '切换失败');
            })
            .catch(function(e){ $('shufei-storage-status-tip').textContent = '切换失败: ' + e.message; });
    });

    // 上传弹窗
    function openUploadModal(){
        pendingFiles = [];
        uploadResults = [];
        $('shufei-storage-up-list').innerHTML = '';
        $('shufei-storage-up-mask').classList.add('show');
        $('shufei-storage-up-modal').classList.add('show');
    }
    function closeUploadModal(){
        $('shufei-storage-up-mask').classList.remove('show');
        $('shufei-storage-up-modal').classList.remove('show');
    }

    // ===== 历史图片 =====
    var histPage = 1, histLimit = 30, histTotal = 0;
    function histStatus(msg, color){
        var el = $('shufei-hist-status');
        el.textContent = msg || '';
        el.style.color = color || '#999';
    }
    function humanSize(b){
        if (!b) return '0 B';
        if (b < 1024) return b + ' B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
        return (b/1024/1024).toFixed(2) + ' MB';
    }
    function openHistoryModal(){
        $('shufei-storage-hist-mask').classList.add('show');
        $('shufei-storage-hist-modal').classList.add('show');
        if (!histTotal) loadHistory();
    }
    function closeHistoryModal(){
        $('shufei-storage-hist-mask').classList.remove('show');
        $('shufei-storage-hist-modal').classList.remove('show');
    }
    function loadHistory(){
        var sel = $('shufei-storage-profile-select');
        var pid = sel ? sel.value : '';
        if (!pid){ histStatus('请先选择 Profile', '#c00'); return; }
        histStatus('加载中...', '#999');
        var grid = $('shufei-storage-hist-grid');
        grid.innerHTML = '<div class="shufei-storage-hist-empty">加载中...</div>';
        var fd = new FormData();
        fd.append('action', 'list_images');
        fd.append('profile_id', pid);
        fd.append('page', histPage);
        fd.append('limit', histLimit);
        fetch(AJAX_URL, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                if (!res || !res.success){
                    histStatus(res && res.message ? res.message : '加载失败', '#c00');
                    grid.innerHTML = '<div class="shufei-storage-hist-empty">'+(res&&res.message?res.message:'加载失败')+'</div>';
                    return;
                }
                var data = res.data || {};
                histTotal = data.total || 0;
                var list = data.list || [];
                histStatus('共 ' + histTotal + ' 张', '#080');
                $('shufei-hist-page').textContent = '第 ' + histPage + '/' + Math.max(1, Math.ceil(histTotal/histLimit)) + ' 页';
                if (!list.length){
                    grid.innerHTML = '<div class="shufei-storage-hist-empty">暂无图片</div>';
                    return;
                }
                grid.innerHTML = '';
                list.forEach(function(item){
                    var cell = document.createElement('div');
                    cell.className = 'shufei-storage-hist-cell';
                    var img = document.createElement('img');
                    img.src = item.url; img.loading = 'lazy';
                    img.title = '点击插入 ' + (item.name||'');
                    img.onerror = function(){ img.style.display='none'; cell.querySelector('.hist-ph').style.display='flex'; };
                    img.onclick = function(){ insertImageMarkdown(item.url, item.name||''); closeHistoryModal(); };
                    var ph = document.createElement('div');
                    ph.className = 'hist-ph';
                    ph.textContent = '图片加载失败';
                    var ins = document.createElement('div');
                    ins.className = 'hist-insert';
                    ins.textContent = '插入';
                    ins.onclick = function(e){ e.stopPropagation(); insertImageMarkdown(item.url, item.name||''); closeHistoryModal(); };
                    var del = document.createElement('div');
                    del.className = 'hist-del';
                    del.innerHTML = '&times;';
                    del.title = '删除该图片';
                    del.onclick = function(e){
                        e.stopPropagation();
                        if (!confirm('确定删除这张图片吗？此操作不可恢复。\n\n' + (item.name||''))) return;
                        del.textContent = '...';
                        var fd2 = new FormData();
                        fd2.append('action', 'delete_image');
                        fd2.append('profile_id', pid);
                        if (item.id) fd2.append('image_id', item.id);
                        if (item.key) fd2.append('key', item.key);
                        if (item.url) fd2.append('url', item.url);
                        fetch(AJAX_URL, {method:'POST', body:fd2, credentials:'same-origin'})
                            .then(function(r){return r.json();})
                            .then(function(res2){
                                if (res2 && res2.success){
                                    histStatus('已删除', '#080');
                                    cell.style.transition='opacity .3s'; cell.style.opacity='0';
                                    setTimeout(function(){ cell.remove(); histTotal--; histStatus('共 ' + histTotal + ' 张', '#080'); }, 300);
                                } else {
                                    histStatus(res2 && res2.message ? res2.message : '删除失败', '#c00');
                                    del.innerHTML = '&times;';
                                }
                            })
                            .catch(function(err){
                                histStatus('网络错误: ' + err.message, '#c00');
                                del.innerHTML = '&times;';
                            });
                    };
                    var info = document.createElement('div');
                    info.className = 'hist-info';
                    info.title = item.name||'';
                    info.textContent = (item.name||'') + ' · ' + humanSize(item.size);
                    cell.appendChild(img);
                    cell.appendChild(ph);
                    cell.appendChild(ins);
                    cell.appendChild(del);
                    cell.appendChild(info);
                    grid.appendChild(cell);
                });
            })
            .catch(function(err){
                histStatus('网络错误: ' + err.message, '#c00');
                grid.innerHTML = '<div class="shufei-storage-hist-empty">网络错误</div>';
            });
    }
    function insertImageMarkdown(url, name){
        var md = '![' + (name||'') + '](' + url + ')\n';
        insertTextToEditor(md);
        histStatus('已插入: ' + (name||'图片'), '#080');
    }

    $('shufei-storage-open-upload-btn').addEventListener('click', openUploadModal);
    $('shufei-storage-up-close').addEventListener('click', closeUploadModal);
    $('shufei-storage-up-cancel-btn').addEventListener('click', closeUploadModal);
    $('shufei-storage-up-mask').addEventListener('click', closeUploadModal);

    $('shufei-storage-open-history-btn').addEventListener('click', openHistoryModal);
    $('shufei-storage-hist-close').addEventListener('click', closeHistoryModal);
    $('shufei-storage-hist-mask').addEventListener('click', closeHistoryModal);
    $('shufei-hist-refresh').addEventListener('click', function(){ histPage = 1; loadHistory(); });
    $('shufei-hist-prev').addEventListener('click', function(){ if (histPage > 1){ histPage--; loadHistory(); } });
    $('shufei-hist-next').addEventListener('click', function(){ if (histPage * histLimit < histTotal){ histPage++; loadHistory(); } });

    // 文件选择
    var dropzone = $('shufei-storage-dropzone');
    var fileInput = $('shufei-storage-file-input');
    dropzone.addEventListener('click', function(){ fileInput.click(); });
    fileInput.addEventListener('change', function(){
        if (this.files && this.files.length) addFiles(this.files);
        this.value = '';
    });
    ['dragenter','dragover'].forEach(function(ev){
        dropzone.addEventListener(ev, function(e){ e.preventDefault(); dropzone.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(function(ev){
        dropzone.addEventListener(ev, function(e){ e.preventDefault(); dropzone.classList.remove('dragover'); });
    });
    dropzone.addEventListener('drop', function(e){
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            addFiles(e.dataTransfer.files);
        }
    });

    function addFiles(fileList){
        for (var i = 0; i < fileList.length; i++) {
            var f = fileList[i];
            if (!f.type.startsWith('image/')) continue;
            pendingFiles.push({ file: f, status: 'pending', url: '', error: '' });
        }
        renderList();
    }

    function renderList(){
        var list = $('shufei-storage-up-list');
        list.innerHTML = '';
        pendingFiles.forEach(function(item, idx){
            var div = document.createElement('div');
            div.className = 'shufei-storage-up-item';
            var statusText = { pending: '待上传', uploading: '上传中', success: '成功', error: '失败' }[item.status] || item.status;
            var html = '';
            if (item.url) {
                html += '<img class="up-thumb" src="' + escapeAttr(item.url) + '">';
            } else if (item.file.type.startsWith('image/')) {
                html += '<img class="up-thumb" src="' + escapeAttr(URL.createObjectURL(item.file)) + '">';
            }
            html += '<span class="up-name">' + escapeHtml(item.file.name) + ' (' + formatSize(item.file.size) + ')</span>';
            html += '<span class="up-status ' + item.status + '">' + statusText + (item.error ? ': ' + escapeHtml(item.error) : '') + '</span>';
            div.innerHTML = html;
            list.appendChild(div);
        });
    }

    function escapeHtml(s){
        s = (s === null || s === undefined) ? '' : String(s);
        return s.replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function escapeAttr(s){ return escapeHtml(s).replace(/"/g, '&quot;'); }
    function formatSize(b){
        if (b < 1024) return b + 'B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + 'KB';
        return (b/1024/1024).toFixed(2) + 'MB';
    }

    // 上传逻辑：逐个串行上传
    $('shufei-storage-start-upload-btn').addEventListener('click', function(){
        if (pendingFiles.length === 0) { alert('请先选择图片'); return; }
        var profileId = $('shufei-storage-profile-select').value;
        if (!profileId) { alert('请先选择存储 Profile'); return; }
        var insertMd = $('shufei-up-insert-markdown').checked;
        var insertNewline = $('shufei-up-insert-newline').checked;
        var mdText = '';

        var idx = 0;
        function next(){
            if (idx >= pendingFiles.length) {
                // 全部完成，插入 Markdown
                if (insertMd && mdText) {
                    insertTextToEditor(mdText);
                }
                var ok = pendingFiles.filter(function(x){ return x.status === 'success'; }).length;
                var fail = pendingFiles.filter(function(x){ return x.status === 'error'; }).length;
                alert('上传完成：成功 ' + ok + ' 个' + (fail ? '，失败 ' + fail + ' 个' : ''));
                if (fail === 0) {
                    closeUploadModal();
                }
                return;
            }
            var item = pendingFiles[idx];
            if (item.status === 'success') { idx++; next(); return; }
            item.status = 'uploading';
            renderList();

            var fd = new FormData();
            fd.append('action', 'upload_image');
            fd.append('profile_id', profileId);
            fd.append('file', item.file, item.file.name);

            fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d.success) {
                        item.status = 'success';
                        item.url = d.url;
                        if (insertMd) {
                            mdText += '![](' + d.url + ')';
                            if (insertNewline) mdText += '\n\n';
                        }
                    } else {
                        item.status = 'error';
                        item.error = d.message || '上传失败';
                    }
                    renderList();
                    idx++; next();
                })
                .catch(function(e){
                    item.status = 'error';
                    item.error = e.message;
                    renderList();
                    idx++; next();
                });
        }
        next();
    });

    // ESC 关闭
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeUploadModal();
    });

    // 等编辑器加载完成后初始化
    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }

    function init(){
        // 将工具条移动到编辑器 textarea 上方（紧贴标题下方、内容区上方）
        var toolbar = $('shufei-storage-editor-toolbar');
        var ta = document.getElementById('text');
        if (ta && ta.parentNode) {
            ta.parentNode.insertBefore(toolbar, ta);
        }
        loadProfiles();
    }
})();

