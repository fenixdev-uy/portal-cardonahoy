<?php
/** Sube audios del formulario de noticia y devuelve su URL relativa. */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
exigir_permiso('archivos.subir', true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

/** Convierte valores de php.ini como 8M o 1G a bytes. */
function bytes_ini_audio(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') return 0;
    $numero = (float) $valor;
    return match (strtolower(substr($valor, -1))) {
        'g' => (int) ($numero * 1024 * 1024 * 1024),
        'm' => (int) ($numero * 1024 * 1024),
        'k' => (int) ($numero * 1024),
        default => (int) $numero,
    };
}

// Si el cuerpo supera post_max_size, PHP vacía $_POST y $_FILES antes de que
// corra este archivo. Se detecta antes del CSRF para no culpar a la sesión.
$largoPeticion = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$maximoPost = bytes_ini_audio((string) ini_get('post_max_size'));
if ($maximoPost > 0 && $largoPeticion > $maximoPost) {
    http_response_code(413);
    echo json_encode([
        'error' => 'El audio supera el tamaño permitido de 25 MB.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

verificar_csrf(true);
iniciar_sesion_segura();
limpiar_audios_huerfanos_antiguos(48, 10);

$ahora = time();
$subidas = array_values(array_filter(
    $_SESSION['subidas_audio_recientes'] ?? [],
    static fn($ts) => (int) $ts > $ahora - 3600
));
if (count($subidas) >= 20) {
    http_response_code(429);
    echo json_encode(['error' => 'Se alcanzó el límite temporal de audios. Intentá más tarde.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['audio']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ningún audio.']);
    exit;
}

try {
    $ruta = subir_audio($_FILES['audio']);
    if ($ruta === null) {
        http_response_code(400);
        echo json_encode(['error' => 'No se seleccionó ningún audio.']);
        exit;
    }

    $subidas[] = $ahora;
    $_SESSION['subidas_audio_recientes'] = $subidas;
    $_SESSION['archivos_subidos'][$ruta] = $ahora;
    echo json_encode(['url' => $ruta], JSON_UNESCAPED_SLASHES);
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
