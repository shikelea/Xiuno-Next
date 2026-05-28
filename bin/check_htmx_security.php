<?php

$root = dirname(__DIR__) . '/';
$errors = array();

$bridge = file_get_contents($root . 'view/js/htmx-xiuno.js');
if ($bridge === FALSE) {
	$errors[] = 'failed to read view/js/htmx-xiuno.js';
} else {
	if (strpos($bridge, 'allowEval = false') === FALSE) {
		$errors[] = 'HTMX bridge must disable allowEval';
	}
	if (strpos($bridge, 'allowScriptTags = false') === FALSE) {
		$errors[] = 'HTMX bridge must disable allowScriptTags';
	}
	if (strpos($bridge, "textContent = detail.message") === FALSE) {
		$errors[] = 'HTMX message bridge must render message text via textContent';
	}
	if (strpos($bridge, "xiuno:fragment-ready") === FALSE || strpos($bridge, "htmx:afterSettle") === FALSE) {
		$errors[] = 'HTMX bridge must dispatch xiuno:fragment-ready after swaps settle';
	}
}

$misc = file_get_contents($root . 'model/misc.func.php');
if ($misc === FALSE) {
	$errors[] = 'failed to read model/misc.func.php';
} else {
	if (!preg_match('/HTTP_HX_REQUEST[\s\S]+?HX-Trigger[\s\S]+?http_response_code\(204\);[\s\S]+?exit;/m', $misc)) {
		$errors[] = 'HTMX message branch must return HX-Trigger with 204 No Content';
	}
	if (preg_match('/HTTP_HX_REQUEST[\s\S]+?echo\s+\$msg_str;/m', $misc)) {
		$errors[] = 'HTMX message branch must not echo message text into the response body';
	}
}

if (!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "HTMX security smoke OK\n";
exit(0);
