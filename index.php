<?php
/**
 * Landing - Front.
 * El slider y los feeds de noticias de PC y móvil se renderizan desde la base
 * de datos. El slider toma únicamente noticias marcadas como portada.
 */

require_once __DIR__ . '/admin/includes/funciones.php';
require_once __DIR__ . '/admin/includes/votos.php';

$pdo = db();

// Última noticia creada = primera en verse (más reciente primero).
// Los contadores de votos viajan en la misma consulta: son columnas de
// noticias, así que mostrarlos no cuesta ninguna consulta extra.
$noticias = $pdo->query(
    'SELECT n.id, n.categoria_id, n.titulo, n.slug, n.descripcion,
            n.youtube, n.youtube_2, n.youtube_3,
            n.audio_1, n.audio_2, n.audio_3, n.created_at,
            n.me_gusta, n.no_me_gusta, n.portada,
            c.nombre AS categoria_nombre,
            u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
       LEFT JOIN usuarios u ON u.id = n.usuario_id
      ORDER BY n.created_at DESC, n.id DESC'
)->fetchAll();

// La barra de filtros pertenece únicamente a la portada PC. El feed móvil y
// los templates de nota completa siguen recibiendo el conjunto entero.
$categoriasFiltroPc = $pdo->query(
    'SELECT id, nombre FROM categorias ORDER BY nombre ASC'
)->fetchAll();

$buscarEntradaPc = $_GET['buscar'] ?? '';
$buscarPc = is_string($buscarEntradaPc) ? trim($buscarEntradaPc) : '';
if (mb_strlen($buscarPc, 'UTF-8') > 150) {
    $buscarPc = mb_substr($buscarPc, 0, 150, 'UTF-8');
}

$categoriaPc = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$categoriaPc = $categoriaPc !== false && $categoriaPc !== null ? (int) $categoriaPc : null;

