/**
 * Ajax加载功能
 * 用于文章列表的异步加载
 */

(function() {
    // 检查是否开启了ajax加载
    var ajaxLoadEnabled = window.ajaxLoadEnabled || false;
    var ajaxLoadStyle = window.ajaxLoadStyle || 'default';
    var themeUrl = window.themeUrl || '';
    
    if (!ajaxLoadEnabled) return;
    
    var currentPage = 1;
    var isLoading = false;
    var hasMore = true;
    var loadMoreBtn = null;
    var loadingIndicator = null;
    var postList = document.getElementById('ajax-post-list');
    var pageNav = document.getElementById('ajax-page-nav');
    
    // 创建加载更多按钮
    function createLoadMoreBtn() {
        var btn = document.createElement('div');
        btn.id = 'ajax-load-more';
        btn.className = 'ajax-load-more-btn';
        btn.innerHTML = '<i class="fa fa-angle-double-down"></i> 加载更多文章';
        btn.style.cssText = 'text-align: center; padding: 20px; cursor: pointer; color: #666; transition: all 0.3s ease;';
        btn.onmouseover = function() { this.style.color = '#337ab7'; };
        btn.onmouseout = function() { this.style.color = '#666'; };
        return btn;
    }
    
    // 创建加载指示器
    function createLoadingIndicator() {
        var indicator = document.createElement('div');
        indicator.id = 'ajax-loading';
        indicator.className = 'ajax-loading-indicator';
        indicator.style.cssText = 'text-align: center; padding: 20px; display: none;';
        
        if (ajaxLoadStyle === 'minimal') {
            indicator.innerHTML = '<span style="color: #999;">加载中...</span>';
        } else if (ajaxLoadStyle === 'spinner') {
            indicator.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size: 24px; color: #337ab7;"></i>';
        } else {
            indicator.innerHTML = '<div class="ajax-loader-default"><div class="ajax-loader-bar"></div><div class="ajax-loader-bar"></div><div class="ajax-loader-bar"></div></div>';
        }
        
        return indicator;
    }
    
    // 初始化
    function initAjaxLoad() {
        if (!postList || !pageNav) return;
        
        // 隐藏原有分页
        pageNav.style.display = 'none';
        
        // 创建加载更多按钮
        loadMoreBtn = createLoadMoreBtn();
        loadMoreBtn.addEventListener('click', loadMore);
        
        // 创建加载指示器
        loadingIndicator = createLoadingIndicator();
        
        // 添加到页面
        postList.parentNode.insertBefore(loadMoreBtn, postList.nextSibling);
        postList.parentNode.insertBefore(loadingIndicator, loadMoreBtn.nextSibling);
        
        // 检查是否还有更多内容
        var nextLink = pageNav.querySelector('.next');
        hasMore = !!nextLink;
        
        if (!hasMore) {
            loadMoreBtn.style.display = 'none';
        }
    }
    
    // 加载更多内容
    function loadMore() {
        if (isLoading || !hasMore) return;
        
        isLoading = true;
        loadMoreBtn.style.display = 'none';
        loadingIndicator.style.display = 'block';
        
        currentPage++;
        
        var url = themeUrl;
        var params = {
            page: currentPage
        };
        
        // 检查是否是分类页
        var categoryLink = document.querySelector('.category-widget a.layui-this');
        if (categoryLink && categoryLink.href) {
            var match = categoryLink.href.match(/\/category\/([^\/]+)/);
            if (match) {
                params.category = match[1];
            }
        }
        
        fetch(url + '?' + new URLSearchParams(params))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.html) {
                    // 插入新内容
                    var temp = document.createElement('div');
                    temp.innerHTML = data.html;
                    while (temp.firstChild) {
                        postList.appendChild(temp.firstChild);
                    }
                    
                    // 更新分页导航
                    if (data.pageNav) {
                        pageNav.innerHTML = data.pageNav;
                        var nextLink = pageNav.querySelector('.next');
                        hasMore = !!nextLink;
                    }
                    
                    // 更新URL而不刷新页面
                    var newUrl = window.location.pathname + '?page=' + currentPage;
                    window.history.pushState({page: currentPage}, '', newUrl);
                }
                
                isLoading = false;
                loadingIndicator.style.display = 'none';
                
                if (hasMore) {
                    loadMoreBtn.style.display = 'block';
                } else {
                    loadMoreBtn.innerHTML = '<i class="fa fa-check"></i> 没有更多内容了';
                    loadMoreBtn.style.cursor = 'default';
                }
            })
            .catch(function(error) {
                console.error('Ajax加载失败:', error);
                isLoading = false;
                loadingIndicator.style.display = 'none';
                loadMoreBtn.style.display = 'block';
                loadMoreBtn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 加载失败，点击重试';
            });
    }
    
    // 初始化
    initAjaxLoad();
})();