
(function() {
    window.addEventListener("load", function() {
        var selectorEl = document.querySelector(".cat-group-nav-github-selector");
        if (selectorEl) {
            selectorEl.style.display = "";
            var navPane = document.getElementById("cat-nav");
            if (navPane) navPane.appendChild(selectorEl);
        }

        var fetchBtn = document.getElementById("cat-github-fetch-btn");
        var fetchStatus = document.getElementById("cat-github-fetch-status");
        var reposContainer = document.getElementById("cat-github-repos-container");
        var selectedReposInput = document.querySelector("textarea[name=githubSelectedRepos]");
        var toggleBtn = document.getElementById("cat-github-toggle-btn");

        function getSelectedRepos() {
            if (!selectedReposInput || !selectedReposInput.value.trim()) return [];
            try { return JSON.parse(selectedReposInput.value); } catch(e) { return []; }
        }

        function updateSelectedRepos() {
            if (!reposContainer) return;
            var checked = reposContainer.querySelectorAll("input[data-repo-name]:checked");
            var selected = [];
            checked.forEach(function(cb) { selected.push(cb.getAttribute("data-repo-name")); });
            if (selectedReposInput) selectedReposInput.value = selected.length > 0 ? JSON.stringify(selected) : "";
        }

        function renderRepos(repos) {
            if (!reposContainer) return;
            var selected = getSelectedRepos();
            if (repos.length === 0) {
                reposContainer.innerHTML = '<div style="padding:20px;text-align:center;color:#999;">暂无公开项目</div>';
                return;
            }
            var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;">';
            repos.forEach(function(repo) {
                var isChecked = selected.length === 0 || selected.indexOf(repo.name) !== -1;
                html += '<label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#fafafa;border:1px solid #eee;border-radius:6px;cursor:pointer;transition:all .2s;font-size:13px;" onmouseover="this.style.borderColor=\'#467B96\'" onmouseout="this.style.borderColor=\'#eee\'">';
                html += '<input type="checkbox" data-repo-name="' + repo.name + '" ' + (isChecked ? "checked" : "") + ' style="margin-top:2px;accent-color:#467B96;">';
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="font-weight:600;color:#333;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + repo.name + '</div>';
                html += '<div style="color:#999;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (repo.description || '暂无描述') + '</div>';
                html += '<div style="margin-top:4px;display:flex;gap:12px;color:#aaa;font-size:11px;">';
                if (repo.language) {
                    html += '<span><i class="fa fa-circle" style="font-size:8px;color:#467B96;"></i> ' + repo.language + '</span>';
                }
                html += '<span><i class="fa fa-star"></i> ' + repo.stars + '</span>';
                html += '</div></div></label>';
            });
            html += '</div>';
            reposContainer.innerHTML = html;

            reposContainer.querySelectorAll("input[data-repo-name]").forEach(function(cb) {
                cb.addEventListener("change", updateSelectedRepos);
            });

            if (toggleBtn) toggleBtn.style.display = "";
        }

        if (toggleBtn) {
            toggleBtn.addEventListener("click", function() {
                if (!reposContainer) return;
                reposContainer.querySelectorAll("input[data-repo-name]").forEach(function(cb) {
                    cb.checked = !cb.checked;
                });
                updateSelectedRepos();
            });
        }

        if (fetchBtn) {
            fetchBtn.addEventListener("click", function() {
                var usernameInput = document.querySelector("input[name=githubUsername]");
                var username = usernameInput ? usernameInput.value.trim() : "";
                if (!username) {
                    fetchStatus.textContent = "请先填写 GitHub 用户名并保存设置";
                    fetchStatus.style.color = "#e74c3c";
                    return;
                }
                fetchStatus.textContent = "正在获取项目列表...";
                fetchStatus.style.color = "#999";
                fetchBtn.disabled = true;

                var page = 1;
                var allRepos = [];

                function fetchPage() {
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", "https://api.github.com/users/" + encodeURIComponent(username) + "/repos?sort=stars&per_page=100&page=" + page, true);
                    xhr.setRequestHeader("Accept", "application/vnd.github.v3+json");
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (!Array.isArray(data) || data.length === 0) {
                                    renderRepos(allRepos);
                                    fetchStatus.textContent = "共获取到 " + allRepos.length + " 个项目";
                                    fetchStatus.style.color = "#389e0d";
                                    fetchBtn.disabled = false;
                                    return;
                                }
                                data.forEach(function(r) {
                                    allRepos.push({
                                        name: r.name || "",
                                        description: r.description || "",
                                        language: r.language || "",
                                        stars: r.stargazers_count || 0,
                                        forks: r.forks_count || 0
                                    });
                                });
                                if (data.length < 100) {
                                    renderRepos(allRepos);
                                    fetchStatus.textContent = "共获取到 " + allRepos.length + " 个项目";
                                    fetchStatus.style.color = "#389e0d";
                                    fetchBtn.disabled = false;
                                } else {
                                    page++;
                                    fetchPage();
                                }
                            } catch(e) {
                                fetchStatus.textContent = "解析数据失败";
                                fetchStatus.style.color = "#e74c3c";
                                fetchBtn.disabled = false;
                            }
                        } else if (xhr.status === 403) {
                            fetchStatus.textContent = "GitHub API 请求频率受限，请稍后再试";
                            fetchStatus.style.color = "#e74c3c";
                            fetchBtn.disabled = false;
                        } else if (xhr.status === 404) {
                            fetchStatus.textContent = "用户名不存在，请检查后重试";
                            fetchStatus.style.color = "#e74c3c";
                            fetchBtn.disabled = false;
                        } else {
                            fetchStatus.textContent = "请求失败 (HTTP " + xhr.status + ")";
                            fetchStatus.style.color = "#e74c3c";
                            fetchBtn.disabled = false;
                        }
                    };
                    xhr.onerror = function() {
                        fetchStatus.textContent = "网络请求失败，请检查网络连接";
                        fetchStatus.style.color = "#e74c3c";
                        fetchBtn.disabled = false;
                    };
                    xhr.send();
                }

                fetchPage();
            });
        }
    });
})();

