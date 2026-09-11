<?php
require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('text');
header('Content-Type: application/xml; charset=utf-8');
$pdo = db();
$filtroEstado = noticias_estados_disponibles($pdo) ? "estado = 'publicada' AND " : '';
$noticias = $pdo->query("SELECT slug, updated_at FROM noticias WHERE {$filtroEstado}slug <> '' ORDER BY updated_at DESC")->fetchAll();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= e(url_base_portal()) ?></loc><lastmod><?= e(date('c', filemtime(__DIR__ . '/index.php'))) ?></lastmod></url>
<?php foreach ($noticias as $noticia): ?>  <url><loc><?= e(url_noticia($noticia['slug'])) ?></loc><lastmod><?= e(date('c', strtotime($noticia['updated_at']))) ?></lastmod></url>
<?php endforeach; ?></urlset>
