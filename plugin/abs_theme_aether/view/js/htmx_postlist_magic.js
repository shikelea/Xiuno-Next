// HTMX帖子页面动作

document.body.addEventListener("removePost", function (evt) {
    post = document.querySelector(`.post[data-pid="${evt.detail.pid}"]`);
    if (post) {
        post.remove();
    } else {
        console.warn(`Post with pid ${evt.detail.pid} not found. Nothing happened.`);
    }
});
document.body.addEventListener("updatePostCount", function (evt) {
    document.querySelector('.posts').textContent = evt.detail.count;
});