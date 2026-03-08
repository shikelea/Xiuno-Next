/**
 * # Aether 主题插件兼容
 * @type {Object}
 * @namespace Aether
 */
var AetherPlugin = window.AetherPlugin || {};

AetherPlugin.haya_post_like = {

    /**
     * 处理未登录用户点击
     * @param {number} tid - 帖子ID
     * @param {HTMLElement} button - 被点击的按钮
     */
    handleLikeLogin: function(tid, button) {
        // 暂时禁用按钮
        const originalContent = button.innerHTML;
        button.disabled = true;
        
        // 显示提示弹窗
        showModal(
            `<p>登录后才能点赞。</p>
            <div class="text-end mt-3">
                <button class="btn btn-outline-secondary me-2" data-target="htmx-modal"
                onclick="toggleModal(event)">
                    取消
                </button>
                <button class="btn btn-primary"
                        onclick="
                            closeModal(document.querySelector('#htmx-modal'));
                            htmx.ajax('GET', 'user-login.htm?back=thread-${tid}.htm&htmx=1', 
                                      {target: '#S6-Body', swap: 'innerHTML'});
                        ">
                    去登录
                </button>
            </div>`,
            '登录提示',
            '',
            true,
            'warning'
        );
        
        // 1秒后恢复按钮
        setTimeout(function() {
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 1000);
    },
    
};

AetherPlugin.haya_favorite = {

    /**
     * 处理未登录用户点击
     * @param {number} tid - 帖子ID
     * @param {HTMLElement} button - 被点击的按钮
     */
    handleLoginRequired: function(tid, button) {
        // 暂时禁用按钮
        const originalContent = button.innerHTML;
        button.disabled = true;
        
        // 显示提示弹窗
        showModal(
            `<p>登录后才能收藏。</p>
            <div class="text-end mt-3">
                <button class="btn btn-outline-secondary me-2" data-target="htmx-modal"
                onclick="toggleModal(event)">
                    取消
                </button>
                <button class="btn btn-primary"
                        onclick="
                            closeModal(document.querySelector('#htmx-modal'));
                            htmx.ajax('GET', 'user-login.htm?back=thread-${tid}.htm&htmx=1', 
                                      {target: '#S6-Body', swap: 'innerHTML'});
                        ">
                    去登录
                </button>
            </div>`,
            '登录提示',
            '',
            true,
            'warning'
        );
        
        // 1秒后恢复按钮
        setTimeout(function() {
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 1000);
    },
    
};