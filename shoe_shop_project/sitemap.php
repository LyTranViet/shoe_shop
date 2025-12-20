<?php
header("Content-Type: application/xml; charset=utf-8");

$baseUrl = "https://shoeshop.dpdns.org/shoe_shop/shoe_shop_project";
$today = date("Y-m-d");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

<url>
    <loc><?= $baseUrl ?>/</loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
</url>

<url>
    <loc><?= $baseUrl ?>/about.php</loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
</url>

<url>
    <loc><?= $baseUrl ?>/cart.php</loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.5</priority>
</url>

</urlset>
