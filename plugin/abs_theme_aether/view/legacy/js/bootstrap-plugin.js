
$.alert = function (subject, timeout, options) {
	// 没有使用timeout, options参数
	console.warn('[THANKS] 你正在使用HTMX增强的功能');
	openModal(subject, lang.tips_title);
}

$.confirm = function (subject, ok_callback, options) {
	// TODO 将该功能剥离jQuery和Bootstrap依赖
	console.warn('[DEPRECATED] 使用HX系列属性实现我的功能');
	var options = options || { size: "md" };
	options.body = options.body || '';
	var title = options.body ? subject : lang.confirm_title + ':';
	var subject = options.body ? '' : '<p>' + subject + '</p>';
	var s = '\
	<div class="modal fade" tabindex="-1" role="dialog">\
		<div class="modal-dialog modal-'+ options.size + '">\
			<div class="modal-content">\
				<div class="modal-header">\
					<h5 class="modal-title">'+ title + '</h5>\
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">\
						<span aria-hidden="true">&times;</span>\
					</button>\
				</div>\
				<div class="modal-body">\
					'+ subject + '\
					'+ options.body + '\
				</div>\
				<div class="modal-footer">\
					<button type="button" class="btn btn-primary">'+ lang.confirm + '</button>\
					<button type="button" class="btn btn-secondary" data-dismiss="modal">'+ lang.close + '</button>\
				</div>\
			</div>\
		</div>\
	</div>';
	var jmodal = $(s).appendTo('body');
	jmodal.find('.modal-footer').find('.btn-primary').on('click', function () {
		jmodal.modal('hide');
		if (ok_callback) ok_callback();
	});
	jmodal.modal('show');
	return jmodal;
}

$.ajax_modal = function (url, title, size, callback, arg) {

	console.warn('[THANKS] 你正在使用HTMX增强的功能');
	// callback, arg参数不再使用
	let modal_title = '';
	let MODAL_INIT_CONTENT = title || 'Loading...';
	showModal(modal_title, MODAL_INIT_CONTENT);

	// ajax 加载内容
	let additional_args = { target: '#htmx-modal .modal-body', swap: 'innerHTML' };
	htmx.ajax('GET', url, additional_args);

	/*
	$.xget(url, function (code, message) {
		// 对页面 html 进行解析
		if (code == -101) {
			var r = xn.get_title_body_script_css(message);
			jmodal.find('.modal-body').html(r.body);
			jmodal.find('.modal-footer').hide();
		} else {
			jmodal.find('.modal-body').html(message);
			return;
		}
		// eval script, css
		xn.eval_stylesheet(r.stylesheet_links);
		jmodal.script_sections = r.script_sections;
		if (r.script_srcs.length > 0) {
			$.require(r.script_srcs, function () {
				xn.eval_script(r.script_sections, { jmodal: jmodal, callback: callback, arg: arg });
			});
		} else {
			xn.eval_script(r.script_sections, { jmodal: jmodal, callback: callback, arg: arg });
		}
	});
	return jmodal;
	*/
}

document.addEventListener('click', function (e) {
	// 检查点击的元素或其祖先元素是否有 data-modal-url 属性
	const target = e.target.closest('[data-modal-url]');
	if (!target) return;

	console.warn('[THANKS] 你正在使用HTMX增强的功能');

	// 如果是a标签，阻止默认跳转行为
	if (target.tagName === 'A' && window.htmx) {
		e.preventDefault();
	}

	let modal_title = lang.tips_title;
	let MODAL_INIT_CONTENT = 'Loading...';
	let additional_args = { target: '#htmx-modal .modal-body', swap: 'innerHTML' };

	// 收集参数（表单输入值）
	if (target.hasAttribute('data-modal-arg')) {
		let gather_args_selector = target.getAttribute('data-modal-arg');


		// 检查是否是简单的元素选择器（如 input、textarea、select）
		const isSimpleSelector = /^(input|textarea|select)/i.test(gather_args_selector);

		// 如果是简单的元素选择器，直接使用
		// 否则，需要处理旧版 xiuno 格式，自动补全
		if (!isSimpleSelector) {
			gather_args_selector =
				gather_args_selector + ' input, ' +
				gather_args_selector + ' textarea, ' +
				gather_args_selector + ' select';
		}


		const inputs = document.querySelectorAll(gather_args_selector);


		const params = new URLSearchParams();

		inputs.forEach(input => {


			// 如果是 checkbox/radio，只取选中的
			if ((input.type === 'checkbox' || input.type === 'radio')) {
				if (!input.checked) {


					return;
				}


			}

			// 处理数组参数
			if (input.name && input.name.endsWith('[]')) {
				params.append(input.name, input.value);
			} else if (input.name) {
				params.set(input.name, input.value);
			}
		});

		// 如果参数不为空，则附加到 URL
		if (params.toString()) {
			const currentUrl = target.getAttribute('data-modal-url');
			const separator = currentUrl.includes('?') ? '&' : '?';
			additional_args['url'] = currentUrl + separator + params.toString();


		}
	}

	// 设置模态框标题
	if (target.hasAttribute('data-modal-title')) {
		modal_title = target.getAttribute('data-modal-title');
	}

	showModal(MODAL_INIT_CONTENT, modal_title);

	// 确定最终使用的URL
	const url = additional_args.url || target.getAttribute('data-modal-url');



	htmx.ajax('GET', url, additional_args);
});

