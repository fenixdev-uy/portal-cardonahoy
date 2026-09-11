<?php
/**
 * Landing - Front.
 * El slider y los feeds de noticias de PC y móvil se renderizan desde la base
 * de datos. El slider toma únicamente noticias marcadas como portada.
 */

require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible();
require_once __DIR__ . '/admin/includes/votos.php';
require_once __DIR__ . '/partials/portada-paginacion.php';

$pdo = db();
$usuarioPublico = usuario_actual_publico();
$logoPortalRuta = configuracion_logo_portal();
$logoPortalArchivo = __DIR__ . '/' . $logoPortalRuta;
$logoPortalVersion = is_file($logoPortalArchivo) ? (string) filemtime($logoPortalArchivo) : '1';
$logoPortalUrl = url_portal($logoPortalRuta) . '?v=' . rawurlencode($logoPortalVersion);
$logoPortalTamano = configuracion_logo_portal_tamano();
$logoPortalEscala = $logoPortalTamano / 100;
$logoPortalEstilo = sprintf('--portal-logo-desktop:%.2fpx;--portal-logo-tablet:%.2fpx;--portal-logo-mobile:%.2fpx', 180 * $logoPortalEscala, 150 * $logoPortalEscala, 115 * $logoPortalEscala);
$identidadAdmin = configuracion_logo_admin();
$faviconPortalArchivo = $identidadAdmin['favicon_ruta'] !== null ? __DIR__ . '/' . $identidadAdmin['favicon_ruta'] : __DIR__ . '/imagenes/Logo2027v2.png';
$faviconPortalVersion = is_file($faviconPortalArchivo) ? (string) filemtime($faviconPortalArchivo) : '1';
$seoPortada = configuracion_seo_portada();
$nombreSitio = configuracion_nombre_sitio();
$jsonLdPortada = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $nombreSitio,
    'url' => $seoPortada['url'],
    'description' => $seoPortada['descripcion'],
    'publisher' => [
        '@type' => 'NewsMediaOrganization',
        'name' => $nombreSitio,
        'url' => $seoPortada['url'],
        'logo' => ['@type' => 'ImageObject', 'url' => url_portal($logoPortalRuta)],
    ],
];

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

$categoriaIdsPc = normalizar_ids_categorias($_GET['categoria'] ?? []);
$categoriaIdsValidasPc = array_map('intval', array_column($categoriasFiltroPc, 'id'));
$categoriaIdsPc = array_values(array_intersect($categoriaIdsPc, $categoriaIdsValidasPc));

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

