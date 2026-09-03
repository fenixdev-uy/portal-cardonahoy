<?php
/**
 * Endpoint para subir imágenes desde el editor de texto enriquecido.
 * Devuelve JSON con la ruta relativa a la raíz de landing/.
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
limpiar_imagenes_huerfanas_antiguas(48, 20);

iniciar_sesion_segura();
$ahora = time();
$subidas = array_values(array_filter($_SESSION['subidas_recientes'] ?? [], fn($ts) => (int) $ts > $ahora - 3600));
if (count($subidas) >= 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Se alcanzo el limite temporal de subidas. Intenta mas tarde.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['imagen']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ninguna imagen.']);
    exit;
}

try {
    $ruta = subir_imagen($_FILES['imagen']);

    if ($ruta === null) {
        http_response_code(400);
        echo json_encode(['error' => 'No se seleccionó ninguna imagen.']);
        exit;
    }

    $subidas[] = $ahora;
    $_SESSION['subidas_recientes'] = $subidas;
    $_SESSION['archivos_subidos'][$ruta] = $ahora;
    $respuesta = ['url' => $ruta];
    if (($_POST['uso'] ?? '') === 'seo') {
        try {
            $variantesSeo = generar_variantes_imagen_seo($ruta);
            $respuesta['seo_preview_url'] = $variantesSeo['social']['ruta'];
            $respuesta['seo_peso'] = $variantesSeo['social']['peso'];
        } catch (Throwable $e) {
            eliminar_imagen($ruta);
            unset($_SESSION['archivos_subidos'][$ruta]);
            throw $e;
        }
    }
    echo json_encode($respuesta, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo procesar la imagen.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
