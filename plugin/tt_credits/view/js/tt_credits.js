$.tt_confirm = function(subject, ok_callback, options,btn_title) {
    var options = options || {size: "md"};
    options.body = options.body || '';
    var title = "积分";
    var subject = options.body ? '' : '<p>'+subject+'</p>';
    var s = '\
	<div class="modal fade" tabindex="-1" role="dialog">\
		<div class="modal-dialog modal-'+options.size+'">\
			<div class="modal-content">\
				<div class="modal-header">\
					<h5 class="modal-title">'+title+'</h5>\
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">\
						<span aria-hidden="true">&times;</span>\
					</button>\
				</div>\
				<div class="modal-body">\
					'+subject+'\
					'+options.body+'\
				</div>\
				<div class="modal-footer">\
					<button type="button" class="btn btn-primary">'+btn_title+'</button>\
					<button type="button" class="btn btn-secondary" data-dismiss="modal">'+lang.close+'</button>\
				</div>\
			</div>\
		</div>\
	</div>';
    var jmodal = $(s).appendTo('body');
    jmodal.find('.modal-footer').find('.btn-primary').on('click', function() {
        jmodal.modal('hide');
        if(ok_callback) ok_callback();
    });
    jmodal.modal('show');
    return jmodal;
}