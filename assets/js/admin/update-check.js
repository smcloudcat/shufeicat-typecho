
(function(){
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
        });
    }

    // 简单版本比较：返回 -1/0/1
    function compareVersion(a, b){
        var pa = String(a).split(/[.+-]/);
        var pb = String(b).split(/[.+-]/);
        var len = Math.max(pa.length, pb.length);
        for (var i = 0; i < len; i++){
            var na = parseInt(pa[i] || "0", 10);
            var nb = parseInt(pb[i] || "0", 10);
            if (isNaN(na)) na = 0;
            if (isNaN(nb)) nb = 0;
            if (na > nb) return 1;
            if (na < nb) return -1;
        }
        return 0;
    }

    // 构造更新服务器 API 请求 URL
    function buildApiUrl(api, owner, repo, version, channel){
        var query = "owner=" + encodeURIComponent(owner)
            + "&repo=" + encodeURIComponent(repo)
            + "&version=" + encodeURIComponent(version)
            + "&channel=" + encodeURIComponent(channel)
            + "&blogurl=" + encodeURIComponent(location.origin);
        var sep = api.indexOf("?") === -1 ? "?" : "&";
        return api + sep + query;
    }

    // 渲染更新检查结果到顶部面板
    function renderUpdateResult(notice, data, currentVer){
        if (data.code == 1 && data.version) {
            if (currentVer && compareVersion(data.version, currentVer) > 0) {
                var h = "发现新版本 <b>v" + esc(data.version) + "</b>";
                if (data.msg) h += "<br>更新内容：" + esc(data.msg);
                if (data.url) h += "<br>下载链接：<a href=\"" + esc(data.url) + "\" target=\"_blank\" rel=\"noopener\">" + esc(data.url) + "</a>";
                notice.className = "shufei-update-notice has-update";
                notice.innerHTML = h;
            } else {
                notice.className = "shufei-update-notice latest";
                notice.innerHTML = "当前已是最新版本";
            }
        } else {
            notice.className = "shufei-update-notice info";
            notice.innerHTML = esc(data.msg || "当前已是最新版本");
        }
    }

    // ===== 顶部面板自动异步检查（浏览器直连更新服务器） =====
    var notice = document.getElementById("shufei-update-notice");
    if (notice) {
        var api = notice.getAttribute("data-api");
        var owner = notice.getAttribute("data-owner");
        var repo = notice.getAttribute("data-repo");
        var currentVer = notice.getAttribute("data-version");
        var savedChannel = notice.getAttribute("data-channel");

        // 读取"更新通道"radio 当前选中值（实时，无需保存）
        function getUpdateChannel(){
            var radios = document.getElementsByName("shufeiUpdateChannel");
            for (var i = 0; i < radios.length; i++){
                if (radios[i].checked) return radios[i].value;
            }
            return savedChannel;
        }

        function runAutoCheck(){
            var ch = getUpdateChannel();
            if (!api || !ch || ch === "manual") {
                notice.className = "shufei-update-notice info";
                notice.innerHTML = '已切换为手动检查模式，请前往"更新设置"分类中手动检查更新（不会自动请求网络）';
                return;
            }
            notice.className = "shufei-update-notice loading";
            notice.innerHTML = "正在检查更新...";
            var url = buildApiUrl(api, owner, repo, currentVer, ch);
            fetch(url, { credentials: "omit" })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    renderUpdateResult(notice, d, currentVer);
                })
                .catch(function(e){
                    notice.className = "shufei-update-notice error";
                    notice.innerHTML = "检查失败：" + esc(e.message);
                });
        }

        // 页面加载后自动检查一次
        runAutoCheck();

        // 监听"更新通道"radio 变化，实时重新检查
        var radios = document.getElementsByName("shufeiUpdateChannel");
        for (var i = 0; i < radios.length; i++){
            radios[i].addEventListener("change", runAutoCheck);
        }
    }

    // ===== "立即检查更新"按钮（手动触发，直连更新接口） =====
    var btn = document.getElementById("shufei-check-btn");
    if (!btn) return;
    var status = document.getElementById("shufei-check-status");
    var chSel = document.getElementById("shufei-check-channel");
    btn.addEventListener("click", function(){
        var ch = chSel.value;
        var api = btn.getAttribute("data-api");
        var url = buildApiUrl(api, btn.getAttribute("data-owner"), btn.getAttribute("data-repo"), btn.getAttribute("data-version"), ch);
        status.className = "shufei-update-status show info";
        status.innerHTML = "正在检查更新...";
        btn.disabled = true;
        fetch(url).then(function(r){ return r.json(); }).then(function(d){
            if (d.code == 1 && d.version) {
                var h = "<b>发现新版本：v" + esc(d.version) + "</b>";
                if (d.url) h += "<br>下载链接：<a href=\"" + esc(d.url) + "\" target=\"_blank\" rel=\"noopener\">" + esc(d.url) + "</a>";
                if (d.msg) h += "<br>更新内容：" + esc(d.msg);
                status.className = "shufei-update-status show success";
                status.innerHTML = h;
                // 同步更新顶部面板
                var topNotice = document.getElementById("shufei-update-notice");
                if (topNotice) {
                    renderUpdateResult(topNotice, d, topNotice.getAttribute("data-version"));
                }
            } else {
                status.className = "shufei-update-status show info";
                status.innerHTML = esc(d.msg || "当前已是最新版本");
            }
        }).catch(function(e){
            status.className = "shufei-update-status show error";
            status.innerHTML = "检查失败：" + esc(e.message);
        }).finally(function(){ btn.disabled = false; });
    });
})();
