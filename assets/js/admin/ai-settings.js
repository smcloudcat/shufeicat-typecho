/**
 * AI 设置页面交互
 * 从 functions.php themeConfig() heredoc 抽取
 * 依赖: window.SHUFEI_ADMIN {freeApiUrl, freeApiKey, freeApiModel, aiAjaxUrl}
 * 支持：提供商选择（Chat Completions / Responses / DeepSeek / OpenAI / 免费）、获取模型列表、AI总结设置
 */
(function(){
    function initAiSettings(){
        var modeRadios=document.querySelectorAll("input[name=aiUnifiedApi]");
        // 提供商选择（新）
        var unifiedProviderRadios=document.querySelectorAll("input[name=aiUnifiedProvider]");
        var writerProviderRadios=document.querySelectorAll("input[name=aiWriterProvider]");
        var modProviderRadios=document.querySelectorAll("input[name=aiModerationProvider]");
        // 旧版接口类型
        var unifiedTypeRadios=document.querySelectorAll("input[name=aiUnifiedApiType]");
        var writerTypeRadios=document.querySelectorAll("input[name=aiWriterApiType]");
        var modTypeRadios=document.querySelectorAll("input[name=aiModerationApiType]");
        var modEnabledRadios=document.querySelectorAll("input[name=aiModerationEnabled]");
        var summaryEnabledRadios=document.querySelectorAll("input[name=aiSummaryEnabled]");
        var unifiedFields=document.querySelectorAll(".ai-unified-field");
        var writerFields=document.querySelectorAll(".ai-separate-writer-field");
        var modFields=document.querySelectorAll(".ai-separate-moderation-field");
        var customUnifiedFields=document.querySelectorAll(".ai-custom-unified-field");
        var customWriterFields=document.querySelectorAll(".ai-custom-writer-field");
        var customModFields=document.querySelectorAll(".ai-custom-moderation-field");
        var modAdvancedFields=document.querySelectorAll(".ai-moderation-advanced");
        var summaryFields=document.querySelectorAll(".ai-summary-field");
        var oldFields=document.querySelectorAll(".ai-old-field");
        var btnUnified=document.getElementById("cat-test-unified-api");
        var btnWriter=document.getElementById("cat-test-writer-api");
        var btnMod=document.getElementById("cat-test-moderation-api");
        var btnModelsUnified=document.getElementById("cat-models-unified");
        var btnModelsWriter=document.getElementById("cat-models-writer");
        var btnModelsMod=document.getElementById("cat-models-moderation");

        function getRadio(name,def){
            var r=document.querySelector("input[name="+name+"]:checked");
            return r?r.value:def;
        }
        function getMode(){return getRadio("aiUnifiedApi","on");}
        function getUnifiedProvider(){return getRadio("aiUnifiedProvider","custom_chat");}
        function getWriterProvider(){return getRadio("aiWriterProvider","custom_chat");}
        function getModProvider(){return getRadio("aiModerationProvider","custom_chat");}
        function getUnifiedType(){return getRadio("aiUnifiedApiType","custom");}
        function getWriterType(){return getRadio("aiWriterApiType","custom");}
        function getModType(){return getRadio("aiModerationApiType","custom");}
        function getModEnabled(){return getRadio("aiModerationEnabled","off");}
        function getSummaryEnabled(){return getRadio("aiSummaryEnabled","off");}

        // 提供商对应的自定义字段显示判断
        function providerNeedsUrl(provider){
            // 仅 custom_chat / custom_responses 需要填写地址
            return provider==="custom_chat"||provider==="custom_responses";
        }
        function providerNeedsKey(provider){
            // 所有（除 free 外）都需要密钥；free 不需要
            return provider!=="free";
        }
        function providerNeedsModel(provider){
            // 所有都需要模型；free 内置模型
            return provider!=="free";
        }

        function updateFields(){
            var mode=getMode();
            // 接口模式：统一/分别
            unifiedFields.forEach(function(el){el.style.display=mode==="on"?"":"none";});
            writerFields.forEach(function(el){el.style.display=mode==="off"?"":"none";});
            modFields.forEach(function(el){el.style.display=mode==="off"?"":"none";});
            // 隐藏旧版字段
            oldFields.forEach(function(el){el.style.display="none";});

            // 提供商对应的字段显隐
            if(mode==="on"){
                var up=getUnifiedProvider();
                // 显示/隐藏统一接口的地址/密钥/模型输入
                document.querySelectorAll(".ai-custom-unified-field").forEach(function(el){
                    var name=el.querySelector("input,textarea");
                    if(!name)return;
                    var fieldName=name.getAttribute("name");
                    var show=false;
                    if(fieldName==="aiModerationApiUrl"){show=providerNeedsUrl(up);}
                    else if(fieldName==="aiModerationApiKey"){show=providerNeedsKey(up);}
                    else if(fieldName==="aiModerationModel"){show=providerNeedsModel(up);}
                    el.style.display=show?"":"none";
                });
            }else{
                document.querySelectorAll(".ai-custom-unified-field").forEach(function(el){el.style.display="none";});
            }
            if(mode==="off"){
                var wp=getWriterProvider();
                document.querySelectorAll(".ai-custom-writer-field").forEach(function(el){
                    var name=el.querySelector("input,textarea");
                    if(!name)return;
                    var fieldName=name.getAttribute("name");
                    var show=false;
                    if(fieldName==="aiWriterApiUrl"){show=providerNeedsUrl(wp);}
                    else if(fieldName==="aiWriterApiKey"){show=providerNeedsKey(wp);}
                    else if(fieldName==="aiWriterModel"){show=providerNeedsModel(wp);}
                    el.style.display=show?"":"none";
                });
                var mp=getModProvider();
                document.querySelectorAll(".ai-custom-moderation-field").forEach(function(el){
                    var name=el.querySelector("input,textarea");
                    if(!name)return;
                    var fieldName=name.getAttribute("name");
                    var show=false;
                    if(fieldName==="aiModerationSepApiUrl"){show=providerNeedsUrl(mp);}
                    else if(fieldName==="aiModerationSepApiKey"){show=providerNeedsKey(mp);}
                    else if(fieldName==="aiModerationSepModel"){show=providerNeedsModel(mp);}
                    el.style.display=show?"":"none";
                });
            }else{
                document.querySelectorAll(".ai-custom-writer-field").forEach(function(el){el.style.display="none";});
                document.querySelectorAll(".ai-custom-moderation-field").forEach(function(el){el.style.display="none";});
            }

            // AI 审核高级设置：仅在 AI 评论审核开启时显示
            var modOn=getModEnabled()==="on";
            modAdvancedFields.forEach(function(el){el.style.display=modOn?"":"none";});

            // AI 总结设置：仅在文章AI总结开启时显示（缓存时间/提示词）
            var sumOn=getSummaryEnabled()==="on";
            summaryFields.forEach(function(el){
                var name=el.querySelector("input,textarea,select");
                if(name&&name.getAttribute("name")==="aiSummaryEnabled")return;
                el.style.display=sumOn?"":"none";
            });

            // 测试按钮显示
            if(btnUnified)btnUnified.style.display=mode==="on"?"inline-block":"none";
            if(btnWriter)btnWriter.style.display=mode==="off"?"inline-block":"none";
            if(btnMod)btnMod.style.display=mode==="off"?"inline-block":"none";
            // 获取模型列表按钮显示
            if(btnModelsUnified)btnModelsUnified.style.display=mode==="on"?"inline-block":"none";
            if(btnModelsWriter)btnModelsWriter.style.display=mode==="off"?"inline-block":"none";
            if(btnModelsMod)btnModelsMod.style.display=mode==="off"?"inline-block":"none";
        }
        [modeRadios,unifiedTypeRadios,writerTypeRadios,modTypeRadios,modEnabledRadios,summaryEnabledRadios,
         unifiedProviderRadios,writerProviderRadios,modProviderRadios].forEach(function(group){
            if(group&&group.length)group.forEach(function(r){r.addEventListener("change",updateFields);});
        });
        updateFields();

        var statusEl=document.getElementById("cat-api-test-status");
        function showStatus(msg,type){
            if(!statusEl)return;
            statusEl.className="cat-data-status show "+(type||"info");
            statusEl.innerHTML=msg;
        }
        var modelsStatusEl=document.getElementById("cat-models-status");
        function showModelsStatus(msg,type){
            if(!modelsStatusEl)return;
            modelsStatusEl.className="cat-data-status show "+(type||"info");
            modelsStatusEl.innerHTML=msg;
        }

        /**
         * 收集指定前缀的提供商配置
         */
        function collectConfig(prefix){
            // prefix: unified => aiModeration(ApiUrl/ApiKey/Model); writer => aiWriter*; moderation => aiModerationSep*
            var urlInput,keyInput,modelInput,provider;
            if(prefix==="unified"){
                provider=getUnifiedProvider();
                urlInput=getFieldInputs("aiModerationApiUrl");
                keyInput=getFieldInputs("aiModerationApiKey");
                modelInput=getFieldInputs("aiModerationModel");
            }else if(prefix==="writer"){
                provider=getWriterProvider();
                urlInput=getFieldInputs("aiWriterApiUrl");
                keyInput=getFieldInputs("aiWriterApiKey");
                modelInput=getFieldInputs("aiWriterModel");
            }else{
                provider=getModProvider();
                urlInput=getFieldInputs("aiModerationSepApiUrl");
                keyInput=getFieldInputs("aiModerationSepApiKey");
                modelInput=getFieldInputs("aiModerationSepModel");
            }
            return {
                provider:provider,
                url:urlInput?urlInput.value.trim():"",
                key:keyInput?keyInput.value.trim():"",
                model:modelInput?modelInput.value.trim():""
            };
        }

        function buildConfigParam(cfg){
            var p="&provider="+encodeURIComponent(cfg.provider)
                +"&api_url="+encodeURIComponent(cfg.url)
                +"&api_key="+encodeURIComponent(cfg.key)
                +"&model="+encodeURIComponent(cfg.model||"gpt-3.5-turbo");
            return p;
        }

        /**
         * 执行测试
         */
        function doTest(btn,prefix,label){
            var cfg=collectConfig(prefix);
            if(cfg.provider==="free"){
                cfg.url=window.SHUFEI_ADMIN.freeApiUrl;
                cfg.key=window.SHUFEI_ADMIN.freeApiKey;
                cfg.model=window.SHUFEI_ADMIN.freeApiModel;
            }else{
                if(!cfg.key){showStatus("请先填写 API 密钥","error");return;}
                if(cfg.provider==="custom_chat"||cfg.provider==="custom_responses"){
                    if(!cfg.url){showStatus("请先填写 API 地址","error");return;}
                }
            }
            btn.disabled=true;
            btn.textContent="测试中...";
            showStatus("正在测试 "+label+"...","info");
            fetch(window.SHUFEI_ADMIN.aiAjaxUrl,{
                method:"POST",
                credentials:"same-origin",
                headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body:"action=test_api"+buildConfigParam(cfg)
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

        /**
         * 获取模型列表
         */
        function doFetchModels(btn,prefix,label){
            var cfg=collectConfig(prefix);
            if(cfg.provider==="free"){
                cfg.url=window.SHUFEI_ADMIN.freeApiUrl;
                cfg.key=window.SHUFEI_ADMIN.freeApiKey;
            }else{
                if(!cfg.key){showModelsStatus("请先填写 API 密钥","error");return;}
                if(cfg.provider==="custom_chat"||cfg.provider==="custom_responses"){
                    if(!cfg.url){showModelsStatus("请先填写 API 地址","error");return;}
                }
            }
            btn.disabled=true;
            btn.textContent="获取中...";
            showModelsStatus("正在获取 "+label+" 模型列表...","info");
            fetch(window.SHUFEI_ADMIN.aiAjaxUrl,{
                method:"POST",
                credentials:"same-origin",
                headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body:"action=fetch_models"+buildConfigParam(cfg)
            })
            .then(function(r){return r.json();})
            .then(function(data){
                if(data.success&&data.models&&data.models.length){
                    var modelInput=getFieldInputs(prefix==="unified"?"aiModerationModel":(prefix==="writer"?"aiWriterModel":"aiModerationSepModel"));
                    var html="<b>"+label+" 模型列表（"+data.models.length+"个）：</b><br>";
                    html+="<select style='max-width:100%;margin-top:6px;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;' onchange='var t=this.options[this.selectedIndex].value;";
                    if(modelInput){html+="document.getElementsByName("+JSON.stringify(modelInput.getAttribute("name"))+")[0].value=t;";}
                    html+="'>";
                    html+="<option value=''>— 选择模型填入 —</option>";
                    data.models.forEach(function(m){
                        html+="<option value='"+escAttr(m)+"'>"+esc(m)+"</option>";
                    });
                    html+="</select>";
                    showModelsStatus(html,"success");
                }else{
                    showModelsStatus(data.message||"未获取到模型","error");
                }
                btn.disabled=false;
                btn.textContent="获取"+label+"模型";
            })
            .catch(function(e){
                showModelsStatus("获取失败："+e,"error");
                btn.disabled=false;
                btn.textContent="获取"+label+"模型";
            });
        }

        function esc(s){
            return String(s==null?"":s).replace(/[&<>"']/g,function(c){
                return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
            });
        }
        function escAttr(s){
            return esc(s);
        }

        if(btnUnified)btnUnified.addEventListener("click",function(){doTest(btnUnified,"unified","统一接口");});
        if(btnWriter)btnWriter.addEventListener("click",function(){doTest(btnWriter,"writer","写作接口");});
        if(btnMod)btnMod.addEventListener("click",function(){doTest(btnMod,"moderation","审核接口");});
        if(btnModelsUnified)btnModelsUnified.addEventListener("click",function(){doFetchModels(btnModelsUnified,"unified","统一接口");});
        if(btnModelsWriter)btnModelsWriter.addEventListener("click",function(){doFetchModels(btnModelsWriter,"writer","写作接口");});
        if(btnModelsMod)btnModelsMod.addEventListener("click",function(){doFetchModels(btnModelsMod,"moderation","审核接口");});

        function getFieldInputs(fieldName){
            var els=document.getElementsByName(fieldName);
            return els.length>0?els[0]:null;
        }
    }
    if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",initAiSettings);}else{initAiSettings();}
})();
