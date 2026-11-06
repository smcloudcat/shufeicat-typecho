(function() {
    window.addEventListener("load", function() {
        var dataEl = document.querySelector(".cat-group-data");
        if (dataEl) dataEl.style.display = "";

        function collectFormData() {
            var form = document.querySelector(".main form");
            if (!form) return {};
            var data = {};
            var inputs = form.querySelectorAll("input[name], textarea[name], select[name]");
            inputs.forEach(function(el) {
                var name = el.getAttribute("name");
                if (!name || name.indexOf("do") === 0) return;
                if (el.type === "checkbox") {
                    if (el.checked) {
                        if (!data[name]) data[name] = [];
                        data[name].push(el.value);
                    }
                } else if (el.type === "radio") {
                    if (el.checked) data[name] = el.value;
                } else {
                    data[name] = el.value;
                }
            });
            return data;
        }

        var exportBtn = document.getElementById("cat-export-btn");
        if (exportBtn) {
            exportBtn.addEventListener("click", function() {
                var data = collectFormData();
                var exportData = {
                    theme: "shufeicat-typecho",
                    version: window.SHUFEI_ADMIN.version,
                    exportTime: new Date().toLocaleString("zh-CN"),
                    options: data
                };
                var json = JSON.stringify(exportData, null, 2);
                var blob = new Blob([json], {type: "application/json"});
                var url = URL.createObjectURL(blob);
                var a = document.createElement("a");
                a.href = url;
                a.download = "shufeicat-config-" + new Date().toISOString().slice(0,19).replace(/[T:]/g, "") + ".json";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }

        var fileInput = document.getElementById("cat-import-file");
        var fileName = document.getElementById("cat-file-name");
        var importBtn = document.getElementById("cat-import-btn");
        var importStatus = document.getElementById("cat-import-status");

        if (fileInput) {
            fileInput.addEventListener("change", function() {
                if (this.files && this.files[0]) {
                    fileName.textContent = this.files[0].name;
                    importBtn.disabled = false;
                    importStatus.className = "cat-data-status";
                    importStatus.textContent = "";
                } else {
                    fileName.textContent = "未选择文件";
                    importBtn.disabled = true;
                }
            });
        }

        if (importBtn) {
            importBtn.addEventListener("click", function() {
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    importStatus.className = "cat-data-status show error";
                    importStatus.textContent = "请先选择配置文件";
                    return;
                }
                if (!confirm("导入配置将覆盖当前所有主题设置并自动保存，确定要继续吗？")) return;
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var importData = JSON.parse(e.target.result);
                        if (!importData || !importData.options || typeof importData.options !== "object") {
                            importStatus.className = "cat-data-status show error";
                            importStatus.textContent = "无效的配置文件格式，请检查文件内容";
                            return;
                        }
                        var form = document.querySelector(".main form");
                        if (!form) {
                            importStatus.className = "cat-data-status show error";
                            importStatus.textContent = "未找到设置表单";
                            return;
                        }
                        var options = importData.options;
                        var formInputs = {};
                        form.querySelectorAll("input[name], textarea[name], select[name]").forEach(function(el) {
                            var n = el.getAttribute("name");
                            if (n && n.indexOf("do") !== 0) {
                                if (!formInputs[n]) formInputs[n] = [];
                                formInputs[n].push(el);
                            }
                        });
                        for (var key in options) {
                            if (!formInputs[key]) continue;
                            var els = formInputs[key];
                            var val = options[key];
                            els.forEach(function(el) {
                                if (el.type === "checkbox") {
                                    var vals = Array.isArray(val) ? val : [val];
                                    el.checked = vals.indexOf(el.value) !== -1;
                                } else if (el.type === "radio") {
                                    el.checked = (el.value === String(val));
                                } else if (el.tagName === "SELECT") {
                                    el.value = val;
                                } else {
                                    el.value = val || "";
                                }
                            });
                        }
                        importStatus.className = "cat-data-status show success";
                        importStatus.textContent = "配置已填入表单，正在自动保存...";
                        var submitBtn = form.querySelector("button[type=submit], input[type=submit]");
                        if (submitBtn) {
                            setTimeout(function() { submitBtn.click(); }, 800);
                        } else {
                            importStatus.textContent = "配置已填入表单，请手动点击保存设置按钮。";
                        }
                    } catch(err) {
                        importStatus.className = "cat-data-status show error";
                        importStatus.textContent = "解析配置文件失败：" + err.message;
                    }
                };
                reader.readAsText(fileInput.files[0]);
            });
        }
    });
})();