$filtrosPc = [
    'buscar' => $buscarPc,
    'categorias' => $categoriaIdsPc,
    'desde' => $desdePc,
    'hasta' => $hastaPc,
];
$paginaMovil = consultar_bloque_portada($pdo, ['limite' => PORTAL_NOTICIAS_POR_BLOQUE]);
$paginaPc = consultar_bloque_portada($pdo, $filtrosPc + ['limite' => PORTAL_NOTICIAS_POR_BLOQUE]);
$paginaRecientes = consultar_bloque_portada($pdo, ['limite' => 11]);
$noticias = $paginaMovil['noticias'];
$noticiasPc = $paginaPc['noticias'];
$noticiasRecientes = $paginaRecientes['noticias'];
$hayMasNoticiasMovil = $paginaMovil['hay_mas'];
$cursorNoticiasMovil = $paginaMovil['cursor'];
$hayMasNoticiasPc = $paginaPc['hay_mas'];
$cursorNoticiasPc = $paginaPc['cursor'];
$semillaPortadaPc = (int) sprintf('%u', crc32(json_encode($filtrosPc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));

$noticiasTemplatesPorId = [];
foreach (array_merge($noticias, $noticiasPc, $noticiasRecientes) as $noticiaTemplate) {
    $noticiasTemplatesPorId[(int) $noticiaTemplate['id']] = $noticiaTemplate;
}
$noticiasTemplates = array_values($noticiasTemplatesPorId);
$fotosPorNoticia = $paginaRecientes['fotos'] + $paginaPc['fotos'] + $paginaMovil['fotos'];

$estadosNoticiasDisponibles = noticias_estados_disponibles($pdo);
$fechaPublicaSql = $estadosNoticiasDisponibles ? 'n.publicada_at' : 'n.created_at';
$filtroPublicadaSql = $estadosNoticiasDisponibles ? " AND n.estado = 'publicada'" : '';
$noticiasPortada = $pdo->query(
    'SELECT n.id, n.categoria_id, n.titulo, n.slug, n.descripcion,
            n.youtube, n.youtube_2, n.youtube_3,
            n.audio_1, n.audio_2, n.audio_3, ' . $fechaPublicaSql . ' AS created_at,
            n.me_gusta, n.no_me_gusta, n.portada,
            c.nombre AS categoria_nombre,
            u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
      LEFT JOIN usuarios u ON u.id = n.usuario_id
      WHERE n.portada = 1
        ' . $filtroPublicadaSql . '
        AND EXISTS (SELECT 1 FROM noticias_fotos nf WHERE nf.noticia_id = n.id)
      ORDER BY ' . $fechaPublicaSql . ' DESC, n.id DESC
      LIMIT ' . PORTADA_NOTICIAS_LIMITE
)->fetchAll();
cargar_categorias_noticias($noticiasPortada);
$fotosPorNoticia = fotos_noticias_portada($pdo, array_column($noticiasPortada, 'id')) + $fotosPorNoticia;

// Votos ya emitidos por este visitante, en una sola consulta, para marcar los
// botones. No se crea la cookie al mirar: se emite recién al votar.
$misVotos = votos_del_visitante($pdo, visitante_id());
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($seoPortada['titulo']) ?></title>
  <meta name="description" content="<?= e($seoPortada['descripcion']) ?>" />
  <link rel="canonical" href="<?= e($seoPortada['url']) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= e($seoPortada['titulo']) ?>" />
  <meta property="og:description" content="<?= e($seoPortada['descripcion']) ?>" />
  <meta property="og:image" content="<?= e($seoPortada['imagen_url']) ?>" />
  <meta property="og:image:alt" content="<?= e($seoPortada['titulo']) ?>" />
  <meta property="og:url" content="<?= e($seoPortada['url']) ?>" />
  <meta property="og:site_name" content="<?= e($nombreSitio) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e($seoPortada['titulo']) ?>" />
  <meta name="twitter:description" content="<?= e($seoPortada['descripcion']) ?>" />
  <meta name="twitter:image" content="<?= e($seoPortada['imagen_url']) ?>" />
  <script type="application/ld+json"><?= json_encode($jsonLdPortada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <link rel="icon" href="<?= e(url_portal('favicon.php')) ?>?v=<?= e(rawurlencode($faviconPortalVersion)) ?>" type="image/x-icon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400;1,700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/portal.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/portal.css') ?>">
  <link rel="stylesheet" href="assets/css/popup.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/popup.css') ?>">
<?php imprimir_codigo_header_publico(); ?>
</head>
<body style="<?= e($logoPortalEstilo) ?>">
  <header class="hero" aria-label="Banner principal">
    <nav class="navbar" aria-label="Menú principal">
      <button class="hamburger" id="hamburger" aria-label="Abrir menú" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <a href="#" class="logo" aria-label="Logo - Inicio">
        <img src="<?= e($logoPortalUrl) ?>" alt="Logo del portal" />
      </a>

      <?php if ($usuarioPublico): ?>
        <?php require __DIR__ . '/partials/acceso-admin.php'; ?>
      <?php endif; ?>
    </nav>

    <div class="slider" id="slider">
      <?php foreach ($noticiasPortada as $indicePortada => $noticiaPortada): ?>
        <?php
          $fotoPortada = url_imagen_front((string) $fotosPorNoticia[(int) $noticiaPortada['id']][0]['ruta']);
          $fotoPortadaCss = json_encode($fotoPortada, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
          $resumenPortada = html_a_texto($noticiaPortada['descripcion'] ?? '', 220);
          $urlNoticiaPortada = url_noticia((string) $noticiaPortada['slug']);
        ?>
        <div class="slide<?= $indicePortada === 0 ? ' active' : '' ?>" style="background-image: url(<?= e($fotoPortadaCss) ?>);">
        <div class="slide-content">
          <span class="slide-tag"><?= e($noticiaPortada['categoria_nombre'] ?? 'Noticias') ?></span>
          <h2 class="slide-title"><?= e($noticiaPortada['titulo']) ?></h2>
          <?php if ($resumenPortada !== ''): ?>
            <p class="slide-subtitle">
              <?= e($resumenPortada) ?>
              <a class="slide-more" href="<?= e($urlNoticiaPortada) ?>" aria-label="Ver nota completa: <?= e($noticiaPortada['titulo']) ?>"><span aria-hidden="true">→</span> Ver nota completa</a>
            </p>
          <?php endif; ?>
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
      <img src="<?= e($logoPortalUrl) ?>" alt="Logo del portal" />
    </a>

    <div class="menu-news-search" role="search" data-menu-news-search data-search-url="<?= e(url_portal('buscar-noticias.php')) ?>">
      <label class="menu-news-search-field" for="menuNewsSearchInput">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"></circle>
          <path d="m20 20-4-4"></path>
        </svg>
        <input type="search" id="menuNewsSearchInput" placeholder="Buscar noticias..." maxlength="100" autocomplete="off" spellcheck="false" aria-controls="menuNewsSearchResults" aria-expanded="false" />
      </label>
      <div class="menu-news-search-results" id="menuNewsSearchResults" aria-live="polite" hidden></div>
    </div>

  </div>

<?php require __DIR__ . '/partials/popup-publico.php'; ?>

  <script src="assets/js/portal.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/portal.js') ?>" data-vote-url="<?= e(url_portal('votar.php')) ?>" data-view-url="<?= e(url_portal('noticia-vista.php')) ?>" data-share-url="<?= e(url_portal('noticia-compartir.php')) ?>" data-ad-placements-url="<?= e(url_portal('publicidad-ubicaciones.php')) ?>"></script>
  <script src="assets/js/popup.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/popup.js') ?>" data-popup-endpoint="<?= e(url_portal('popup-publico.php')) ?>"></script>
</body>
</html>
