<?php

$root = dirname(__DIR__) . '/';
$modelInc = file_get_contents($root . 'model.inc.php');
$errors = array();

if($modelInc === FALSE) {
	$errors[] = 'failed to read model.inc.php';
} else {
	$tokens = token_get_all($modelInc);
	$modelForCheck = '';
	foreach($tokens as $token) {
		if(is_array($token)) {
			if($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) continue;
			$modelForCheck .= $token[1];
		} else {
			$modelForCheck .= $token;
		}
	}
	if(strpos($modelForCheck, '$model_min_mtime = filemtime($model_min_file);') === FALSE) {
		$errors[] = 'model.min.php cache must record its mtime.';
	}
	if(strpos($modelForCheck, 'filemtime(__FILE__) > $model_min_mtime') === FALSE) {
		$errors[] = 'model.min.php cache must rebuild when model.inc.php changes.';
	}
	if(strpos($modelForCheck, 'filemtime($model_file) > $model_min_mtime') === FALSE) {
		$errors[] = 'model.min.php cache must rebuild when source model files are newer.';
	}
	if(strpos($modelForCheck, '$isfile = FALSE;') === FALSE) {
		$errors[] = 'model.min.php cache must mark stale cache as missing before regeneration.';
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

echo "OK: model cache safety checks passed\n";
exit(0);

?>
