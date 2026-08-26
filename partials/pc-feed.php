<?php
/**
 * Portada de noticias - versión PC.
 * Grilla editorial resumida que reutiliza las noticias y portadas ya cargadas
 * por index.php. La experiencia móvil continúa en mobile-feed.php.
 */
?>
  <section class="pc-feed" aria-label="Noticias">
    <div class="pc-news-grid">
<?php foreach ($noticias as $n): ?>
<?php
    $fotos = $fotosPorNoticia[(int) $n['id']] ?? [];
    $portada = $fotos[0] ?? null;
    $categoria = trim((string) ($n['categoria_nombre'] ?? ''));
    $resumen = html_a_texto($n['descripcion'] ?? '', 220);
    $timestamp = strtotime((string) ($n['created_at'] ?? ''));
    $fecha = $timestamp !== false ? date('j/n/Y', $timestamp) : '';
    $url = url_noticia((string) $n['slug']);
?>
      <article class="pc-news-card">
        <a class="pc-news-card-link" href="<?= e($url) ?>" data-story-id="<?= (int) $n['id'] ?>" aria-label="Abrir noticia: <?= e($n['titulo']) ?>">
<?php if ($portada): ?>
          <figure class="pc-news-card-media">
            <img src="<?= e(url_imagen_front($portada['ruta'])) ?>" alt="<?= e($n['titulo']) ?>" loading="lazy" decoding="async">
          </figure>
<?php else: ?>
          <div class="pc-news-card-media pc-news-card-media-empty" aria-hidden="true"></div>
<?php endif; ?>

          <div class="pc-news-card-body">
<?php if ($categoria !== '' || $fecha !== ''): ?>
            <p class="pc-news-card-meta">
              <?php if ($categoria !== ''): ?><span><?= e($categoria) ?></span><?php endif; ?>
              <?php if ($categoria !== '' && $fecha !== ''): ?><span aria-hidden="true">·</span><?php endif; ?>
              <?php if ($fecha !== ''): ?><time datetime="<?= e(date('Y-m-d', $timestamp)) ?>"><?= e($fecha) ?></time><?php endif; ?>
            </p>
<?php endif; ?>
            <h2 class="pc-news-card-title"><?= e($n['titulo']) ?></h2>
<?php if ($resumen !== ''): ?>
            <p class="pc-news-card-summary"><?= e($resumen) ?></p>
<?php endif; ?>
          </div>
        </a>
      </article>
<?php endforeach; ?>
    </div>
  </section>
