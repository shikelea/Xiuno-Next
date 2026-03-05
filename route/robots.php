<?php

!defined('DEBUG') and exit('Access Denied.');

$route = param(0);

// Set header
header('Content-Type: text/plain; charset=utf-8');

// Basic robots.txt content
echo "User-agent: *\r\n";
echo "Disallow: /admin/\r\n";
echo "Disallow: /api/\r\n";
echo "Disallow: /install/\r\n";
echo "Disallow: /tmp/\r\n";
echo "Disallow: /log/\r\n";
echo "Disallow: /plugin/\r\n";
echo "Disallow: /upload/tmp/\r\n";
echo "\r\n";

if (isset($_SERVER['HTTP_HOST'])) {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $scheme . "://" . $host;
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = rtrim($path, '/\\');
    echo "Sitemap: " . $baseUrl . $path . "/sitemap.xml\r\n";
}

exit;

?>
