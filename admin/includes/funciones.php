<?php
/**
 * Funciones auxiliares del administrador.
 */

require_once __DIR__ . '/auth.php';

const PORTADA_NOTICIAS_LIMITE = 5;

/**
 * Bloquea la selección actual de Portada y evita agregar una sexta noticia.
 * Debe ejecutarse dentro de la misma transacción que guarda el cambio.
 */
function exigir_cupo_noticia_portada(PDO $pdo, int $noticiaId): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('El cupo de Portada debe validarse dentro de una transacción.');
    }

    $ids = array_map(
        'intval',
        $pdo->query('SELECT id FROM noticias WHERE portada = 1 ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)
    );

    if (!in_array($noticiaId, $ids, true) && count($ids) >= PORTADA_NOTICIAS_LIMITE) {
        throw new DomainException(
            'La portada admite un máximo de ' . PORTADA_NOTICIAS_LIMITE
            . ' noticias destacadas. Desmarcá una antes de agregar otra.'
        );
    }
}

/**
 * Arranca la sesión si aún no está iniciada.
 */
function iniciar_sesion(): void
{
    iniciar_sesion_segura();
}

/**
 * Guarda un mensaje flash en sesión.
 */
function flash(string $tipo, string $mensaje): void
{
    iniciar_sesion();
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/**
 * Devuelve y limpia los mensajes flash.
 *
 * @return array<int, array{tipo: string, mensaje: string}>
 */
function obtener_flash(): array
{
    iniciar_sesion();
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

/**
 * Configuración efectiva de la marca de agua.
 * Mantiene el logo histórico como fallback para instalaciones sin migrar.
 *
 * @return array{ruta:string,opacidad:int,tamano:int}
 */
function configuracion_marca_agua(): array
{
    $ruta = 'imagenes/Logo2027v2.png';
    $opacidad = 15;
    $tamano = 36;

    try {
        $stmt = db()->query("SELECT clave, valor FROM configuracion WHERE clave IN ('marca_agua_ruta', 'marca_agua_opacidad', 'marca_agua_tamano')");
        foreach ($stmt->fetchAll() as $fila) {
            if ($fila['clave'] === 'marca_agua_ruta') {
                $candidata = trim((string) $fila['valor']);
                if ($candidata === 'imagenes/Logo2027v2.png'
                    || preg_match('#^uploads/configuracion/marca_agua_[A-Za-z0-9_-]+\.png$#', $candidata)) {
                    $ruta = $candidata;
                }
            } elseif ($fila['clave'] === 'marca_agua_opacidad') {
                $opacidad = max(5, min(100, (int) $fila['valor']));
            } elseif ($fila['clave'] === 'marca_agua_tamano') {
                $tamano = max(15, min(65, (int) $fila['valor']));
            }
        }
    } catch (PDOException $e) {
        // La migración puede estar pendiente durante un despliegue incremental.
    }

    $raiz = dirname(__DIR__, 2);
    if (!is_file($raiz . '/' . $ruta)) {
        $ruta = 'imagenes/Logo2027v2.png';
    }

    return ['ruta' => $ruta, 'opacidad' => $opacidad, 'tamano' => $tamano];
}

/**
 * Logo efectivo de la pantalla de ingreso.
 * Mantiene el recurso actual como fallback para instalaciones anteriores.
 */
function configuracion_logo_login(): string
{
    $ruta = 'imagenes/Logo2027v3.png';

    try {
        $stmt = db()->prepare("SELECT valor FROM configuracion WHERE clave = 'logo_login_ruta' LIMIT 1");
        $stmt->execute();
        $candidata = trim((string) $stmt->fetchColumn());
        if ($candidata === 'imagenes/Logo2027v3.png'
            || preg_match('#^uploads/configuracion/logo_login_[A-Za-z0-9_-]+\.png$#', $candidata)) {
            $ruta = $candidata;
        }
    } catch (PDOException $e) {
        // La tabla de configuración puede estar pendiente durante un despliegue incremental.
    }

    if (!is_file(dirname(__DIR__, 2) . '/' . $ruta)) {
        $ruta = 'imagenes/Logo2027v3.png';
    }

    return $ruta;
}

/** Tamaño porcentual del logo de ingreso (100 mantiene el diseño original). */
function configuracion_logo_login_tamano(): int
{
    $tamano = 100;

    try {
        $stmt = db()->prepare("SELECT valor FROM configuracion WHERE clave = 'logo_login_tamano' LIMIT 1");
        $stmt->execute();
        $valor = filter_var($stmt->fetchColumn(), FILTER_VALIDATE_INT);
        if ($valor !== false) $tamano = max(60, min(140, (int) $valor));
    } catch (PDOException $e) {
        // El valor predeterminado conserva compatibilidad con instalaciones anteriores.
    }

    return $tamano;
}

/** Logo efectivo del encabezado y menú público. */
function configuracion_logo_portal(): string
{
    $ruta = 'imagenes/Logo2027v2.png';

    try {
        $stmt = db()->prepare("SELECT valor FROM configuracion WHERE clave = 'logo_portal_ruta' LIMIT 1");
        $stmt->execute();
        $valor = trim((string) $stmt->fetchColumn());
        if ($valor === 'imagenes/Logo2027v2.png'
            || preg_match('#^uploads/configuracion/logo_portal_[A-Za-z0-9_-]+\.png$#', $valor)) {
            $ruta = $valor;
        }
    } catch (PDOException $e) {
        // La tabla de configuración puede estar pendiente durante un despliegue incremental.
    }

    $raiz = dirname(__DIR__, 2);
    if (!is_file($raiz . '/' . $ruta)) {
        $ruta = 'imagenes/Logo2027v2.png';
    }

    return $ruta;
}

/** Tamaño porcentual compartido por el logo del encabezado y del menú público. */
function configuracion_logo_portal_tamano(): int
{
    $tamano = 100;

    try {
        $stmt = db()->prepare("SELECT valor FROM configuracion WHERE clave = 'logo_portal_tamano' LIMIT 1");
        $stmt->execute();
        $valor = filter_var($stmt->fetchColumn(), FILTER_VALIDATE_INT);
        if ($valor !== false) $tamano = max(60, min(140, (int) $valor));
    } catch (PDOException $e) {
        // El valor predeterminado conserva compatibilidad con instalaciones anteriores.
    }

    return $tamano;
}

/**
 * Configuración efectiva de la pantalla pública de mantenimiento.
 * Si todavía no se aplicó la migración, el portal continúa activo normalmente.
 *
 * @return array{activo:bool,logo_ruta:string,logo_personalizado:bool,logo_tamano:int,mensaje:string,mostrar_login:bool}
 */
function configuracion_mantenimiento(): array
{
    $activo = false;
    $logoRuta = configuracion_logo_portal();
    $logoPersonalizado = false;
    $logoTamano = 58;
    $mensaje = 'En mantenimiento, ¡volvemos pronto!';
    $mostrarLogin = true;

    try {
        $stmt = db()->query(
            "SELECT clave, valor FROM configuracion WHERE clave IN (
                'mantenimiento_activo',
                'mantenimiento_logo_ruta',
                'mantenimiento_logo_tamano',
                'mantenimiento_mensaje',
                'mantenimiento_mostrar_login'
            )"
        );
        foreach ($stmt->fetchAll() as $fila) {
            $valor = trim((string) $fila['valor']);
            if ($fila['clave'] === 'mantenimiento_activo') {
                $activo = $valor === '1';
            } elseif ($fila['clave'] === 'mantenimiento_logo_ruta'
                && preg_match('#^uploads/configuracion/mantenimiento_[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#', $valor)) {
                $logoRuta = $valor;
                $logoPersonalizado = true;
            } elseif ($fila['clave'] === 'mantenimiento_logo_tamano') {
                $logoTamano = max(25, min(80, (int) $valor));
            } elseif ($fila['clave'] === 'mantenimiento_mensaje' && $valor !== '') {
                $mensaje = mb_substr($valor, 0, 160);
            } elseif ($fila['clave'] === 'mantenimiento_mostrar_login') {
                $mostrarLogin = $valor === '1';
            }
        }
    } catch (PDOException $e) {
        // La ausencia de la migración nunca debe bloquear accidentalmente el portal.
    }

    if (!is_file(dirname(__DIR__, 2) . '/' . $logoRuta)) {
        $logoRuta = configuracion_logo_portal();
        $logoPersonalizado = false;
    }

    return [
        'activo' => $activo,
        'logo_ruta' => $logoRuta,
        'logo_personalizado' => $logoPersonalizado,
        'logo_tamano' => $logoTamano,
        'mensaje' => $mensaje,
        'mostrar_login' => $mostrarLogin,
    ];
}

/** Indica si la sesión pública actual puede atravesar el modo mantenimiento. */
function usuario_puede_omitir_mantenimiento(): bool
{
    $usuario = usuario_actual_publico();
    return $usuario !== null
        && rol_tiene_permiso((int) ($usuario['rol_id'] ?? 0), 'mantenimiento.gestionar');
}

/**
 * Detiene una ruta pública cuando el portal está en mantenimiento.
 * Los endpoints usan JSON/texto; portada y noticias reciben la pantalla visual.
 */
function exigir_portal_disponible(string $respuesta = 'html'): void
{
    $configuracionMantenimiento = configuracion_mantenimiento();
    if (!$configuracionMantenimiento['activo'] || usuario_puede_omitir_mantenimiento()) {
        return;
    }

    http_response_code(503);
    header('Cache-Control: no-store, max-age=0');
    header('Retry-After: 3600');
    header('X-Robots-Tag: noindex, nofollow', true);

    if ($respuesta === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'El portal se encuentra temporalmente en mantenimiento.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($respuesta === 'robots') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /\n";
        exit;
    }

    if ($respuesta === 'text') {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Portal temporalmente en mantenimiento.';
        exit;
    }

    require dirname(__DIR__, 2) . '/partials/mantenimiento.php';
    exit;
}

/**
 * Identidad del menú interno del Admin y favicon compartido por todo el sitio.
 * La marca del menú queda en null para conservar la "N" histórica hasta que
 * el administrador elija una imagen.
 *
 * @return array{ruta:string|null,favicon_ruta:string|null}
 */
function configuracion_logo_admin(): array
{
    $ruta = null;
    $faviconRuta = null;

    try {
        $stmt = db()->query("SELECT clave, valor FROM configuracion WHERE clave IN ('logo_admin_ruta', 'favicon_admin_ruta')");
        foreach ($stmt->fetchAll() as $fila) {
            $valor = trim((string) $fila['valor']);
            if ($fila['clave'] === 'logo_admin_ruta'
                && preg_match('#^uploads/configuracion/logo_admin_[A-Za-z0-9_-]+\.png$#', $valor)) {
                $ruta = $valor;
            } elseif ($fila['clave'] === 'favicon_admin_ruta'
                && preg_match('#^uploads/configuracion/favicon_admin_[A-Za-z0-9_-]+\.ico$#', $valor)) {
                $faviconRuta = $valor;
            }
        }
    } catch (PDOException $e) {
        // La tabla de configuración puede estar pendiente durante un despliegue incremental.
    }

    $raiz = dirname(__DIR__, 2);
    if ($ruta !== null && !is_file($raiz . '/' . $ruta)) {
        $ruta = null;
    }
    if ($faviconRuta !== null && !is_file($raiz . '/' . $faviconRuta)) {
        $faviconRuta = null;
    }

    return ['ruta' => $ruta, 'favicon_ruta' => $faviconRuta];
}

/**
 * SEO efectivo de la página principal.
 * Las claves ausentes mantienen valores automáticos seguros.
 *
 * @return array{titulo:string,descripcion:string,imagen_ruta:string,imagen_url:string,titulo_personalizado:bool,descripcion_personalizada:bool,imagen_personalizada:bool,url:string}
 */
function configuracion_seo_portada(): array
{
    $tituloAutomatico = 'Radio Sur | Noticias de Colonia y la región';
    $descripcionAutomatica = 'Últimas noticias de Colonia, Uruguay y la región. Información local, actualidad, deportes, cultura y comunidad en Radio Sur.';
    $titulo = $tituloAutomatico;
    $descripcion = $descripcionAutomatica;
    $imagenRuta = 'imagenes/Logo2027v3.png';
    $tituloPersonalizado = false;
    $descripcionPersonalizada = false;
    $imagenPersonalizada = false;

    try {
        $stmt = db()->query("SELECT clave, valor FROM configuracion WHERE clave IN ('seo_portada_titulo', 'seo_portada_descripcion', 'seo_portada_imagen')");
        foreach ($stmt->fetchAll() as $fila) {
            $valor = trim((string) $fila['valor']);
            if ($fila['clave'] === 'seo_portada_titulo' && $valor !== '') {
                $titulo = $valor;
                $tituloPersonalizado = true;
            } elseif ($fila['clave'] === 'seo_portada_descripcion' && $valor !== '') {
                $descripcion = $valor;
                $descripcionPersonalizada = true;
            } elseif ($fila['clave'] === 'seo_portada_imagen'
                && preg_match('#^uploads/configuracion/seo_portada_[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#', $valor)) {
                $imagenRuta = $valor;
                $imagenPersonalizada = true;
            }
        }
    } catch (PDOException $e) {
        // La tabla de configuración puede estar pendiente durante un despliegue incremental.
    }

    $raiz = dirname(__DIR__, 2);
    if (!is_file($raiz . '/' . $imagenRuta)) {
        $imagenRuta = 'imagenes/Logo2027v3.png';
        $imagenPersonalizada = false;
    }

    return [
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'imagen_ruta' => $imagenRuta,
        'imagen_url' => url_recurso_portal($imagenRuta),
        'titulo_personalizado' => $tituloPersonalizado,
        'descripcion_personalizada' => $descripcionPersonalizada,
        'imagen_personalizada' => $imagenPersonalizada,
        'url' => url_portal(),
    ];
}

/**
 * Código de integraciones insertado al final del <head> público.
 *
 * @return array{codigo:string,activo:bool}
 */
function configuracion_codigo_header(): array
{
    $codigo = '';
    $activo = false;

    try {
        $stmt = db()->query("SELECT clave, valor FROM configuracion WHERE clave IN ('codigo_header_contenido', 'codigo_header_activo')");
        foreach ($stmt->fetchAll() as $fila) {
            if ($fila['clave'] === 'codigo_header_contenido') {
                $codigo = (string) $fila['valor'];
            } elseif ($fila['clave'] === 'codigo_header_activo') {
                $activo = (string) $fila['valor'] === '1';
            }
        }
    } catch (PDOException $e) {
        // La tabla de configuración puede estar pendiente durante un despliegue incremental.
    }

    return ['codigo' => $codigo, 'activo' => $activo && trim($codigo) !== ''];
}

/** Imprime deliberadamente HTML/JS administrado; usar solo dentro del head público. */
function imprimir_codigo_header_publico(): void
{
    $configuracion = configuracion_codigo_header();
    if (!$configuracion['activo']) return;
    echo "\n<!-- Código del Header administrado -->\n";
    echo $configuracion['codigo'];
    echo "\n<!-- Fin Código del Header administrado -->\n";
}

/** Genera un ICO de 256×256 cuyo único frame contiene un PNG cuadrado. */
function generar_favicon_ico_desde_png(string $rutaPng): string
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('La extensión GD no está disponible.');
    }
    $origen = @imagecreatefrompng($rutaPng);
    if ($origen === false) {
        throw new RuntimeException('No se pudo procesar el logo para crear el favicon.');
    }

    $lado = 256;
    $margen = 24;
    $destino = imagecreatetruecolor($lado, $lado);
    if ($destino === false) {
        imagedestroy($origen);
        throw new RuntimeException('No se pudo preparar el favicon.');
    }

    try {
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        $fondo = imagecolorallocate($destino, 15, 23, 42);
        imagefilledrectangle($destino, 0, 0, $lado, $lado, $fondo);

        $anchoOrigen = imagesx($origen);
        $altoOrigen = imagesy($origen);
        $escala = min(($lado - 2 * $margen) / $anchoOrigen, ($lado - 2 * $margen) / $altoOrigen);
        $anchoDestino = max(1, (int) round($anchoOrigen * $escala));
        $altoDestino = max(1, (int) round($altoOrigen * $escala));
        $x = (int) round(($lado - $anchoDestino) / 2);
        $y = (int) round(($lado - $altoDestino) / 2);
        imagealphablending($destino, true);
        imagecopyresampled($destino, $origen, $x, $y, 0, 0, $anchoDestino, $altoDestino, $anchoOrigen, $altoOrigen);

        ob_start();
        $guardada = imagepng($destino, null, 6);
        $png = ob_get_clean();
        if (!$guardada || !is_string($png) || $png === '') {
            throw new RuntimeException('No se pudo codificar el favicon.');
        }

        $cabecera = pack('vvv', 0, 1, 1);
        $entrada = pack('CCCCvvVV', 0, 0, 0, 0, 1, 32, strlen($png), 22);
        return $cabecera . $entrada . $png;
    } finally {
        imagedestroy($origen);
        imagedestroy($destino);
    }
}

