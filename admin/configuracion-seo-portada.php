<?php
/** Guarda el SEO global de la página principal. */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
exigir_login(true);
verificar_csrf(true);

$pagina = (string) ($_POST['pagina'] ?? 'home');
if ($pagina !== 'home') {
    http_response_code(422);
    echo json_encode(['error' => 'La página seleccionada no es válida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!tiene_permiso('paginas.gestionar')) {
    http_response_code(403);
    echo json_encode(['error' => 'No tenés permiso para gestionar esta página.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tituloPersonalizado = ($_POST['titulo_personalizado'] ?? '') === '1';
$descripcionPersonalizada = ($_POST['descripcion_personalizada'] ?? '') === '1';
$imagenAutomatica = ($_POST['imagen_automatica'] ?? '') === '1';
$titulo = trim(strip_tags((string) ($_POST['titulo'] ?? '')));
$descripcion = trim(strip_tags((string) ($_POST['descripcion'] ?? '')));
$archivo = $_FILES['imagen'] ?? null;
$archivoNuevo = null;
$rutaNueva = null;

try {
    if ($tituloPersonalizado && $titulo === '') throw new RuntimeException('El título SEO personalizado no puede quedar vacío.');
    if ($descripcionPersonalizada && $descripcion === '') throw new RuntimeException('La descripción SEO personalizada no puede quedar vacía.');
    if (mb_strlen($titulo) > 255) throw new RuntimeException('El título SEO supera los 255 caracteres.');
    if (mb_strlen($descripcion) > 500) throw new RuntimeException('La descripción SEO supera los 500 caracteres.');

    $hayArchivo = is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hayArchivo) {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir la imagen SEO.');
        if (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > 5 * 1024 * 1024) throw new RuntimeException('La imagen SEO debe pesar como máximo 5 MB.');
        $info = @getimagesize($archivo['tmp_name']);
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (string) ($info['mime'] ?? '');
        if ($info === false || !isset($tipos[$mime])) throw new RuntimeException('La imagen SEO debe ser JPG, PNG o WEBP.');
        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);
        if ($ancho <= 0 || $alto <= 0 || ($ancho * $alto) > 40_000_000) throw new RuntimeException('La imagen SEO tiene dimensiones demasiado grandes.');
        if (!extension_loaded('gd')) throw new RuntimeException('El servidor no puede procesar imágenes en este momento.');

        $origen = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($archivo['tmp_name']),
            'image/png' => @imagecreatefrompng($archivo['tmp_name']),
            'image/webp' => @imagecreatefromwebp($archivo['tmp_name']),
        };
        if ($origen === false) throw new RuntimeException('No se pudo procesar la imagen SEO.');
        if ($mime === 'image/png') {
            imagealphablending($origen, false);
            imagesavealpha($origen, true);
        }

        $directorio = dirname(__DIR__) . '/uploads/configuracion';
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            imagedestroy($origen);
            throw new RuntimeException('No se pudo preparar la carpeta de configuración.');
        }
        $nombre = 'seo_portada_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $tipos[$mime];
        $rutaNueva = 'uploads/configuracion/' . $nombre;
        $archivoNuevo = $directorio . '/' . $nombre;
        $temporal = $archivoNuevo . '.tmp';
        $guardada = match ($mime) {
            'image/jpeg' => imagejpeg($origen, $temporal, 88),
            'image/png' => imagepng($origen, $temporal, 6),
            'image/webp' => imagewebp($origen, $temporal, 88),
        };
        imagedestroy($origen);
        if (!$guardada || !is_file($temporal) || filesize($temporal) === 0 || !rename($temporal, $archivoNuevo)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo guardar la imagen SEO.');
        }
        @chmod($archivoNuevo, 0644);
        $imagenAutomatica = false;
    }

    $anterior = configuracion_seo_portada();
    $pdo = db();
    $guardar = $pdo->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
    $borrar = $pdo->prepare('DELETE FROM configuracion WHERE clave = ?');
    $pdo->beginTransaction();
    $tituloPersonalizado ? $guardar->execute(['seo_portada_titulo', $titulo]) : $borrar->execute(['seo_portada_titulo']);
    $descripcionPersonalizada ? $guardar->execute(['seo_portada_descripcion', $descripcion]) : $borrar->execute(['seo_portada_descripcion']);
    if ($rutaNueva !== null) {
        $guardar->execute(['seo_portada_imagen', $rutaNueva]);
    } elseif ($imagenAutomatica) {
        $borrar->execute(['seo_portada_imagen']);
    }
    $pdo->commit();

    if (($rutaNueva !== null || $imagenAutomatica)
        && $anterior['imagen_personalizada']
        && preg_match('#^uploads/configuracion/seo_portada_[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#', $anterior['imagen_ruta'])) {
        $archivoAnterior = dirname(__DIR__) . '/' . $anterior['imagen_ruta'];
        if (is_file($archivoAnterior)) @unlink($archivoAnterior);
    }

    $seo = configuracion_seo_portada();
    echo json_encode([
        'ok' => true,
        'titulo' => $seo['titulo'],
        'descripcion' => $seo['descripcion'],
        'imagen_url' => $seo['imagen_url'] . '?v=' . rawurlencode((string) (@filemtime(dirname(__DIR__) . '/' . $seo['imagen_ruta']) ?: '1')),
        'imagen_personalizada' => $seo['imagen_personalizada'],
        'mensaje' => 'SEO de la página principal guardado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($archivoNuevo !== null && is_file($archivoNuevo)) @unlink($archivoNuevo);
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el SEO de la página principal.'], JSON_UNESCAPED_UNICODE);
}
