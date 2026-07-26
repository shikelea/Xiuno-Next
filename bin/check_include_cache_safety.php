<?php

$root = dirname(__DIR__) . '/';
$pluginFunc = file_get_contents($root . 'model/plugin.func.php');
$errors = array();

if($pluginFunc === FALSE) {
	$errors[] = 'failed to read model/plugin.func.php';
} else {
	if(!preg_match('#function\s+_include\s*\(\s*\$srcfile\s*\)(.*?)(?=^function\s+\w|\z)#ms', $pluginFunc, $m)) {
		$errors[] = '_include() must exist.';
	} else {
		$tokens = token_get_all("<?php\n".$m[1]);
		$include = '';
		foreach($tokens as $token) {
			if(is_array($token)) {
				if($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT || $token[0] === T_OPEN_TAG) continue;
				$include .= $token[1];
			} else {
				$include .= $token;
			}
		}
		if(strpos($include, '$compile_srcfile = empty($conf[\'disabled_plugin\']) ? plugin_find_overwrite($srcfile) : $srcfile;') === FALSE) {
			$errors[] = '_include() cache must track the actual overwrite source when plugins are enabled.';
		}
		if(strpos($include, '$src_mtime = plugin_include_src_mtime($compile_srcfile);') === FALSE) {
			$errors[] = '_include() cache must record effective source and hook file mtime.';
		}
		if(strpos($include, "\$cache_suffix = empty(\$conf['disabled_plugin']) ? '' : '.safe_mode';") === FALSE) {
			$errors[] = '_include() must isolate safe-mode caches from normal plugin caches.';
		}
		if(strpos($include, 'static $compiler_mtime;') === FALSE || strpos($include, '$compiler_mtime === NULL AND $compiler_mtime = filemtime(__FILE__);') === FALSE || strpos($include, '$src_mtime = max($src_mtime, $compiler_mtime);') === FALSE) {
			$errors[] = '_include() caches must rebuild when the plugin compiler changes.';
		}
		if(strpos($include, '$tmp_mtime = $tmp_isfile ? filemtime($tmpfile) : 0;') === FALSE) {
			$errors[] = '_include() cache must record compiled tmp file mtime.';
		}
		if(strpos($include, 'if(!$tmp_isfile || ($src_mtime && $src_mtime > $tmp_mtime) || DEBUG > 1)') === FALSE) {
			$errors[] = '_include() cache must rebuild when tmp cache is missing, debug is enabled, or source is newer than tmp cache.';
		}
	}
	if(!preg_match('#function\s+plugin_include_src_mtime\s*\(\s*\$srcfile\s*\)(.*?)(?=^function\s+\w|\z)#ms', $pluginFunc, $m)) {
		$errors[] = 'plugin_include_src_mtime() must exist.';
	} else {
		$tokens = token_get_all("<?php\n".$m[1]);
		$helper = '';
		foreach($tokens as $token) {
			if(is_array($token)) {
				if($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT || $token[0] === T_OPEN_TAG) continue;
				$helper .= $token[1];
			} else {
				$helper .= $token;
			}
		}
		if(strpos($helper, "preg_match_all('#(?:<!--\\{hook\\s+(.*?)}-->|//\\s*hook\\s+(\\S+))#is', \$s, \$m);") === FALSE) {
			$errors[] = 'plugin_include_src_mtime() must discover template and PHP hook markers.';
		}
		if(strpos($helper, 'foreach(plugin_paths_enabled() as $path=>$pconf)') === FALSE || strpos($helper, 'filemtime($hookfile)') === FALSE) {
			$errors[] = 'plugin_include_src_mtime() must include enabled plugin hook file mtimes.';
		}
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

echo "OK: include cache safety checks passed\n";
exit(0);

?>
