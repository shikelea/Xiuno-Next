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
	'php8_compat.php',
);

$s = '';
foreach($files as $file) {
	$s .= xiuno_strip_file($dir.$file)."\r\n";
}

$xiunophp = file_get_contents($dir.'xiunophp.php');
$before = '// hook xiunophp_include_before.php';
$after = '// hook xiunophp_include_after.php';
$pre = substr($xiunophp, 0, strpos($xiunophp, $before) + 1 + strlen($before));
$suffix = substr($xiunophp, strpos($xiunophp, $after));
$xiunophp_min = trim($pre)."\r\n\r\n".trim($s)."\r\n\r\n".trim($suffix);

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
