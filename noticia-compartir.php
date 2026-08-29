<?php
/** Cuenta el clic real sobre un boton de compartir. */

require_once __DIR__ . '/admin/includes/metricas-noticias.php';

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
$destino = strtolower(trim((string) ($_POST['destino'] ?? '')));
if ($noticiaId <= 0 || !in_array($destino, ['facebook', 'whatsapp'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Datos inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!registrar_compartido_noticia(db(), $noticiaId, $destino)) {
    http_response_code(404);
    echo json_encode(['error' => 'Noticia no encontrada'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
