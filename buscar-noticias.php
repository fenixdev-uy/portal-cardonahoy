<?php
/** Búsqueda pública y acotada para el menú fullscreen de la portada PC. */

require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('json');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$entrada = $_GET['q'] ?? '';
$busqueda = is_string($entrada) ? trim($entrada) : '';
$busqueda = preg_replace('/\s+/u', ' ', $busqueda) ?? '';
$busqueda = mb_substr($busqueda, 0, 100, 'UTF-8');

if (mb_strlen($busqueda, 'UTF-8') < 2) {
    echo json_encode(['resultados' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// "=" funciona como carácter de escape para que %, _ y el propio = se
// busquen literalmente y no amplíen accidentalmente el patrón LIKE.
$patron = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $busqueda);
$patron = '%' . $patron . '%';

$pdo = db();
$estadosDisponibles = noticias_estados_disponibles($pdo);
$filtroEstado = $estadosDisponibles ? "n.estado = 'publicada' AND " : '';
$ordenFecha = $estadosDisponibles ? 'n.publicada_at' : 'n.created_at';
$stmt = $pdo->prepare(
    "SELECT n.id, n.titulo, n.slug, n.descripcion,
            (SELECT f.ruta
               FROM noticias_fotos f
              WHERE f.noticia_id = n.id
              ORDER BY f.posicion ASC, f.id ASC
              LIMIT 1) AS miniatura
       FROM noticias n
      WHERE " . $filtroEstado . "(n.titulo LIKE :patron_titulo ESCAPE '='
         OR n.descripcion LIKE :patron_descripcion ESCAPE '=')
      ORDER BY " . $ordenFecha . " DESC, n.id DESC
      LIMIT 5"
);
$stmt->execute([
    ':patron_titulo' => $patron,
    ':patron_descripcion' => $patron,
]);

$resultados = [];
foreach ($stmt->fetchAll() as $noticia) {
    $descripcionConEspacios = preg_replace(
        '/<\s*\/?(?:p|div|h[1-6]|li|blockquote|br|hr)\b[^>]*>/i',
        ' ',
        (string) $noticia['descripcion']
    );
    $resultados[] = [
        'id' => (int) $noticia['id'],
        'titulo' => (string) $noticia['titulo'],
        'descripcion' => html_a_texto($descripcionConEspacios, 180),
        'miniatura' => url_recurso_portal((string) ($noticia['miniatura'] ?? '')),
        'url' => url_noticia((string) $noticia['slug']),
    ];
}

echo json_encode(['resultados' => $resultados], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
