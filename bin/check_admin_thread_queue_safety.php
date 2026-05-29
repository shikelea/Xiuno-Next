<?php

$root = dirname(__DIR__);
$thread_route = file_get_contents($root.'/admin/route/thread.php');
$thread_list = file_get_contents($root.'/admin/view/htm/thread_list.htm');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

$list = section_between($thread_route, "if(empty(\$action) || \$action == 'list')", "} elseif(\$action == 'scan')");
strpos($list, '$queueid = thread_queue_create();') !== FALSE
	|| fail('Admin thread list must create a per-page queue id.');
strpos($list, 'queue_destory($queueid)') === FALSE
	|| fail('Opening a new thread admin page must not destroy another tab queue.');

$scan = section_between($thread_route, "} elseif(\$action == 'scan')", "} elseif(\$action == 'operation')");
strpos($scan, "thread_queue_require(param('queueid', 0))") !== FALSE
	|| fail('Admin thread scan must use the queue id submitted by this page.');
strpos($scan, 'queue_destory($queueid)') !== FALSE
	|| fail('Admin thread scan must reset only its own queue on page one.');
strpos($scan, "_SESSION('thread_find_queueid')") === FALSE
	|| fail('Admin thread scan must not read the single legacy session queue slot.');

$operation = section_between($thread_route, "} elseif(\$action == 'operation')", "} elseif(\$action == 'found')");
strpos($operation, "thread_queue_require(param('queueid', 0))") !== FALSE
	|| fail('Admin thread batch operation must use the queue id submitted by this page.');
strpos($operation, 'thread_queue_destroy($queueid);') !== FALSE
	|| fail('Admin thread batch operation must destroy only its own queue after completion.');
strpos($operation, "_SESSION('thread_find_queueid')") === FALSE
	|| fail('Admin thread batch operation must not read the single legacy session queue slot.');

$found = section_between($thread_route, "} elseif(\$action == 'found')", 'function thread_queue_create');
strpos($found, 'thread_queue_require(param(2, 0))') !== FALSE
	|| fail('Admin thread found page must use the queue id from the URL.');
strpos($found, 'url("thread-found-$queueid-{page}")') !== FALSE
	|| fail('Admin thread found pagination must preserve the queue id.');

foreach(array('thread_queue_create', 'thread_queue_require', 'thread_queue_destroy') as $function) {
	strpos($thread_route, "function $function(") !== FALSE
		|| fail("$function() helper is missing.");
}

strpos($thread_list, 'name="queueid" value="<?php echo $queueid;?>"') !== FALSE
	|| fail('Admin thread list must submit its per-page queue id during scan.');
strpos($thread_list, 'url("thread-found-$queueid")') !== FALSE
	|| fail('Admin thread found link must include the per-page queue id.');
strpos($thread_list, "var postdata = {queueid: jform.find('input[name=\"queueid\"]').val()};") !== FALSE
	|| fail('Admin thread operation requests must submit the per-page queue id.');
strpos($thread_list, 'jprogress.width( (postdata.page / totalpage) * 100 + \'%\' );') !== FALSE
	|| fail('Admin thread operation progress must update the progress bar width.');

echo "OK: admin thread queue safety checks passed\n";
