<?php
/**
 * Funciones auxiliares del administrador.
 */

require_once __DIR__ . '/auth.php';

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
 * Aplica el logo del portal en el centro de una imagen subida.
 * El archivo se reemplaza de forma atómica solamente cuando termina bien.
 */
function aplicar_marca_agua_centrada(string $rutaCompleta, string $mime): void
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('La extensión GD no está disponible.');
    }

    $logoRuta = dirname(__DIR__, 2) . '/imagenes/Logo2027v2.png';
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

    $marca = null;
    $rutaTemporal = $rutaCompleta . '.marca_' . bin2hex(random_bytes(4));

    try {
        $anchoImagen = imagesx($imagen);
        $altoImagen = imagesy($imagen);
        $anchoLogo = imagesx($logo);
        $altoLogo = imagesy($logo);

        // Logo centrado al 36% del ancho de la fotografía.
        $anchoMarca = max(1, (int) round($anchoImagen * 0.36));
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

        // Suma transparencia al canal alfa: la parte más visible queda al 15%.
        if (!imagefilter($marca, IMG_FILTER_COLORIZE, 0, 0, 0, 108)) {
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

function imagen_subida_referenciada(string $ruta): bool
{
    $stmt = db()->prepare(
        'SELECT EXISTS(SELECT 1 FROM noticias_fotos WHERE ruta = ?)
             OR EXISTS(SELECT 1 FROM noticias WHERE LOCATE(?, descripcion) > 0)'
    );
    $stmt->execute([$ruta, $ruta]);
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
