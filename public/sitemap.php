<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
$base=rtrim((string)app_config('app.base_url',''),'/');
header('Content-Type: application/xml; charset=UTF-8');
$items=db()->query('SELECT id,updated_at FROM items WHERE availability=1 ORDER BY id DESC LIMIT 40000')->fetchAll();
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
echo '<url><loc>'.htmlspecialchars($base.'/',ENT_XML1,'UTF-8').'</loc></url>';
echo '<url><loc>'.htmlspecialchars($base.'/rankings.php',ENT_XML1,'UTF-8').'</loc></url>';
foreach($items as $item){
    echo '<url><loc>'.htmlspecialchars($base.'/item.php?id='.(int)$item['id'],ENT_XML1,'UTF-8').'</loc><lastmod>'.htmlspecialchars(substr((string)$item['updated_at'],0,10),ENT_XML1,'UTF-8').'</lastmod></url>';
}
echo '</urlset>';
