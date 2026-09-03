<?php

$root = dirname(__DIR__).'/';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function source_text($path) {
	$source = file_get_contents($path);
	$source === FALSE AND fail("Unable to read $path");
	return str_replace(array("\r\n", "\r"), "\n", $source);
}

function xn_strlen($value) {
	return mb_strlen($value, 'UTF-8');
}

require_once $root.'model/thread.func.php';

thread_subject_maxlength() === 128
	|| fail('The shared thread subject limit must preserve the existing 128-character creation contract.');
thread_subject_is_too_long(str_repeat('a', 128)) === FALSE
	&& thread_subject_is_too_long(str_repeat('a', 129)) === TRUE
	&& thread_subject_is_too_long(str_repeat('帖', 128)) === FALSE
	&& thread_subject_is_too_long(str_repeat('帖', 129)) === TRUE
	|| fail('The shared thread subject validator must enforce the same UTF-8 boundary for every entry point.');
thread_subject_normalize(str_repeat('&', 26)) === str_repeat('&', 26)
	&& thread_subject_is_too_long(str_repeat('&', 128)) === FALSE
	&& thread_subject_normalize('A&amp;B') === 'A&B'
	&& thread_subject_normalize('<b>Title</b>') === 'Title'
	&& thread_subject_html('A&amp;B') === 'A&amp;B'
	|| fail('Thread subjects must use an idempotent plain-text storage and escaped display contract.');

$thread_route = source_text($root.'route/thread.php');
$post_route = source_text($root.'route/post.php');
$api_thread_route = source_text($root.'route/api/thread.php');
$my_route = source_text($root.'route/my.php');
$post_template = source_text($root.'view/htm/post.htm');

foreach(array('post_not_exists', 'thread_not_exists', 'forum_not_exists') as $missing_key) {
	strpos($post_route, "lang('$missing_key:')") === FALSE
		|| fail("Stale post-edit errors must use the existing translation key without a colon: $missing_key.");
}

strpos($thread_route, "\$header['mobile_linke']") === FALSE
	&& strpos($thread_route, "\$header['mobile_link'] = url(\"forum-\$fid\");") !== FALSE
	|| fail('Thread creation must publish the standard mobile_link header field.');
strpos($my_route, "\$header['mobile_linke']") === FALSE
	&& strpos($my_route, "\$header['mobile_link'] = url(\"my\");") !== FALSE
	|| fail('The account area must publish the standard mobile_link header field.');

foreach(array(
	'web thread creation'=>$thread_route,
	'web thread edit'=>$post_route,
	'API thread creation'=>$api_thread_route,
) as $entry=>$source) {
	strpos($source, 'thread_subject_is_too_long($subject)') !== FALSE
		|| fail("$entry must use the shared thread subject validator.");
}
strpos($thread_route, "htmlspecialchars(param('subject'") === FALSE
	&& strpos($post_route, "htmlspecialchars(param('subject'") === FALSE
	&& strpos($api_thread_route, "param('subject', '', FALSE)") !== FALSE
	|| fail('Thread routes must validate canonical plain text before storage instead of counting HTML entities.');
strpos($post_route, 'forum_access_user($newfid, $gid, \'allowthread\')') !== FALSE
	&& strpos($post_route, 'forum_access_user($fid, $gid, \'allowthread\')') === FALSE
	|| fail('Moving a thread must authorize creation in the target forum, not the source forum.');
strpos($post_route, "empty(\$subject) AND message('subject'") !== FALSE
	|| fail('Editing the first post must reject an empty subject.');
preg_match('/(?:xn_strlen|mb_strlen)\s*\(\s*\$subject[^;]*(?:80|128)/', $thread_route.$post_route.$api_thread_route) === 0
	|| fail('Thread routes must not restore a private numeric subject-length contract.');
strpos($post_template, 'maxlength="<?php echo thread_subject_maxlength();?>"') !== FALSE
	|| fail('The full post form must expose the shared server-side subject limit to the browser.');

$safe_field_lookup = "jform.find(':input').filter(function() { return this.name == code; }).first();";
strpos($post_template, $safe_field_lookup) !== FALSE
	&& strpos($post_template, 'jfield.length ? jfield.alert(message).focus() : $.alert(message);') !== FALSE
	|| fail('The full post form must focus a matching server-reported field and keep a global fallback.');
