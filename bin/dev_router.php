<?php

// Safe router for PHP's built-in development server.
// Usage from the repository root:
// php -S 127.0.0.1:8081 -t . bin/dev_router.php

if(PHP_SAPI !== 'cli-server') {
	PHP_SAPI === 'cli' AND fwrite(STDERR, "bin/dev_router.php must be used with php -S.\n");
	exit(1);
}

function xn_dev_router_response($status, $message) {
	http_response_code($status);
	header('Content-Type: text/plain; charset=UTF-8');
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	if(strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'HEAD') {
		echo $message."\n";
	}
	return TRUE;
}

function xn_dev_router_path_identity($path) {
	$path = str_replace('\\', '/', (string)$path);
	$path = rtrim($path, '/');
	return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
}

function xn_dev_router_path_inside($path, $root) {
	$path = xn_dev_router_path_identity($path);
	$root = xn_dev_router_path_identity($root);
	return $path !== $root && strpos($path, $root.'/') === 0;
}

function xn_dev_router_root() {
	$root = realpath(dirname(__DIR__));
	if($root === FALSE || !is_file($root.DIRECTORY_SEPARATOR.'index.php')) return FALSE;

	$document_root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : FALSE;
	if($document_root === FALSE || xn_dev_router_path_identity($document_root) !== xn_dev_router_path_identity($root)) {
		return FALSE;
	}
	return $root;
}

function xn_dev_router_request_path() {
	$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
	$path = parse_url($uri, PHP_URL_PATH);
	if(!is_string($path) || $path === '') $path = '/';
	$path = rawurldecode($path);

	if($path === '' || $path[0] !== '/' || substr($path, 0, 2) === '//') return FALSE;
	if(preg_match('/[\x00-\x1F\x7F\\\\:]/', $path)) return FALSE;
	$path = preg_replace('#/{2,}#', '/', $path);
	if(!is_string($path)) return FALSE;

	foreach(explode('/', $path) as $segment) {
		if($segment === '') continue;
		if($segment === '.' || $segment === '..' || $segment[0] === '.') return FALSE;
	}
	return $path;
}

function xn_dev_router_php_path($path) {
	return preg_match('#(?:^|/)[^/]*(?:\.php\d*|\.phps|\.phtml|\.phar|\.inc)(?:/|$)#i', $path) === 1;
}

function xn_dev_router_sensitive_path($path) {
	if(preg_match('#^/(?:conf|log|tmp|data|bin|database|model|route|src|tool|xiunophp)(?:/|$)#i', $path)) return TRUE;
	if(preg_match('#^/(?:admin/)?view/htm(?:/|$)#i', $path)) return TRUE;
	if(preg_match('#^/admin/route(?:/|$)#i', $path)) return TRUE;
	return FALSE;
}

function xn_dev_router_public_runtime_asset_path($path) {
	return preg_match('#^/tmp/[A-Za-z0-9][A-Za-z0-9._-]{0,127}\.(?:css|js)$#D', $path) === 1;
}

function xn_dev_router_public_static_path($path) {
	if($path === '/robots.txt' || $path === '/favicon.ico') return TRUE;
	// Language packs may add locales without changing the development router. Keep the public
	// surface to one bounded, lowercase locale segment and the one browser language asset.
	if(preg_match('#^/lang/[a-z0-9][a-z0-9_-]{0,63}/bbs\.js$#D', $path)) return TRUE;
	return preg_match('#^/(?:view|plugin|upload)(?:/|$)#i', $path) === 1
		|| preg_match('#^/admin/view(?:/|$)#i', $path) === 1;
}

