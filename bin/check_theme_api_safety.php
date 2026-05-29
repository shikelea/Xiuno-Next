<?php

$root = dirname(__DIR__);

defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');

include $root.'/model/misc.func.php';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

$conf = array('static_version'=>'?v=1');
$g_theme_info = array();
$g_theme_styles = array();
$g_theme_scripts = array();

theme_register('safe_theme', array('htmx', 'htmx_message'));
theme_has('htmx') || fail('theme_has() should detect registered capabilities.');
theme_has('missing') && fail('theme_has() should not report missing capabilities.');

theme_enqueue_style('base"style', './plugin/safe_theme/view/css/base.css', 20);
theme_enqueue_style('bad-style', 'javascript:alert(1)', 1);
theme_enqueue_style('protocol-relative', '//cdn.example.test/theme.css', 2);
theme_enqueue_style('space-prefixed', ' javascript:alert(1)', 3);

theme_enqueue_script('app<script', 'https://cdn.example.test/app.js', 10, array(
	'defer'=>'defer',
	'integrity'=>'sha384-test',
	'onload'=>'alert(1)',
	'bad attr'=>'x',
));
theme_enqueue_script('bad-script', "data:text/javascript,alert(1)", 1);
theme_enqueue_script('control-script', "./ok.js\nbad", 1);

ob_start();
theme_render_styles();
$styles = ob_get_clean();

strpos($styles, './plugin/safe_theme/view/css/base.css?v=1') !== FALSE
	|| fail('theme_render_styles() should render safe relative styles with static version.');
strpos($styles, 'javascript:') === FALSE
	|| fail('theme_render_styles() must skip javascript: URLs.');
strpos($styles, '//cdn.example.test') === FALSE
	|| fail('theme_render_styles() must skip protocol-relative URLs.');
strpos($styles, ' javascript:') === FALSE
	|| fail('theme_render_styles() must skip whitespace-bearing URLs.');
strpos($styles, 'style-base&quot;style') !== FALSE
	|| fail('theme_render_styles() must escape style handles as attributes.');

ob_start();
theme_render_scripts();
$scripts = ob_get_clean();

strpos($scripts, 'https://cdn.example.test/app.js?v=1') !== FALSE
	|| fail('theme_render_scripts() should render safe HTTPS scripts with static version.');
strpos($scripts, 'defer="defer"') !== FALSE && strpos($scripts, 'integrity="sha384-test"') !== FALSE
	|| fail('theme_render_scripts() should keep safe script attributes.');
strpos($scripts, 'onload') === FALSE
	|| fail('theme_render_scripts() must skip event handler attributes.');
strpos($scripts, 'bad attr') === FALSE
	|| fail('theme_render_scripts() must skip invalid attribute names.');
strpos($scripts, 'data:text') === FALSE && strpos($scripts, './ok.js') === FALSE
	|| fail('theme_render_scripts() must skip unsafe script URLs.');
strpos($scripts, 'script-app&lt;script') !== FALSE
	|| fail('theme_render_scripts() must escape script handles as attributes.');

echo "OK: theme API safety checks passed\n";
