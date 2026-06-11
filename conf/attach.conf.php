<?php

// 🔒 安全修复：移除危险可执行文件类型，防止恶意软件传播
// 已移除：exe, bin, swf, fla, as（可执行文件和 Flash 文件）
// 原因：这些文件可能被用于传播恶意软件，通过社会工程学诱导用户执行

return array (
	'all'=> array('av','wmv','wav','wma','avi','rm','rmvb','mp4', 'mp3','gif','jpg','jpeg','png','bmp','doc','xls','ppt','docx','xlsx','pptx','pdf',
		'c','cpp','cc', 'txt','tar','zip','gz','rar','7z','bz','chm','bt','torrent','ttf','font','fon'
	),
	'video' => array('av','wmv','wav','wma','avi','rm','rmvb','mp4'),
	'music' => array('mp3','mp4'),
	// 🔒 安全修复：已移除 exe 分类，不再允许上传可执行文件
	// 'exe' => array('exe','bin'),
	// 🔒 安全修复：已移除 flash 分类，Flash 已被主流浏览器弃用且存在安全风险
	// 'flash' => array('swf','fla','as'),
	'image' => array('gif','jpg','jpeg','png','bmp'),
	'office' => array('doc','xls','ppt','docx','xlsx','pptx'),
	'pdf' => array('pdf'),
	'text' => array('c','cpp','cc', 'txt'),
	'zip' => array('tar','zip','gz','rar','7z','bz'),
	'book' => array('chm'),
	'torrent' => array('bt','torrent'),
	'font' => array('ttf','font','fon'),
	'other' => array(),
);

?>