function xn_dev_router_public_static_root($root, $path) {
	$prefixes = array(
		'/admin/view'=>'admin/view',
		'/lang'=>'lang',
		'/view'=>'view',
		'/plugin'=>'plugin',
		'/tmp'=>'tmp',
		'/upload'=>'upload',
	);
	foreach($prefixes as $url_prefix=>$relative_root) {
		if(strcasecmp($path, $url_prefix) !== 0 && stripos($path, $url_prefix.'/') !== 0) continue;
		$public_root = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative_root));
		if($public_root === FALSE || !is_dir($public_root) || !xn_dev_router_path_inside($public_root, $root)) return FALSE;
		return $public_root;
	}
	return FALSE;
}

function xn_dev_router_static_file($root, $path) {
	$relative = ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
	$candidate = $root.DIRECTORY_SEPARATOR.$relative;
	$file = realpath($candidate);
	if($file === FALSE || !is_file($file)) return FALSE;
	// Development fixtures do not need static symlinks. Rejecting every resolved identity change
	// prevents a public-looking path from aliasing templates, lifecycle PHP, or another trust root.
	if(is_link($candidate) || xn_dev_router_path_identity($file) !== xn_dev_router_path_identity($candidate)) return FALSE;
	if($path === '/robots.txt' || $path === '/favicon.ico') {
		return $file;
	}
	$public_root = xn_dev_router_public_static_root($root, $path);
	if($public_root === FALSE || !xn_dev_router_path_inside($file, $public_root)) return FALSE;
	return $file;
}

function xn_dev_router_entry($root, $relative_script, $script_name, $working_directory) {
	return array(
		'script'=>$root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative_script),
		'script_name'=>$script_name,
		'working_directory'=>$root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $working_directory),
	);
}

function xn_dev_router_restore_cwd($path = NULL) {
	static $previous_cwd = NULL;
	if($path !== NULL) {
		if($previous_cwd !== NULL || !is_string($path) || $path === '') return FALSE;
		$previous_cwd = $path;
		return TRUE;
	}
	if(!is_string($previous_cwd) || $previous_cwd === '') return FALSE;
	return @chdir($previous_cwd);
}

$xn_dev_router_root_path = xn_dev_router_root();
if($xn_dev_router_root_path === FALSE) return xn_dev_router_response(500, 'Development server document root is invalid.');

$xn_dev_router_request_path = xn_dev_router_request_path();
if($xn_dev_router_request_path === FALSE) return xn_dev_router_response(404, 'Not Found');

