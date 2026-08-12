(function() {
    window.addEventListener("load", function() {
        var f = document.querySelector(".main form");
        if (!f) return;
        var c = document.getElementById("cat-tpl").querySelector(".cat-config-container");
        var pWrap = c.querySelector("#cat-panes");
        f.insertBefore(c, f.firstChild);
        var ids = ["cat-basic", "cat-avatar", "cat-appearance", "cat-pjax", "cat-resource", "cat-article", "cat-sidebar", "cat-seo", "cat-mail", "cat-ai", "cat-storage", "cat-verify", "cat-enhance", "cat-data", "cat-update"];
        ids.forEach(function(id) {
            var p = document.createElement("div");
            p.id = id; p.className = "cat-pane" + (id === "cat-basic" ? " active" : "");
            pWrap.appendChild(p);
        });
        document.querySelectorAll(".typecho-option:not(.typecho-option-submit)").forEach(function(el) {
            var m = el.className.match(/cat-group-[\w-]+/);
            if (m) {
                var target = document.getElementById(m[0].replace("cat-group-", "cat-"));
                if (target) target.appendChild(el);
            } else {
                var basic = document.getElementById("cat-basic");
                if (basic) basic.appendChild(el);
            }
        });
        document.querySelectorAll("#cat-tabs li").forEach(function(li) {
            li.onclick = function() {
                document.querySelectorAll("#cat-tabs li").forEach(function(n) { n.classList.remove("active"); });
                this.classList.add("active");
                document.querySelectorAll(".cat-pane").forEach(function(p) { p.classList.remove("active"); });
                var targetPane = document.getElementById(this.getAttribute("data-id"));
                if (targetPane) targetPane.classList.add("active");
            };
        });
        // ===== 条件显示：开关关闭时隐藏其详细设置 =====
        function applyToggleGroup(g) {
            var checked = document.querySelector('input[name="' + g + '"][type=radio]:checked');
            var on = checked && checked.value === "on";
            document.querySelectorAll('[data-toggle-dep="' + g + '"]').forEach(function(el) {
                el.style.display = on ? "" : "none";
            });
        }
        ["guestbookEnabled", "linksPageEnabled", "githubEnabled"].forEach(function(g) {
            document.querySelectorAll('input[name="' + g + '"][type=radio]').forEach(function(r) {
                r.addEventListener("change", function() { applyToggleGroup(g); });
            });
            applyToggleGroup(g);
        });
        // ===== AJAX 表单提交：无需刷新页面 =====
        f.addEventListener("submit", function(e) {
            e.preventDefault();
            var btn = f.querySelector(".typecho-option-submit button");
            if (!btn) return;
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = "<i class=\"fa fa-spinner fa-pulse\"></i> 保存中…";
            var toast = document.createElement("div");
            toast.className = "cat-save-toast";
            document.body.appendChild(toast);
            function showMsg(msg, type) {
                toast.className = "cat-save-toast show " + type;
                toast.innerHTML = (type === "success" ? "<i class=\"fa fa-check-circle\"></i>" : "<i class=\"fa fa-exclamation-circle\"></i>") + " " + msg;
                setTimeout(function() { toast.classList.remove("show"); setTimeout(function() { toast.remove(); }, 300); }, 2500);
            }
            fetch(f.action, { method: "POST", body: new FormData(f), credentials: "same-origin", redirect: "follow" })
            .then(function(r) {
                if (r.ok) { showMsg("保存成功", "success"); }
                else { showMsg("保存失败 (HTTP " + r.status + ")", "error"); }
            })
            .catch(function(err) { showMsg("保存失败: " + err.message, "error"); })
            .finally(function() { btn.innerHTML = orig; btn.disabled = false; });
        });
    });
})();
