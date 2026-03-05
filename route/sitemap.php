<?php

!defined('DEBUG') and exit('Access Denied.');

// Set XML header
header('Content-Type: application/xml; charset=utf-8');

$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$baseUrl = $scheme . "://" . $host;
$path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$path = rtrim($path, '/\\');
$siteUrl = $baseUrl . $path . "/";

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Add homepage
echo "  <url>\n";
echo "    <loc>" . htmlspecialchars($siteUrl) . "</loc>\n";
echo "    <changefreq>always</changefreq>\n";
echo "    <priority>1.0</priority>\n";
echo "  </url>\n";

// Add active forums
$forumlist = forum_list_cache();
if (!empty($forumlist)) {
    foreach ($forumlist as $fid => $forum) {
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($siteUrl . url("forum-$fid", '', false)) . "</loc>\n";
        echo "    <changefreq>daily</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
}

// Add recent threads (max 1000)
$threads = db_find('thread', array(), array('tid' => -1), 1, 1000, '', array('tid', 'last_date'));
if (!empty($threads)) {
    foreach ($threads as $thread) {
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($siteUrl . url("thread-{$thread['tid']}", '', false)) . "</loc>\n";
        $lastDate = date('c', $thread['last_date']);
        echo "    <lastmod>{$lastDate}</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "  </url>\n";
    }
}

echo '</urlset>';
exit;
?>