// These are the only PHP scripts the development router executes directly.
$xn_dev_router_entry = NULL;
if($xn_dev_router_request_path === '/' || $xn_dev_router_request_path === '/index.php') {
	$xn_dev_router_entry = xn_dev_router_entry($xn_dev_router_root_path, 'index.php', '/index.php', '');
} elseif($xn_dev_router_request_path === '/admin' || $xn_dev_router_request_path === '/admin/' || $xn_dev_router_request_path === '/admin/index.php') {
	$xn_dev_router_entry = xn_dev_router_entry($xn_dev_router_root_path, 'admin/index.php', '/admin/index.php', 'admin');
} elseif($xn_dev_router_request_path === '/install' || $xn_dev_router_request_path === '/install/' || $xn_dev_router_request_path === '/install/index.php') {
	$xn_dev_router_entry = xn_dev_router_entry($xn_dev_router_root_path, 'install/index.php', '/install/index.php', 'install');
} elseif(xn_dev_router_public_runtime_asset_path($xn_dev_router_request_path)) {
	// Some legacy packages compile browser-only bundles into the root of tmp/. Expose only a
	// single regular CSS/JS file; every other tmp path remains behind the sensitive-path guard.
	$xn_dev_router_static_file = xn_dev_router_static_file($xn_dev_router_root_path, $xn_dev_router_request_path);
	if($xn_dev_router_static_file === FALSE) return xn_dev_router_response(404, 'Not Found');
	return FALSE;
} elseif(xn_dev_router_php_path($xn_dev_router_request_path) || xn_dev_router_sensitive_path($xn_dev_router_request_path)) {
	// Never execute package scripts, upload payloads, route fragments, Hook fragments, template
	// fragments, or any other PHP-like file through the development server.
	return xn_dev_router_response(404, 'Not Found');
} elseif(preg_match('#^/install(?:/|$)#i', $xn_dev_router_request_path)) {
	// The installer exposes one entry only. Its implementation files are not clean routes.
	return xn_dev_router_response(404, 'Not Found');
} elseif(xn_dev_router_public_static_path($xn_dev_router_request_path)) {
	// Public assets are served only when the requested file really exists inside the document root.
	// A missing icon/script/style must stay a real 404 instead of being replaced by forum HTML.
	$xn_dev_router_static_file = xn_dev_router_static_file($xn_dev_router_root_path, $xn_dev_router_request_path);
	if($xn_dev_router_static_file === FALSE) return xn_dev_router_response(404, 'Not Found');
	return FALSE;
} else {
	// Admin and front-controller routes may be extensionless or use Xiuno's .htm form. Other
	// file-looking paths are missing static resources and must not be rewritten to a successful page.
	$xn_dev_router_leaf = basename($xn_dev_router_request_path);
	$xn_dev_router_is_clean_route = strpos($xn_dev_router_leaf, '.') === FALSE || substr($xn_dev_router_leaf, -4) === '.htm';
	if(strpos($xn_dev_router_request_path, '/admin/') === 0 && $xn_dev_router_is_clean_route) {
		$xn_dev_router_entry = xn_dev_router_entry($xn_dev_router_root_path, 'admin/index.php', '/admin/index.php', 'admin');
	} elseif($xn_dev_router_request_path === '/sitemap.xml' || $xn_dev_router_is_clean_route) {
		$xn_dev_router_entry = xn_dev_router_entry($xn_dev_router_root_path, 'index.php', '/index.php', '');
	} else {
		return xn_dev_router_response(404, 'Not Found');
	}
}

$xn_dev_router_script = $xn_dev_router_entry['script'];
$xn_dev_router_working_directory = $xn_dev_router_entry['working_directory'];
if(!is_file($xn_dev_router_script)) return xn_dev_router_response(500, 'Development server entry point is unavailable.');
if(!is_dir($xn_dev_router_working_directory)) return xn_dev_router_response(500, 'Development server working directory is unavailable.');

$_SERVER['SCRIPT_NAME'] = $xn_dev_router_entry['script_name'];
$_SERVER['PHP_SELF'] = $xn_dev_router_entry['script_name'];
$_SERVER['SCRIPT_FILENAME'] = $xn_dev_router_script;
unset($_SERVER['PATH_INFO']);

$xn_dev_router_previous_cwd = getcwd();
if(!is_string($xn_dev_router_previous_cwd) || $xn_dev_router_previous_cwd === '' || !@chdir($xn_dev_router_working_directory)) {
	return xn_dev_router_response(500, 'Development server working directory is unavailable.');
}
if(!xn_dev_router_restore_cwd($xn_dev_router_previous_cwd)) {
	return xn_dev_router_response(500, 'Development server working directory is unavailable.');
}
register_shutdown_function('xn_dev_router_restore_cwd');

// Avoid exposing routing-only descriptors to the procedural application symbol table.
unset(
	$xn_dev_router_root_path,
	$xn_dev_router_request_path,
	$xn_dev_router_entry,
	$xn_dev_router_working_directory,
	$xn_dev_router_previous_cwd,
	$xn_dev_router_script,
	$xn_dev_router_static_file,
	$xn_dev_router_leaf,
	$xn_dev_router_is_clean_route
);

// Keep the require in file scope. Xiuno's procedural entry points initialize variables which
// model functions later read through `global`; requiring an entry from a helper function would
// strand those initializers in the helper's local symbol table.
try {
	require $_SERVER['SCRIPT_FILENAME'];
} finally {
	xn_dev_router_restore_cwd();
}
return TRUE;