strpos($post_template, "find('[name=\"'+code+'\"]')") === FALSE
	|| fail('Post field errors must not interpolate a response code into a CSS selector.');

$upload_guard = strpos($post_template, 'if (attach_upload_busy) {');
$form_reset = strpos($post_template, 'jform.reset();');
$upload_guard !== FALSE && $form_reset !== FALSE && $upload_guard < $form_reset
	&& strpos($post_template, '$.alert(attach_upload_wait_message);') !== FALSE
	|| fail('The post form must reject keyboard and scripted submission while attachments are uploading.');
strpos($post_template, 'jattachinput.prop(\'disabled\', attach_upload_busy);') !== FALSE
	&& strpos($post_template, 'jsubmit.prop(\'disabled\', attach_upload_busy);') !== FALSE
	|| fail('The attachment state must disable both file selection and the visible submit control.');
strpos($post_template, 'jattachinput.on(\'change\'') !== FALSE
	&& strpos($post_template, "jattachinput.val('');") !== FALSE
	|| fail('The real file input must clear its selection so the same failed file can be selected again.');

preg_match(
	'#\$\.each_sync\(files,\s*function\(i, callback\)\s*\{(?P<item>.*?)\n\t\}, function\(\)\s*\{(?P<complete>.*?)\n\t\}\);#s',
	$post_template,
	$upload_queue
) === 1 || fail('The attachment uploader must expose an explicit serial-queue completion boundary.');

preg_match('#if \(code != 0\)\s*\{\s*\$\.alert\(message\);\s*callback\(\);\s*return;\s*\}#s', $upload_queue['item']) === 1
	|| fail('A failed attachment must report its error and release the serial queue.');
substr_count($upload_queue['item'], 'callback();') >= 2
	|| fail('Both failed and successful attachment uploads must release the serial queue.');
strpos($upload_queue['item'], 'jprogress.hide();') === FALSE
	&& strpos($upload_queue['complete'], 'jprogress.hide();') !== FALSE
	&& strpos($upload_queue['complete'], 'attach_upload_set_busy(false);') !== FALSE
	|| fail('Attachment progress must stay available between files and hide only after the queue completes.');
strpos($post_template, 'attach_upload_set_busy(true);') !== FALSE
	|| fail('The attachment queue must enter its busy state before starting uploads.');
strpos($post_template, 'if (code != 0) return $.alert(message);') === FALSE
	|| fail('Attachment upload failures must not terminate the remaining file queue.');

$thread_template = source_text($root.'view/htm/thread.htm');
strpos($thread_template, "var jli = jresponse.children('li');") !== FALSE
	&& strpos($thread_template, "var janchor = $('.postlist > .post').last();") !== FALSE
	&& strpos($thread_template, 'if(!jli.length || !janchor.length) {') !== FALSE
	&& strpos($thread_template, 'window.location.reload();') !== FALSE
	|| fail('Quick reply must reload when a legacy theme returns no core insertable reply node.');
$quick_reply_fallback = strpos($thread_template, 'if(!jli.length || !janchor.length) {');
$quick_reply_clear = strpos($thread_template, "$('#message').val('');");
$quick_reply_count = strpos($thread_template, 'xn_thread_post_count_update(1);');
$quick_reply_fallback !== FALSE && $quick_reply_clear !== FALSE && $quick_reply_count !== FALSE
	&& $quick_reply_fallback < $quick_reply_clear && $quick_reply_fallback < $quick_reply_count
	|| fail('Quick reply must validate the response shape before clearing input or changing counters.');
strpos($thread_template, 'data-xn-user-post-count data-uid="<?php echo intval($thread[\'user\'][\'uid\']);?>"') !== FALSE
	&& strpos($thread_template, "var replyUid = xn.intval(jli.first().attr('data-uid'));") !== FALSE
	&& strpos($thread_template, "'[data-xn-user-post-count][data-uid=\"'+replyUid+'\"]'") !== FALSE
	|| fail('Quick reply must update a visible core user post count for the author returned by the successful response.');

foreach(array('zh-cn', 'zh-tw', 'en-us', 'ru-ru', 'th-th') as $language) {
	$lang_source = source_text($root.'lang/'.$language.'/bbs.php');
	strpos($lang_source, "'attach_upload_in_progress'=>") !== FALSE
		|| fail("The attachment upload state must have a public $language translation.");
}

echo "Post form UX checks passed\n";

?>
