<?php
/**
 * Vista ampliada del feed móvil.
 *
 * El contenido de cada noticia vive en un <template> inerte y solo se inserta
 * en el panel cuando el visitante lo abre. Así las imágenes, audios y videos
 * de las demás noticias no se cargan dos veces al abrir el feed.
 */
require_once __DIR__ . '/publicidad.php';
$soloTemplatesNoticias = !empty($soloTemplatesNoticias);
$noticiasParaTemplates = array_values($noticiasParaTemplates ?? $noticiasTemplates ?? $noticias);
?>
<?php if (!$soloTemplatesNoticias): ?>
  <div class="story-sheet" id="storySheet" aria-hidden="true">
    <button class="story-sheet-backdrop" type="button" data-story-close tabindex="-1" aria-label="Cerrar nota completa"></button>
    <section class="story-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="storySheetHeading" tabindex="-1">
      <header class="story-sheet-header">
        <div class="story-sheet-handle" aria-hidden="true"></div>
        <h2 class="story-sheet-heading" id="storySheetHeading">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h13v16H5.5A2.5 2.5 0 0 1 3 17.5V5a1 1 0 0 1 1-1Z"></path><path d="M17 8h4v9.5a2.5 2.5 0 0 1-2.5 2.5H17M7 8h6M7 12h6M7 16h3"></path></svg>
          <span>NOTICIA</span>
        </h2>
        <button class="story-sheet-close" type="button" data-story-close aria-label="Cerrar nota completa">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
        </button>
      </header>
      <div class="story-sheet-scroll" id="storySheetContent"></div>
    </section>
  </div>

<?php $ultimasNoticiasDrawer = array_slice(array_values($noticiasRecientes ?? $noticias), 0, 11); ?>
<?php if (count($ultimasNoticiasDrawer) > 1): ?>
  <template id="storyLatestTemplate">
    <section class="story-latest" aria-labelledby="storyLatestTitle">
      <header class="story-latest-header">
        <span aria-hidden="true"></span>
        <h2 id="storyLatestTitle">Últimas Noticias</h2>
        <span aria-hidden="true"></span>
      </header>
      <div class="story-latest-list">
<?php foreach ($ultimasNoticiasDrawer as $indiceUltima => $noticiaUltima): ?>
        <div class="story-latest-entry" data-story-recommendation="<?= (int) $noticiaUltima['id'] ?>">
<?php
    $n = $noticiaUltima;
    $mostrarBotonNotaCompletaPc = true;
    require __DIR__ . '/pc-news-card.php';
    $mostrarBotonNotaCompletaPc = false;
?>
<?php if ($anunciosPublicidadActivos): ?>
<?php
    $publicidad = $anunciosPublicidadActivos[$indiceUltima % count($anunciosPublicidadActivos)];
    $nombrePublicidad = trim((string) $publicidad['nombre']);
?>
          <aside class="pc-news-ad-card story-latest-ad-card" aria-label="Publicidad de <?= e($nombrePublicidad) ?>">
            <figure class="pc-news-ad-media">
              <img src="<?= e(url_imagen_front($publicidad['imagen'])) ?>" alt="<?= e($publicidad['alt']) ?>" loading="lazy" decoding="async">
            </figure>
            <footer class="pc-news-ad-footer">
              <span class="pc-news-ad-label">Publicidad</span>
<?php
    $claseRedesPublicidad = 'pc-news-ad-social';
    $claseEnlacePublicidad = 'pc-news-ad-social-link';
    require __DIR__ . '/publicidad-social.php';
?>
            </footer>
          </aside>
<?php endif; ?>
        </div>
<?php endforeach; ?>
      </div>
    </section>
  </template>
<?php endif; ?>
  <div id="storyTemplates">
<?php endif; ?>

<?php foreach ($noticiasParaTemplates as $indiceNoticia => $n): ?>
<?php
    $fotos = $fotosPorNoticia[(int) $n['id']] ?? [];
    $fecha = fecha_larga($n['created_at']);
    $autor = $n['autor_nombre'] ?? '';
    $categoria = $n['categoria_nombre'] ?? '';
