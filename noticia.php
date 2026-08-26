<?php
/** Página pública individual, canónica e indexable de una noticia. */
require_once __DIR__ . '/admin/includes/funciones.php';
require_once __DIR__ . '/admin/includes/votos.php';

$pdo = db();
$slugSolicitado = normalizar_slug_noticia($_GET['slug'] ?? '');
$stmt = $pdo->prepare(
    'SELECT n.*, c.nombre AS categoria_nombre, u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id=n.categoria_id
       LEFT JOIN usuarios u ON u.id=n.usuario_id
      WHERE n.slug=? LIMIT 1'
);
$stmt->execute([$slugSolicitado]);
$noticia = $stmt->fetch();

if (!$noticia) {
    $stmt = $pdo->prepare('SELECT n.slug FROM noticias_slugs_historial h JOIN noticias n ON n.id=h.noticia_id WHERE h.slug=? LIMIT 1');
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

$fotos = $noticia ? obtener_fotos_noticia((int) $noticia['id']) : [];
$seo = $noticia ? valores_seo_noticia($noticia, $fotos) : [
    'titulo' => 'Noticia no encontrada', 'descripcion' => 'La noticia solicitada no está disponible.',
    'imagen' => url_portal('imagenes/Logo2027v3.png'), 'url' => url_noticia($slugSolicitado), 'slug' => $slugSolicitado,
];
$autor = trim((string) ($noticia['autor_nombre'] ?? '')) ?: 'Radio Sur';
$categoria = trim((string) ($noticia['categoria_nombre'] ?? ''));
$fecha = $noticia ? fecha_larga($noticia['created_at']) : '';
$jsonLd = $noticia ? [
    '@context' => 'https://schema.org', '@type' => 'NewsArticle',
    'headline' => $seo['titulo'], 'description' => $seo['descripcion'], 'image' => [$seo['imagen']],
    'datePublished' => date(DATE_ATOM, strtotime($noticia['created_at'])),
    'dateModified' => date(DATE_ATOM, strtotime($noticia['updated_at'])),
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $seo['url']],
    'author' => ['@type' => 'Person', 'name' => $autor],
    'publisher' => ['@type' => 'Organization', 'name' => 'Radio Sur', 'logo' => ['@type' => 'ImageObject', 'url' => url_portal('imagenes/Logo2027v2.png')]],
] : null;
if ($jsonLd && $categoria !== '') $jsonLd['articleSection'] = $categoria;
$misVotos = $noticia ? votos_del_visitante($pdo, visitante_id()) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <base href="<?= e(url_base_portal()) ?>/" />
  <title><?= e($seo['titulo']) ?></title>
  <meta name="description" content="<?= e($seo['descripcion']) ?>" />
  <link rel="canonical" href="<?= e($seo['url']) ?>" />
<?php if ($noticia): ?>
  <meta property="og:type" content="article" />
  <meta property="og:title" content="<?= e($seo['titulo']) ?>" />
  <meta property="og:description" content="<?= e($seo['descripcion']) ?>" />
  <meta property="og:image" content="<?= e($seo['imagen']) ?>" />
  <meta property="og:image:alt" content="<?= e($seo['titulo']) ?>" />
  <meta property="og:url" content="<?= e($seo['url']) ?>" />
  <meta property="og:site_name" content="Radio Sur" />
  <meta property="article:published_time" content="<?= e(date(DATE_ATOM, strtotime($noticia['created_at']))) ?>" />
  <meta property="article:modified_time" content="<?= e(date(DATE_ATOM, strtotime($noticia['updated_at']))) ?>" />
