<?php
/** Guarda el logo mostrado en la pantalla de ingreso del panel. */

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

$archivo = $_FILES['logo_login'] ?? null;
$hayArchivo = is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$tamano = filter_var($_POST['tamano'] ?? null, FILTER_VALIDATE_INT);
if ($tamano === false || $tamano < 60 || $tamano > 140) {
    http_response_code(422);
    echo json_encode(['error' => 'El tamaño del logo debe estar entre 60% y 140%.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$archivoNuevo = null;
try {
    $rutaAnterior = configuracion_logo_login();
    $rutaNueva = $rutaAnterior;
    $ancho = null;
    $alto = null;

    if ($hayArchivo && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo recibir el logo.');
    }
    if ($hayArchivo && (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > 2 * 1024 * 1024)) {
        throw new RuntimeException('El logo debe pesar como máximo 2 MB.');
    }

    $info = $hayArchivo ? @getimagesize($archivo['tmp_name']) : null;
    if ($hayArchivo && ($info === false || ($info['mime'] ?? '') !== 'image/png')) {
        throw new RuntimeException('El logo debe ser una imagen PNG.');
    }
    if ($hayArchivo) {
        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);
    }
    if ($hayArchivo && ($ancho <= 0 || $alto <= 0 || ($ancho * $alto) > 20_000_000)) {
        throw new RuntimeException('El logo tiene dimensiones demasiado grandes.');
    }
    if ($hayArchivo && !extension_loaded('gd')) {
        throw new RuntimeException('El servidor no puede procesar imágenes PNG en este momento.');
    }

    if ($hayArchivo) {
        $logo = @imagecreatefrompng($archivo['tmp_name']);
        if ($logo === false) throw new RuntimeException('No se pudo procesar el logo.');
        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        $directorio = dirname(__DIR__) . '/uploads/configuracion';
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            imagedestroy($logo);
            throw new RuntimeException('No se pudo preparar la carpeta de configuración.');
        }

        $nombre = 'logo_login_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $rutaNueva = 'uploads/configuracion/' . $nombre;
        $archivoNuevo = $directorio . '/' . $nombre;
        $temporal = $archivoNuevo . '.tmp';
        $guardada = imagepng($logo, $temporal, 6);
        imagedestroy($logo);
        if (!$guardada || !is_file($temporal) || filesize($temporal) === 0 || !rename($temporal, $archivoNuevo)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo guardar el nuevo logo.');
        }
        @chmod($archivoNuevo, 0644);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
    );
    $pdo->beginTransaction();
    $stmt->execute(['logo_login_ruta', $rutaNueva]);
    $stmt->execute(['logo_login_tamano', (string) $tamano]);
    $pdo->commit();

    if ($hayArchivo && preg_match('#^uploads/configuracion/logo_login_[A-Za-z0-9_-]+\.png$#', $rutaAnterior)) {
        $anterior = dirname(__DIR__) . '/' . $rutaAnterior;
        if (is_file($anterior)) @unlink($anterior);
    }

    $archivoEfectivo = dirname(__DIR__) . '/' . $rutaNueva;

    echo json_encode([
        'ok' => true,
        'ruta' => $rutaNueva,
        'logo_url' => url_imagen($rutaNueva) . '?v=' . rawurlencode(is_file($archivoEfectivo) ? (string) filemtime($archivoEfectivo) : '1'),
        'ancho' => $ancho,
        'alto' => $alto,
        'tamano' => (int) $tamano,
        'mensaje' => $hayArchivo ? 'Logo y tamaño del login guardados correctamente.' : 'Tamaño del logo del login guardado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($archivoNuevo !== null && is_file($archivoNuevo)) @unlink($archivoNuevo);
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el logo.'], JSON_UNESCAPED_UNICODE);
}