/**
 * Sube una imagen y devuelve la ruta relativa pública, o null si no se subió.
 *
 * @param array $archivo Elemento de $_FILES.
 * @return string|null
 */
function subir_imagen(array $archivo): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir la imagen (código ' . $archivo['error'] . ').');
    }

    // Validación de tamaño (máx. 5 MB)
    if ($archivo['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen supera el tamaño máximo permitido (5 MB).');
    }

    $info = @getimagesize($archivo['tmp_name']);
    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if ($info === false || !isset($tiposPermitidos[$info['mime']])) {
        throw new RuntimeException('El archivo debe ser una imagen JPG, PNG o WEBP.');
    }

    $pixeles = (int) $info[0] * (int) $info[1];
    if ($pixeles <= 0 || $pixeles > 40_000_000) {
        throw new RuntimeException('La imagen tiene dimensiones demasiado grandes.');
    }

    $extension = $tiposPermitidos[$info['mime']];
    $directorio = dirname(__DIR__, 2) . '/uploads/noticias';

    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio de subidas.');
    }

    $nombre = 'noticia_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $rutaCompleta = $directorio . '/' . $nombre;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    try {
        aplicar_marca_agua_centrada($rutaCompleta, $info['mime']);
    } catch (Throwable $e) {
        @unlink($rutaCompleta);
        throw new RuntimeException('No se pudo aplicar la marca de agua a la imagen.');
    }

    @chmod($rutaCompleta, 0644);

    return 'uploads/noticias/' . $nombre;
}

