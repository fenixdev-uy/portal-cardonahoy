<?php
/**
 * Tarjeta publicitaria pública reutilizable.
 * Requiere $publicidad y admite $claseTarjetaPublicidad adicional.
 */
$nombrePublicidad = trim((string) ($publicidad['nombre'] ?? 'Anunciante'));
$claseTarjetaPublicidad = trim((string) ($claseTarjetaPublicidad ?? ''));
?>
<aside class="pc-news-ad-card<?= $claseTarjetaPublicidad !== '' ? ' ' . e($claseTarjetaPublicidad) : '' ?>" aria-label="Publicidad de <?= e($nombrePublicidad) ?>">
  <figure class="pc-news-ad-media">
    <img src="<?= e(url_imagen_front((string) $publicidad['imagen'])) ?>" alt="<?= e($publicidad['alt'] ?? ('Publicidad de ' . $nombrePublicidad)) ?>" loading="lazy" decoding="async">
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
