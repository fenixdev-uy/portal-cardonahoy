<?php
/** Guarda únicamente el logo del encabezado y menú público. */

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

$archivo = $_FILES['logo_portal'] ?? null;
if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    http_response_code(422);
    echo json_encode(['error' => 'Elegí un logo PNG para guardar.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$archivoLogo = null;
try {
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo recibir el logo.');
    }
    if (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('El logo debe pesar como máximo 2 MB.');
    }

    $info = @getimagesize($archivo['tmp_name']);
    if ($info === false || ($info['mime'] ?? '') !== 'image/png') {
        throw new RuntimeException('El logo debe ser una imagen PNG.');
    }
    $ancho = (int) ($info[0] ?? 0);
    $alto = (int) ($info[1] ?? 0);
    if ($ancho <= 0 || $alto <= 0 || ($ancho * $alto) > 20_000_000) {
        throw new RuntimeException('El logo tiene dimensiones demasiado grandes.');
    }

    $logo = @imagecreatefrompng($archivo['tmp_name']);
    if ($logo === false) {
        throw new RuntimeException('No se pudo procesar el logo.');
    }
    imagealphablending($logo, false);
    imagesavealpha($logo, true);

    $directorio = dirname(__DIR__) . '/uploads/configuracion';
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        imagedestroy($logo);
        throw new RuntimeException('No se pudo preparar la carpeta de configuración.');
    }

    $sufijo = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $rutaLogo = 'uploads/configuracion/logo_portal_' . $sufijo . '.png';
    $archivoLogo = dirname(__DIR__) . '/' . $rutaLogo;
    $temporalLogo = $archivoLogo . '.tmp';

    $guardadoLogo = imagepng($logo, $temporalLogo, 6);
    imagedestroy($logo);
    if (!$guardadoLogo || !is_file($temporalLogo) || filesize($temporalLogo) === 0 || !rename($temporalLogo, $archivoLogo)) {
        @unlink($temporalLogo);
        throw new RuntimeException('No se pudo guardar el nuevo logo.');
    }

    @chmod($archivoLogo, 0644);

    $anterior = configuracion_logo_portal();
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
    );
    $pdo->beginTransaction();
    $stmt->execute(['logo_portal_ruta', $rutaLogo]);
    $pdo->commit();

    if (preg_match('#^uploads/configuracion/logo_portal_[A-Za-z0-9_-]+\.png$#', $anterior)) {
        $archivoAnterior = dirname(__DIR__) . '/' . $anterior;
        if (is_file($archivoAnterior)) @unlink($archivoAnterior);
    }

    echo json_encode([
        'ok' => true,
        'logo_url' => url_imagen($rutaLogo) . '?v=' . rawurlencode((string) filemtime($archivoLogo)),
        'ancho' => $ancho,
        'alto' => $alto,
        'mensaje' => 'Logo del portal guardado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($archivoLogo !== null && is_file($archivoLogo)) @unlink($archivoLogo);
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el logo del portal.'], JSON_UNESCAPED_UNICODE);
}
