<?php
/** Favicon configurable desde la identidad del Admin. */

require_once __DIR__ . '/admin/includes/funciones.php';

try {
    $identidad = configuracion_logo_admin();
    if ($identidad['favicon_ruta'] !== null) {
        $archivo = __DIR__ . '/' . $identidad['favicon_ruta'];
        $contenido = file_get_contents($archivo);
    } else {
        $archivo = __DIR__ . '/imagenes/Logo2027v2.png';
        $contenido = generar_favicon_ico_desde_png($archivo);
    }
    if (!is_string($contenido) || $contenido === '') {
        throw new RuntimeException('Favicon vacío.');
    }

    $etag = '"' . hash('sha256', $contenido) . '"';
    header('Content-Type: image/x-icon');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: public, max-age=86400');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    echo $contenido;
} catch (Throwable $e) {
    http_response_code(404);
}