/**
 * Rutas deterministas de las copias optimizadas usadas por metadatos sociales.
 * La imagen original continúa siendo la fuente y nunca se modifica.
 *
 * @return array{social:string,discover:string}|array{}
 */
function rutas_variantes_imagen_seo(string $rutaFuente): array
{
    if (!ruta_imagen_subida_valida($rutaFuente)) return [];
    $nombreFuente = pathinfo(basename($rutaFuente), PATHINFO_FILENAME);
    return [
        'social' => 'uploads/noticias/seo_' . $nombreFuente . '_1200x630.jpg',
        'discover' => 'uploads/noticias/seo_' . $nombreFuente . '_1200x675.jpg',
    ];
}

/** @return array{social:string,discover:string}|array{} */
function variantes_imagen_seo_existentes(string $rutaFuente): array
{
    $variantes = rutas_variantes_imagen_seo($rutaFuente);
    if ($variantes === []) return [];
    $raiz = dirname(__DIR__, 2);
    return array_filter(
        $variantes,
        static fn(string $ruta): bool => is_file($raiz . '/' . $ruta) && filesize($raiz . '/' . $ruta) > 0
    );
}

/**
 * Lee Orientation desde APP1/EXIF sin depender de la extensión PHP exif.
 * Solo recorre los primeros 256 KB y valida todos los límites del bloque TIFF.
 */
function leer_orientacion_exif_jpeg(string $archivo): int
{
    $datos = @file_get_contents($archivo, false, null, 0, 262144);
    if (!is_string($datos) || strlen($datos) < 12 || substr($datos, 0, 2) !== "\xFF\xD8") return 1;
    $largo = strlen($datos);
    $posicion = 2;
    while ($posicion + 4 <= $largo) {
        if (ord($datos[$posicion]) !== 0xFF) break;
        while ($posicion < $largo && ord($datos[$posicion]) === 0xFF) $posicion++;
        if ($posicion >= $largo) break;
        $marcador = ord($datos[$posicion++]);
        if ($marcador === 0xD9 || $marcador === 0xDA) break;
        if ($marcador === 0x01 || ($marcador >= 0xD0 && $marcador <= 0xD8)) continue;
        if ($posicion + 2 > $largo) break;
        $largoSegmento = unpack('n', substr($datos, $posicion, 2))[1];
        if ($largoSegmento < 2 || $posicion + $largoSegmento > $largo) break;
        $inicio = $posicion + 2;
        if ($marcador === 0xE1 && $largoSegmento >= 16 && substr($datos, $inicio, 6) === "Exif\0\0") {
            $tiff = $inicio + 6;
            $orden = substr($datos, $tiff, 2);
            if ($orden !== 'II' && $orden !== 'MM') return 1;
            $littleEndian = $orden === 'II';
            $leer16 = static function (int $offset) use ($datos, $largo, $littleEndian): ?int {
                if ($offset < 0 || $offset + 2 > $largo) return null;
                $a = ord($datos[$offset]);
                $b = ord($datos[$offset + 1]);
                return $littleEndian ? ($a | ($b << 8)) : (($a << 8) | $b);
            };
            $leer32 = static function (int $offset) use ($datos, $largo, $littleEndian): ?int {
                if ($offset < 0 || $offset + 4 > $largo) return null;
                $b = [ord($datos[$offset]), ord($datos[$offset + 1]), ord($datos[$offset + 2]), ord($datos[$offset + 3])];
                return $littleEndian
                    ? ($b[0] | ($b[1] << 8) | ($b[2] << 16) | ($b[3] << 24))
                    : (($b[0] << 24) | ($b[1] << 16) | ($b[2] << 8) | $b[3]);
            };
            if ($leer16($tiff + 2) !== 42) return 1;
            $offsetIfd = $leer32($tiff + 4);
            if ($offsetIfd === null || $offsetIfd < 8) return 1;
            $ifd = $tiff + $offsetIfd;
            $cantidad = $leer16($ifd);
            if ($cantidad === null || $cantidad > 512) return 1;
            for ($i = 0; $i < $cantidad; $i++) {
                $entrada = $ifd + 2 + ($i * 12);
                if ($entrada + 12 > $largo) return 1;
                if ($leer16($entrada) !== 0x0112) continue;
                $orientacion = $leer16($entrada + 8);
                return $orientacion !== null && $orientacion >= 1 && $orientacion <= 8 ? $orientacion : 1;
            }
            return 1;
        }
        $posicion += $largoSegmento;
    }
    return 1;
}

/**
 * Aplica la orientación EXIF antes del recorte, incluso cuando PHP no tiene
 * habilitada la extensión exif.
 *
 * @param resource|GdImage $imagen
 * @return resource|GdImage
 */
function orientar_imagen_jpeg($imagen, string $archivo, string $mime)
{
    if ($mime !== 'image/jpeg') return $imagen;
    $orientacion = 1;
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($archivo, 'IFD0', true, false);
        $orientacion = (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1);
    }
    if ($orientacion < 2 || $orientacion > 8) $orientacion = leer_orientacion_exif_jpeg($archivo);
    if ($orientacion === 2 && function_exists('imageflip')) {
        imageflip($imagen, IMG_FLIP_HORIZONTAL);
        return $imagen;
    }
    if ($orientacion === 4 && function_exists('imageflip')) {
        imageflip($imagen, IMG_FLIP_VERTICAL);
        return $imagen;
    }
    $angulo = match ($orientacion) {
        3 => 180,
        5, 6 => -90,
        7, 8 => 90,
        default => 0,
    };
    if ($angulo === 0) return $imagen;
    $rotada = @imagerotate($imagen, $angulo, 0);
    if ($rotada === false) return $imagen;
    imagedestroy($imagen);
    if (in_array($orientacion, [5, 7], true) && function_exists('imageflip')) {
        imageflip($rotada, IMG_FLIP_HORIZONTAL);
    }
    return $rotada;
}

/**
 * Recorta al centro, escala y guarda un JPEG progresivo con peso controlado.
 *
 * @return array{ancho:int,alto:int,peso:int,calidad:int}
 */
