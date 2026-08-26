<?php
/** Guarda la marca de agua y su opacidad desde el drawer de Configuración. */

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

$opacidad = filter_var($_POST['opacidad'] ?? null, FILTER_VALIDATE_INT);
if ($opacidad === false || $opacidad < 5 || $opacidad > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'La opacidad debe estar entre 5% y 100%.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$archivo = $_FILES['marca_agua'] ?? null;
$hayArchivo = is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$rutaNueva = null;
$archivoNuevo = null;

try {
    if ($hayArchivo) {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo recibir la marca de agua.');
        }
        if (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException('La marca de agua debe pesar como máximo 2 MB.');
        }
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false || ($info['mime'] ?? '') !== 'image/png') {
            throw new RuntimeException('La marca de agua debe ser una imagen PNG.');
        }
        $pixeles = (int) $info[0] * (int) $info[1];
        if ($pixeles <= 0 || $pixeles > 20_000_000) {
            throw new RuntimeException('La marca de agua tiene dimensiones demasiado grandes.');
        }
        if (!extension_loaded('gd')) {
            throw new RuntimeException('El servidor no puede procesar imágenes PNG en este momento.');
        }

        $logo = @imagecreatefrompng($archivo['tmp_name']);
        if ($logo === false) {
            throw new RuntimeException('No se pudo procesar la marca de agua.');
        }
        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        $directorio = dirname(__DIR__) . '/uploads/configuracion';
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            imagedestroy($logo);
            throw new RuntimeException('No se pudo preparar la carpeta de configuración.');
        }
        $nombre = 'marca_agua_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $rutaNueva = 'uploads/configuracion/' . $nombre;
        $archivoNuevo = $directorio . '/' . $nombre;
        $temporal = $archivoNuevo . '.tmp';
        $guardada = imagepng($logo, $temporal, 6);
        imagedestroy($logo);
        if (!$guardada || !is_file($temporal) || filesize($temporal) === 0 || !rename($temporal, $archivoNuevo)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo guardar la nueva marca de agua.');
        }
        @chmod($archivoNuevo, 0644);
    }

    $pdo = db();
    $configuracionAnterior = configuracion_marca_agua();
    $rutaActiva = $rutaNueva ?? $configuracionAnterior['ruta'];
    $stmt = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
    );
    $pdo->beginTransaction();
    $stmt->execute(['marca_agua_ruta', $rutaActiva]);
    $stmt->execute(['marca_agua_opacidad', (string) $opacidad]);
    $pdo->commit();

    if ($rutaNueva !== null
        && preg_match('#^uploads/configuracion/marca_agua_[A-Za-z0-9_-]+\.png$#', $configuracionAnterior['ruta'])) {
        $anterior = dirname(__DIR__) . '/' . $configuracionAnterior['ruta'];
        if (is_file($anterior)) @unlink($anterior);
    }

    $version = is_file(dirname(__DIR__) . '/' . $rutaActiva)
        ? (string) filemtime(dirname(__DIR__) . '/' . $rutaActiva)
        : (string) time();
    echo json_encode([
        'ok' => true,
        'ruta' => $rutaActiva,
        'logo_url' => url_imagen($rutaActiva) . '?v=' . rawurlencode($version),
        'opacidad' => $opacidad,
        'mensaje' => 'Configuración de marca de agua guardada.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($archivoNuevo !== null && is_file($archivoNuevo)) @unlink($archivoNuevo);
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar la configuración.'], JSON_UNESCAPED_UNICODE);
}