<?php if ($categoria !== ''): ?>  <meta property="article:section" content="<?= e($categoria) ?>" /><?php endif; ?>
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e($seo['titulo']) ?>" />
  <meta name="twitter:description" content="<?= e($seo['descripcion']) ?>" />
  <meta name="twitter:image" content="<?= e($seo['imagen']) ?>" />
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php else: ?>  <meta name="robots" content="noindex,follow" /><?php endif; ?>
  <style>
    :root{--ink:#0f172a;--muted:#64748b;--accent:#5eead4;--line:#e2e8f0}
    *{box-sizing:border-box}
    html{min-height:100%;overflow-x:hidden;scroll-behavior:smooth}
    body{min-height:100%;margin:0;color:var(--ink);background:#fff;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
    body.overlay-open{overflow:hidden}
    a{color:inherit}

    .navbar{position:fixed;inset:0 0 auto;z-index:40;display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:linear-gradient(to bottom,rgba(0,0,0,.58),transparent);transition:background-color .25s ease,backdrop-filter .25s ease}
    .navbar.scrolled{background:rgba(0,0,0,.68);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px)}
    .hamburger{display:flex;flex-direction:column;gap:5px;padding:8px;background:none;border:0;filter:drop-shadow(0 1px 3px rgba(0,0,0,.55));cursor:pointer}
    .hamburger span{display:block;height:3px;border-radius:3px;background:#fff;transition:transform .3s ease,opacity .3s ease}
    .hamburger span:first-child{width:30px}.hamburger span:nth-child(2),.hamburger span:nth-child(3){width:22px}
    .hamburger.active span:first-child{transform:translateY(8px) rotate(45deg)}.hamburger.active span:nth-child(2){opacity:0}.hamburger.active span:nth-child(3){transform:translateY(-8px) rotate(-45deg)}
    .logo{position:absolute;top:50%;left:50%;display:inline-flex;align-items:center;transform:translate(-50%,-50%);filter:drop-shadow(0 1px 3px rgba(0,0,0,.55))}
    .logo img{display:block;width:115px;height:auto}
    .logo-right{display:inline-flex;align-items:center;filter:drop-shadow(0 1px 3px rgba(0,0,0,.55))}
    .logo-right img{display:block;width:80px;height:auto}

    .menu-overlay{position:fixed;inset:0;z-index:60;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:24px;background:rgba(0,0,0,.95);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .35s ease,visibility .35s ease}
    .menu-overlay.open{opacity:1;visibility:visible;pointer-events:auto}
    .close-btn{position:absolute;top:16px;right:16px;display:flex;align-items:center;justify-content:center;width:48px;height:48px;padding:0;background:none;border:0;cursor:pointer}
    .close-btn span{position:absolute;width:30px;height:2px;border-radius:2px;background:#fff}.close-btn span:first-child{transform:rotate(45deg)}.close-btn span:last-child{transform:rotate(-45deg)}
    .menu-logo{position:absolute;top:16px;left:50%;display:inline-flex;transform:translateX(-50%)}
    .menu-logo img{display:block;width:115px;height:auto}
    .menu-radio{display:inline-flex;opacity:0;transform:translateY(14px);transition:opacity .4s ease,transform .4s ease}
    .menu-radio img{display:block;width:100px;height:auto}
    .menu-overlay.open .menu-radio{opacity:1;transform:none}
    .menu-nav{display:flex;flex-direction:column;align-items:center;gap:22px}
    .menu-link{color:#fff;font-size:clamp(2.25rem,12vw,4.7rem);font-weight:750;letter-spacing:.055em;text-decoration:none;text-transform:uppercase;opacity:0;transform:translateY(18px);transition:opacity .4s ease,transform .4s ease}
    .menu-overlay.open .menu-link{opacity:1;transform:none}.menu-overlay.open .menu-link:nth-child(1){transition-delay:.08s}.menu-overlay.open .menu-link:nth-child(2){transition-delay:.14s}.menu-overlay.open .menu-link:nth-child(3){transition-delay:.2s}

    .story-hero{position:relative;width:100%;height:100vh;height:100svh;overflow:hidden;background:linear-gradient(135deg,#0f172a,#334155)}
    .story-gallery-track{display:flex;width:100%;height:100%;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .story-gallery-track::-webkit-scrollbar{display:none}
    .story-gallery-frame{flex:0 0 100%;width:100%;height:100%;margin:0;scroll-snap-align:center}
    .story-gallery-frame img{display:block;width:100%;height:100%;object-fit:cover}
    .story-hero::after{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(to top,rgba(0,0,0,.8) 0%,rgba(0,0,0,.34) 38%,rgba(0,0,0,.04) 66%);pointer-events:none}
    .story-hero-copy{position:absolute;right:0;bottom:0;left:0;z-index:2;padding:0 20px 76px;color:#fff;pointer-events:none}
    .story-hero.no-gallery-controls .story-hero-copy{padding-bottom:34px}
    .story-category{display:block;margin-bottom:11px;color:var(--accent);font-size:.78rem;font-weight:800;letter-spacing:.2em;text-transform:uppercase}
    .story-title{max-width:900px;margin:0;font-size:clamp(1.85rem,8.3vw,4.8rem);font-weight:850;line-height:1.08;letter-spacing:-.035em;text-shadow:0 3px 18px rgba(0,0,0,.5)}
    .story-gallery-dots{position:absolute;bottom:24px;left:20px;z-index:3;display:flex;align-items:center;gap:7px}
    .story-gallery-dot{width:7px;height:7px;padding:0;border:0;border-radius:999px;background:rgba(255,255,255,.5);transition:width .25s ease,background-color .25s ease;cursor:pointer}
    .story-gallery-dot.active{width:24px;background:#fff}
    .story-gallery-expand{position:absolute;right:18px;bottom:15px;z-index:3;display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;padding:0;color:#111;background:rgba(255,255,255,.93);border:1px solid rgba(255,255,255,.75);border-radius:50%;box-shadow:0 8px 24px rgba(0,0,0,.28);cursor:pointer;backdrop-filter:blur(8px)}
    .story-gallery-expand svg{width:21px;height:21px}

    .story-content{width:min(760px,100%);margin:0 auto;padding:30px 20px 56px;background:#fff}
    .story-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:28px;color:var(--muted);font-size:.85rem}
    .story-meta svg{width:17px;height:17px;flex:0 0 auto;color:#111}
    .story-author{font-weight:700;color:#111}
    .article-body{color:#202735;font-family:Georgia,"Times New Roman",serif;font-size:1.08rem;line-height:1.75}
    .article-body>:first-child{margin-top:0}.article-body>:last-child{margin-bottom:0}
    .article-body p{margin:0 0 16px}
    .article-body h1,.article-body h2,.article-body h3{margin:1.2em 0 .5em;color:#111;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;font-weight:700;line-height:1.25}
    .article-body h1{font-size:1.55rem}.article-body h2{font-size:1.35rem}.article-body h3{font-size:1.15rem}
    .article-body strong,.article-body b{font-weight:700}.article-body em,.article-body i{font-style:italic}.article-body u{text-decoration:underline}.article-body s{text-decoration:line-through}
    .article-body ul,.article-body ol{margin:0 0 16px;padding-left:1.6rem}.article-body ul{list-style:disc}.article-body ol{list-style:decimal}.article-body li{margin-bottom:6px}
    .article-body blockquote{margin:0 0 16px;padding-left:14px;border-left:3px solid #0ea5e9;color:#555;font-style:italic}
    .article-body code{padding:2px 6px;border-radius:4px;background:#f1f5f9;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.85em}
    .article-body pre{margin:0 0 16px;padding:14px;overflow-x:auto;border-radius:8px;background:#0f172a;color:#e2e8f0;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.85rem;line-height:1.55}
    .article-body pre code{padding:0;background:none;color:inherit}.article-body mark{padding:0 2px;border-radius:2px;background:#fef08a;color:inherit}.article-body sub,.article-body sup{font-size:.75em}
    .article-body hr{margin:1.2em 0;border:0;border-top:2px solid var(--line)}
    .article-body img{max-width:100%;height:auto;margin-bottom:16px;border-radius:12px}.article-body a{color:#0284c7;text-decoration:underline}
    .story-media{display:grid;gap:22px;margin-top:32px}.story-media-group{display:grid;gap:12px}.story-media-heading{margin:0;font-size:1rem}.story-audio{display:grid;gap:8px;padding:15px;background:#f8fafc;border-radius:12px}.story-audio audio{width:100%}.story-video{position:relative;padding-top:56.25%;overflow:hidden;border-radius:14px;background:#0f172a}.story-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}

    .article-actions{display:flex;align-items:center;width:100%;margin-top:38px;padding-top:22px;border-top:1px solid var(--line)}
    .article-vote{display:flex;align-items:center;gap:14px;min-width:0}
    .article-share{display:flex;align-items:center;gap:18px;margin-left:auto;padding-left:17px;border-left:1px solid #cbd5e1}
    .vote-btn{display:inline-flex;align-items:center;gap:5px;padding:0;color:#111;background:none;border:0;cursor:pointer;font:inherit;font-size:.78rem;font-weight:700;white-space:nowrap;transition:opacity .2s ease,color .2s ease}
    .vote-btn svg{width:18px;height:18px;flex:0 0 auto}.vote-btn:disabled{cursor:default}.vote-btn:disabled:not(.voted){opacity:.45}.vote-btn.voted{color:#0f766e}.vote-count{font-size:.75rem;color:#64748b;font-variant-numeric:tabular-nums}.vote-count:empty{display:none}
    .share-btn{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;color:#111;transition:opacity .2s ease,transform .2s ease}.share-btn:hover{opacity:.65;transform:translateY(-1px)}.share-btn svg{width:21px;height:21px}
    .article-footer{padding:24px 20px 34px;background:#f8fafc;text-align:center}
    .article-footer-link{display:inline-flex;align-items:center;gap:9px;color:#334155;font-size:.88rem;font-weight:750;text-decoration:none;transition:color .2s ease,transform .2s ease}
    .article-footer-link:hover,.article-footer-link:focus-visible{color:#0f766e;transform:translateX(-2px)}
    .article-footer-arrow{font-size:1.15rem;line-height:1}

    .article-lightbox{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;overflow:hidden;background:rgba(0,0,0,.97)}
    .article-lightbox.open{display:flex}.article-lightbox-stage{position:absolute;inset:70px 0 64px;display:flex;align-items:center;justify-content:center;overflow:hidden;touch-action:none}
    .article-lightbox-image{display:block;max-width:100%;max-height:100%;object-fit:contain;user-select:none;cursor:zoom-in;transition:transform .16s ease;will-change:transform}
    .article-lightbox.pinching .article-lightbox-image,.article-lightbox.panning .article-lightbox-image{transition:none}.article-lightbox.panning .article-lightbox-image{cursor:grabbing}
    .article-lightbox-close,.article-lightbox-arrow{position:absolute;z-index:2;display:inline-flex;align-items:center;justify-content:center;padding:0;color:#fff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.3);border-radius:50%;cursor:pointer}
    .article-lightbox-close{top:16px;right:16px;width:44px;height:44px}.article-lightbox-close svg{width:23px;height:23px}
    .article-lightbox-arrow{top:50%;width:50px;height:50px;transform:translateY(-50%)}.article-lightbox-arrow svg{width:28px;height:28px}.article-lightbox-prev{left:20px}.article-lightbox-next{right:20px}
    .article-lightbox-counter,.article-lightbox-zoom{position:absolute;bottom:20px;z-index:2;color:rgba(255,255,255,.82);font-size:.82rem;font-weight:700;letter-spacing:.03em}.article-lightbox-counter{left:20px}.article-lightbox-zoom{right:20px}
    .not-found{min-height:100svh;display:grid;place-content:center;padding:100px 24px;text-align:center;background:#f8fafc}.not-found h1{margin:0 0 12px;font-size:2rem}.not-found a{color:#0284c7;font-weight:700}

    @media(max-width:768px){.article-lightbox-arrow{display:none}}
    @media(min-width:769px){.navbar{padding:18px 24px}.logo img{width:180px}.logo-right img{width:120px}.story-content{padding:54px 32px 76px}.story-title{font-size:clamp(3rem,5.5vw,5.2rem)}.story-hero-copy{padding:0 clamp(34px,7vw,110px) 86px}.story-gallery-dots{left:clamp(34px,7vw,110px)}.menu-logo{top:24px}.menu-logo img{width:180px}.close-btn{top:24px;right:24px}.menu-radio img{width:150px}}
    @media(max-width:370px){.story-title{font-size:1.65rem}.article-vote{gap:10px}.article-share{gap:13px;padding-left:13px}.vote-btn{font-size:.72rem}.vote-btn svg{width:17px;height:17px}}
  </style>
</head>
<body>
  <nav class="navbar" aria-label="Menú principal">
    <button class="hamburger" id="hamburger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menuOverlay"><span></span><span></span><span></span></button>
    <a href="<?= e(url_base_portal()) ?>" class="logo" aria-label="Radio Sur - Inicio"><img src="<?= e(url_portal('imagenes/Logo2027v2.png')) ?>" alt="Radio Sur" /></a>
    <a href="#" class="logo-right" aria-label="Escuchar Radio Sur"><img src="<?= e(url_portal('imagenes/Logo2027-radiosur.png')) ?>" alt="Escuchar Radio Sur" /></a>
  </nav>
  <div class="menu-overlay" id="menuOverlay" aria-hidden="true">
    <button class="close-btn" id="closeMenu" type="button" aria-label="Cerrar menú"><span></span><span></span></button>
    <a href="<?= e(url_base_portal()) ?>" class="menu-logo" aria-label="Radio Sur - Inicio"><img src="<?= e(url_portal('imagenes/Logo2027v2.png')) ?>" alt="Radio Sur" /></a>
    <a href="#" class="menu-radio" aria-label="Escuchar Radio Sur"><img src="<?= e(url_portal('imagenes/Logo2027-radiosur.png')) ?>" alt="Escuchar Radio Sur" /></a>
    <nav class="menu-nav" aria-label="Navegación"><a href="<?= e(url_base_portal()) ?>" class="menu-link">Noticias</a><a href="#" class="menu-link">Videos</a><a href="#" class="menu-link">Contactos</a></nav>
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
        <div class="article-body"><?= $noticia['descripcion'] ?></div>
<?php $n=$noticia; include __DIR__ . '/partials/medios-noticia.php'; ?>
<?php $prefijo='article'; include __DIR__ . '/partials/acciones-noticia.php'; ?>
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
  <script>
    const hamburger=document.getElementById('hamburger'),menuOverlay=document.getElementById('menuOverlay'),closeMenuButton=document.getElementById('closeMenu'),navbar=document.querySelector('.navbar');
    function setMenu(open){hamburger.classList.toggle('active',open);hamburger.setAttribute('aria-expanded',open?'true':'false');menuOverlay.classList.toggle('open',open);menuOverlay.setAttribute('aria-hidden',open?'false':'true');document.body.classList.toggle('overlay-open',open);if(open)closeMenuButton.focus();}
    hamburger.addEventListener('click',()=>setMenu(!menuOverlay.classList.contains('open')));closeMenuButton.addEventListener('click',()=>setMenu(false));menuOverlay.querySelectorAll('.menu-link').forEach(link=>link.addEventListener('click',()=>setMenu(false)));window.addEventListener('scroll',()=>navbar.classList.toggle('scrolled',window.scrollY>10),{passive:true});
<?php if ($noticia && $fotos): ?>
    const galleryTrack=document.getElementById('storyGalleryTrack'),galleryFrames=[...galleryTrack.querySelectorAll('.story-gallery-frame')],galleryDots=document.getElementById('storyGalleryDots');let galleryIndex=0,galleryTimer=null,galleryTicking=false;
    const lightbox=document.getElementById('articleLightbox'),lightboxImage=document.getElementById('articleLightboxImage'),lightboxCounter=document.getElementById('articleLightboxCounter'),lightboxZoom=document.getElementById('articleLightboxZoom'),lightboxStage=document.getElementById('articleLightboxStage');let zoomLevel=1,panX=0,panY=0;
    function setGalleryIndex(index,scroll=false){galleryIndex=(index+galleryFrames.length)%galleryFrames.length;galleryDots?.querySelectorAll('.story-gallery-dot').forEach((dot,i)=>dot.classList.toggle('active',i===galleryIndex));if(scroll)galleryTrack.scrollTo({left:galleryFrames[galleryIndex].offsetLeft,behavior:'smooth'});}
    function stopGallery(){if(galleryTimer){clearInterval(galleryTimer);galleryTimer=null;}}
    function startGallery(){stopGallery();if(galleryFrames.length>1)galleryTimer=setInterval(()=>setGalleryIndex(galleryIndex+1,true),4000);}
    if(galleryDots){galleryFrames.forEach((_,i)=>{const dot=document.createElement('button');dot.type='button';dot.className='story-gallery-dot'+(i===0?' active':'');dot.setAttribute('aria-label','Ir a la imagen '+(i+1));dot.addEventListener('click',()=>{setGalleryIndex(i,true);startGallery();});galleryDots.appendChild(dot);});}
    galleryTrack.addEventListener('scroll',()=>{if(galleryTicking)return;galleryTicking=true;requestAnimationFrame(()=>{setGalleryIndex(Math.round(galleryTrack.scrollLeft/galleryTrack.clientWidth));galleryTicking=false;});},{passive:true});
    galleryTrack.addEventListener('pointerdown',stopGallery);galleryTrack.addEventListener('pointerup',startGallery);galleryTrack.addEventListener('touchend',startGallery,{passive:true});
    function limitPan(){const maxX=Math.max(0,(lightboxImage.offsetWidth*zoomLevel-lightboxStage.clientWidth)/2),maxY=Math.max(0,(lightboxImage.offsetHeight*zoomLevel-lightboxStage.clientHeight)/2);panX=Math.min(maxX,Math.max(-maxX,panX));panY=Math.min(maxY,Math.max(-maxY,panY));}
    function applyImageTransform(){lightboxImage.style.transformOrigin='50% 50%';lightboxImage.style.transform=`translate3d(${panX}px,${panY}px,0) scale(${zoomLevel})`;}
    function updateZoom(nextZoom,originX=50,originY=50){const previousZoom=zoomLevel;zoomLevel=Math.min(4,Math.max(1,nextZoom));if(zoomLevel===1){panX=0;panY=0;}else if(previousZoom>0&&zoomLevel!==previousZoom){const bounds=lightboxStage.getBoundingClientRect(),originPxX=((originX/100)-.5)*bounds.width,originPxY=((originY/100)-.5)*bounds.height,ratio=zoomLevel/previousZoom;panX=originPxX-(originPxX-panX)*ratio;panY=originPxY-(originPxY-panY)*ratio;}limitPan();applyImageTransform();lightboxImage.style.cursor=zoomLevel>1?'zoom-out':'zoom-in';const help=window.matchMedia('(max-width:768px)').matches?(zoomLevel>1?'Arrastrá con un dedo':'Pinza para ampliar'):'Rueda del mouse para ampliar';lightboxZoom.textContent=`${help} · ${Math.round(zoomLevel*100)}%`;}
    function panImage(deltaX,deltaY){if(zoomLevel<=1)return;panX+=deltaX;panY+=deltaY;limitPan();applyImageTransform();}
    function renderLightboxImage(index){setGalleryIndex(index);updateZoom(1);lightboxImage.src=galleryFrames[galleryIndex].querySelector('img').src;lightboxImage.alt='Imagen '+(galleryIndex+1)+' de '+galleryFrames.length;lightboxCounter.textContent=(galleryIndex+1)+' / '+galleryFrames.length;}
    function showLightbox(index){lightbox.classList.add('open');lightbox.setAttribute('aria-hidden','false');document.body.classList.add('overlay-open');stopGallery();renderLightboxImage(index);document.getElementById('articleLightboxClose').focus();}
    function closeLightbox(){pointers.clear();initialDistance=0;lightbox.classList.remove('open','pinching','panning');lightbox.setAttribute('aria-hidden','true');updateZoom(1);lightboxImage.removeAttribute('src');document.body.classList.remove('overlay-open');startGallery();document.getElementById('storyGalleryExpand')?.focus();}
    function moveLightbox(step){renderLightboxImage(galleryIndex+step);}
    galleryFrames.forEach((frame,i)=>frame.querySelector('img').addEventListener('click',()=>showLightbox(i)));document.getElementById('storyGalleryExpand')?.addEventListener('click',()=>showLightbox(galleryIndex));document.getElementById('articleLightboxClose').addEventListener('click',closeLightbox);document.getElementById('articleLightboxPrev').addEventListener('click',()=>moveLightbox(-1));document.getElementById('articleLightboxNext').addEventListener('click',()=>moveLightbox(1));lightbox.addEventListener('click',event=>{if(event.target===lightbox)closeLightbox();});
    lightboxStage.addEventListener('wheel',event=>{if(!lightbox.classList.contains('open'))return;event.preventDefault();const bounds=lightboxStage.getBoundingClientRect(),originX=((event.clientX-bounds.left)/bounds.width)*100,originY=((event.clientY-bounds.top)/bounds.height)*100;updateZoom(zoomLevel+(event.deltaY<0?.25:-.25),originX,originY);},{passive:false});
    const pointers=new Map();let initialDistance=0,initialZoom=1;
    function pointerDistance(){const [a,b]=[...pointers.values()];return Math.hypot(a.x-b.x,a.y-b.y);}
    lightboxStage.addEventListener('pointerdown',event=>{if(event.pointerType==='mouse'||!lightbox.classList.contains('open'))return;pointers.set(event.pointerId,{x:event.clientX,y:event.clientY,startX:event.clientX,startY:event.clientY});if(pointers.size===2){initialDistance=pointerDistance();initialZoom=zoomLevel;lightbox.classList.remove('panning');lightbox.classList.add('pinching');}else if(zoomLevel>1){lightbox.classList.add('panning');}lightboxStage.setPointerCapture?.(event.pointerId);});
    lightboxStage.addEventListener('pointermove',event=>{const pointer=pointers.get(event.pointerId);if(!pointer)return;const deltaX=event.clientX-pointer.x,deltaY=event.clientY-pointer.y;pointer.x=event.clientX;pointer.y=event.clientY;if(pointers.size===2&&initialDistance>0){const bounds=lightboxStage.getBoundingClientRect(),[a,b]=[...pointers.values()],originX=(((a.x+b.x)/2-bounds.left)/bounds.width)*100,originY=(((a.y+b.y)/2-bounds.top)/bounds.height)*100;updateZoom(initialZoom*(pointerDistance()/initialDistance),originX,originY);event.preventDefault();return;}if(pointers.size===1&&zoomLevel>1){panImage(deltaX,deltaY);event.preventDefault();}});
    function finishPointer(event){const pointer=pointers.get(event.pointerId);if(!pointer)return;const wasSimple=pointers.size===1;pointers.delete(event.pointerId);if(pointers.size<2){initialDistance=0;lightbox.classList.remove('pinching');lightbox.classList.toggle('panning',pointers.size===1&&zoomLevel>1);}if(pointers.size===0)lightbox.classList.remove('panning');if(lightboxStage.hasPointerCapture?.(event.pointerId))lightboxStage.releasePointerCapture(event.pointerId);if(!wasSimple||zoomLevel>1)return;const deltaX=pointer.x-pointer.startX,deltaY=pointer.y-pointer.startY;if(Math.abs(deltaX)>50&&Math.abs(deltaX)>Math.abs(deltaY))moveLightbox(deltaX>0?1:-1);else if(deltaY>90)closeLightbox();}
    lightboxStage.addEventListener('pointerup',finishPointer);lightboxStage.addEventListener('pointercancel',finishPointer);startGallery();
<?php endif; ?>
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){if(document.getElementById('articleLightbox')?.classList.contains('open'))closeLightbox();else if(menuOverlay.classList.contains('open'))setMenu(false);}<?php if ($noticia && $fotos): ?>if(lightbox.classList.contains('open')&&event.key==='ArrowRight')moveLightbox(1);if(lightbox.classList.contains('open')&&event.key==='ArrowLeft')moveLightbox(-1);<?php endif; ?>});
<?php if ($noticia): ?>
    document.addEventListener('click',async(e)=>{const b=e.target.closest('.vote-btn[data-noticia-id]');if(!b||b.disabled)return;const buttons=[...document.querySelectorAll('.vote-btn[data-noticia-id="'+b.dataset.noticiaId+'"]')];buttons.forEach(x=>x.disabled=true);try{const body=new URLSearchParams({noticia_id:b.dataset.noticiaId,valor:b.dataset.voto});const response=await fetch(<?= json_encode(url_portal('votar.php')) ?>,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin'});const data=await response.json();if(!response.ok)throw new Error(data.error||'Error al votar');buttons.forEach(x=>{const value=Number(x.dataset.voto),voted=value===data.mi_voto;x.classList.toggle('voted',voted);x.setAttribute('aria-pressed',voted?'true':'false');x.querySelector('.vote-count').textContent=(value===1?data.me_gusta:data.no_me_gusta)||'';});}catch(error){buttons.forEach(x=>x.disabled=false);console.error(error);}});
<?php endif; ?>
  </script>
</body>
</html>
