(function () {
    function esc(s) {
        return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function statusShow(el, msg, type) {
        el.className = "cat-data-status show " + (type || "info");
        el.innerHTML = '<i class="fa fa-' + (type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle") + '"></i> ' + msg;
    }

    function collectSettings() {
        var fields = ["themeColor", "bgColor", "bgImage", "bgGradientEnabled", "bgGradient", "bgGradientDark", "bgGradientAttachment", "cardOpacity", "postListStyle"];
        var data = {};
        fields.forEach(function (name) {
            var list = document.querySelectorAll('[name="' + name + '"]');
            if (!list || !list.length) return;
            var el = list[0];
            if (el.type === "radio" || el.type === "checkbox") {
                list.forEach(function (r) { if (r.checked) data[name] = r.value; });
            } else {
                data[name] = el.value;
            }
        });
        return data;
    }

    function currentId() {
        var checked = document.querySelector('#style-preset-list input[name="style-preset-pick"]:checked');
        return checked ? checked.value : "";
    }

    function updateButtons() {
        var has = !!currentId();
        var applyBtn = document.getElementById("style-preset-apply");
        var delBtn = document.getElementById("style-preset-del");
        if (applyBtn) applyBtn.disabled = !has;
        if (delBtn) delBtn.disabled = !has;
    }

    function renderList(container, presets) {
        var countEl = document.getElementById("style-preset-count");
        if (countEl) countEl.textContent = presets && presets.length ? "（已保存 " + presets.length + " 个样式）" : "";
        if (!presets || !presets.length) {
            container.innerHTML = '<div style="padding:8px 0;color:#8c8c8c;">暂未保存任何样式，可先调配外观后点击「保存当前样式」。</div>';
            updateButtons();
            return;
        }
        var html = '<ul class="style-preset-options" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;">';
        presets.forEach(function (p, idx) {
            var date = p.created_at ? new Date(p.created_at * 1000) : null;
            var dateStr = date ? date.getFullYear() + "-" + (date.getMonth() + 1) + "-" + date.getDate() : "";
            var swatch = "";
            if (p.preview) {
                var dot = "";
                if (p.preview.themeColor) dot += '<i class="style-preset-dot" title="主题色" style="background:' + esc(p.preview.themeColor) + ';display:inline-block;width:16px;height:16px;border-radius:4px;border:1px solid rgba(0,0,0,.12);vertical-align:middle;"></i>';
                if (p.preview.background) dot += '<i class="style-preset-dot" title="背景" style="background:linear-gradient(135deg,' + esc(p.preview.background) + ');display:inline-block;width:16px;height:16px;border-radius:4px;border:1px solid rgba(0,0,0,.12);vertical-align:middle;"></i>';
                if (dot) swatch = '<span style="display:inline-flex;gap:4px;margin-right:6px;">' + dot + '</span>';
            }
            html += '<li class="typecho-option" style="border:1px solid #e8e8e8;border-radius:4px;padding:0;border-bottom:1px solid #e8e8e8;">' +
                '<label style="display:flex;align-items:center;gap:8px;width:100%;box-sizing:border-box;padding:10px 12px;cursor:pointer;font-size:13px;color:#333;line-height:1.4;">' +
                '<input type="radio" name="style-preset-pick" value="' + esc(p.id) + '"' + (idx === 0 ? ' checked' : '') + ' />' +
                swatch +
                '<span style="font-weight:600;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + esc(p.name) + '">' + esc(p.name) + '</span>' +
                '<span style="color:#8c8c8c;font-size:12px;flex-shrink:0;">' + esc(dateStr) + ' · ' + (p.count || 0) + ' 项</span>' +
                '</label></li>';
        });
        html += '</ul>';
        container.innerHTML = html;
        container.querySelectorAll('input[name="style-preset-pick"]').forEach(function (r) {
            r.addEventListener("change", updateButtons);
        });
        updateButtons();
    }

    function renderRecommended(container, recs, post, statusEl) {
        if (!recs || !recs.length) {
            container.innerHTML = "";
            return;
        }
        var html = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">';
        recs.forEach(function (r) {
            var bg = esc(r.preview && r.preview.background ? r.preview.background : "#ffffff");
            var dot = r.preview && r.preview.themeColor ? '<i style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + esc(r.preview.themeColor) + ';margin-right:4px;vertical-align:middle;"></i>' : "";
            html += '<div style="border:1px solid #e3e3e3;border-radius:6px;overflow:hidden;background:#fff;display:flex;flex-direction:column;">' +
                '<a class="style-preset-rec-app" href="javascript:;" data-id="' + esc(r.id) + '" title="' + esc(r.name) + '" style="display:block;height:86px;background:' + bg + ';position:relative;overflow:hidden;text-decoration:none;cursor:pointer;">' +
                '<img src="' + esc(r.image) + '" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="this.style.display=\'none\';" />' +
                '<span style="position:absolute;left:8px;bottom:8px;background:rgba(0,0,0,.55);color:#fff;font-size:11px;border-radius:10px;padding:2px 8px;white-space:nowrap;">' + dot + '一键应用</span>' +
                '</a>' +
                '<div style="padding:8px 10px 10px;display:flex;flex-direction:column;gap:2px;">' +
                '<b style="font-size:12px;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + esc(r.name) + '">' + esc(r.name) + '</b>' +
                '<span style="font-size:11px;color:#8c8c8c;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">' + esc(r.desc) + '</span>' +
                '</div></div>';
        });
        html += '</div>';
        container.innerHTML = html;
        container.querySelectorAll(".style-preset-rec-app").forEach(function (a) {
            a.addEventListener("click", function (ev) {
                ev.preventDefault();
                if (!window.confirm("确定应用官方推荐「" + a.getAttribute("title") + "」吗？将覆盖当前外观设置中对应字段的值。")) return;
                var id = a.getAttribute("data-id");
                a.style.opacity = ".55";
                a.style.pointerEvents = "none";
                post({ action: "apply", id: id }).then(function (res) {
                    a.style.opacity = "1";
                    a.style.pointerEvents = "";
                    if (res && res.success) {
                        if (statusEl) {
                            statusEl.className = "cat-data-status show success";
                            statusEl.innerHTML = '<i class="fa fa-check-circle"></i> ' + res.message;
                        }
                        setTimeout(function () { location.reload(); }, 1200);
                    } else if (statusEl) {
                        statusEl.className = "cat-data-status show error";
                        statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (res && res.message ? res.message : "应用失败");
                    }
                }).catch(function () {
                    a.style.opacity = "1";
                    a.style.pointerEvents = "";
                    if (statusEl) {
                        statusEl.className = "cat-data-status show error";
                        statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> 应用失败，请重试';
                    }
                });
            });
        });
    }

    window.addEventListener("load", function () {
        var box = document.getElementById("cat-style-presets-box");
        if (!box) return;
        var listEl = document.getElementById("style-preset-list");
        var statusEl = document.getElementById("style-preset-status");
        var nameInput = document.getElementById("style-preset-name");
        var saveBtn = document.getElementById("style-preset-save");
        var applyBtn = document.getElementById("style-preset-apply");
        var delBtn = document.getElementById("style-preset-del");
        if (!listEl || !statusEl || !nameInput || !saveBtn || !applyBtn || !delBtn) return;
        var ajax = box.getAttribute("data-ajax");

        // ===== 默认折叠，点击标题展开/收起 =====
        var toggleEl = document.getElementById("style-preset-toggle");
        var bodyEl = document.getElementById("style-preset-body");
        if (toggleEl && bodyEl) {
            var headBtn = toggleEl.querySelector(".style-preset-head-btn");
            var open = false;
            function setOpen(v) {
                open = v;
                bodyEl.style.display = open ? "block" : "none";
                toggleEl.classList.toggle("open", open);
                toggleEl.setAttribute("aria-expanded", open ? "true" : "false");
                if (headBtn) headBtn.innerHTML = open ? '<i class="fa fa-angle-double-up"></i>收起设置' : '<i class="fa fa-angle-double-down"></i>展开设置';
            }
            toggleEl.addEventListener("click", function () { setOpen(!open); });
        }

        function post(data) {
            var fd = new FormData();
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
            return fetch(ajax, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); });
        }

        function refresh() {
            return post({ action: "list" }).then(function (res) {
                var recEl = document.getElementById("style-preset-recommend");
                if (recEl) renderRecommended(recEl, res && res.recommended, post, statusEl);
                if (res && res.success) {
                    renderList(listEl, res.presets);
                } else {
                    listEl.innerHTML = '<div style="padding:8px 0;color:#cf1322;">加载失败：' + esc(res && res.message ? res.message : "未知错误") + '</div>';
                    updateButtons();
                }
            });
        }

        saveBtn.addEventListener("click", function () {
            var name = nameInput.value.trim();
            if (!name) {
                statusEl.className = "cat-data-status show error";
                statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> 请先填写样式名称';
                return;
            }
            var settings = collectSettings();
            if (!Object.keys(settings).length) {
                statusEl.className = "cat-data-status show error";
                statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> 未读取到外观配置，请确认在当前页面的「外观设置」分组操作';
                return;
            }
            saveBtn.disabled = true;
            var data = { action: "save", name: name };
            Object.keys(settings).forEach(function (k) { data[k] = settings[k]; });
            post(data).then(function (res) {
                saveBtn.disabled = false;
                if (res && res.success) {
                    nameInput.value = "";
                    statusEl.className = "cat-data-status show success";
                    statusEl.innerHTML = '<i class="fa fa-check-circle"></i> ' + res.message;
                    renderList(listEl, res.presets);
                } else {
                    statusEl.className = "cat-data-status show error";
                    statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (res && res.message ? res.message : "保存失败");
                }
            }).catch(function (err) {
                saveBtn.disabled = false;
                statusEl.className = "cat-data-status show error";
                statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> 保存失败: ' + err.message;
            });
        });

        function doAction(act, btn) {
            var id = currentId();
            if (!id) return;
            if (act === "delete") {
                if (!window.confirm("确定删除该样式预设吗？")) return;
            } else if (act === "apply") {
                if (!window.confirm("确定应用该样式吗？会覆盖当前外观设置中对应字段的值。")) return;
            }
            btn.disabled = true;
            post({ action: act, id: id }).then(function (res) {
                btn.disabled = false;
                if (res && res.success) {
                    if (act === "apply") {
                        statusEl.className = "cat-data-status show success";
                        statusEl.innerHTML = '<i class="fa fa-check-circle"></i> ' + res.message;
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        statusEl.className = "cat-data-status show success";
                        statusEl.innerHTML = '<i class="fa fa-check-circle"></i> ' + res.message;
                        renderList(listEl, res.presets);
                    }
                } else {
                    statusEl.className = "cat-data-status show error";
                    statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (res && res.message ? res.message : "操作失败");
                }
            }).catch(function (err) {
                btn.disabled = false;
                statusEl.className = "cat-data-status show error";
                statusEl.innerHTML = '<i class="fa fa-exclamation-circle"></i> 操作失败: ' + err.message;
            });
        }

        applyBtn.addEventListener("click", function () { doAction("apply", applyBtn); });
        delBtn.addEventListener("click", function () { doAction("delete", delBtn); });

        refresh();
    });
})();