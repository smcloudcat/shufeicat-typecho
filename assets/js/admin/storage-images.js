
(function(){
    var ajaxUrl = window.SHUFEI_ADMIN.ajaxUrl;
    var grid = document.getElementById('shufei-img-grid');
    var sel = document.getElementById('shufei-img-profile');
    var statusEl = document.getElementById('shufei-img-status');
    var pageInfo = document.getElementById('shufei-img-page-info');
    var prevBtn = document.getElementById('shufei-img-prev');
    var nextBtn = document.getElementById('shufei-img-next');
    var page = 1, limit = 24, total = 0;

    function status(msg, color){
        statusEl.textContent = msg;
        statusEl.style.color = color || '#999';
    }

    function humanSize(b){
        if (!b) return '0 B';
        if (b < 1024) return b + ' B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
        return (b/1024/1024).toFixed(2) + ' MB';
    }

    function load(){
        var pid = sel.value;
        if (!pid){ status('请先创建并选择 Profile', '#c00'); return; }
        status('加载中...', '#999');
        grid.innerHTML = '<div style="grid-column:1/-1;color:#999;text-align:center;padding:30px;">加载中...</div>';
        var fd = new FormData();
        fd.append('action', 'list_images');
        fd.append('profile_id', pid);
        fd.append('page', page);
        fd.append('limit', limit);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                if (!res || !res.success){
                    status(res && res.message ? res.message : '加载失败', '#c00');
                    grid.innerHTML = '<div style="grid-column:1/-1;color:#c00;text-align:center;padding:30px;">'+(res&&res.message?res.message:'加载失败')+'</div>';
                    return;
                }
                var data = res.data || {};
                total = data.total || 0;
                var list = data.list || [];
                status('共 ' + total + ' 张图片', '#080');
                pageInfo.textContent = '第 ' + page + '/' + Math.max(1, Math.ceil(total/limit)) + ' 页';
                if (!list.length){
                    grid.innerHTML = '<div style="grid-column:1/-1;color:#999;text-align:center;padding:30px;">暂无图片</div>';
                    return;
                }
                grid.innerHTML = '';
                list.forEach(function(item){
                    var cell = document.createElement('div');
                    cell.style.cssText = 'position:relative;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;background:#fafafa;';
                    var img = document.createElement('img');
                    img.src = item.url;
                    img.loading = 'lazy';
                    img.style.cssText = 'width:100%;height:140px;object-fit:cover;cursor:pointer;display:block;background:#fff;';
                    img.title = '点击复制 URL';
                    img.onerror = function(){ img.style.display='none'; cell.querySelector('.shufei-ph').style.display='flex'; };
                    img.onclick = function(){
                        if (navigator.clipboard){
                            navigator.clipboard.writeText(item.url).then(function(){ status('已复制 URL', '#080'); });
                        } else {
                            var ta = document.createElement('textarea'); ta.value = item.url; document.body.appendChild(ta); ta.select();
                            try { document.execCommand('copy'); status('已复制 URL', '#080'); } catch(e){ status('复制失败', '#c00'); }
                            document.body.removeChild(ta);
                        }
                    };
                    var ph = document.createElement('div');
                    ph.className = 'shufei-ph';
                    ph.style.cssText = 'width:100%;height:140px;display:none;align-items:center;justify-content:center;color:#999;font-size:12px;background:#f0f0f0;';
                    ph.textContent = '图片加载失败';
                    var del = document.createElement('div');
                    del.innerHTML = '&times;';
                    del.style.cssText = 'position:absolute;top:2px;right:4px;width:22px;height:22px;line-height:20px;text-align:center;background:rgba(0,0,0,0.55);color:#fff;border-radius:50%;cursor:pointer;font-size:16px;';
                    del.title = '删除该图片';
                    del.onclick = function(e){
                        e.stopPropagation();
                        if (!confirm('确定删除这张图片吗？此操作不可恢复。\n\n' + item.name)) return;
                        del.style.background = 'rgba(0,0,0,0.3)';
                        del.textContent = '...';
                        var fd2 = new FormData();
                        fd2.append('action', 'delete_image');
                        fd2.append('profile_id', pid);
                        if (item.id) fd2.append('image_id', item.id);
                        if (item.key) fd2.append('key', item.key);
                        if (item.url) fd2.append('url', item.url);
                        fetch(ajaxUrl, {method:'POST', body:fd2, credentials:'same-origin'})
                            .then(function(r){return r.json();})
                            .then(function(res2){
                                if (res2 && res2.success){
                                    status('已删除', '#080');
                                    cell.style.transition='opacity .3s'; cell.style.opacity='0';
                                    setTimeout(function(){ cell.remove(); total--; status('共 ' + total + ' 张图片', '#080'); }, 300);
                                } else {
                                    status(res2 && res2.message ? res2.message : '删除失败', '#c00');
                                    del.style.background = 'rgba(0,0,0,0.55)'; del.innerHTML='&times;';
                                }
                            })
                            .catch(function(err){
                                status('网络错误: ' + err.message, '#c00');
                                del.style.background = 'rgba(0,0,0,0.55)'; del.innerHTML='&times;';
                            });
                    };
                    var info = document.createElement('div');
                    info.style.cssText = 'padding:6px 8px;font-size:11px;color:#666;border-top:1px solid #eee;background:#fff;';
                    info.innerHTML = '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="'+(item.name||'').replace(/"/g,'&quot;')+'">'+(item.name||'')+'</div><div style="color:#999;margin-top:2px;">'+humanSize(item.size)+(item.time?' · '+item.time:'')+'</div>';
                    cell.appendChild(img);
                    cell.appendChild(ph);
                    cell.appendChild(del);
                    cell.appendChild(info);
                    grid.appendChild(cell);
                });
            })
            .catch(function(err){
                status('网络错误: ' + err.message, '#c00');
                grid.innerHTML = '<div style="grid-column:1/-1;color:#c00;text-align:center;padding:30px;">网络错误</div>';
            });
    }

    document.getElementById('shufei-img-refresh').onclick = function(){ page = 1; load(); };
    prevBtn.onclick = function(){ if (page > 1){ page--; load(); } };
    nextBtn.onclick = function(){ if (page * limit < total){ page++; load(); } };
    sel.onchange = function(){ page = 1; };
})();

