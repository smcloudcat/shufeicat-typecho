
(function(){
    var btn = document.getElementById("shufei-clear-cache-btn");
    if (!btn) return;
    var status = document.getElementById("shufei-clear-cache-status");
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
        });
    }
    btn.addEventListener("click", function(){
        if (!confirm("确定清空所有缓存？下次访问将重新生成。")) return;
        var url = btn.getAttribute("data-url");
        status.className = "shufei-update-status show info";
        status.innerHTML = "正在清空缓存...";
        btn.disabled = true;
        var formData = new FormData();
        formData.append("action", "clear_cache");
        fetch(url, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        }).then(function(r){ return r.json(); }).then(function(d){
            if (d.success) {
                status.className = "shufei-update-status show success";
                status.innerHTML = esc(d.message || "清空完成");
            } else {
                status.className = "shufei-update-status show error";
                status.innerHTML = esc(d.message || "清空失败");
            }
        }).catch(function(e){
            status.className = "shufei-update-status show error";
            status.innerHTML = "请求失败：" + esc(e.message);
        }).finally(function(){
            btn.disabled = false;
        });
    });
})();

