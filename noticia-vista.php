<?php
/** Registra una lectura local sin duplicarla durante el mismo dia. */

require_once __DIR__ . '/admin/includes/metricas-noticias.php';
exigir_portal_disponible('json');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$noticiaId = (int) ($_POST['noticia_id'] ?? 0);
if ($noticiaId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Noticia inválida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$resultado = registrar_vista_noticia(db(), $noticiaId, visitante_id(true));
if ($resultado === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Noticia no encontrada'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true] + $resultado, JSON_UNESCAPED_UNICODE);