function procesar_imagen_seo(string $archivoFuente, string $archivoDestino, int $anchoDestino, int $altoDestino): array
{
    if (!extension_loaded('gd')) throw new RuntimeException('El servidor no puede procesar imágenes SEO en este momento.');
    $info = @getimagesize($archivoFuente);
    $mime = (string) ($info['mime'] ?? '');
    $creadores = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    if ($info === false || !isset($creadores[$mime]) || !function_exists($creadores[$mime])) {
        throw new RuntimeException('La imagen SEO debe ser JPG, PNG o WEBP.');
    }
    $pixeles = (int) ($info[0] ?? 0) * (int) ($info[1] ?? 0);
    if ($pixeles <= 0 || $pixeles > 40_000_000) {
        throw new RuntimeException('La imagen SEO tiene dimensiones demasiado grandes.');
    }

    $origen = @$creadores[$mime]($archivoFuente);
    if ($origen === false) throw new RuntimeException('No se pudo leer la imagen SEO.');
    $destino = null;
    $temporal = $archivoDestino . '.tmp-' . bin2hex(random_bytes(4));
    try {
        $origen = orientar_imagen_jpeg($origen, $archivoFuente, $mime);
        $anchoOrigen = imagesx($origen);
        $altoOrigen = imagesy($origen);
        if ($anchoOrigen <= 0 || $altoOrigen <= 0) throw new RuntimeException('La imagen SEO no tiene dimensiones válidas.');

        $proporcionDestino = $anchoDestino / $altoDestino;
        $proporcionOrigen = $anchoOrigen / $altoOrigen;
        $xOrigen = 0;
        $yOrigen = 0;
        $anchoRecorte = $anchoOrigen;
        $altoRecorte = $altoOrigen;
        if ($proporcionOrigen > $proporcionDestino) {
            $anchoRecorte = max(1, (int) round($altoOrigen * $proporcionDestino));
            $xOrigen = max(0, (int) floor(($anchoOrigen - $anchoRecorte) / 2));
        } elseif ($proporcionOrigen < $proporcionDestino) {
            $altoRecorte = max(1, (int) round($anchoOrigen / $proporcionDestino));
            $yOrigen = max(0, (int) floor(($altoOrigen - $altoRecorte) / 2));
        }

        $destino = imagecreatetruecolor($anchoDestino, $altoDestino);
        if ($destino === false) throw new RuntimeException('No se pudo preparar la imagen SEO.');
        $blanco = imagecolorallocate($destino, 255, 255, 255);
        imagefill($destino, 0, 0, $blanco);
        imagealphablending($destino, true);
        if (!imagecopyresampled(
            $destino,
            $origen,
            0,
            0,
            $xOrigen,
            $yOrigen,
            $anchoDestino,
            $altoDestino,
            $anchoRecorte,
            $altoRecorte
        )) {
            throw new RuntimeException('No se pudo recortar la imagen SEO.');
        }
        imageinterlace($destino, true);

        $pesoMaximo = 400 * 1024;
        $calidadUsada = 86;
        $guardada = false;
        foreach ([86, 82, 78, 74, 70, 66, 62, 58, 54, 50, 46] as $calidad) {
            $guardada = imagejpeg($destino, $temporal, $calidad);
            if (!$guardada || !is_file($temporal)) continue;
            $calidadUsada = $calidad;
            clearstatcache(true, $temporal);
            if ((int) filesize($temporal) <= $pesoMaximo) break;
        }
        if (!$guardada || !is_file($temporal) || (int) filesize($temporal) <= 0
            || (int) filesize($temporal) > $pesoMaximo) {
            throw new RuntimeException('No se pudo optimizar la imagen SEO al peso requerido.');
        }
        if (!rename($temporal, $archivoDestino)) throw new RuntimeException('No se pudo guardar la imagen SEO optimizada.');
        @chmod($archivoDestino, 0644);
        clearstatcache(true, $archivoDestino);
        return [
            'ancho' => $anchoDestino,
            'alto' => $altoDestino,
            'peso' => (int) filesize($archivoDestino),
            'calidad' => $calidadUsada,
        ];
    } finally {
        if (is_file($temporal)) @unlink($temporal);
        if ($destino !== null && $destino !== false) imagedestroy($destino);
        imagedestroy($origen);
    }
}

/**
 * Genera las variantes social (1.91:1) y Discover (16:9) desde una imagen local.
 *
 * @return array{social:array{ruta:string,ancho:int,alto:int,peso:int,calidad:int},discover:array{ruta:string,ancho:int,alto:int,peso:int,calidad:int}}
 */
function generar_variantes_imagen_seo(string $rutaFuente): array
{
    $variantes = rutas_variantes_imagen_seo($rutaFuente);
    if ($variantes === []) throw new RuntimeException('La fuente de la imagen SEO no es válida.');
    $raiz = realpath(dirname(__DIR__, 2));
    $directorio = realpath(dirname(__DIR__, 2) . '/uploads/noticias');
    $fuente = realpath(dirname(__DIR__, 2) . '/' . $rutaFuente);
    if ($raiz === false || $directorio === false || $fuente === false
        || !str_starts_with($fuente, $directorio . DIRECTORY_SEPARATOR)
        || !is_file($fuente)) {
        throw new RuntimeException('No se encontró la imagen elegida para SEO.');
    }
    $social = procesar_imagen_seo($fuente, $raiz . '/' . $variantes['social'], 1200, 630);
    $discover = procesar_imagen_seo($fuente, $raiz . '/' . $variantes['discover'], 1200, 675);
    return [
        'social' => ['ruta' => $variantes['social']] + $social,
        'discover' => ['ruta' => $variantes['discover']] + $discover,
    ];
}

function eliminar_variantes_imagen_seo(string $rutaFuente): void
{
    $raiz = dirname(__DIR__, 2);
    foreach (rutas_variantes_imagen_seo($rutaFuente) as $ruta) {
        $archivo = $raiz . '/' . $ruta;
        if (is_file($archivo)) @unlink($archivo);
    }
}

/**
 * Guarda una pieza publicitaria sin aplicar la marca de agua editorial.
 */
function subir_imagen_publicidad(array $archivo): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir la imagen publicitaria.');
    }
    if (($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen supera el tamaño máximo permitido (5 MB).');
    }

    $info = @getimagesize((string) $archivo['tmp_name']);
    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($tiposPermitidos[$info['mime']])) {
        throw new RuntimeException('La imagen debe ser JPG, PNG o WEBP.');
    }
    $pixeles = (int) $info[0] * (int) $info[1];
    if ($pixeles <= 0 || $pixeles > 40_000_000) {
        throw new RuntimeException('La imagen tiene dimensiones demasiado grandes.');
    }

    $directorio = dirname(__DIR__, 2) . '/uploads/publicidad';
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio de publicidad.');
    }
    $nombre = 'anuncio_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $tiposPermitidos[$info['mime']];
    $rutaCompleta = $directorio . '/' . $nombre;
    if (!move_uploaded_file((string) $archivo['tmp_name'], $rutaCompleta)) {
        throw new RuntimeException('No se pudo guardar la imagen publicitaria.');
    }
    @chmod($rutaCompleta, 0644);
    return 'uploads/publicidad/' . $nombre;
}

/** Guarda una foto de perfil sin marca de agua, hasta 3 MB. */
function subir_imagen_usuario(array $archivo): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la foto de perfil.');
    }
    if (($archivo['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('La foto de perfil supera el máximo permitido de 3 MB.');
    }

    $info = @getimagesize((string) $archivo['tmp_name']);
    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($tiposPermitidos[$info['mime']])) {
        throw new RuntimeException('La foto de perfil debe ser JPG, PNG o WEBP.');
    }
    $pixeles = (int) $info[0] * (int) $info[1];
    if ($pixeles <= 0 || $pixeles > 20_000_000) {
        throw new RuntimeException('La foto de perfil tiene dimensiones demasiado grandes.');
    }

    $directorio = dirname(__DIR__, 2) . '/uploads/usuarios';
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio de fotos de usuario.');
    }
    $nombre = 'usuario_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $tiposPermitidos[$info['mime']];
    $rutaCompleta = $directorio . '/' . $nombre;
    if (!move_uploaded_file((string) $archivo['tmp_name'], $rutaCompleta)) {
        throw new RuntimeException('No se pudo guardar la foto de perfil.');
    }
    @chmod($rutaCompleta, 0644);
    return 'uploads/usuarios/' . $nombre;
}

