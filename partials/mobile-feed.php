<?php
/**
 * Feed de noticias - versión móvil.
 * Resumen de noticias una debajo de la otra: una sola portada, fecha, autor,
 * extracto y acceso a la vista completa.
 *
 * Consume las mismas variables que el feed PC ($noticias y $fotosPorNoticia),
 * por lo que no agrega consultas a la base de datos.
 *
 * La galería completa queda reservada para la vista ampliada de la noticia.
 */
require_once __DIR__ . '/publicidad.php';
$noticiasMovil = array_values($noticias);
$offsetNoticiasMovil = 0;
?>
  <section class="news-feed" aria-label="Noticias">
    <div class="news-feed-items">
<?php require __DIR__ . '/mobile-news-items.php'; ?>
    </div>
<?php if (!empty($hayMasNoticiasMovil)): ?>
    <div class="news-load-more-wrap">
      <button class="news-load-more-button" type="button" data-news-load-more data-view="mobile" data-url="<?= e(url_portal('cargar-noticias.php')) ?>" data-cursor="<?= e($cursorNoticiasMovil ?? '') ?>" data-ad-seed="<?= (int) $semillaPublicidad ?>">Ver + Noticias</button>
    </div>
<?php endif; ?>
  </section>
