<?php
/**
 * Feed de noticias - versión móvil.
 * Noticias completas una debajo de la otra, estilo red social: foto a pantalla
 * completa con título encima y, a continuación, el texto completo de la nota.
 *
 * Consume las mismas variables que el feed PC ($noticias y $fotosPorNoticia),
 * por lo que no agrega consultas a la base de datos.
 *
 * Las galerías de más de una foto se recorren con swipe nativo (scroll-snap) y
 * se pueden ampliar en el visor compartido.
 */
require_once __DIR__ . '/publicidad.php';
?>
  <section class="news-feed" aria-label="Noticias">
<?php foreach ($noticias as $indiceNoticia => $n): ?>
<?php
    $fotos = $fotosPorNoticia[(int) $n['id']] ?? [];
    $youtube = youtube_embed_url($n['youtube'] ?? '');
    $fecha = fecha_larga($n['created_at']);
    $autor = $n['autor_nombre'] ?? '';
    $categoria = $n['categoria_nombre'] ?? '';
    $aviso = $avisosPublicidad[$indiceNoticia % count($avisosPublicidad)];
?>
    <article class="feed-item">
<?php if (count($fotos) > 1): ?>
      <div class="feed-media feed-gallery">
        <div class="feed-track" tabindex="0" aria-label="Galería de fotos, deslizar para ver más">
<?php foreach ($fotos as $i => $foto): ?>
          <div class="feed-frame">
            <img src="<?= e(url_imagen_front($foto['ruta'])) ?>" alt="<?= e($n['titulo']) ?>" loading="lazy" decoding="async">
          </div>
<?php endforeach; ?>
        </div>
        <div class="feed-dots" aria-hidden="true"></div>
        <button class="feed-gallery-expand" type="button" aria-label="Ampliar galería" title="Ampliar galería">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
        </button>
<?php elseif ($fotos): ?>
      <div class="feed-media">
        <img src="<?= e(url_imagen_front($fotos[0]['ruta'])) ?>" alt="<?= e($n['titulo']) ?>" loading="lazy" decoding="async">
<?php else: ?>
      <div class="feed-media feed-media-empty">
<?php endif; ?>
        <div class="feed-media-content">
          <?php if ($categoria !== ''): ?><span class="feed-media-tag"><?= e($categoria) ?></span><?php endif; ?>
          <h2 class="feed-media-title"><?= e($n['titulo']) ?></h2>
        </div>
      </div>

      <div class="feed-text rich-text">
        <div class="feed-meta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <?php if ($fecha !== ''): ?><span><?= e($fecha) ?></span><?php endif; ?>
          <?php if ($fecha !== '' && $autor !== ''): ?><span>-</span><?php endif; ?>
          <?php if ($autor !== ''): ?><span class="feed-author"><?= e($autor) ?></span><?php endif; ?>
        </div>

        <?= $n['descripcion'] /* HTML ya saneado en el servidor */ ?>

        <?php if ($youtube !== ''): ?>
        <div class="feed-video">
          <iframe src="<?= e($youtube) ?>" title="Video de YouTube" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
        <?php endif; ?>

<?php $prefijo = 'feed'; include __DIR__ . '/acciones-noticia.php'; ?>
      </div>
    </article>

    <aside class="feed-ad-item" aria-label="Publicidad">
      <figure class="feed-ad-panel">
        <img src="<?= e($aviso['imagen']) ?>" alt="<?= e($aviso['alt']) ?>" loading="lazy" decoding="async">
      </figure>
    </aside>
<?php endforeach; ?>
  </section>
