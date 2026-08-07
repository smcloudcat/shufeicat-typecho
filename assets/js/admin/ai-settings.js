/**
 * AI 设置页面交互
 * 从 functions.php themeConfig() heredoc 抽取
 * 依赖: window.SHUFEI_ADMIN {freeApiUrl, freeApiKey, freeApiModel, aiAjaxUrl}
 */
(function(){
    function initAiSettings(){
        var modeRadios=document.querySelectorAll("input[name=aiUnifiedApi]");
        var unifiedTypeRadios=document.querySelectorAll("input[name=aiUnifiedApiType]");
        var writerTypeRadios=document.querySelectorAll("input[name=aiWriterApiType]");
        var modTypeRadios=document.querySelectorAll("input[name=aiModerationApiType]");
        var modEnabledRadios=document.querySelectorAll("input[name=aiModerationEnabled]");
        var unifiedFields=document.querySelectorAll(".ai-unified-field");
        var writerFields=document.querySelectorAll(".ai-separate-writer-field");
        var modFields=document.querySelectorAll(".ai-separate-moderation-field");
        var customUnifiedFields=document.querySelectorAll(".ai-custom-unified-field");
        var customWriterFields=document.querySelectorAll(".ai-custom-writer-field");
        var customModFields=document.querySelectorAll(".ai-custom-moderation-field");
        var modAdvancedFields=document.querySelectorAll(".ai-moderation-advanced");
        var btnUnified=document.getElementById("cat-test-unified-api");
        var btnWriter=document.getElementById("cat-test-writer-api");
        var btnMod=document.getElementById("cat-test-moderation-api");

        function getRadio(name,def){
            var r=document.querySelector("input[name="+name+"]:checked");
            return r?r.value:def;
        }
        function getMode(){return getRadio("aiUnifiedApi","on");}
        function getUnifiedType(){return getRadio("aiUnifiedApiType","custom");}
        function getWriterType(){return getRadio("aiWriterApiType","custom");}
        function getModType(){return getRadio("aiModerationApiType","custom");}
        function getModEnabled(){return getRadio("aiModerationEnabled","off");}

        function updateFields(){
            var mode=getMode();
            // 接口模式：统一/分别
            unifiedFields.forEach(function(el){el.style.display=mode==="on"?"":"none";});
            writerFields.forEach(function(el){el.style.display=mode==="off"?"":"none";});
            modFields.forEach(function(el){el.style.display=mode==="off"?"":"none";});
            // 接口类型：免费模式下隐藏自定义字段
            var uType=getUnifiedType();
            var wType=getWriterType();
            var mType=getModType();
            if(mode==="on"){
                customUnifiedFields.forEach(function(el){el.style.display=uType==="custom"?"":"none";});
            }else{
                customUnifiedFields.forEach(function(el){el.style.display="none";});
            }
            if(mode==="off"){
                customWriterFields.forEach(function(el){el.style.display=wType==="custom"?"":"none";});
                customModFields.forEach(function(el){el.style.display=mType==="custom"?"":"none";});
            }else{
                customWriterFields.forEach(function(el){el.style.display="none";});
                customModFields.forEach(function(el){el.style.display="none";});
            }
            // AI 审核高级设置：仅在 AI 评论审核开启时显示
            var modOn=getModEnabled()==="on";
            modAdvancedFields.forEach(function(el){el.style.display=modOn?"":"none";});
            // 测试按钮显示
            if(btnUnified)btnUnified.style.display=mode==="on"?"inline-block":"none";
            if(btnWriter)btnWriter.style.display=mode==="off"?"inline-block":"none";
            if(btnMod)btnMod.style.display=mode==="off"?"inline-block":"none";
        }
        [modeRadios,unifiedTypeRadios,writerTypeRadios,modTypeRadios,modEnabledRadios].forEach(function(group){
            group.forEach(function(r){r.addEventListener("change",updateFields);});
        });
        updateFields();

        var statusEl=document.getElementById("cat-api-test-status");
        function showStatus(msg,type){
            if(!statusEl)return;
            statusEl.className="cat-data-status show "+(type||"info");
            statusEl.innerHTML=msg;
        }

        /**
         * 执行测试
         * @param apiType "free" 使用内置免费接口；"custom" 使用表单填写的地址密钥
         */
        function doTest(btn,apiType,urlInput,keyInput,modelInput,label){
            var url="",key="",model="gpt-3.5-turbo";
            if(apiType==="free"){
                url=window.SHUFEI_ADMIN.freeApiUrl;
                key=window.SHUFEI_ADMIN.freeApiKey;
                model=window.SHUFEI_ADMIN.freeApiModel;
            }else{
                if(!urlInput||!keyInput||!modelInput){showStatus("接口字段缺失","error");return;}
                url=urlInput.value.trim();
                key=keyInput.value.trim();
                model=modelInput.value.trim()||"gpt-3.5-turbo";
                if(!url||!key){showStatus("请先填写 API 地址和密钥","error");return;}
            }
            btn.disabled=true;
            btn.textContent="测试中...";
            showStatus("正在测试 "+label+"...","info");
            fetch(window.SHUFEI_ADMIN.aiAjaxUrl,{
                method:"POST",
                credentials:"same-origin",
                headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body:"action=test_api&api_type="+encodeURIComponent(apiType)
                    +"&api_url="+encodeURIComponent(url)
                    +"&api_key="+encodeURIComponent(key)
                    +"&model="+encodeURIComponent(model)
            })
            .then(function(r){return r.json();})
            .then(function(data){
                var type=data.success?"success":"error";
                showStatus("<b>"+label+"测试结果：</b><br>"+(data.message||"未知结果"),type);
                btn.disabled=false;
                btn.textContent="测试"+label;
            })
            .catch(function(e){
                showStatus("测试请求失败："+e,"error");
                btn.disabled=false;
                btn.textContent="测试"+label;
            });
        }

        if(btnUnified)btnUnified.addEventListener("click",function(){
            doTest(btnUnified,getUnifiedType(),getFieldInputs("aiModerationApiUrl"),getFieldInputs("aiModerationApiKey"),getFieldInputs("aiModerationModel"),"统一接口");
        });
        if(btnWriter)btnWriter.addEventListener("click",function(){
            doTest(btnWriter,getWriterType(),getFieldInputs("aiWriterApiUrl"),getFieldInputs("aiWriterApiKey"),getFieldInputs("aiWriterModel"),"写作接口");
        });
        if(btnMod)btnMod.addEventListener("click",function(){
            doTest(btnMod,getModType(),getFieldInputs("aiModerationSepApiUrl"),getFieldInputs("aiModerationSepApiKey"),getFieldInputs("aiModerationSepModel"),"审核接口");
        });

        function getFieldInputs(fieldName){
            var els=document.getElementsByName(fieldName);
            return els.length>0?els[0]:null;
        }
    }
    if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",initAiSettings);}else{initAiSettings();}
})();