function ruta_imagen_usuario_valida(string $ruta): bool
{
    return (bool) preg_match('#^uploads/usuarios/usuario_[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#i', $ruta);
}

function imagen_usuario_disponible(string $ruta): bool
{
    if (!ruta_imagen_usuario_valida($ruta)) return false;
    return is_file(dirname(__DIR__, 2) . '/' . $ruta);
}

function eliminar_imagen_usuario(?string $rutaRelativa): void
{
    if (!$rutaRelativa || !ruta_imagen_usuario_valida($rutaRelativa)) return;
    $directorio = realpath(dirname(__DIR__, 2) . '/uploads/usuarios');
    $rutaCompleta = realpath(dirname(__DIR__, 2) . '/' . $rutaRelativa);
    if ($directorio !== false && $rutaCompleta !== false
        && str_starts_with($rutaCompleta, $directorio . DIRECTORY_SEPARATOR)
        && is_file($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
}

function ruta_imagen_publicidad_valida(string $ruta): bool
{
    return (bool) preg_match('#^uploads/publicidad/anuncio_[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#i', $ruta);
}

function eliminar_imagen_publicidad(?string $rutaRelativa): void
{
    if (!$rutaRelativa || !ruta_imagen_publicidad_valida($rutaRelativa)) return;
    $directorio = realpath(dirname(__DIR__, 2) . '/uploads/publicidad');
    $rutaCompleta = realpath(dirname(__DIR__, 2) . '/' . $rutaRelativa);
    if ($directorio !== false && $rutaCompleta !== false
        && str_starts_with($rutaCompleta, $directorio . DIRECTORY_SEPARATOR)
        && is_file($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
}

/**
 * Sube un audio y devuelve la ruta relativa pública.
 * Admite MP3, M4A, OGG y WAV, hasta 25 MB.
 *
 * @param array $archivo Elemento de $_FILES.
 */
function subir_audio(array $archivo): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE
        || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('El audio supera el tamaño permitido de 25 MB.');
    }
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el audio (código ' . (int) $archivo['error'] . ').');
    }
    if (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > 25 * 1024 * 1024) {
        throw new RuntimeException('El audio debe pesar como máximo 25 MB.');
    }
    // Este hosting no tiene fileinfo: se valida la firma binaria real, no solo
    // el MIME declarado por el navegador ni la extensión original.
    $cabecera = file_get_contents($archivo['tmp_name'], false, null, 0, 64);
    $extensionOriginal = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));
    $extension = null;
    if (is_string($cabecera)) {
        if (substr($cabecera, 0, 4) === 'RIFF' && substr($cabecera, 8, 4) === 'WAVE') {
            $extension = 'wav';
        } elseif (substr($cabecera, 0, 4) === 'OggS') {
            $extension = 'ogg';
        } elseif (substr($cabecera, 0, 3) === 'ID3'
            || (strlen($cabecera) >= 2 && ord($cabecera[0]) === 0xFF && (ord($cabecera[1]) & 0xE0) === 0xE0)) {
            $extension = 'mp3';
        } elseif ($extensionOriginal === 'm4a' && substr($cabecera, 4, 4) === 'ftyp') {
            $extension = 'm4a';
        }
    }
    if ($extension === null) {
        throw new RuntimeException('El archivo debe ser un audio MP3, M4A, OGG o WAV.');
    }

    $directorio = dirname(__DIR__, 2) . '/uploads/audios';
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear la carpeta de audios.');
    }

    $nombre = 'audio_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $rutaCompleta = $directorio . '/' . $nombre;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        throw new RuntimeException('No se pudo guardar el audio en el servidor.');
    }
    @chmod($rutaCompleta, 0644);

    return 'uploads/audios/' . $nombre;
}

/**
 * Aplica el logo del portal en el centro de una imagen subida.
 * El archivo se reemplaza de forma atómica solamente cuando termina bien.
 */
function aplicar_marca_agua_centrada(string $rutaCompleta, string $mime): void
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('La extensión GD no está disponible.');
    }

    $configuracionMarca = configuracion_marca_agua();
    $logoRuta = dirname(__DIR__, 2) . '/' . $configuracionMarca['ruta'];
    $opacidadMarca = $configuracionMarca['opacidad'];
    $tamanoMarca = $configuracionMarca['tamano'];
    if (!is_file($logoRuta)) {
        throw new RuntimeException('No se encontró el logo para la marca de agua.');
    }

    $imagen = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($rutaCompleta),
        'image/png' => @imagecreatefrompng($rutaCompleta),
        'image/webp' => @imagecreatefromwebp($rutaCompleta),
        default => false,
    };
    $logo = @imagecreatefrompng($logoRuta);

    if ($imagen === false || $logo === false) {
        if ($imagen !== false) imagedestroy($imagen);
        if ($logo !== false) imagedestroy($logo);
        throw new RuntimeException('No se pudo procesar la imagen o el logo.');
    }

    $imagen = orientar_imagen_jpeg($imagen, $rutaCompleta, $mime);

    $marca = null;
    $rutaTemporal = $rutaCompleta . '.marca_' . bin2hex(random_bytes(4));

    try {
        $anchoImagen = imagesx($imagen);
        $altoImagen = imagesy($imagen);
        $anchoLogo = imagesx($logo);
        $altoLogo = imagesy($logo);

        // Logo centrado al porcentaje del ancho elegido en Configuración.
        $anchoMarca = max(1, (int) round($anchoImagen * ($tamanoMarca / 100)));
        $altoMarca = max(1, (int) round($anchoMarca * ($altoLogo / $anchoLogo)));
        $marca = imagecreatetruecolor($anchoMarca, $altoMarca);
        if ($marca === false) {
            throw new RuntimeException('No se pudo preparar la marca de agua.');
        }

        imagealphablending($marca, false);
        imagesavealpha($marca, true);
        $transparente = imagecolorallocatealpha($marca, 0, 0, 0, 127);
        imagefilledrectangle($marca, 0, 0, $anchoMarca, $altoMarca, $transparente);
        imagecopyresampled($marca, $logo, 0, 0, 0, 0, $anchoMarca, $altoMarca, $anchoLogo, $altoLogo);

        // Suma transparencia al canal alfa según la opacidad elegida (15% por defecto).
        $alphaAdicional = (int) round(127 * (1 - ($opacidadMarca / 100)));
        if (!imagefilter($marca, IMG_FILTER_COLORIZE, 0, 0, 0, $alphaAdicional)) {
            throw new RuntimeException('No se pudo ajustar la transparencia de la marca.');
        }

        $destinoX = (int) round(($anchoImagen - $anchoMarca) / 2);
        $destinoY = (int) round(($altoImagen - $altoMarca) / 2);
        imagealphablending($imagen, true);
        imagesavealpha($imagen, true);
        imagecopy($imagen, $marca, $destinoX, $destinoY, 0, 0, $anchoMarca, $altoMarca);

        $guardada = match ($mime) {
            'image/jpeg' => imagejpeg($imagen, $rutaTemporal, 90),
            'image/png' => imagepng($imagen, $rutaTemporal, 6),
            'image/webp' => imagewebp($imagen, $rutaTemporal, 90),
            default => false,
        };

        if (!$guardada || !is_file($rutaTemporal) || filesize($rutaTemporal) === 0) {
            throw new RuntimeException('No se pudo guardar la imagen marcada.');
        }

        if (!rename($rutaTemporal, $rutaCompleta)) {
            throw new RuntimeException('No se pudo reemplazar la imagen original.');
        }
    } finally {
        if (is_file($rutaTemporal)) @unlink($rutaTemporal);
        if ($marca !== null) imagedestroy($marca);
        imagedestroy($logo);
        imagedestroy($imagen);
    }
}

/**
 * Elimina un archivo de imagen local del proyecto (ignora URLs externas).
 */
