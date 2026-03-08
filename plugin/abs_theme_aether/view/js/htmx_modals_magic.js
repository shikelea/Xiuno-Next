// HTMX弹窗动作

document.body.addEventListener("showModalSimple", function (evt) {
    showModal(evt.detail.content, evt.detail.title, evt.detail.subtitle, false, evt.detail.type ?? '');
});
document.body.addEventListener("closeModal", function (evt) {
    document.querySelectorAll('.htmx-modal').forEach(function (m) { closeModal(m) });
});
document.body.addEventListener("showToast", function (evt) {
    showToast(evt.detail);
});
document.body.addEventListener("showSnackBar", function (evt) {
    showSnackBar(evt.detail);
});
document.body.addEventListener("showToastMulti", function (evt) {
    evt.detail.value.forEach(function (notice) {
        showToast(notice);
    });
});

// 显示模态框
function showModal(content, title, subtitle = '', allow_html_unsafe = false, type = '') {
    // 处理参数
    const the_content = content ?? null;
    const the_title = (typeof title === 'string' && title.length !== 0) ? title : (typeof lang === 'object' && typeof lang.tips_title === 'string' ? lang.tips_title : 'Modal');
    const the_subtitle = (typeof subtitle === 'string' && subtitle.length !== 0) ? subtitle : '';
    const the_allow_html = allow_html_unsafe ?? false;
    const the_type = type ?? '';

    // 播放音效【开始】
    if (the_type && SOUND_DEFAULTS.enabled) {
        const soundCategory = SOUND_DEFAULTS.typeToSound[the_type];
        if (soundCategory && SOUND_DEFAULTS.sounds[soundCategory]) {
            const soundFile = SOUND_DEFAULTS.sounds[soundCategory];
            const audio = new Audio(SOUND_DEFAULTS.basePath + soundFile);
            audio.volume = SOUND_DEFAULTS.volume;
            audio.play().catch(e => console.warn("Modal audio play failed:", e));
        }
    }
    // 播放音效【结束】

    openModal(document.querySelector('#htmx-modal'));

    document.querySelector('#htmx-modal .modal-title').textContent = the_title;
    document.querySelector('#htmx-modal .modal-subtitle').textContent = the_subtitle;

    if (content !== null) {
        if (the_allow_html) {
            document.querySelector('#htmx-modal .modal-body').innerHTML = the_content;
        } else {
            document.querySelector('#htmx-modal .modal-body').textContent = the_content;
        }
    }

    applyModalType(the_type);
}

function applyModalType(type) {
    const modal = document.querySelector('#htmx-modal');
    if (!modal) return;

    const typeClasses = ['modal-success', 'modal-info', 'modal-warning', 'modal-danger', 'modal-primary', 'modal-secondary'];
    modal.classList.remove(...typeClasses);

    if (type && type !== '') {
        modal.classList.add(`modal-${type}`);
    }
}
