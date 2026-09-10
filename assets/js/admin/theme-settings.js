(function() {
    window.addEventListener("load", function() {
        var f = document.querySelector(".main form");
        if (!f) return;
        var c = document.getElementById("cat-tpl");
        if (!c) return;
        c = c.querySelector(".cat-config-container");
        if (!c) return;
        var pWrap = c.querySelector("#cat-panes");
        if (!pWrap) return;
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
        // ===== 条件显示：按验证方式显示对应配置字段 =====
        function applyVerifyType() {
            var checked = document.querySelector('input[name="captchaType"][type=radio]:checked');
            var type = checked ? checked.value : "none";
            document.querySelectorAll("[data-verify-type]").forEach(function(el) {
                var target = el.getAttribute("data-verify-type");
                var show = type === target || (type === "captcha_number" || type === "captcha_alpha" || type === "captcha_alnum") && target === "image";
                el.style.display = show ? "" : "none";
            });
        }
        document.querySelectorAll('input[name="captchaType"][type=radio]').forEach(function(r) {
            r.addEventListener("change", applyVerifyType);
        });
        applyVerifyType();
        // ===== AJAX 表单提交：走主题 JSON 端点，返回可读的逐字段校验结果 =====
        // （核心 Edit::config 的 validate() 失败会静默 goBack 且 AJAX 无法区分，故由后端接管保存）
        var saveUrl = "";
        var tpl = document.getElementById("cat-tpl");
        if (tpl) { saveUrl = tpl.getAttribute("data-save") || ""; }
        if (!saveUrl) { saveUrl = c.getAttribute("data-save") || ""; }
        f.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!saveUrl) {
                // 端点地址缺失时退回原生提交流程（整页刷新，由后端标准流程保存）
                f.removeEventListener("submit", arguments.callee);
                f.submit();
                return;
            }
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
                setTimeout(function() { toast.classList.remove("show"); setTimeout(function() { toast.remove(); }, 300); }, 3500);
            }
            // 清除上次的字段错误高亮
            f.querySelectorAll(".cat-field-error").forEach(function(el) { el.classList.remove("cat-field-error"); });
            var fd = new FormData(f);
            fd.append("action", "save");
            fetch(saveUrl, { method: "POST", body: fd, credentials: "same-origin" })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res && res.success) {
                    showMsg(res.message || "保存成功", res.errors && Object.keys(res.errors).length ? "info" : "success");
                    if (res.errors) {
                        Object.keys(res.errors).forEach(function(name) {
                            var el = f.querySelector('[name="' + name + '"]');
                            if (el) {
                                el.classList.add("cat-field-error");
                                var label = el.closest(".typecho-option");
                                if (label) {
                                    var note = label.querySelector(".cat-field-error-note");
                                    if (!note) {
                                        note = document.createElement("div");
                                        note.className = "cat-field-error-note";
                                        note.style.cssText = "color:#cf1322;font-size:12px;margin-top:4px;";
                                        el.parentNode.insertBefore(note, el.nextSibling);
                                    }
                                    note.innerHTML = "<i class=\"fa fa-exclamation-circle\"></i> " + res.errors[name];
                                }
                            }
                        });
                    }
                } else {
                    showMsg((res && res.message) || "保存失败", "error");
                }
            })
            .catch(function(err) { showMsg("保存失败: " + err.message, "error"); })
            .finally(function() { btn.innerHTML = orig; btn.disabled = false; });
        });
    });
})();
