
(function(){
    if (window.__shufeiStorageInit) return;
    window.__shufeiStorageInit = true;

    var DRIVERS = window.SHUFEI_ADMIN.drivers;
    var DRIVER_FIELDS = window.SHUFEI_ADMIN.driverFields;
    var AJAX_URL = window.SHUFEI_ADMIN.ajaxUrl;

    function $(id){ return document.getElementById(id); }

    function getProfiles(){
        var ta = document.querySelector('.shufei-storage-profiles-field textarea');
        if (!ta) return [];
        try {
            var v = JSON.parse(ta.value || '[]');
            return Array.isArray(v) ? v : [];
        } catch(e){ return []; }
    }
    function setProfiles(arr){
        var ta = document.querySelector('.shufei-storage-profiles-field textarea');
        if (ta) ta.value = JSON.stringify(arr, null, 2);
    }
    function getActiveId(){
        var inp = document.querySelector('.shufei-storage-active-field input');
        return inp ? inp.value : '';
    }
    function setActiveId(id){
        var inp = document.querySelector('.shufei-storage-active-field input');
        if (inp) inp.value = id;
    }

    function genId(){
        return 'p_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2,6);
    }

    function escapeHtml(s){
        s = (s === null || s === undefined) ? '' : String(s);
        return s.replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function renderList(){
        var list = getProfiles();
        var activeId = getActiveId();
        var wrap = $('shufei-storage-list');
        var empty = $('shufei-storage-empty');
        wrap.innerHTML = '';
        if (list.length === 0) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';
        list.forEach(function(p){
            var card = document.createElement('div');
            card.className = 'shufei-storage-card' + (p.id === activeId ? ' active' : '');
            var isActive = p.id === activeId;
            var driverName = DRIVERS[p.driver] || p.driver;
            var html = '<div class="shufei-storage-card-head">';
            html += '<div><span class="shufei-storage-card-title">' + escapeHtml(p.name) + '</span>';
            html += '<span class="shufei-storage-card-driver">' + escapeHtml(driverName) + '</span>';
            if (isActive) html += '<span class="shufei-storage-badge">已激活</span>';
            html += '</div>';
            html += '<div class="shufei-storage-card-actions">';
            if (!isActive) {
                html += '<button type="button" class="shufei-storage-btn success" data-act="activate" data-id="' + escapeHtml(p.id) + '">设为激活</button>';
            }
            html += '<button type="button" class="shufei-storage-btn" data-act="edit" data-id="' + escapeHtml(p.id) + '">编辑</button>';
            html += '<button type="button" class="shufei-storage-btn" data-act="test" data-id="' + escapeHtml(p.id) + '">测试</button>';
            html += '<button type="button" class="shufei-storage-btn danger" data-act="delete" data-id="' + escapeHtml(p.id) + '">删除</button>';
            html += '</div></div>';
            card.innerHTML = html;
            wrap.appendChild(card);
        });
    }

    // 事件委托
    $('shufei-storage-list').addEventListener('click', function(e){
        var btn = e.target.closest('button[data-act]');
        if (!btn) return;
        var act = btn.getAttribute('data-act');
        var id = btn.getAttribute('data-id');
        var profiles = getProfiles();
        var p = null;
        for (var i = 0; i < profiles.length; i++) {
            if (profiles[i].id === id) { p = profiles[i]; break; }
        }
        if (!p) return;
        if (act === 'activate') {
            setActiveId(id);
            renderList();
        } else if (act === 'edit') {
            openModal(p);
        } else if (act === 'delete') {
            if (!confirm('确定删除 Profile "' + p.name + '" 吗？')) return;
            var newArr = profiles.filter(function(x){ return x.id !== id; });
            setProfiles(newArr);
            if (getActiveId() === id) setActiveId('');
            renderList();
        } else if (act === 'test') {
            testProfile(p);
        }
    });

    // 模态框
    var editingId = null;
    function openModal(p){
        editingId = p ? p.id : null;
        $('shufei-storage-modal-title').textContent = p ? '编辑 Profile' : '新建 Profile';
        $('shufei-storage-profile-name').value = p ? (p.name || '') : '';
        var driverSel = $('shufei-storage-profile-driver');
        driverSel.innerHTML = '';
        for (var did in DRIVERS) {
            var opt = document.createElement('option');
            opt.value = did; opt.textContent = DRIVERS[did];
            driverSel.appendChild(opt);
        }
        if (p && p.driver) driverSel.value = p.driver;
        renderConfigFields(p ? (p.config || {}) : {});
        $('shufei-storage-test-status').className = 'shufei-storage-test-status';
        $('shufei-storage-test-status').textContent = '';
        $('shufei-storage-mask').classList.add('show');
        $('shufei-storage-modal').classList.add('show');
        updateDriverDesc();
    }
    function closeModal(){
        $('shufei-storage-mask').classList.remove('show');
        $('shufei-storage-modal').classList.remove('show');
        editingId = null;
    }

    function renderConfigFields(existingConfig){
        var driverId = $('shufei-storage-profile-driver').value;
        var fields = DRIVER_FIELDS[driverId] || [];
        var wrap = $('shufei-storage-config-fields');
        wrap.innerHTML = '';
        fields.forEach(function(f){
            var div = document.createElement('div');
            div.className = 'shufei-storage-field';
            var label = document.createElement('label');
            label.textContent = f.label;
            div.appendChild(label);
            var input;
            var val = (existingConfig && existingConfig[f.name] !== undefined) ? existingConfig[f.name] : (f.default || '');
            if (f.type === 'select') {
                input = document.createElement('select');
                // 兼容 PHP 关联数组 json_encode 后的对象（{key:value}）与索引数组（[{value,label}]）
                var opts = f.options || [];
                if (!Array.isArray(opts)) {
                    // 对象 {value:label} 转为数组 [{value:value, label:label}]
                    var arr = [];
                    Object.keys(opts).forEach(function(k){
                        var v = opts[k];
                        if (v && typeof v === 'object') {
                            arr.push(v);
                        } else {
                            arr.push({ value: k, label: v });
                        }
                    });
                    opts = arr;
                }
                opts.forEach(function(opt){
                    var o = document.createElement('option');
                    // opt 可能是 {value,label} 或字符串
                    var ov = (typeof opt === 'object') ? opt.value : opt;
                    var ol = (typeof opt === 'object') ? opt.label : opt;
                    o.value = ov; o.textContent = ol;
                    if (String(val) === String(ov)) o.selected = true;
                    input.appendChild(o);
                });
            } else if (f.type === 'password') {
                input = document.createElement('input');
                input.type = 'password';
                input.value = val;
            } else if (f.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3; input.value = val;
            } else {
                input = document.createElement('input');
                input.type = 'text'; input.value = val;
            }
            input.name = 'cfg_' + f.name;
            input.setAttribute('data-field', f.name);
            div.appendChild(input);
            if (f.desc) {
                var d = document.createElement('div');
                d.className = 'desc'; d.innerHTML = f.desc;
                div.appendChild(d);
            }
            wrap.appendChild(div);
        });
    }

    function updateDriverDesc(){
        var driverId = $('shufei-storage-profile-driver').value;
        var desc = {
            local: '本地存储：图片保存至 usr/uploads/，遵循 Typecho 原生目录结构。',
            lsky: 'Lsky Pro 兰空图床：支持 v1（/api/v1/upload）与 v2（/api/v2/upload）接口，v2 支持图片列表与删除。',
            s3: 'AWS S3 兼容：AWS S3、MinIO、Cloudflare R2、阿里云 OSS（S3 兼容模式）。',
            webdav: 'WebDAV：标准 WebDAV 协议，支持 Nextcloud / 坚果云 / 群晖等。',
            aliyunoss: '阿里云 OSS：使用 V1 签名直传，需提供 Endpoint/Bucket/AccessKey。',
            tencentcos: '腾讯云 COS：使用 COS V5 签名直传。',
            qiniukodo: '七牛云 KODO：使用管理 AccessToken 签名直传。',
            upyun: '又拍云 USS：使用 HMAC-SHA1 签名直传。',
            catimg: '小猫咪图床：通过 X-API-Key 认证上传至 img.czzu.cn 或自建实例，支持 jpg/png/gif/webp/svg。'
        };
        $('shufei-storage-driver-desc').textContent = desc[driverId] || '';
    }

    $('shufei-storage-profile-driver').addEventListener('change', function(){
        renderConfigFields({});
        updateDriverDesc();
    });
    $('shufei-storage-add-btn').addEventListener('click', function(){ openModal(null); });
    $('shufei-storage-modal-close').addEventListener('click', closeModal);
    $('shufei-storage-cancel-btn').addEventListener('click', closeModal);
    $('shufei-storage-mask').addEventListener('click', closeModal);

    function collectConfig(){
        var cfg = {};
        var inputs = $('shufei-storage-config-fields').querySelectorAll('[data-field]');
        inputs.forEach(function(el){
            cfg[el.getAttribute('data-field')] = el.value;
        });
        return cfg;
    }

    $('shufei-storage-save-btn').addEventListener('click', function(){
        var name = $('shufei-storage-profile-name').value.trim();
        var driver = $('shufei-storage-profile-driver').value;
        if (!name) { alert('请填写 Profile 名称'); return; }
        var cfg = collectConfig();
        var profiles = getProfiles();
        if (editingId) {
            for (var i = 0; i < profiles.length; i++) {
                if (profiles[i].id === editingId) {
                    profiles[i].name = name;
                    profiles[i].driver = driver;
                    profiles[i].config = cfg;
                    break;
                }
            }
        } else {
            var newP = { id: genId(), name: name, driver: driver, config: cfg };
            profiles.push(newP);
            // 若是第一个，自动激活
            if (profiles.length === 1) setActiveId(newP.id);
        }
        setProfiles(profiles);
        renderList();
        closeModal();
    });

    function showTestStatus(msg, type){
        var el = $('shufei-storage-test-status');
        el.className = 'shufei-storage-test-status show ' + type;
        el.textContent = msg;
    }

    $('shufei-storage-test-btn').addEventListener('click', function(){
        var driver = $('shufei-storage-profile-driver').value;
        var cfg = collectConfig();
        showTestStatus('正在测试连接并上传测试图片，请稍候...', '');
        var fd = new FormData();
        fd.append('action', 'test_connection');
        fd.append('driver', driver);
        fd.append('config', JSON.stringify(cfg));
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success) {
                    showTestStatus(d.message || '✓ 连接成功', 'success');
                } else {
                    showTestStatus(d.message || '连接失败', 'error');
                }
            })
            .catch(function(e){ showTestStatus('请求失败: ' + e.message, 'error'); });
    });

    function testProfile(p){
        if (!p) return;
        if (!confirm('测试 Profile "' + p.name + '"？将上传一张测试图片并自动删除。')) return;
        var fd = new FormData();
        fd.append('action', 'test_connection');
        fd.append('driver', p.driver);
        fd.append('config', JSON.stringify(p.config || {}));
        alert('正在测试 ' + p.name + '，请稍候...');
        fetch(AJAX_URL, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                alert((d.success ? '✓ ' : '✗ ') + (d.message || (d.success ? '连接成功' : '连接失败')));
            })
            .catch(function(e){ alert('请求失败: ' + e.message); });
    }

    // 水印类型切换
    function updateWatermarkFields(){
        var wmOn = document.querySelector('input[name=shufeiStorageWatermark]:checked');
        wmOn = wmOn && wmOn.value === 'on';
        var wmType = document.querySelector('input[name=shufeiStorageWatermarkType]:checked');
        wmType = wmType ? wmType.value : 'text';
        document.querySelectorAll('.shufei-storage-wm-text, .shufei-storage-wm-image, .shufei-storage-wm-type, .shufei-storage-wm-color, .shufei-storage-wm-size, .shufei-storage-wm-font, .shufei-storage-wm-position, .shufei-storage-wm-opacity').forEach(function(el){
            // 全部按水印总开关控制
            if (!wmOn) { el.style.display = 'none'; return; }
            // 文字字段与图片字段按类型切换
            if (el.classList.contains('shufei-storage-wm-image') && wmType !== 'image') { el.style.display = 'none'; return; }
            if ((el.classList.contains('shufei-storage-wm-text') || el.classList.contains('shufei-storage-wm-color') || el.classList.contains('shufei-storage-wm-size') || el.classList.contains('shufei-storage-wm-font')) && wmType !== 'text') { el.style.display = 'none'; return; }
            el.style.display = '';
        });
    }
    document.querySelectorAll('input[name=shufeiStorageWatermark], input[name=shufeiStorageWatermarkType]').forEach(function(r){
        r.addEventListener('change', updateWatermarkFields);
    });

    // 在页面加载完成后初始化（等待 Tab 渲染完成）
    window.addEventListener('load', function(){
        // 显示 storage 字段（cat-group-storage 已被 Tab 系统处理）
        updateWatermarkFields();
        renderList();
    });
})();

