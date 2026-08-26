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
  <link rel="stylesheet" href="assets/css/noticia.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/noticia.css') ?>" />
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
