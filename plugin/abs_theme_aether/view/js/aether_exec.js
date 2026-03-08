/**
 * 统一执行器 - 确保回调函数在整页加载和HTMX加载时都能执行
 * @param {Function} callback - 需要执行的函数
 */
function AetherExec(callback) {
    if (typeof callback !== 'function') {return;}
    
    const executeCallback = function() {
        try {
            callback();
        } catch (error) {
            console.error('AetherExec callback error:', error);
        }
    };
    
    // 情况1：整页加载 - 监听DOMContentLoaded
    document.addEventListener('DOMContentLoaded', executeCallback);
    
    // 情况2：HTMX加载 - 监听afterSettle
    if (typeof htmx !== 'undefined') {
        document.addEventListener('htmx:afterSettle', executeCallback);
    }
    
    // 情况3：如果文档已经加载完成，立即执行一次
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(executeCallback, 0);
    }
}

window.AetherExec = AetherExec;