$validarFechaPc = static function (mixed $valor): string {
    if (!is_string($valor)) {
        return '';
    }

    $fecha = trim($valor);
    if ($fecha === '') {
        return '';
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $objeto && $objeto->format('Y-m-d') === $fecha ? $fecha : '';
};

$desdePc = $validarFechaPc($_GET['desde'] ?? '');
$hastaPc = $validarFechaPc($_GET['hasta'] ?? '');

$normalizarBusquedaPc = static function (string $valor): string {
    $texto = html_entity_decode(strip_tags($valor), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) : false;
    $texto = $ascii !== false ? $ascii : $texto;
    return trim((string) preg_replace('/\s+/', ' ', $texto));
};

$terminosBusquedaPc = preg_split('/\s+/', $normalizarBusquedaPc($buscarPc), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$noticiasPc = array_values(array_filter($noticias, static function (array $noticia) use (
    $categoriaPc,
    $desdePc,
    $hastaPc,
    $terminosBusquedaPc,
    $normalizarBusquedaPc
): bool {
    if ($categoriaPc !== null && (int) ($noticia['categoria_id'] ?? 0) !== $categoriaPc) {
        return false;
    }

    $fechaNoticia = substr((string) ($noticia['created_at'] ?? ''), 0, 10);
    if ($desdePc !== '' && $fechaNoticia < $desdePc) {
        return false;
    }
    if ($hastaPc !== '' && $fechaNoticia > $hastaPc) {
        return false;
    }

    if ($terminosBusquedaPc) {
        $contenido = $normalizarBusquedaPc(
            (string) ($noticia['titulo'] ?? '') . ' ' . (string) ($noticia['descripcion'] ?? '')
        );
        foreach ($terminosBusquedaPc as $termino) {
            if (!str_contains($contenido, $termino)) {
                return false;
            }
        }
    }

    return true;
}));

// Todas las galerias se cargan en una sola consulta para evitar una consulta
// adicional por cada noticia.
$fotosPorNoticia = [];
foreach ($pdo->query('SELECT id, noticia_id, ruta, posicion FROM noticias_fotos ORDER BY noticia_id, posicion, id') as $foto) {
    $fotosPorNoticia[(int) $foto['noticia_id']][] = $foto;
}

$noticiasPortada = array_values(array_filter($noticias, static function (array $noticia) use ($fotosPorNoticia): bool {
    return (int) ($noticia['portada'] ?? 0) === 1
        && !empty($fotosPorNoticia[(int) $noticia['id']][0]['ruta']);
}));

// Votos ya emitidos por este visitante, en una sola consulta, para marcar los
// botones. No se crea la cookie al mirar: se emite recién al votar.
$misVotos = votos_del_visitante($pdo, visitante_id());
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Landing - Slider</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400;1,700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/portal.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/portal.css') ?>">
</head>
<body>
  <header class="hero" aria-label="Banner principal">
    <nav class="navbar" aria-label="Menú principal">
      <button class="hamburger" id="hamburger" aria-label="Abrir menú" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <a href="#" class="logo" aria-label="Logo - Inicio">
        <img src="imagenes/Logo2027v2.png" alt="Logo 2027" />
      </a>

      <a href="#" class="logo-right" aria-label="Radio Sur">
        <img src="imagenes/Logo2027-radiosur.png" alt="Radio Sur" />
      </a>
    </nav>

    <div class="slider" id="slider">
      <?php foreach ($noticiasPortada as $indicePortada => $noticiaPortada): ?>
        <?php
          $fotoPortada = url_imagen_front((string) $fotosPorNoticia[(int) $noticiaPortada['id']][0]['ruta']);
          $fotoPortadaCss = json_encode($fotoPortada, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
          $resumenPortada = html_a_texto($noticiaPortada['descripcion'] ?? '', 220);
        ?>
        <div class="slide<?= $indicePortada === 0 ? ' active' : '' ?>" style="background-image: url(<?= e($fotoPortadaCss) ?>);">
        <div class="slide-content">
          <span class="slide-tag"><?= e($noticiaPortada['categoria_nombre'] ?? 'Noticias') ?></span>
          <h2 class="slide-title"><?= e($noticiaPortada['titulo']) ?></h2>
          <?php if ($resumenPortada !== ''): ?><p class="slide-subtitle"><?= e($resumenPortada) ?></p><?php endif; ?>
        </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="dots" id="dots" role="tablist" aria-label="Indicadores de imagen"></div>

    <!-- Flecha de scroll: indica que hay contenido deslizante/scroll -->
    <a href="#contenido" class="scroll-down" aria-label="Desplazarse hacia abajo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </a>
  </header>

  <div id="contenido">

  <!-- Feed de noticias estilo red social (solo móvil) -->
<?php include __DIR__ . '/partials/mobile-feed.php'; ?>

  <!-- Feed de noticias estilo artículo sticky (solo PC) -->
<?php include __DIR__ . '/partials/pc-feed.php'; ?>

  <!-- Nota completa en panel inferior, disponible solo en el feed móvil -->
<?php include __DIR__ . '/partials/nota-completa.php'; ?>

  <!-- Visor ampliado de galerías, compartido por ambos feeds -->
<?php include __DIR__ . '/partials/lightbox.php'; ?>

  </div>

  <!-- Menú desplegable a pantalla completa -->
  <div class="menu-overlay" id="menuOverlay" aria-hidden="true">
    <button class="close-btn" id="closeMenu" aria-label="Cerrar menú">
      <span></span>
      <span></span>
    </button>

    <a href="#" class="menu-logo" aria-label="Logo - Inicio">
      <img src="imagenes/Logo2027v2.png" alt="Logo 2027" />
    </a>

    <a href="#" class="menu-radio" aria-label="Escuchar Radio Sur">
      <img src="imagenes/Logo2027-radiosur.png" alt="Escuchar Radio Sur" />
    </a>

    <nav class="menu-nav" aria-label="Menú de navegación">
      <a href="#" class="menu-link">Noticias</a>
      <a href="#" class="menu-link">Videos</a>
      <a href="#" class="menu-link">Contactos</a>
    </nav>
  </div>

  <script src="assets/js/portal.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/portal.js') ?>" data-vote-url="<?= e(url_portal('votar.php')) ?>"></script>
</body>
</html>
