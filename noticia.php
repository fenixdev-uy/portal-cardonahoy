<?php
/** Página pública individual, canónica e indexable de una noticia. */
require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible();
require_once __DIR__ . '/admin/includes/votos.php';

$pdo = db();
$usuarioPublico = usuario_actual_publico();
$logoPortalRuta = configuracion_logo_portal();
$logoPortalArchivo = __DIR__ . '/' . $logoPortalRuta;
$logoPortalVersion = is_file($logoPortalArchivo) ? (string) filemtime($logoPortalArchivo) : '1';
$logoPortalUrl = url_portal($logoPortalRuta) . '?v=' . rawurlencode($logoPortalVersion);
$logoPortalTamano = configuracion_logo_portal_tamano();
$logoPortalEscala = $logoPortalTamano / 100;
$logoPortalEstilo = sprintf('--portal-logo-desktop:%.2fpx;--portal-logo-mobile:%.2fpx', 180 * $logoPortalEscala, 115 * $logoPortalEscala);
$identidadAdmin = configuracion_logo_admin();
$faviconPortalArchivo = $identidadAdmin['favicon_ruta'] !== null ? __DIR__ . '/' . $identidadAdmin['favicon_ruta'] : __DIR__ . '/imagenes/Logo2027v2.png';
$faviconPortalVersion = is_file($faviconPortalArchivo) ? (string) filemtime($faviconPortalArchivo) : '1';
require_once __DIR__ . '/partials/publicidad.php';
$slugSolicitado = normalizar_slug_noticia($_GET['slug'] ?? '');
$estadosDisponibles = noticias_estados_disponibles($pdo);
$filtroPublicadaSql = $estadosDisponibles ? " AND n.estado = 'publicada'" : '';
$stmt = $pdo->prepare(
    'SELECT n.*, c.nombre AS categoria_nombre, u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id=n.categoria_id
       LEFT JOIN usuarios u ON u.id=n.usuario_id
      WHERE n.slug=?' . $filtroPublicadaSql . ' LIMIT 1'
);
$stmt->execute([$slugSolicitado]);
$noticia = $stmt->fetch();

if (!$noticia) {
    $stmt = $pdo->prepare('SELECT n.slug FROM noticias_slugs_historial h JOIN noticias n ON n.id=h.noticia_id WHERE h.slug=?' . $filtroPublicadaSql . ' LIMIT 1');
    $stmt->execute([$slugSolicitado]);
    $slugActual = $stmt->fetchColumn();
    if (is_string($slugActual) && $slugActual !== '') {
        header('Location: ' . url_noticia($slugActual), true, 301);
        exit;
    }
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $noticia = null;
}

if ($noticia) {
    $noticiaConCategorias = [$noticia];
    cargar_categorias_noticias($noticiaConCategorias);
    $noticia = $noticiaConCategorias[0];
}

$fotos = $noticia ? obtener_fotos_noticia((int) $noticia['id']) : [];
$seo = $noticia ? valores_seo_noticia($noticia, $fotos) : [
    'titulo' => 'Noticia no encontrada', 'descripcion' => 'La noticia solicitada no está disponible.',
    'imagen' => url_portal('imagenes/Logo2027v3.png'), 'url' => url_noticia($slugSolicitado), 'slug' => $slugSolicitado,
];
$nombreSitio = configuracion_nombre_sitio();
$autor = trim((string) ($noticia['autor_nombre'] ?? '')) ?: $nombreSitio;
$categoria = trim((string) ($noticia['categoria_nombre'] ?? ''));
$categoriasNoticia = $noticia['categorias'] ?? [];
$fechaPublicacion = $noticia['publicada_at'] ?? $noticia['created_at'] ?? null;
$fecha = $noticia ? fecha_larga((string) $fechaPublicacion) : '';
$jsonLd = $noticia ? [
    '@context' => 'https://schema.org', '@type' => 'NewsArticle',
    'headline' => $seo['titulo'], 'description' => $seo['descripcion'], 'image' => $seo['imagenes'],
    'datePublished' => date(DATE_ATOM, strtotime((string) $fechaPublicacion)),
    'dateModified' => date(DATE_ATOM, strtotime($noticia['updated_at'])),
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $seo['url']],
    'author' => ['@type' => 'Person', 'name' => $autor],
    'publisher' => ['@type' => 'NewsMediaOrganization', 'name' => $nombreSitio, 'url' => url_base_portal(), 'logo' => ['@type' => 'ImageObject', 'url' => url_portal($logoPortalRuta)]],
] : null;
if ($jsonLd && $categoriasNoticia) $jsonLd['articleSection'] = array_column($categoriasNoticia, 'nombre');
$misVotos = $noticia ? votos_del_visitante($pdo, visitante_id()) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <base href="<?= e(url_base_portal()) ?>/" />
  <title><?= e($seo['titulo']) ?></title>
  <link rel="icon" href="<?= e(url_portal('favicon.php')) ?>?v=<?= e(rawurlencode($faviconPortalVersion)) ?>" type="image/x-icon" />
  <meta name="description" content="<?= e($seo['descripcion']) ?>" />
  <link rel="canonical" href="<?= e($seo['url']) ?>" />