?>
  <template data-story-template="<?= (int) $n['id'] ?>" data-story-title="<?= e($n['titulo']) ?>" data-story-url="<?= e(url_noticia((string) ($n['slug'] ?? $n['titulo']))) ?>">
    <article class="story-sheet-article rich-text">
<?php if ($fotos): ?>
      <div class="story-sheet-gallery" aria-label="Galería de la noticia">
        <div class="story-sheet-gallery-track" tabindex="0" aria-label="Galería de fotos, deslizar para ver más">
<?php foreach ($fotos as $i => $foto): ?>
          <figure class="story-sheet-photo">
          <img src="<?= e(url_imagen_front($foto['ruta'])) ?>" alt="<?= e($n['titulo']) ?><?= count($fotos) > 1 ? ' — imagen ' . ($i + 1) : '' ?>" loading="lazy" decoding="async">
          </figure>
<?php endforeach; ?>
        </div>
<?php if (count($fotos) > 1): ?>
        <div class="story-sheet-gallery-dots" aria-hidden="true"></div>
<?php endif; ?>
        <button class="story-sheet-gallery-expand" type="button" aria-label="Ampliar galería" title="Ampliar galería">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
        </button>
      </div>
<?php endif; ?>

      <div class="story-sheet-copy">
        <?php if ($categoria !== ''): ?><span class="story-sheet-tag"><?= e($categoria) ?></span><?php endif; ?>
        <h2 class="story-sheet-title"><?= e($n['titulo']) ?></h2>
        <div class="story-sheet-meta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <?php if ($fecha !== ''): ?><span><?= e($fecha) ?></span><?php endif; ?>
          <?php if ($fecha !== '' && $autor !== ''): ?><span>—</span><?php endif; ?>
          <?php if ($autor !== ''): ?><span class="story-sheet-author"><?= e($autor) ?></span><?php endif; ?>
        </div>

        <div class="story-sheet-placement-ad-wrap story-sheet-placement-ad-header" data-ad-placement="encabezado"<?= $anuncioPublicidadEncabezado === null ? ' hidden' : '' ?>>
<?php if ($anuncioPublicidadEncabezado !== null): ?>
<?php $publicidad = $anuncioPublicidadEncabezado; $claseTarjetaPublicidad = 'story-sheet-placement-ad article-ad-card'; require __DIR__ . '/anuncio-card.php'; ?>
<?php endif; ?>
        </div>

        <div class="story-reading-tools" role="group" aria-label="Tamaño del texto">
          <button class="story-reading-button" type="button" data-reading-adjust="-0.1" aria-label="Achicar texto" title="Achicar texto">
            <span>A</span><span class="story-reading-symbol" aria-hidden="true">−</span>
          </button>
          <button class="story-reading-button" type="button" data-reading-adjust="0.1" aria-label="Agrandar texto" title="Agrandar texto">
            <span>A</span><span class="story-reading-symbol" aria-hidden="true">+</span>
          </button>
          <span class="story-reading-status" aria-live="polite">Tamaño de texto 100%</span>
        </div>

        <div class="story-sheet-body" data-reading-scale="1">
          <?= $n['descripcion'] /* HTML ya saneado en el servidor */ ?>
        </div>

<?php include __DIR__ . '/medios-noticia.php'; ?>

<?php
    $prefijo = 'story-sheet';
    include __DIR__ . '/acciones-noticia.php';
?>

        <section class="story-sheet-placement-ad-wrap story-sheet-placement-ad-footer" data-ad-placement="pie" aria-label="Publicidad al pie de la noticia"<?= $anuncioPublicidadPie === null ? ' hidden' : '' ?>>
<?php if ($anuncioPublicidadPie !== null): ?>
<?php $publicidad = $anuncioPublicidadPie; $claseTarjetaPublicidad = 'story-sheet-placement-ad article-ad-card'; require __DIR__ . '/anuncio-card.php'; ?>
<?php endif; ?>
        </section>
      </div>
    </article>
  </template>
<?php endforeach; ?>
<?php if (!$soloTemplatesNoticias): ?>
  </div>
<?php endif; ?>
