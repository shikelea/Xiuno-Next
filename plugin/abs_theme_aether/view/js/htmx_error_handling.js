// HTMX全局错误处理 - 简单直接面向过程
(function() {
    // 监听HTMX响应错误（4xx, 5xx状态码）
    document.addEventListener('htmx:responseError', function(event) {
        console.error('HTMX请求失败:', event.detail);
        
        // 获取错误信息
        let errorMsg = '请求失败';
        if (event.detail.xhr && event.detail.xhr.status) {
            const status = event.detail.xhr.status;
            if (status >= 500) {errorMsg = '服务器内部错误';}
            else if (status === 404) {errorMsg = '请求的资源不存在';}
            else if (status === 403) {errorMsg = '没有权限执行此操作';}
            else if (status === 401) {errorMsg = '请先登录';}
            else if (status === 429) {errorMsg = '操作过于频繁，请稍后再试';}
            else if (status === 0) {errorMsg = '网络连接失败，请检查网络';}
            else if (status === 400) {return;} // 400错误由服务器通过HX-Trigger处理，此处不显示默认错误
            else {errorMsg = `请求失败 (${status})`;}
        }
        
        // 直接触发现有的Snackbar系统
        htmx.trigger(document.body, 'showSnackBar', {
            type: 'danger',
            title: '错误',
            content: errorMsg,
            delay: 5000
        });
    });
    
    // 监听HTMX超时
    document.addEventListener('htmx:timeout', function(event) {
        htmx.trigger(document.body, 'showSnackBar', {
            type: 'warning',
            title: '超时',
            content: '请求超时，请重试',
            delay: 5000
        });
    });
    
    // 监听网络离线（简单检测）
    window.addEventListener('offline', function() {
        htmx.trigger(document.body, 'showSnackBar', {
            type: 'warning',
            title: '网络断开',
            content: '网络连接已断开，请检查网络',
            delay: 3000
        });
    });
    
    // 可选：全局请求指示器
    let activeRequests = 0;
    document.addEventListener('htmx:beforeRequest', function() {
        activeRequests++;
        if (activeRequests === 1) {
            // 显示全局加载指示器（如果需要）
            // document.body.classList.add('htmx-global-loading');
        }
    });
    
    document.addEventListener('htmx:afterRequest', function() {
        activeRequests--;
        if (activeRequests === 0) {
            // 隐藏全局加载指示器
            // document.body.classList.remove('htmx-global-loading');
        }
    });
})();

// 如果需要全局加载指示器的CSS，可以这样简单添加：
// <style>.htmx-global-loading::after{content:'';position:fixed;top:10px;right:10px;width:20px;height:20px;border:2px solid #ccc;border-top-color:#007bff;border-radius:50%;animation:spin 1s linear infinite;}@keyframes spin{to{transform:rotate(360deg);}}</style>