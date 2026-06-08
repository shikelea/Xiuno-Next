<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
	fwrite(STDERR, "Unable to locate project root.\n");
	exit(1);
}

$root = str_replace('\\', '/', $root);
$check = in_array('--check', $argv, true);

$assets = array(
	array('src' => 'view/css/bootstrap.css', 'dest' => 'view/css/bootstrap.min.css', 'type' => 'css'),
	array('src' => 'view/css/bs4-compat.css', 'dest' => 'view/css/bs4-compat.min.css', 'type' => 'css'),
	array('src' => 'view/css/bootstrap-bbs.css', 'dest' => 'view/css/bootstrap-bbs.min.css', 'type' => 'css'),
	array('src' => 'admin/view/css/admin.css', 'dest' => 'admin/view/css/admin.min.css', 'type' => 'css'),
	array('src' => 'view/js/xiuno.js', 'dest' => 'view/js/xiuno.min.js', 'type' => 'js'),
	array('src' => 'view/js/htmx-xiuno.js', 'dest' => 'view/js/htmx-xiuno.min.js', 'type' => 'js'),
	array('src' => 'view/js/bs4-compat.js', 'dest' => 'view/js/bs4-compat.min.js', 'type' => 'js'),
	array('src' => 'view/js/bootstrap-plugin.js', 'dest' => 'view/js/bootstrap-plugin.min.js', 'type' => 'js'),
	array('src' => 'view/js/async.js', 'dest' => 'view/js/async.min.js', 'type' => 'js'),
	array('src' => 'view/js/form.js', 'dest' => 'view/js/form.min.js', 'type' => 'js'),
	array('src' => 'view/js/bbs.js', 'dest' => 'view/js/bbs.min.js', 'type' => 'js'),
);

$errors = array();
$written = 0;

foreach ($assets as $asset) {
	$src = $root . '/' . $asset['src'];
	$dest = $root . '/' . $asset['dest'];
	$content = file_get_contents($src);
	if ($content === false) {
		$errors[] = "failed to read {$asset['src']}";
		continue;
	}

	$minified = $asset['type'] === 'css' ? minify_css($content) : minify_js($content);
	$minified = trim($minified) . "\n";

	if ($check) {
		$current = is_file($dest) ? file_get_contents($dest) : false;
		if ($current !== $minified) {
			$errors[] = "stale generated asset: {$asset['dest']}";
		}
		continue;
	}

	if (!is_dir(dirname($dest))) {
		mkdir(dirname($dest), 0755, true);
	}
	file_put_contents($dest, $minified);
	$written++;
}

if ($check) {
	$errors = array_merge($errors, check_template_references($root));
}

if (!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo $check ? "Frontend assets are up to date\n" : "Built {$written} frontend assets\n";
exit(0);

function minify_css(string $css): string
{
	$css = preg_replace('#/\*.*?\*/#s', '', $css);
	$css = preg_replace('/\s+/', ' ', $css);
	$css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);
	$css = str_replace(';}', '}', $css);
	return trim($css);
}

function minify_js(string $js): string
{
	$js = strip_js_comments($js);
	$lines = preg_split('/\R/', $js);
	$out = array();
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line !== '') {
			$out[] = $line;
		}
	}
	return implode("\n", $out);
}

function strip_js_comments(string $js): string
{
	$out = '';
	$len = strlen($js);
	$state = 'normal';
	$quote = '';
	$escape = false;
	$regexClass = false;
	$commentHasNewline = false;

	for ($i = 0; $i < $len; $i++) {
		$ch = $js[$i];
		$next = $i + 1 < $len ? $js[$i + 1] : '';

		if ($state === 'line_comment') {
			if ($ch === "\n" || $ch === "\r") {
				$out .= "\n";
				$state = 'normal';
			}
			continue;
		}

		if ($state === 'block_comment') {
			if ($ch === "\n" || $ch === "\r") {
				$commentHasNewline = true;
			}
			if ($ch === '*' && $next === '/') {
				$i++;
				if ($commentHasNewline) {
					$out .= "\n";
				}
				$state = 'normal';
				$commentHasNewline = false;
			}
			continue;
		}

		if ($state === 'string') {
			$out .= $ch;
			if ($escape) {
				$escape = false;
			} elseif ($ch === '\\') {
				$escape = true;
			} elseif ($ch === $quote) {
				$state = 'normal';
			}
			continue;
		}

		if ($state === 'template') {
			$out .= $ch;
			if ($escape) {
				$escape = false;
			} elseif ($ch === '\\') {
				$escape = true;
			} elseif ($ch === '`') {
				$state = 'normal';
			}
			continue;
		}

		if ($state === 'regex') {
			$out .= $ch;
			if ($escape) {
				$escape = false;
			} elseif ($ch === '\\') {
				$escape = true;
			} elseif ($ch === '[') {
				$regexClass = true;
			} elseif ($ch === ']') {
				$regexClass = false;
			} elseif ($ch === '/' && !$regexClass) {
				$state = 'normal';
			}
			continue;
		}

		if ($ch === '\'' || $ch === '"') {
			$state = 'string';
			$quote = $ch;
			$out .= $ch;
			continue;
		}

		if ($ch === '`') {
			$state = 'template';
			$out .= $ch;
			continue;
		}

		if ($ch === '/' && $next === '/') {
			$state = 'line_comment';
			$i++;
			continue;
		}

		if ($ch === '/' && $next === '*') {
			$state = 'block_comment';
			$commentHasNewline = false;
			$i++;
			continue;
		}

		if ($ch === '/' && js_allows_regex($out)) {
			$state = 'regex';
			$regexClass = false;
			$out .= $ch;
			continue;
		}

		$out .= $ch;
	}

	return $out;
}

function js_allows_regex(string $out): bool
{
	$trimmed = rtrim($out);
	if ($trimmed === '') {
		return true;
	}

	$last = substr($trimmed, -1);
	if (strpos('([{=,:;!&|?+-*%^~<>', $last) !== false) {
		return true;
	}

	return preg_match('/\b(?:return|throw|case|delete|typeof|void|new|in|of|yield|else|do)\s*$/', $trimmed) === 1;
}

function check_template_references(string $root): array
{
	$checks = array(
		'view/htm/header.inc.htm' => array(
			'css/bootstrap.min.css',
			'css/bs4-compat.min.css',
			'css/bootstrap-bbs.min.css',
		),
		'admin/view/htm/header.inc.htm' => array(
			'../view/css/bootstrap.min.css',
			'../view/css/bs4-compat.min.css',
			'../view/css/bootstrap-bbs.min.css',
			'view/css/admin.min.css',
		),
		'view/htm/footer.inc.htm' => array(
			'js/xiuno.min.js',
			'js/htmx-xiuno.min.js',
			'js/bs4-compat.min.js',
			'js/bootstrap-plugin.min.js',
			'js/async.min.js',
			'js/form.min.js',
			'js/bbs.min.js',
		),
		'admin/view/htm/footer.inc.htm' => array(
			'../view/js/xiuno.min.js',
			'../view/js/bs4-compat.min.js',
			'../view/js/bootstrap-plugin.min.js',
			'../view/js/async.min.js',
			'../view/js/form.min.js',
			'../view/js/bbs.min.js',
		),
	);

	$errors = array();
	foreach ($checks as $file => $patterns) {
		$content = file_get_contents($root . '/' . $file);
		if ($content === false) {
			$errors[] = "failed to read $file";
			continue;
		}
		foreach ($patterns as $pattern) {
			if (strpos($content, $pattern) === false) {
				$errors[] = "template does not load generated asset $pattern in $file";
			}
		}
	}
	return $errors;
}
