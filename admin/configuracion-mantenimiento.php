<?php
/** Guarda el modo mantenimiento y su presentación pública. */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

exigir_permiso('mantenimiento.gestionar', true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
verificar_csrf(true);

$accion = (string) ($_POST['accion'] ?? 'configuracion');
$activo = (string) ($_POST['activo'] ?? '0') === '1';
$archivoNuevo = null;

try {
    $pdo = db();
    $actual = configuracion_mantenimiento();

    if ($accion === 'estado') {
        $stmt = $pdo->prepare(
            'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
        );
        $stmt->execute(['mantenimiento_activo', $activo ? '1' : '0']);
        echo json_encode([
            'ok' => true,
            'activo' => $activo,
            'mensaje' => $activo ? 'Modo mantenimiento activado.' : 'Portal disponible nuevamente.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mensaje = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['mensaje'] ?? '')) ?? '');
    if ($mensaje === '' || mb_strlen($mensaje) > 160) {
        throw new RuntimeException('El mensaje debe tener entre 1 y 160 caracteres.');
    }
    $tamano = filter_var($_POST['logo_tamano'] ?? null, FILTER_VALIDATE_INT);
    if ($tamano === false || $tamano < 25 || $tamano > 80) {
        throw new RuntimeException('El tamaño del logo debe estar entre 25% y 80%.');
    }
    $mostrarLogin = (string) ($_POST['mostrar_login'] ?? '0') === '1';
    $rutaLogo = $actual['logo_personalizado'] ? $actual['logo_ruta'] : '';
    $archivo = $_FILES['logo_mantenimiento'] ?? null;

    if (is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo recibir la imagen.');
        }
        if (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > 3 * 1024 * 1024) {
            throw new RuntimeException('La imagen debe pesar como máximo 3 MB.');
        }

        $info = @getimagesize($archivo['tmp_name']);
        $formatos = [
            'image/jpeg' => ['extension' => 'jpg', 'crear' => 'imagecreatefromjpeg', 'guardar' => 'imagejpeg'],
            'image/png' => ['extension' => 'png', 'crear' => 'imagecreatefrompng', 'guardar' => 'imagepng'],
            'image/webp' => ['extension' => 'webp', 'crear' => 'imagecreatefromwebp', 'guardar' => 'imagewebp'],
        ];
        $mime = (string) ($info['mime'] ?? '');
        if ($info === false || !isset($formatos[$mime])) {
            throw new RuntimeException('Elegí una imagen JPG, PNG o WEBP.');
        }
        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);
        if ($ancho <= 0 || $alto <= 0 || ($ancho * $alto) > 24_000_000) {
            throw new RuntimeException('La imagen tiene dimensiones demasiado grandes.');
        }

        $formato = $formatos[$mime];
        if (!function_exists($formato['crear']) || !function_exists($formato['guardar'])) {
            throw new RuntimeException('El servidor no puede procesar este formato de imagen.');
        }
        $imagen = @$formato['crear']($archivo['tmp_name']);
        if ($imagen === false) {
            throw new RuntimeException('No se pudo procesar la imagen.');
        }
        if ($mime === 'image/png') {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
        }

        $directorio = dirname(__DIR__) . '/uploads/configuracion';
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            imagedestroy($imagen);
            throw new RuntimeException('No se pudo preparar la carpeta de configuración.');
        }
        $sufijo = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $rutaLogo = 'uploads/configuracion/mantenimiento_' . $sufijo . '.' . $formato['extension'];
        $archivoNuevo = dirname(__DIR__) . '/' . $rutaLogo;
        $temporal = $archivoNuevo . '.tmp';
        $guardado = $mime === 'image/png'
            ? $formato['guardar']($imagen, $temporal, 6)
            : $formato['guardar']($imagen, $temporal, 90);
        imagedestroy($imagen);
        if (!$guardado || !is_file($temporal) || filesize($temporal) === 0 || !rename($temporal, $archivoNuevo)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo guardar la imagen.');
        }
        @chmod($archivoNuevo, 0644);
    }

    $guardar = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
    );
    $pdo->beginTransaction();
    foreach ([
        'mantenimiento_activo' => $activo ? '1' : '0',
        'mantenimiento_logo_ruta' => $rutaLogo,
        'mantenimiento_logo_tamano' => (string) $tamano,
        'mantenimiento_mensaje' => $mensaje,
        'mantenimiento_mostrar_login' => $mostrarLogin ? '1' : '0',
    ] as $clave => $valor) {
        $guardar->execute([$clave, $valor]);
    }
    $pdo->commit();

    if ($archivoNuevo !== null
        && $actual['logo_personalizado']
        && preg_match('#^uploads/configuracion/mantenimiento_[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#', $actual['logo_ruta'])) {
        $anterior = dirname(__DIR__) . '/' . $actual['logo_ruta'];
        if (is_file($anterior)) @unlink($anterior);
    }

    $efectiva = configuracion_mantenimiento();
    $archivoEfectivo = dirname(__DIR__) . '/' . $efectiva['logo_ruta'];
    echo json_encode([
        'ok' => true,
        'activo' => $efectiva['activo'],
        'mostrar_login' => $efectiva['mostrar_login'],
        'logo_tamano' => $efectiva['logo_tamano'],
        'mensaje_publico' => $efectiva['mensaje'],
        'logo_url' => url_imagen($efectiva['logo_ruta']) . '?v=' . rawurlencode((string) (filemtime($archivoEfectivo) ?: time())),
        'mensaje' => 'Configuración de mantenimiento guardada.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($archivoNuevo !== null && is_file($archivoNuevo)) @unlink($archivoNuevo);
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el modo mantenimiento.'], JSON_UNESCAPED_UNICODE);
}
