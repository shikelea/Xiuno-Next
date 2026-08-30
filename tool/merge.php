<?php
// 合并 XiunoPHP

function_exists('set_magic_quotes_runtime') AND set_magic_quotes_runtime(0);
$dir = '../xiunophp/';

function xiuno_strip_file($file) {
	$s = php_strip_whitespace($file);
	$s = preg_replace('#^\s*<\?php\s*#', '', $s);
	$s = preg_replace('#\?>\s*$#', '', $s);
	return trim($s);
}

function xiuno_normalize_lf($s) {
	return str_replace(array("\r\n", "\r"), "\n", $s);
}

$files = array(
	'db_mysql.class.php',
	'db_pdo_mysql.class.php',
	'db_pdo_sqlite.class.php',
	'cache_apc.class.php',
	'cache_memcached.class.php',
	'cache_mysql.class.php',
	'cache_redis.class.php',
	'cache_xcache.class.php',
	'cache_yac.class.php',
	'db.func.php',
	'cache.func.php',
	'image.func.php',
	'array.func.php',
	'config.func.php',
	'xn_encrypt.func.php',
	'logger.func.php',
	'misc.func.php',
	'http.func.php',
	'php8_compat.php',
);

$s = '';
foreach($files as $file) {
	$s .= xiuno_strip_file($dir.$file)."\n";
}

$xiunophp = file_get_contents($dir.'xiunophp.php');
$request_bootstrap_include = "include_once XIUNOPHP_PATH.'request.func.php';";
if(substr_count($xiunophp, $request_bootstrap_include) !== 1) {
	throw new RuntimeException('Unable to locate the XiunoPHP request bootstrap include.');
}
$request_bootstrap = xiuno_strip_file($dir.'request.func.php');
// index.php must initialize the Request ID before install-state/database failures, while the
// generated bundle must remain usable by standalone CLI/tool consumers. Keep both contracts:
// inline the bootstrap only when the early entry point has not already loaded it.
$request_bootstrap = "if(!function_exists('xn_runtime_is_command')) {\n".$request_bootstrap."\n}";
$xiunophp = str_replace($request_bootstrap_include, $request_bootstrap, $xiunophp);
$before = '// hook xiunophp_include_before.php';
$after = '// hook xiunophp_include_after.php';
$pre = substr($xiunophp, 0, strpos($xiunophp, $before) + 1 + strlen($before));
$suffix = substr($xiunophp, strpos($xiunophp, $after));
$xiunophp_min = trim($pre)."\n\n".trim($s)."\n\n".trim($suffix);
$xiunophp_min = xiuno_normalize_lf($xiunophp_min);

//echo $xiunophp_min;exit;
/*
$p = '#//\shook\sxiunophp_include_before\.php(.*?)//\shook\sxiunophp_include_after\.php#ism';
$xiunophp_min = preg_replace($p, $s, $xiunophp);
*/

/*
$xiunophp_min = preg_replace(
'#//\shook\sxiunophp_include_before\.php(.*)//\shook\sxiunophp_include_after\.php#ism', 
'//\shook\sxiunophp_include_before.php'.$s.'//\shook\sxiunophp_include_after.php', 
$xiunophp);*/

file_put_contents($dir.'xiunophp.min.php', $xiunophp_min);

echo 'ok';
