<?php
/**
 * Feed de noticias - versión PC.
 * 1 noticia por pantalla (scroll-snap), imagen a la izquierda, texto a la derecha.
 * La galería se muestra como mini-slider si hay más de una foto; con una sola
 * se muestra como imagen fija; sin fotos, un fondo neutro.
 */
require_once __DIR__ . '/publicidad.php';
?>
  <section class="pc-feed" aria-label="Noticias">
<?php foreach ($noticias as $indiceNoticia => $n): ?>
<?php
    $fotos = $fotosPorNoticia[(int) $n['id']] ?? [];
    $urlPortada = $fotos ? url_imagen_front($fotos[0]['ruta']) : '';
    $youtube = youtube_embed_url($n['youtube'] ?? '');
    $fecha = fecha_larga($n['created_at']);
    $autor = $n['autor_nombre'] ?? '';
    $categoria = $n['categoria_nombre'] ?? '';
    $publicidades = $paresPublicidad[$indiceNoticia % count($paresPublicidad)];
?>
    <article class="pc-item">
      <button class="pc-scroll-hint" type="button" aria-label="Desplazarse hacia abajo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg>
      </button>

<?php if (count($fotos) > 1): ?>
      <div class="pc-media pc-slider">
<?php foreach ($fotos as $i => $foto): ?>
        <div class="pc-slide<?= $i === 0 ? ' active' : '' ?>" data-full-src="<?= e(url_imagen_front($foto['ruta'])) ?>" style="background-image: url('<?= e(url_imagen_front($foto['ruta'])) ?>');"></div>
<?php endforeach; ?>
        <div class="pc-dots" role="tablist" aria-label="Indicadores de imagen"></div>
        <button class="pc-gallery-expand" type="button" aria-label="Ampliar galería" title="Ampliar galería">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
        </button>
      </div>
<?php elseif ($urlPortada !== ''): ?>
      <div class="pc-media" style="background-image: url('<?= e($urlPortada) ?>');"></div>
<?php else: ?>
      <div class="pc-media pc-media-empty"></div>
<?php endif; ?>

      <div class="pc-content rich-text">
        <?php if ($categoria !== ''): ?><span class="pc-tag"><?= e($categoria) ?></span><?php endif; ?>
        <h2 class="pc-title"><?= e($n['titulo']) ?></h2>
        <div class="pc-meta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <?php if ($fecha !== ''): ?><span><?= e($fecha) ?></span><?php endif; ?>
          <?php if ($fecha !== '' && $autor !== ''): ?><span>-</span><?php endif; ?>
          <?php if ($autor !== ''): ?><span class="pc-author"><?= e($autor) ?></span><?php endif; ?>
        </div>

        <?= $n['descripcion'] /* HTML ya saneado en el servidor */ ?>

        <?php if ($youtube !== ''): ?>
        <div class="pc-video">
          <iframe src="<?= e($youtube) ?>" title="Video de YouTube" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
        <?php endif; ?>

<?php $prefijo = 'pc'; include __DIR__ . '/acciones-noticia.php'; ?>
      </div>
    </article>

    <aside class="pc-ad-item" aria-label="Publicidad">
<?php foreach ($publicidades as $publicidad): ?>
      <figure class="pc-ad-panel">
        <img src="<?= e($publicidad['imagen']) ?>" alt="<?= e($publicidad['alt']) ?>" loading="lazy" decoding="async">
      </figure>
<?php endforeach; ?>
    </aside>
<?php endforeach; ?>
  </section>