<?php if ($noticia): ?>
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <meta property="og:type" content="article" />
  <meta property="og:title" content="<?= e($seo['titulo']) ?>" />
  <meta property="og:description" content="<?= e($seo['descripcion']) ?>" />
  <meta property="og:image" content="<?= e($seo['imagen']) ?>" />
<?php if ($seo['imagen_procesada']): ?>
  <meta property="og:image:type" content="image/jpeg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
<?php endif; ?>
  <meta property="og:image:alt" content="<?= e($seo['titulo']) ?>" />
  <meta property="og:url" content="<?= e($seo['url']) ?>" />
  <meta property="og:site_name" content="<?= e($nombreSitio) ?>" />
  <meta property="article:published_time" content="<?= e(date(DATE_ATOM, strtotime($noticia['created_at']))) ?>" />
  <meta property="article:modified_time" content="<?= e(date(DATE_ATOM, strtotime($noticia['updated_at']))) ?>" />
<?php foreach ($categoriasNoticia as $categoriaNoticia): ?>  <meta property="article:section" content="<?= e($categoriaNoticia['nombre']) ?>" />
<?php endforeach; ?>
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e($seo['titulo']) ?>" />
  <meta name="twitter:description" content="<?= e($seo['descripcion']) ?>" />
  <meta name="twitter:image" content="<?= e($seo['imagen']) ?>" />
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php else: ?>  <meta name="robots" content="noindex,follow" /><?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400;1,700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/noticia.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/noticia.css') ?>" />
  <link rel="stylesheet" href="assets/css/popup.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/popup.css') ?>" />
<?php imprimir_codigo_header_publico(); ?>
</head>
<body style="<?= e($logoPortalEstilo) ?>">
  <nav class="navbar" aria-label="Menú principal">
    <button class="hamburger" id="hamburger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menuOverlay"><span></span><span></span><span></span></button>
    <a href="<?= e(url_base_portal()) ?>" class="logo" aria-label="<?= e($nombreSitio) ?> - Inicio"><img src="<?= e($logoPortalUrl) ?>" alt="<?= e($nombreSitio) ?>" /></a>
    <?php if ($usuarioPublico): ?>
      <?php require __DIR__ . '/partials/acceso-admin.php'; ?>
    <?php endif; ?>
  </nav>
  <div class="menu-overlay" id="menuOverlay" aria-hidden="true">
    <button class="close-btn" id="closeMenu" type="button" aria-label="Cerrar menú"><span></span><span></span></button>
    <a href="<?= e(url_base_portal()) ?>" class="menu-logo" aria-label="<?= e($nombreSitio) ?> - Inicio"><img src="<?= e($logoPortalUrl) ?>" alt="<?= e($nombreSitio) ?>" /></a>
    <div class="menu-news-search" role="search" data-menu-news-search data-search-url="<?= e(url_portal('buscar-noticias.php')) ?>">
      <label class="menu-news-search-field" for="articleMenuNewsSearchInput">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
        <input type="search" id="articleMenuNewsSearchInput" placeholder="Buscar noticias..." maxlength="100" autocomplete="off" spellcheck="false" aria-controls="articleMenuNewsSearchResults" aria-expanded="false" />
      </label>
      <div class="menu-news-search-results" id="articleMenuNewsSearchResults" aria-live="polite" hidden></div>
    </div>
  </div>
  <main>
<?php if (!$noticia): ?>
    <section class="not-found"><h1>Noticia no encontrada</h1><p>La dirección solicitada no está disponible.</p><a href="<?= e(url_base_portal()) ?>">Volver al portal</a></section>
<?php else: ?>
    <article>
      <section class="story-hero<?= !$fotos ? ' no-gallery-controls' : '' ?>" aria-label="Portada y galería de la noticia">
<?php if ($fotos): ?>
        <div class="story-gallery-track" id="storyGalleryTrack">
<?php foreach ($fotos as $i => $foto): ?>
          <figure class="story-gallery-frame"><img src="<?= e(url_recurso_portal($foto['ruta'])) ?>" alt="<?= e($noticia['titulo']) ?><?= count($fotos)>1?' — imagen '.($i+1):'' ?>" <?= $i>0?'loading="lazy" ':'' ?>decoding="async" /></figure>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <div class="story-hero-copy">
          <?php if ($categoria !== ''): ?><span class="story-category"><?= e($categoria) ?></span><?php endif; ?>
          <h1 class="story-title"><?= e($noticia['titulo']) ?></h1>
        </div>
