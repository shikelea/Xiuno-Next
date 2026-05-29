<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_syntax_guard_app/';

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function rm_dir($dir) {
	if(!is_dir($dir)) return;
	$items = glob(rtrim($dir, '/').'/*');
	if($items) {
		foreach($items as $item) {
			is_dir($item) ? rm_dir($item) : unlink($item);
		}
	}
	rmdir($dir);
}

rm_dir($app);
mkdir($app.'plugin/good/model', 0777, TRUE);
mkdir($app.'plugin/good/hook', 0777, TRUE);
mkdir($app.'plugin/bad/model', 0777, TRUE);
mkdir($app.'plugin/bad/hook', 0777, TRUE);

file_put_contents($app.'plugin/good/model/ok.func.php', "<?php\nfunction syntax_guard_ok() { return true; }\n");
file_put_contents($app.'plugin/good/hook/index_route_case_end.php', "<?php exit;\ncase 'legacy': echo 'hook fragment'; break;\n");
file_put_contents($app.'plugin/bad/model/broken.func.php', "<?php\nfunction syntax_guard_broken( {\n");
file_put_contents($app.'plugin/bad/hook/my_end.php', "<?php exit;\nelse { echo 'hook fragment'; }\n");

include $root.'/model/plugin.func.php';

$good = plugin_php_syntax_errors('good');
empty($good) || fail('Hook fragments should be skipped and valid standalone plugin files should pass.');

$bad = plugin_php_syntax_errors('bad');
count($bad) === 1 || fail('Exactly one standalone PHP syntax error should be reported.');
strpos($bad[0]['file'], 'plugin/bad/model/broken.func.php') !== FALSE
	|| fail('Syntax guard should report the standalone broken model file.');
strpos($bad[0]['file'], '/hook/') === FALSE
	|| fail('Syntax guard must not report hook fragments as standalone PHP errors.');

rm_dir($app);

echo "OK: plugin PHP syntax guard checks passed\n";
