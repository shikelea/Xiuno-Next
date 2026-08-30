<?php

// Locale directory identifiers also form one public URL segment in bin/dev_router.php. Keep this
// filesystem-side contract equally bounded and canonical so an admin save cannot make the next
// request include a missing or unsafe language path.
function locale_identifier_is_valid($locale) {
	return is_string($locale)
		&& preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/D', $locale) === 1;
}

function locale_path_identity($path) {
	$path = rtrim(str_replace('\\', '/', (string)$path), '/');
	return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
}

function locale_required_files() {
	return array('bbs.php', 'bbs_admin.php', 'bbs.js');
}

function locale_is_available($locale, $lang_root = '') {
	if(!locale_identifier_is_valid($locale)) return FALSE;
	if($lang_root === '') {
		if(!defined('APP_PATH')) return FALSE;
		$lang_root = APP_PATH.'lang';
	}
	if(!is_string($lang_root) || $lang_root === '') return FALSE;

	$root = realpath($lang_root);
	if($root === FALSE || !is_dir($root) || !is_readable($root)) return FALSE;
	$directory = rtrim($root, '/\\').DIRECTORY_SEPARATOR.$locale;
	if(is_link($directory) || !is_dir($directory) || !is_readable($directory)) return FALSE;
	$real_directory = realpath($directory);
	if($real_directory === FALSE
		|| basename(str_replace('\\', '/', $real_directory)) !== $locale
		|| locale_path_identity($real_directory) !== locale_path_identity($directory)) return FALSE;

	foreach(locale_required_files() as $required) {
		$file = $directory.DIRECTORY_SEPARATOR.$required;
		if(is_link($file) || !is_file($file) || !is_readable($file)) return FALSE;
		$real_file = realpath($file);
		if($real_file === FALSE
			|| basename(str_replace('\\', '/', $real_file)) !== $required
			|| locale_path_identity($real_file) !== locale_path_identity($file)) return FALSE;
	}
	return TRUE;
}

function locale_list_available($lang_root = '') {
	if($lang_root === '') {
		if(!defined('APP_PATH')) return array();
		$lang_root = APP_PATH.'lang';
	}
	$root = realpath($lang_root);
	if($root === FALSE || !is_dir($root) || !is_readable($root)) return array();
	$entries = scandir($root);
	if(!is_array($entries)) return array();

	$locales = array();
	foreach($entries as $locale) {
		if(!locale_is_available($locale, $root)) continue;
		$locales[] = $locale;
	}
	sort($locales, SORT_STRING);
	return $locales;
}

?>