<?php if (count($fotos) > 1): ?>
        <div class="story-gallery-dots" id="storyGalleryDots" aria-label="Seleccionar imagen"></div>
<?php endif; ?>
<?php if ($fotos): ?>
        <button class="story-gallery-expand" id="storyGalleryExpand" type="button" aria-label="Ampliar galería" title="Ampliar galería"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button>
<?php endif; ?>
      </section>
      <div class="story-content">
        <div class="story-meta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><?php if ($fecha !== ''): ?><span><?= e($fecha) ?></span><?php endif; ?><?php if ($fecha !== '' && $autor !== ''): ?><span>—</span><?php endif; ?><span class="story-author"><?= e($autor) ?></span></div>
        <div class="article-placement-ad article-placement-ad-header" data-ad-placement="encabezado"<?= $anuncioPublicidadEncabezado === null ? ' hidden' : '' ?>>
<?php if ($anuncioPublicidadEncabezado !== null): ?>
<?php $publicidad = $anuncioPublicidadEncabezado; $claseTarjetaPublicidad = 'story-sheet-placement-ad article-ad-card'; require __DIR__ . '/partials/anuncio-card.php'; ?>
<?php endif; ?>
        </div>
        <div class="article-reading-tools" role="group" aria-label="Lectura y valoración de la noticia">
<?php
    $n = $noticia;
    $prefijo = 'article';
    $modoAccionesNoticia = 'votos';
    include __DIR__ . '/partials/acciones-noticia.php';
    unset($modoAccionesNoticia);
?>
          <div class="article-reading-controls" role="group" aria-label="Tamaño del texto">
            <button class="article-reading-button" type="button" data-reading-adjust="-0.1" aria-label="Achicar texto" title="Achicar texto">
              <span>A</span><span class="article-reading-symbol" aria-hidden="true">−</span>
            </button>
            <button class="article-reading-button" type="button" data-reading-adjust="0.1" aria-label="Agrandar texto" title="Agrandar texto">
              <span>A</span><span class="article-reading-symbol" aria-hidden="true">+</span>
            </button>
            <span class="article-reading-status" aria-live="polite">Tamaño de texto 100%</span>
          </div>
        </div>
        <div class="article-body" data-reading-scale="1"><?= $noticia['descripcion'] ?></div>
<?php include __DIR__ . '/partials/medios-noticia.php'; ?>
<?php
    $modoAccionesNoticia = 'compartir';
    include __DIR__ . '/partials/acciones-noticia.php';
    unset($modoAccionesNoticia);
?>
        <div class="article-placement-ad article-placement-ad-footer" data-ad-placement="pie"<?= $anuncioPublicidadPie === null ? ' hidden' : '' ?>>
<?php if ($anuncioPublicidadPie !== null): ?>
<?php $publicidad = $anuncioPublicidadPie; $claseTarjetaPublicidad = 'story-sheet-placement-ad article-ad-card'; require __DIR__ . '/partials/anuncio-card.php'; ?>
<?php endif; ?>
        </div>
      </div>
    </article>
<?php endif; ?>
  </main>
  <footer class="article-footer"><a class="article-footer-link" href="<?= e(url_base_portal()) ?>"><span class="article-footer-arrow" aria-hidden="true">←</span><span>Ver más noticias</span></a></footer>
<?php if ($noticia && $fotos): ?>
  <div class="article-lightbox" id="articleLightbox" role="dialog" aria-modal="true" aria-label="Galería ampliada" aria-hidden="true">
    <button class="article-lightbox-close" id="articleLightboxClose" type="button" aria-label="Cerrar galería"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
    <button class="article-lightbox-arrow article-lightbox-prev" id="articleLightboxPrev" type="button" aria-label="Imagen anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
    <div class="article-lightbox-stage" id="articleLightboxStage"><img class="article-lightbox-image" id="articleLightboxImage" src="" alt="" draggable="false" /></div>
    <button class="article-lightbox-arrow article-lightbox-next" id="articleLightboxNext" type="button" aria-label="Imagen siguiente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
    <div class="article-lightbox-counter" id="articleLightboxCounter" aria-live="polite"></div>
    <div class="article-lightbox-zoom" id="articleLightboxZoom" aria-live="polite">Pinza para ampliar · 100%</div>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/partials/popup-publico.php'; ?>
  <script src="assets/js/noticia.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/noticia.js') ?>" data-vote-url="<?= e(url_portal('votar.php')) ?>" data-view-url="<?= e(url_portal('noticia-vista.php')) ?>" data-share-url="<?= e(url_portal('noticia-compartir.php')) ?>" data-noticia-id="<?= (int) ($noticia['id'] ?? 0) ?>" data-ad-placements-url="<?= e(url_portal('publicidad-ubicaciones.php')) ?>"></script>
  <script src="assets/js/popup.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/popup.js') ?>" data-popup-endpoint="<?= e(url_portal('popup-publico.php')) ?>"></script>
</body>
</html>