function eliminar_imagen(?string $rutaRelativa): void
{
    if (!$rutaRelativa) {
        return;
    }

    // Solo acepta los nombres que genera subir_imagen(). No se admiten carpetas,
    // barras codificadas ni segmentos "..".
    if (!preg_match('#^uploads/noticias/noticia_[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#i', $rutaRelativa)) {
        return;
    }

    eliminar_variantes_imagen_seo($rutaRelativa);

    $directorio = realpath(dirname(__DIR__, 2) . '/uploads/noticias');
    $rutaCompleta = realpath(dirname(__DIR__, 2) . '/' . $rutaRelativa);
    if ($directorio !== false && $rutaCompleta !== false
        && str_starts_with($rutaCompleta, $directorio . DIRECTORY_SEPARATOR)
        && is_file($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
}

function ruta_imagen_subida_valida(string $ruta): bool
{
    return (bool) preg_match('#^uploads/noticias/noticia_[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#i', $ruta);
}

function ruta_imagen_seo_generada_valida(string $ruta): bool
{
    return (bool) preg_match('#^uploads/noticias/seo_[A-Za-z0-9_-]+_1200x(?:630|675)\.jpg$#', $ruta);
}

function ruta_audio_subido_valida(string $ruta): bool
{
    return (bool) preg_match('#^uploads/audios/audio_[A-Za-z0-9_-]+\.(?:mp3|m4a|ogg|wav)$#i', $ruta);
}

/** Conserva rutas locales generadas o URLs HTTPS externas; rechaza otros protocolos. */
function normalizar_url_audio(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') return '';
    if (ruta_audio_subido_valida($url)) return $url;
    if (strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : '';
}

function url_audio_panel(string $url): string
{
    return ruta_audio_subido_valida($url) ? '../' . $url : $url;
}

function audio_subido_referenciado(string $ruta): bool
{
    $stmt = db()->prepare(
        'SELECT EXISTS(SELECT 1 FROM noticias WHERE audio_1 = ? OR audio_2 = ? OR audio_3 = ?)'
    );
    $stmt->execute([$ruta, $ruta, $ruta]);
    return (bool) $stmt->fetchColumn();
}

function eliminar_audio(?string $rutaRelativa): void
{
    if (!$rutaRelativa || !ruta_audio_subido_valida($rutaRelativa)) return;
    $directorio = realpath(dirname(__DIR__, 2) . '/uploads/audios');
    $rutaCompleta = realpath(dirname(__DIR__, 2) . '/' . $rutaRelativa);
    if ($directorio !== false && $rutaCompleta !== false
        && str_starts_with($rutaCompleta, $directorio . DIRECTORY_SEPARATOR)
        && is_file($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
}

function limpiar_audios_huerfanos_antiguos(int $horas = 48, int $limite = 20): int
{
    $directorio = dirname(__DIR__, 2) . '/uploads/audios';
    $corte = time() - max(1, $horas) * 3600;
    $eliminados = 0;
    foreach (glob($directorio . '/audio_*.*') ?: [] as $archivo) {
        if ($eliminados >= $limite || !is_file($archivo) || filemtime($archivo) >= $corte) continue;
        $ruta = 'uploads/audios/' . basename($archivo);
        if (ruta_audio_subido_valida($ruta) && !audio_subido_referenciado($ruta)) {
            eliminar_audio($ruta);
            $eliminados++;
        }
    }
    return $eliminados;
}

/** @return array<int,string> Rutas locales únicas insertadas en el HTML. */
function imagenes_locales_en_html(?string $html): array
{
    $html = (string) $html;
    if ($html === '' || !str_contains($html, 'uploads/noticias/')) return [];

    $dom = new DOMDocument('1.0', 'UTF-8');
    $estadoLibxml = libxml_use_internal_errors(true);
    $cargado = $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="contenido-noticia">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($estadoLibxml);
    if (!$cargado) return [];

    $rutas = [];
    foreach ($dom->getElementsByTagName('img') as $imagen) {
        $src = html_entity_decode(trim($imagen->getAttribute('src')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $src = preg_replace('#^(?:\.\./)+#', '', $src);
        $src = ltrim((string) $src, '/');
        if (ruta_imagen_subida_valida($src)) $rutas[$src] = true;
    }
    return array_keys($rutas);
}

/** Devuelve el tamaño real de una ruta local generada por el portal. */
function tamano_archivo_portal(string $ruta): int
{
    if (!ruta_imagen_subida_valida($ruta) && !ruta_imagen_seo_generada_valida($ruta) && !ruta_audio_subido_valida($ruta)) return 0;
    $raiz = realpath(dirname(__DIR__, 2));
    $archivo = realpath(dirname(__DIR__, 2) . '/' . $ruta);
    if ($raiz === false || $archivo === false
        || !str_starts_with($archivo, $raiz . DIRECTORY_SEPARATOR)
        || !is_file($archivo)) {
        return 0;
    }
    $bytes = filesize($archivo);
    return $bytes === false ? 0 : max(0, (int) $bytes);
}

/**
 * Peso de los archivos locales asociados a una noticia, sin duplicar rutas.
 * Las URLs externas y YouTube no consumen disco local y no se cuentan.
 *
 * @param array<string,mixed> $noticia
 * @param array<int,array<string,mixed>> $fotos
 * @return array{fotos:int,audios:int,total:int}
 */
function peso_archivos_noticia(array $noticia, array $fotos): array
{
    $rutasFotos = [];
    foreach ($fotos as $foto) {
        $ruta = (string) ($foto['ruta'] ?? '');
        if (ruta_imagen_subida_valida($ruta)) $rutasFotos[$ruta] = true;
    }
    foreach (imagenes_locales_en_html($noticia['descripcion'] ?? '') as $ruta) {
        $rutasFotos[$ruta] = true;
    }
    $imagenSeo = (string) ($noticia['seo_imagen'] ?? '');
    if (ruta_imagen_subida_valida($imagenSeo)) $rutasFotos[$imagenSeo] = true;
    $fuenteSeo = $imagenSeo !== '' ? $imagenSeo : (string) ($fotos[0]['ruta'] ?? '');
    foreach (variantes_imagen_seo_existentes($fuenteSeo) as $ruta) $rutasFotos[$ruta] = true;

    $rutasAudios = [];
    foreach (['audio_1', 'audio_2', 'audio_3'] as $campo) {
        $ruta = (string) ($noticia[$campo] ?? '');
        if (ruta_audio_subido_valida($ruta)) $rutasAudios[$ruta] = true;
    }

    $pesoFotos = array_sum(array_map('tamano_archivo_portal', array_keys($rutasFotos)));
    $pesoAudios = array_sum(array_map('tamano_archivo_portal', array_keys($rutasAudios)));
    return ['fotos' => $pesoFotos, 'audios' => $pesoAudios, 'total' => $pesoFotos + $pesoAudios];
}

function formatear_megabytes(int $bytes): string
{
    if ($bytes <= 0) return '0 MB';
    $mb = $bytes / 1048576;
    $decimales = $mb >= 10 ? 1 : 2;
    return number_format($mb, $decimales, ',', '.') . ' MB';
}

function imagen_subida_referenciada(string $ruta): bool
{
    $stmt = db()->prepare(
        'SELECT EXISTS(SELECT 1 FROM noticias_fotos WHERE ruta = ?)
             OR EXISTS(SELECT 1 FROM noticias WHERE LOCATE(?, descripcion) > 0 OR seo_imagen = ?)'
    );
    $stmt->execute([$ruta, $ruta, $ruta]);
    return (bool) $stmt->fetchColumn();
}

function limpiar_imagenes_huerfanas_antiguas(int $horas = 48, int $limite = 50): int
{
    $directorio = dirname(__DIR__, 2) . '/uploads/noticias';
    $corte = time() - max(1, $horas) * 3600;
    $eliminadas = 0;
    foreach (glob($directorio . '/noticia_*.*') ?: [] as $archivo) {
        if ($eliminadas >= $limite || !is_file($archivo) || filemtime($archivo) >= $corte) continue;
        $ruta = 'uploads/noticias/' . basename($archivo);
        if (ruta_imagen_subida_valida($ruta) && !imagen_subida_referenciada($ruta)) {
            eliminar_imagen($ruta);
            $eliminadas++;
        }
    }
    return $eliminadas;
}

function limpiar_subidas_no_usadas_de_sesion(): void
{
    iniciar_sesion_segura();
    foreach (array_keys($_SESSION['archivos_subidos'] ?? []) as $ruta) {
        if (ruta_imagen_subida_valida($ruta) && !imagen_subida_referenciada($ruta)) {
            eliminar_imagen($ruta);
        } elseif (ruta_audio_subido_valida($ruta) && !audio_subido_referenciado($ruta)) {
            eliminar_audio($ruta);
        }
    }
    unset($_SESSION['archivos_subidos']);
}

/**
 * Obtiene todas las categorías ordenadas por nombre.
 *
 * @return array<int, array<string, string>>
 */
function obtener_categorias(): array
{
    return db()->query('SELECT id, nombre, slug FROM categorias ORDER BY nombre ASC')->fetchAll();
}

/**
 * Normaliza una selección múltiple de categorías enviada por formulario.
 *
 * @return array<int, int>
 */
function normalizar_ids_categorias(mixed $valores): array
{
    if (!is_array($valores)) {
        $valores = $valores === null || $valores === '' ? [] : [$valores];
    }

    $ids = [];
    foreach ($valores as $valor) {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false) {
            $ids[(int) $id] = (int) $id;
        }
    }
    return array_values($ids);
}

/** @return array<int, array{id:int,nombre:string,slug:string}> */
function obtener_categorias_noticia(int $noticiaId): array
{
    if ($noticiaId <= 0) return [];

    $stmt = db()->prepare(
        'SELECT c.id, c.nombre, c.slug
           FROM noticias_categorias nc
           JOIN categorias c ON c.id = nc.categoria_id
          WHERE nc.noticia_id = ?
          ORDER BY nc.posicion ASC, c.nombre ASC, c.id ASC'
    );
    $stmt->execute([$noticiaId]);
    return $stmt->fetchAll();
}

/**
 * Agrega a cada noticia sus categorías completas sin producir consultas N+1.
 * Conserva categoria_id como categoría principal para compatibilidad.
 *
 * @param array<int, array<string,mixed>> $noticias
 */
function cargar_categorias_noticias(array &$noticias): void
{
    $ids = array_values(array_unique(array_filter(array_map(
        static fn(array $noticia): int => (int) ($noticia['id'] ?? 0),
        $noticias
    ))));
    if (!$ids) return;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT nc.noticia_id, c.id, c.nombre, c.slug
           FROM noticias_categorias nc
           JOIN categorias c ON c.id = nc.categoria_id
          WHERE nc.noticia_id IN ($placeholders)
          ORDER BY nc.noticia_id, nc.posicion, c.nombre, c.id"
    );
    $stmt->execute($ids);

    $porNoticia = [];
    foreach ($stmt->fetchAll() as $categoria) {
        $porNoticia[(int) $categoria['noticia_id']][] = [
            'id' => (int) $categoria['id'],
            'nombre' => (string) $categoria['nombre'],
            'slug' => (string) $categoria['slug'],
        ];
    }

    foreach ($noticias as &$noticia) {
        $categorias = $porNoticia[(int) ($noticia['id'] ?? 0)] ?? [];
        $noticia['categorias'] = $categorias;
        $noticia['categoria_ids'] = array_column($categorias, 'id');
        $noticia['categoria_nombre'] = implode(' · ', array_column($categorias, 'nombre'));
    }
    unset($noticia);
}

/**
 * Reemplaza atómicamente las categorías asociadas a una noticia.
 * Debe ejecutarse dentro de la misma transacción que guarda la noticia.
 *
 * @param array<int, int> $categoriaIds
 */
function guardar_categorias_noticia(PDO $pdo, int $noticiaId, array $categoriaIds): void
{
    $pdo->prepare('DELETE FROM noticias_categorias WHERE noticia_id = ?')->execute([$noticiaId]);
    if (!$categoriaIds) return;

    $insertar = $pdo->prepare(
        'INSERT INTO noticias_categorias (noticia_id, categoria_id, posicion) VALUES (?, ?, ?)'
    );
    foreach (array_values($categoriaIds) as $posicion => $categoriaId) {
        $insertar->execute([$noticiaId, $categoriaId, $posicion]);
    }
}

/** @return array<int, array<string,mixed>> */
function obtener_usuarios_para_noticias(): array
{
    return db()->query(
        'SELECT u.id, u.nombre, u.email, u.bio, u.activo, r.nombre AS rol_nombre
           FROM usuarios u
           JOIN roles r ON r.id = u.rol_id
          ORDER BY u.activo DESC, u.nombre ASC'
    )->fetchAll();
}

/**
 * Obtiene las fotos de la galería de una noticia, ordenadas por posición.
 *
 * @return array<int, array<string, mixed>>
 */
function obtener_fotos_noticia(int $noticiaId): array
{
    if ($noticiaId <= 0) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, noticia_id, ruta, posicion
           FROM noticias_fotos
          WHERE noticia_id = ?
          ORDER BY posicion ASC, id ASC'
    );
    $stmt->execute([$noticiaId]);
    return $stmt->fetchAll();
}

/**
 * Elimina las fotos de la galería de una noticia (archivos locales + registros).
 */
function eliminar_fotos_noticia(int $noticiaId): void
{
    foreach (obtener_fotos_noticia($noticiaId) as $foto) {
        eliminar_imagen($foto['ruta']);
    }

    $stmt = db()->prepare('DELETE FROM noticias_fotos WHERE noticia_id = ?');
    $stmt->execute([$noticiaId]);
}

/**
 * Sanea el atributo "style", conservando únicamente las propiedades seguras:
 * "text-align" (alineación) y "color" (color de texto).
 */
function sanitizar_estilo(?string $estilo): string
{
    $estilo = trim((string) $estilo);
    if ($estilo === '') {
        return '';
    }

    $permitidas = [];

    foreach (explode(';', $estilo) as $declaracion) {
        $declaracion = trim($declaracion);
        if ($declaracion === '') {
            continue;
        }

        $partes = explode(':', $declaracion, 2);
        if (count($partes) !== 2) {
            continue;
        }

        $propiedad = strtolower(trim($partes[0]));
        $valor = strtolower(trim($partes[1]));

        if ($propiedad === 'text-align' && preg_match('/^(left|right|center|justify|start|end)$/', $valor)) {
            $permitidas[] = 'text-align: ' . $valor;
        } elseif ($propiedad === 'color' && color_seguro($valor)) {
            $permitidas[] = 'color: ' . $valor;
        }
    }

    return implode('; ', $permitidas);
}

/**
 * Indica si un valor de color CSS es seguro (hexadecimal o rgb/rgba).
 */
function color_seguro(string $color): bool
{
    return (bool) preg_match('/^#[0-9a-f]{3,8}$/', $color)
        || (bool) preg_match('/^rgba?\([\d.,%\s]+\)$/', $color);
}


/**
 * Sanitiza el HTML proveniente del editor de texto enriquecido.
 *
 * Se permiten solo etiquetas y atributos seguros; el resto se elimina.
 * Devuelve una cadena vacía si tras limpiar no queda contenido visible.
 */
function sanitizar_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $etiquetasPermitidas = '<p><br><strong><b><em><i><u><s><h1><h2><h3><ul><ol><li><blockquote><pre><code><a><mark><sub><sup><hr><img>';

    $limpio = strip_tags($html, $etiquetasPermitidas);

    // Limpieza a nivel de DOM usando DOMDocument.
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="contenido">' . $limpio . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $cuerpo = $doc->getElementById('contenido');

    if (!$cuerpo) {
        // Si DOM no puede analizarlo, se conserva solo texto. Nunca se devuelven
        // etiquetas con atributos sin pasar por la lista blanca.
        return htmlspecialchars(trim(strip_tags($limpio)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Lista blanca de atributos por etiqueta.
    $atributosPermitidos = [
        'a'   => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title'],
    ];

    $nodos = iterator_to_array($cuerpo->getElementsByTagName('*'));
    foreach ($nodos as $nodo) {
        if (!$nodo instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($nodo->tagName);
        $permitidos = $atributosPermitidos[$tag] ?? [];

        // Elimina atributos no permitidos. El atributo "style" se trata aparte:
        // solo se conservan las propiedades seguras (color y text-align).
        foreach (iterator_to_array($nodo->attributes) as $attr) {
            $nombre = strtolower($attr->nodeName);

            if ($nombre === 'style') {
                $estilo = sanitizar_estilo($attr->value);
                if ($estilo === '') {
                    $nodo->removeAttribute('style');
                } else {
                    $nodo->setAttribute('style', $estilo);
                }
                continue;
            }

            if (!in_array($nombre, $permitidos, true)) {
                $nodo->removeAttribute($attr->nodeName);
            }
        }

        // En enlaces, valida el protocolo y fuerza target="_blank" + rel.
        if ($tag === 'a') {
            $href = $nodo->getAttribute('href');
            if ($href !== '' && !preg_match('#^(https?:|mailto:|/)#i', $href)) {
                $nodo->removeAttribute('href');
            }
            if ($nodo->getAttribute('href') !== '') {
                $nodo->setAttribute('target', '_blank');
                $nodo->setAttribute('rel', 'noopener noreferrer');
            }
        }

        // En imágenes, valida la ruta y descarta las que quedan sin "src".
        if ($tag === 'img') {
            $src = $nodo->getAttribute('src');
            if ($src !== '' && !preg_match('#^(https?:|/|uploads/)#i', $src)) {
                $nodo->removeAttribute('src');
            }
            if ($nodo->getAttribute('src') === '' && $nodo->parentNode) {
                $nodo->parentNode->removeChild($nodo);
            }
        }
    }

    // Extrae solo el contenido interno (sin el div contenedor).
    $resultado = '';
    foreach ($cuerpo->childNodes as $hijo) {
        $resultado .= $doc->saveHTML($hijo);
    }
    $resultado = trim($resultado);

    // Descarta contenido vacío (por ejemplo "<p><br></p>").
    $textoPlano = trim($cuerpo->textContent);
    if ($textoPlano === '' && !$cuerpo->getElementsByTagName('img')->length) {
        return '';
    }

    return $resultado;
}

/**
 * Convierte una URL de YouTube en su URL de embed (iframe).
 *
 * Acepta formatos: youtube.com/watch?v=..., youtu.be/..., embeds,
 * shorts y URLs con listas/timestamps. Devuelve '' si no es válida.
 */
function youtube_embed_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    // Solo se procesan dominios de YouTube.
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (!preg_match('/(^|\.)youtube\.com$|(^|\.)youtu\.be$|(^|\.)youtube-nocookie\.com$/', $host)) {
        return '';
    }

    // 1. youtu.be/XXXX
    if (strpos($host, 'youtu.be') !== false) {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (preg_match('~^/([\w-]{5,})~', $path, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        return '';
    }

    $path = parse_url($url, PHP_URL_PATH) ?? '';

    // 2. /embed/XXXX
    if (preg_match('~/embed/([\w-]{5,})~i', $path, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }

    // 3. /shorts/XXXX
    if (preg_match('~/shorts/([\w-]{5,})~i', $path, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }

    // 4. /watch/XXXX
    if (preg_match('~/watch/([\w-]{5,})~i', $path, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }

    $query = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (!empty($query['v']) && preg_match('/^[\w-]{5,}$/', (string) $query['v'])) {
        return 'https://www.youtube-nocookie.com/embed/' . $query['v'];
    }

    return '';
}

/**
 * Convierte HTML a texto plano (para resúmenes/listados).
 */
function html_a_texto(?string $html, int $limite = 0): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
    $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');

    if ($limite > 0 && mb_strlen($texto) > $limite) {
        return mb_substr($texto, 0, $limite) . '…';
    }

    return $texto;
}

/** Normaliza un texto como slug seguro para URLs de noticias. */
function normalizar_slug_noticia(?string $texto): string
{
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if (is_string($ascii) && $ascii !== '') $texto = $ascii;
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim((string) $texto, '-');
    return mb_substr($texto !== '' ? $texto : 'noticia', 0, 190);
}

function slug_noticia_en_uso(PDO $pdo, string $slug, int $excluirNoticiaId = 0): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE slug = ? AND id <> ?');
    $stmt->execute([$slug, $excluirNoticiaId]);
    if ((int) $stmt->fetchColumn() > 0) return true;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias_slugs_historial WHERE slug = ? AND noticia_id <> ?');
    $stmt->execute([$slug, $excluirNoticiaId]);
    return (int) $stmt->fetchColumn() > 0;
}

function generar_slug_noticia_unico(PDO $pdo, string $texto, int $excluirNoticiaId = 0): string
{
    $base = normalizar_slug_noticia($texto);
    $slug = $base;
    $sufijo = 2;
    while (slug_noticia_en_uso($pdo, $slug, $excluirNoticiaId)) {
        $cola = '-' . $sufijo++;
        $slug = mb_substr($base, 0, 190 - strlen($cola)) . $cola;
    }
    return $slug;
}

/** URL base inferida del portal; admite PORTAL_PUBLIC_URL como override privado. */
function url_base_portal(): string
{
    if (defined('PORTAL_PUBLIC_URL') && trim((string) PORTAL_PUBLIC_URL) !== '') {
        return rtrim((string) PORTAL_PUBLIC_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) $host = 'localhost';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $posAdmin = strpos($script, '/admin/');
    $base = $posAdmin !== false ? substr($script, 0, $posAdmin) : dirname($script);
    $base = $base === '/' || $base === '.' ? '' : rtrim($base, '/');
    return ($https ? 'https' : 'http') . '://' . $host . $base;
}

function url_portal(string $ruta = ''): string
{
    return url_base_portal() . ($ruta === '' ? '' : '/' . ltrim($ruta, '/'));
}

function url_noticia(string $slug): string
{
    return url_portal('noticia/' . rawurlencode(normalizar_slug_noticia($slug)));
}

function url_recurso_portal(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta === '') return '';
    if (preg_match('#^https?://#i', $ruta)) return $ruta;
    return url_portal(ltrim($ruta, '/'));
}

function descripcion_seo_automatica(?string $html, int $limite = 160): string
{
    $texto = html_a_texto($html);
    if (mb_strlen($texto) <= $limite) return $texto;
    $corte = rtrim(mb_substr($texto, 0, $limite - 1));
    $ultimoEspacio = mb_strrpos($corte, ' ');
    if ($ultimoEspacio !== false && $ultimoEspacio >= (int) ($limite * .65)) {
        $corte = mb_substr($corte, 0, $ultimoEspacio);
    }
    return rtrim($corte, " .,;:-") . '…';
}

/** @return array{titulo:string,descripcion:string,imagen:string,imagenes:array<int,string>,imagen_procesada:bool,imagen_fuente:string,url:string,slug:string} */
function valores_seo_noticia(array $noticia, array $fotos = []): array
{
    $slug = normalizar_slug_noticia((string) ($noticia['slug'] ?? $noticia['titulo'] ?? 'noticia'));
    $titulo = trim((string) ($noticia['seo_titulo'] ?? '')) ?: trim((string) ($noticia['titulo'] ?? ''));
    $descripcion = trim((string) ($noticia['seo_descripcion'] ?? '')) ?: descripcion_seo_automatica($noticia['descripcion'] ?? '');
    $fuenteImagen = trim((string) ($noticia['seo_imagen'] ?? ''));
    if ($fuenteImagen === '' && !empty($fotos[0]['ruta'])) $fuenteImagen = (string) $fotos[0]['ruta'];
    $variantes = variantes_imagen_seo_existentes($fuenteImagen);
    $imagenSocial = $variantes['social'] ?? $fuenteImagen;
    if ($imagenSocial === '') $imagenSocial = 'imagenes/Logo2027v3.png';
    $imagenesEstructuradas = [];
    foreach ([$variantes['discover'] ?? '', $variantes['social'] ?? '', $imagenSocial] as $rutaImagen) {
        if ($rutaImagen !== '') $imagenesEstructuradas[$rutaImagen] = url_recurso_portal($rutaImagen);
    }
    return [
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'imagen' => url_recurso_portal($imagenSocial),
        'imagenes' => array_values($imagenesEstructuradas),
        'imagen_procesada' => isset($variantes['social']),
        'imagen_fuente' => $fuenteImagen,
        'url' => url_noticia($slug),
        'slug' => $slug,
    ];
}

/**
 * Resuelve la URL de una imagen para mostrarla desde el admin (landing/admin/).
 *
 * Las rutas guardadas en la base de datos son relativas a la raíz de landing/.
 * Como el panel vive en landing/admin/, se antepone "../". Las URLs absolutas
 * (http/https) o con "/" inicial se devuelven tal cual.
 */
function url_imagen(?string $ruta): string
{
    if (!$ruta) {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $ruta) || str_starts_with($ruta, '/')) {
        return $ruta;
    }

    return '../' . $ruta;
}

/**
 * Redirige a una URL relativa y finaliza el script.
 */
function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}
