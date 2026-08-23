<?php
/**
 * Elimina una foto recién subida de la galería (aún no asociada a una noticia).
 * Solo se permiten rutas dentro de uploads/noticias/.
 */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

exigir_permiso('archivos.subir', true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

verificar_csrf(true);

$url = trim((string) ($_POST['url'] ?? ''));

if ($url === '' || !ruta_imagen_subida_valida($url)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ruta inválida.']);
    exit;
}

iniciar_sesion_segura();
if (!isset($_SESSION['archivos_subidos'][$url])) {
    http_response_code(403);
    echo json_encode(['error' => 'La imagen no pertenece a esta sesion.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare('SELECT COUNT(*) FROM noticias_fotos WHERE ruta = ?');
$stmt->execute([$url]);
if ((int) $stmt->fetchColumn() > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'La imagen ya esta asociada a una noticia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

eliminar_imagen($url);
unset($_SESSION['archivos_subidos'][$url]);

echo json_encode(['ok' => true]);
