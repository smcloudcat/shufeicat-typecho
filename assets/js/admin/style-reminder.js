(function(){
    var box = document.getElementById("shufei-style-box");
    if (!box) return;
    var mask = document.getElementById("shufei-style-mask");
    var ajaxUrl = box.getAttribute("data-ajax");

    function close(){
        if (mask) mask.style.display = "none";
    }

    function setRadio(name, value){
        var radios = document.getElementsByName(name);
        for (var i = 0; i < radios.length; i++){
            radios[i].checked = (radios[i].value === value);
        }
    }

    function setText(name, value){
        var el = document.getElementsByName(name)[0];
        if (el) el.value = value;
    }

    // 应用成功后，同步表单字段值，避免之后点击"保存设置"把推荐设置覆盖回旧值
    function syncForm(d){
        if (!d || !d.settings) return;
        var s = d.settings;
        setRadio("pjaxLoad", s.pjaxLoad);
        setRadio("bgGradientEnabled", s.bgGradientEnabled);
        setRadio("weatherEnabled", s.weatherEnabled);
        setText("bgGradient", s.bgGradient);
        setText("bgGradientDark", s.bgGradientDark);
        setText("cardOpacity", s.cardOpacity);
    }

    function post(action, btn){
        if (!ajaxUrl) { close(); return; }
        var fd = new FormData();
        fd.append("action", action);
        if (btn) btn.disabled = true;
        fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
            .then(function(r){ return r.json().catch(function(){ return null; }); })
            .then(function(d){
                if (d && d.success) { if (action === "apply") syncForm(d); close(); }
                else { window.alert((d && d.message) || "操作失败，请刷新页面后重试"); }
            })
            .catch(function(e){ window.alert("操作失败: " + e.message); })
            .finally(function(){ if (btn) btn.disabled = false; });
    }

    box.addEventListener("click", function(e){
        var t = e.target;
        var btn = t && t.closest ? t.closest("button[data-action]") : null;
        if (!btn || !box.contains(btn)) return;
        var action = btn.getAttribute("data-action");
        if (action === "later") { close(); return; }
        if (action === "apply" || action === "dismiss") { post(action, btn); }
    });
})();
