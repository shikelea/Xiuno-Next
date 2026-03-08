
if($IS_HTMX) {
	$uid = 0;
	$_SESSION['uid'] = 0;
	user_token_clear();
    header("HX-Redirect: " . url('index'));
	message(0, lang('logout_successfully'));
}