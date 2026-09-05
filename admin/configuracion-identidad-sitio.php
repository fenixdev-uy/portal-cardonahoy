<?php
/** Guarda la identidad editorial pública de esta instalación. */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

exigir_permiso('configuracion.gestionar', true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
verificar_csrf(true);

$nombre = trim(strip_tags((string) ($_POST['nombre_sitio'] ?? '')));
$nombre = preg_replace('/\s+/u', ' ', $nombre) ?? '';

try {
    $longitud = mb_strlen($nombre, 'UTF-8');
    if ($longitud < 2) throw new RuntimeException('El nombre del sitio debe tener al menos 2 caracteres.');
    if ($longitud > 120) throw new RuntimeException('El nombre del sitio supera los 120 caracteres.');
    if (preg_match('/[\p{Cc}\p{Cf}]/u', $nombre)) throw new RuntimeException('El nombre del sitio contiene caracteres no válidos.');

    $guardar = db()->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
    );
    $guardar->execute(['nombre_sitio', $nombre]);
    $automaticos = valores_seo_portada_automaticos($nombre);

    echo json_encode([
        'ok' => true,
        'nombre_sitio' => $nombre,
        'seo_titulo_automatico' => $automaticos['titulo'],
        'seo_descripcion_automatica' => $automaticos['descripcion'],
        'mensaje' => 'Identidad del sitio guardada correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar la identidad del sitio.'], JSON_UNESCAPED_UNICODE);
}
