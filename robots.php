<?php
require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('robots');
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /install/\nSitemap: " . url_portal('sitemap.xml') . "\n";
