<?php

define('APP_PATH', dirname(__DIR__).'/');
define('XIUNOPHP_PATH', APP_PATH.'xiunophp/');

require XIUNOPHP_PATH.'misc.func.php';
require APP_PATH.'model/misc.func.php';

function sanitizer_fail($message) {
	fwrite(STDERR, "[FAIL] $message\n");
	exit(1);
}

$unsafe = array(
	'<a href="javascript:alert(1)">direct</a>' => '<a href="">direct</a>',
	'<a href="javascript&#58;alert(1)">decimal</a>' => '<a href="">decimal</a>',
	'<a href="javascript&#x3a;alert(1)">hex</a>' => '<a href="">hex</a>',
	'<a href="javascript&#58alert(1)">decimal-no-semicolon</a>' => '<a href="">decimal-no-semicolon</a>',
	'<a href="java&#x09script:alert(1)">hex-no-semicolon</a>' => '<a href="">hex-no-semicolon</a>',
	'<a href="javascript&colon;alert(1)">named</a>' => '<a href="">named</a>',
	'<a href="javascript&amp;colon;alert(1)">nested</a>' => '<a href="">nested</a>',
	'<a href="javascript&amp;amp;amp;amp;amp;colon;alert(1)">deeply-nested</a>' => '<a href="">deeply-nested</a>',
	'<a href="java&#x09;script:alert(1)">control</a>' => '<a href="">control</a>',
	'<a href="java&#x0d;script:alert(1)">carriage-return</a>' => '<a href="">carriage-return</a>',
	'<a href="java&#x0a;script:alert(1)">line-feed</a>' => '<a href="">line-feed</a>',
	'<a href="java&#x0c;script:alert(1)">form-feed</a>' => '<a href="">form-feed</a>',
	'<a href="JaVaScRiPt&#58;alert(1)">mixed-case</a>' => '<a href="">mixed-case</a>',
	'<a href="&#x09;javascript&#58;alert(1)">leading-control</a>' => '<a href="">leading-control</a>',
	'<a href=javascript&#58;alert(1)>unquoted</a>' => '<a href="">unquoted</a>',
	'<a href=\'javascript&#58;alert(1)\'>single-quoted</a>' => '<a href="">single-quoted</a>',
	'<a href="vbscript&#58;msgbox(1)">vbscript</a>' => '<a href="">vbscript</a>',
	'<img src="javascript:alert(1)" />' => '<img src="" />',
	'<img src="javascript&#58;alert(1)" />' => '<img src="" />',
	'<img src="javascript&amp;colon;alert(1)" />' => '<img src="" />',
	'<img src="data:text/html;base64,PHNjcmlwdD4=" />' => '<img src="" />',
	'<img src="data:image/svg+xml;base64,PHN2Zz4=" />' => '<img src="" />',
	'<span style="background-image:javascript&#58;alert(1)">css</span>' => '<span style="background-image:none;">css</span>',
	'<span style="background:url(javascript&#58;alert(1))">css-url</span>' => '<span style="background:none;">css-url</span>',
	'<span style="background&#58;url(javascript&#58;alert(1))">css-delimiters</span>' => '<span style="background:none;">css-delimiters</span>',
	'<span style="background:url(java/**/script&#58;alert(1))">css-comment</span>' => '<span style="background:none;">css-comment</span>',
	'<span style="background:url(java\\script&#58;alert(1))">css-escape</span>' => '<span style="background:none;">css-escape</span>',
	'<span style="background:url(\\6a avascript&#58;alert(1))">css-hex-escape</span>' => '<span style="background:none;">css-hex-escape</span>',
	'<span style="background:url(\'javascript&#58;alert(1)\')">css-quoted</span>' => '<span style="background:none;">css-quoted</span>',
);

foreach($unsafe as $html => $expected) {
	$safe = xn_html_safe($html);
	if($safe !== $expected) {
		sanitizer_fail('unsafe value was not removed: input='.var_export($html, TRUE).' expected='.var_export($expected, TRUE).' actual='.var_export($safe, TRUE));
	}
}

$allowed = array(
	'<a href="https://www.xiunobbs.cn/thread-1.htm?x=1&amp;y=2">https</a>' => '<a href="https://www.xiunobbs.cn/thread-1.htm?x=1&amp;y=2">https</a>',
	'<a href="http://www.xiunobbs.cn/thread-1.htm">http</a>' => '<a href="http://www.xiunobbs.cn/thread-1.htm">http</a>',
	'<a href="/thread-1.htm?x=1&amp;y=2">relative</a>' => '<a href="/thread-1.htm?x=1&amp;y=2">relative</a>',
	'<a href="#post-1">fragment</a>' => '<a href="#post-1">fragment</a>',
	'<a href="//cdn.example.com/a.png">protocol-relative</a>' => '<a href="//cdn.example.com/a.png">protocol-relative</a>',
	'<a href="ed2k://example">ed2k</a>' => '<a href="ed2k://example">ed2k</a>',
	'<a href="thunder://example">thunder</a>' => '<a href="thunder://example">thunder</a>',
	'<a href="qvod://example">qvod</a>' => '<a href="qvod://example">qvod</a>',
	'<a href="magnet://example">magnet</a>' => '<a href="magnet://example">magnet</a>',
	'<img src="data:image/png;base64,QUJD" />' => '<img src="data:image/png;base64,QUJD" />',
	'<img src="data:image/png;base64,QUI=" />' => '<img src="data:image/png;base64,QUI=" />',
	'<img src="data:image/png;base64,QQ==" />' => '<img src="data:image/png;base64,QQ==" />',
	'<span style="background:url(https://example.com/a.png?x=1&amp;y=2) no-repeat;">css</span>' => '<span style="background:url(https://example.com/a.png?x=1&amp;y=2) no-repeat;">css</span>',
);

foreach($allowed as $html => $expected) {
	$safe = xn_html_safe($html);
	if($safe !== $expected) {
		sanitizer_fail('allowed value changed unexpectedly: input='.var_export($html, TRUE).' expected='.var_export($expected, TRUE).' actual='.var_export($safe, TRUE));
	}
}

echo "[OK] HTML sanitizer URI safety checks passed.\n";
