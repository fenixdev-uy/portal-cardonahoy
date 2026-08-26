<?php
/**
 * Vista ampliada del feed móvil.
 *
 * El contenido de cada noticia vive en un <template> inerte y solo se inserta
 * en el panel cuando el visitante lo abre. Así las imágenes, audios y videos
 * de las demás noticias no se cargan dos veces al abrir el feed.
 */
require_once __DIR__ . '/publicidad.php';
?>
  <div class="story-sheet" id="storySheet" aria-hidden="true">
    <button class="story-sheet-backdrop" type="button" data-story-close tabindex="-1" aria-label="Cerrar nota completa"></button>
    <section class="story-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="storySheetHeading" tabindex="-1">
      <header class="story-sheet-header">
        <div class="story-sheet-handle" aria-hidden="true"></div>
        <h2 class="story-sheet-heading" id="storySheetHeading">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h13v16H5.5A2.5 2.5 0 0 1 3 17.5V5a1 1 0 0 1 1-1Z"></path><path d="M17 8h4v9.5a2.5 2.5 0 0 1-2.5 2.5H17M7 8h6M7 12h6M7 16h3"></path></svg>
          <span>RADIO SUR - NOTICIAS</span>
        </h2>
        <button class="story-sheet-close" type="button" data-story-close aria-label="Cerrar nota completa">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
        </button>
      </header>
      <div class="story-sheet-scroll" id="storySheetContent"></div>
    </section>
  </div>

<?php foreach ($noticias as $indiceNoticia => $n): ?>
<?php
    $fotos = $fotosPorNoticia[(int) $n['id']] ?? [];
    $fecha = fecha_larga($n['created_at']);
    $autor = $n['autor_nombre'] ?? '';
    $categoria = $n['categoria_nombre'] ?? '';
    $publicidadesNota = $paresPublicidad[$indiceNoticia % count($paresPublicidad)];
    $publicidadInicial = $publicidadesNota[0];
    $publicidadesFinales = array_slice($publicidadesNota, 1);
?>
  <template data-story-template="<?= (int) $n['id'] ?>">
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

        <div class="story-sheet-lead-ad-wrap">
          <figure class="story-sheet-ad story-sheet-lead-ad">
            <img src="<?= e($publicidadInicial['imagen']) ?>" alt="<?= e($publicidadInicial['alt']) ?>" loading="lazy" decoding="async">
          </figure>
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

<?php if ($publicidadesFinales): ?>
        <section class="story-sheet-ads" aria-label="Publicidad relacionada">
          <span class="story-sheet-ads-label">Publicidad</span>
<?php foreach ($publicidadesFinales as $publicidad): ?>
          <figure class="story-sheet-ad">
            <img src="<?= e($publicidad['imagen']) ?>" alt="<?= e($publicidad['alt']) ?>" loading="lazy" decoding="async">
          </figure>
<?php endforeach; ?>
        </section>
<?php endif; ?>
      </div>
    </article>
  </template>
<?php endforeach; ?>
