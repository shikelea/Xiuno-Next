// ====== 音效配置 ======
var SOUND_DEFAULTS = {
	enabled: true,
	volume: 0.33,                            // 音量范围：0.0 ~ 1.0
	basePath: './plugin/abs_theme_aether/view/sfx/', 
	sounds: {
		positive: 'positive.mp3',    
		neutral: 'neutral.mp3',
		negative: 'negative.mp3',
		very_negative: 'very_negative.mp3'
	},
	typeToSound: {
		success: 'positive',
		info: 'positive',
		warning: 'negative',
		danger: 'very_negative',
		primary: 'neutral',
		secondary: 'neutral'
	}
};

(function preloadSounds() {
	Object.values(SOUND_DEFAULTS.sounds).forEach(file => {
		const audio = new Audio(SOUND_DEFAULTS.basePath + file);
		audio.preload = 'auto';
	});
})();


// ====== 吐司框配置 ======
var TOAST_DEFAULTS = {
	position: 'top-right',
	dismissible: true,
	stackable: true,
	pauseDelayOnHover: true,
	sound: true,
	style: {
		toast: '',
		info: '',
		success: '',
		warning: '',
		error: '',
	}
};

var toastRunningCount = 1;

/*
Usage:
showToast({
	type: 'info', 提示类型（颜色）
	title: 'Notice!', 标题
	subtitle: '11 mins ago', 副标题
	content: 'Hello, world! This is a toast message.', 正文
	delay: 5000 显示时间
});
*/
function showToast(options) {
	let container = document.getElementById('toast-container');
	if (!container) {
		container = document.createElement('div');
		container.id = 'toast-container';
		container.className = `toast-container ${TOAST_DEFAULTS.position}`;
		document.body.appendChild(container);
	}

	const opts = Object.assign({}, TOAST_DEFAULTS, options);
	//const typeClass = opts.style[opts.type] || (opts.type === 'danger' ? 'bg-danger text-white' : `bg-${opts.type}`);
	const typeClass = opts.style[opts.type] || (opts.type === 'danger' ? 'text-danger ' : `text-${opts.type}`);
	const id = opts.id || `toast-${toastRunningCount++}`;

	let toastHtml = `
        <div id="${id}" class="toast ${opts.style.toast}" role="alert" aria-live="assertive" aria-atomic="true" data-delay="${opts.delay}" data-autohide="false">
            <div class="toast-header ${typeClass}">
                ${opts.img ? `<img src="${opts.img.src}" class="me-2 ${opts.img.class || ''}" alt="${opts.img.alt || 'Image'}">` : ''}
                <strong class="mr-auto">${opts.title}</strong>
                ${opts.subtitle ? `<small class="text-white-50">${opts.subtitle}</small>` : ''}
                ${opts.dismissible ? '<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close"><span aria-hidden="true">&times;</span></button>' : ''}
            </div>
            ${opts.content ? `<div class="toast-body">${opts.content}</div>` : ''}
        </div>
    `;

	const toastElement = new DOMParser().parseFromString(toastHtml, 'text/html').body.firstChild;
	container.appendChild(toastElement);

	const toastInstance = new bootstrap.Toast(toastElement, { delay: opts.delay });
	toastInstance.show();

	// 播放音效【开始】
	if (opts.sound !== false && SOUND_DEFAULTS.enabled) {
		const soundCategory = SOUND_DEFAULTS.typeToSound[opts.type];
		const soundFile = SOUND_DEFAULTS.sounds[soundCategory];

		if (soundFile) {
			const audio = new Audio(SOUND_DEFAULTS.basePath + soundFile);
			audio.volume = SOUND_DEFAULTS.volume;
			audio.play().catch(e => console.warn("Audio play failed:", e));
		}
	}
	//  播放音效【结束】

	// 非堆叠模式：移除旧 Toast
	if (!opts.stackable) {
		Array.from(container.children).slice(0, -1).forEach(child => child.remove());
	}

	// 监听隐藏事件，确保 DOM 被删除（无论是否有 pauseDelayOnHover）
	toastElement.addEventListener('hidden.bs.toast', () => {
		toastElement.remove();
	});

	// 情况1：pauseDelayOnHover === false（默认行为，delay 后自动隐藏）
	if (!opts.pauseDelayOnHover && opts.delay) {
		setTimeout(() => {
			toastInstance.hide(); // 直接隐藏（hidden.bs.toast 会触发删除）
		}, opts.delay);
	}
	// 情况2：pauseDelayOnHover === true（悬停暂停）
	else if (opts.pauseDelayOnHover && opts.delay) {
		let hideTimeout;

		const startHideTimer = () => {
			hideTimeout = setTimeout(() => {
				toastInstance.hide(); // 触发隐藏动画
			}, opts.delay);
		};

		// 初始启动倒计时
		startHideTimer();

		// 鼠标进入：暂停倒计时
		toastElement.addEventListener('mouseenter', () => {
			clearTimeout(hideTimeout);
		});

		// 鼠标离开：重新开始倒计时
		toastElement.addEventListener('mouseleave', () => {
			startHideTimer();
		});
	}

	// 关闭按钮点击事件
	toastElement.querySelector('.close')?.addEventListener('click', () => {
		toastInstance.hide(); // 触发动画，hidden.bs.toast 会处理删除
	});
}