// --------------------- eval script start ---------------------------------

// 获取当前已经加载的 js
xn.get_loaded_script = function () {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	var arr = [];
	$('script[src]').each(function () {
		arr.push($(this).attr('src'));
	});
	return arr;
}
xn.get_stylesheet_link = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	var arr = [];
	var r = s.match(/<link[^>]*?href=\s*\"([^"]+)\"[^>]*>/ig);
	if (!r) return arr;
	for (var i = 0; i < r.length; i++) {
		var r2 = r[i].match(/<link[^>]*?href=\s*\"([^"]+)\"[^>]*>/i);
		arr.push(r2[1]);
	}
	return arr;
}
xn.get_script_src = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	var arr = [];
	var r = s.match(/<script[^>]*?src=\s*\"([^"]+)\"[^>]*><\/script>/ig);
	if (!r) return arr;
	for (var i = 0; i < r.length; i++) {
		var r2 = r[i].match(/<script[^>]*?src=\s*\"([^"]+)\"[^>]*><\/script>/i);
		arr.push(r2[1]);
	}
	return arr;
}
xn.get_script_section = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	var r = '';
	var arr = s.match(/<script[^>]+ajax-eval="true"[^>]*>([\s\S]+?)<\/script>/ig);
	return arr ? arr : [];
}
xn.strip_script_src = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	s = s.replace(/<script[^>]*?src=\s*\"([^"]+)\"[^>]*><\/script>/ig, '');
	return s;
}
xn.strip_script_section = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	s = s.replace(/<script([^>]*)>([\s\S]+?)<\/script>/ig, '');
	return s;
}
xn.strip_stylesheet_link = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	s = s.replace(/<link[^>]*?href=\s*\"([^"]+)\"[^>]*>/ig, '');
	return s;
}
xn.eval_script = function (arr, args) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	if (!arr) return;
	for (var i = 0; i < arr.length; i++) {
		var s = arr[i].replace(/<script([^>]*)>([\s\S]+?)<\/script>/i, '$2');
		try {
			var func = new Function('args', s);
			func(args);
			//func = null;
			//func.call(window, 'aaa'); // 放到 windows 上执行会有内存泄露!!!
		} catch (e) {
			console.log("eval_script() error: %o, script: %s", e, s);
			alert(s);
		}
	}
}
xn.eval_stylesheet = function (arr) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	if (!arr) return;
	if (!$.required_css) $.required_css = {};
	for (var i = 0; i < arr.length; i++) {
		if ($.required_css[arr[i]]) continue;
		$.require_css(arr[i]);
	}
}

xn.get_title_body_script_css = function (s) {
	// TODO 可能不安全，视情况删除或架空该功能
	console.warn('[DEPRECATED] 获取外部资源可能不安全');
	var s = $.trim(s);

	/* 过滤掉 IE 兼容代码
		<!--[if lt IE 9]>
		<script>window.location = '<?= url('browser');?>';</script>
		<![endif]-->
	*/
	s = s.replace(/<!--\[if\slt\sIE\s9\]>([\s\S]+?)<\!\[endif\]-->/ig, '');

	var title = '';
	var body = '';
	var script_sections = xn.get_script_section(s);
	var stylesheet_links = xn.get_stylesheet_link(s);

	var arr1 = xn.get_loaded_script();
	var arr2 = xn.get_script_src(s);
	var script_srcs = xn.array_diff(arr2, arr1); // 避免重复加载 js

	s = xn.strip_script_src(s);
	s = xn.strip_script_section(s);
	s = xn.strip_stylesheet_link(s);

	var r = s.match(/<title>([^<]+?)<\/title>/i);
	if (r && r[1]) title = r[1];

	var r = s.match(/<body[^>]*>([\s\S]+?)<\/body>/i);
	if (r && r[1]) body = r[1];

	// jquery 更方便
	var jtmp = $('<div>' + body + '</div>');
	var t = jtmp.find('div.ajax-body');
	if (t.length == 0) t = jtmp.find('#body'); // 查找 id="body"
	if (t.length > 0) body = t.html();

	if (!body) body = s;
	if (body.indexOf('<meta ') != -1) {
		console.log('加载的数据有问题：body: %s: ', body);
		body = '';
	}
	jtmp.remove();

	return { title: title, body: body, script_sections: script_sections, script_srcs: script_srcs, stylesheet_links: stylesheet_links };
}
// --------------------- eval script end ---------------------------------

