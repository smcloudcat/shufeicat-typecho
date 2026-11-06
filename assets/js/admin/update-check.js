
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

    // 将更新内容（可能含 \r\n 换行）渲染为可换行的 HTML
    function renderChangelog(msg){
        if (!msg) return "";
        // 转义后保留换行，交给 CSS white-space:pre-wrap 显示
        return esc(msg);
    }

    // 渲染更新检查结果到顶部面板
    function renderUpdateResult(notice, data, currentVer){
        if (data.code == 1 && data.version) {
            if (currentVer && compareVersion(data.version, currentVer) > 0) {
                var h = "发现新版本 <b>v" + esc(data.version) + "</b>";
                if (data.msg) h += "<br>更新内容：<span style=\"white-space:pre-wrap\">" + renderChangelog(data.msg) + "</span>";
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

    // ===== 更新弹窗 =====
    var updMask = document.getElementById("shufei-update-mask");
    var updModal = document.getElementById("shufei-update-modal");
    var updBody = document.getElementById("shufei-update-modal-body");
    var updFooter = document.getElementById("shufei-update-modal-footer");
    var updClose = document.getElementById("shufei-update-close");

    function closeUpdateModal(){
        if (updModal) updModal.classList.remove("show");
        if (updMask) updMask.classList.remove("show");
    }

    function openUpdateModal(data, currentVer){
        if (!updModal || !updBody || !updFooter) return;
        var ver = esc(data.version || "");
        var url = esc(data.url || "");
        var changelog = renderChangelog(data.changelog || data.msg || "");

        updBody.innerHTML = '<div class="upd-ver">发现新版本：v' + ver + '</div>'
            + (url ? '<div>更新地址：<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a></div>' : '')
            + (changelog ? '<div class="upd-changelog">' + changelog + '</div>' : '');

        updFooter.innerHTML = ''
            + '<button type="button" class="shufei-update-modal-btn ghost" data-upd-act="later">下次进入再显示</button>'
            + '<button type="button" class="shufei-update-modal-btn ghost" data-upd-act="never">不再显示</button>'
            + (url ? '<button type="button" class="shufei-update-modal-btn primary" data-upd-act="go">前往更新地址</button>' : '');

        updModal.classList.add("show");
        updMask.classList.add("show");
    }

    // 弹窗按钮事件（事件委托，避免重复绑定）
    if (updModal) {
        updModal.addEventListener("click", function(e){
            var btn = e.target.closest("[data-upd-act]");
            if (!btn) return;
            var act = btn.getAttribute("data-upd-act");
            var ver = updModal.getAttribute("data-version") || "";
            if (act === "go") {
                var url = updModal.getAttribute("data-url");
                if (url) window.open(url, "_blank", "noopener");
                closeUpdateModal();
            } else if (act === "later") {
                // 下次进入再显示：记录当前版本，本次不再弹
                try { localStorage.setItem("shufei_update_later", ver); } catch(e){}
                closeUpdateModal();
            } else if (act === "never") {
                // 不再显示：永久忽略该版本
                try { localStorage.setItem("shufei_update_never", ver); } catch(e){}
                closeUpdateModal();
            }
        });
    }
    if (updClose) {
        updClose.addEventListener("click", closeUpdateModal);
    }
    if (updMask) {
        updMask.addEventListener("click", closeUpdateModal);
    }

    // 判断是否应弹出更新提示
    function shouldShowModal(data, currentVer){
        if (!data || data.code != 1 || !data.version) return false;
        if (!(currentVer && compareVersion(data.version, currentVer) > 0)) return false;
        var ver = data.version;
        try {
            if (localStorage.getItem("shufei_update_never") === ver) return false;
            if (localStorage.getItem("shufei_update_later") === ver) return false;
        } catch(e){}
        return true;
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
                    // 自动检测到新版本时弹出提示
                    if (shouldShowModal(d, currentVer)) {
                        updModal.setAttribute("data-version", d.version || "");
                        updModal.setAttribute("data-url", d.url || "");
                        openUpdateModal(d, currentVer);
                    }
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
                if (d.msg) h += "<br>更新内容：<span style=\"white-space:pre-wrap\">" + renderChangelog(d.msg) + "</span>";
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