// ===== snackbar =====

function showSnackBar(options = {}) {
	let container = document.getElementById('snackbar-container');
	if (!container) {
		container = document.createElement('div');
		container.id = 'snackbar-container';
		container.className = `snackbar-container`;
		document.body.appendChild(container);
	}
	
    const opts = {
        type: 'info',
        content: '',
        delay: 5000,
        ...options  // 用户传的覆盖默认
	};
	
	const icons = {
        success: '<i class="las la-check-circle text-success fs-2"></i>',
        info: '<i class="las la-info-circle text-info fs-2"></i>',
        warning: '<i class="las la-exclamation-triangle text-warning fs-2"></i>',
        danger: '<i class="las la-times-circle text-danger fs-2"></i>',
        primary: '<i class="las la-bell text-primary fs-2"></i>',
        secondary: '<i class="las la-comment-dots text-secondary fs-2"></i>'
    };
    const iconHtml = icons[opts.type] || icons.info;
    
    
    // 1. 构建Toast HTML（简化版Snackbar样式）
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" 
             class="toast align-items-center border-0" 
             role="alert" 
             aria-live="assertive" 
             aria-atomic="true"
             data-bs-delay="${opts.delay}">
            <div class="d-flex">
                <!-- 左侧内容区 -->
                <div class="toast-body d-flex align-items-center">
                    ${iconHtml}
                    <span class="ms-2">${opts.content}</span>
                </div>
                <!-- 右侧关闭按钮 -->
                <button type="button" 
                        class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" 
                        aria-label="Close"></button>
            </div>
        </div>
    `;
    
    // 2. 插入到container
    const toastEl = new DOMParser().parseFromString(toastHtml, 'text/html').body.firstChild;
    container.appendChild(toastEl);
    
    // 3. 初始化Bootstrap Toast
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
    
    // 4. 播放音效（你的音效系统）
	// 播放音效【开始】
	if (opts.sound !== false && SOUND_DEFAULTS.enabled) {
		const soundCategory = SOUND_DEFAULTS.typeToSound[opts.type];
		const soundFile = SOUND_DEFAULTS.sounds[soundCategory];

		if (soundFile) {
			const audio = new Audio(SOUND_DEFAULTS.basePath + soundFile);
			audio.volume = SOUND_DEFAULTS.volume;
			audio.play().catch(e => console.warn("Audio play failed:", e));
		}
	}
	//  播放音效【结束】
    
    // 5. 清理（事件监听）
    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
    });
    
    return toastId;
}

