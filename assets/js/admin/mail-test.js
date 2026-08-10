(function() {
    window.addEventListener("load", function() {
        var wrap = document.getElementById("cat-mail-test-wrap");
        if (!wrap) return;
        wrap.style.display = "";
        var btn = document.getElementById("cat-mail-test-btn");
        var toInput = document.getElementById("cat-mail-test-to");
        var status = document.getElementById("cat-mail-test-status");
        var ajaxUrl = wrap.getAttribute("data-ajax");
        if (!btn || !toInput || !status || !ajaxUrl) return;

        function show(msg, type) {
            status.className = "cat-data-status show " + (type || "info");
            status.innerHTML = msg;
        }

        function sendTest() {
            var to = (toInput.value || "").trim();
            if (!to) { show("请先填写测试接收邮箱", "error"); return; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) { show("接收邮箱格式不正确", "error"); return; }

            var fd = new FormData();
            fd.append("to", to);
            btn.disabled = true;
            show("<i class=\"fa fa-spinner fa-pulse\"></i> 正在发送测试邮件，请稍候…", "info");
            fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
                .then(function(r) { return r.json().catch(function() { return null; }); })
                .then(function(d) {
                    if (d && d.success) {
                        show("<i class=\"fa fa-check-circle\"></i> " + d.message, "success");
                    } else {
                        show("<i class=\"fa fa-exclamation-circle\"></i> " + ((d && d.message) || "发送失败，请刷新页面后重试"), "error");
                    }
                })
                .catch(function(e) { show("<i class=\"fa fa-exclamation-circle\"></i> 请求失败：" + e.message, "error"); })
                .finally(function() { btn.disabled = false; });
        }

        btn.addEventListener("click", sendTest);
        toInput.addEventListener("keydown", function(e) {
            if (e.key === "Enter") { e.preventDefault(); btn.click(); }
        });
    });
})();
