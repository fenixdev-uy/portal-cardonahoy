<?php
/**
 * Cuatro destinos compartidos por las cards publicitarias PC y móvil.
 * Requiere $publicidad, $nombrePublicidad, $claseRedesPublicidad y
 * $claseEnlacePublicidad.
 */
?>
            <nav class="<?= e($claseRedesPublicidad) ?>" aria-label="Enlaces de <?= e($nombrePublicidad) ?>">
<?php foreach ($redesPublicidadPortal as $redPublicidad): ?>
<?php
    $urlRedPublicidad = trim((string) ($publicidad[$redPublicidad['campo']] ?? ''));
    $esUrlSegura = filter_var($urlRedPublicidad, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($urlRedPublicidad, PHP_URL_SCHEME)), ['http', 'https'], true);
    if (!$esUrlSegura) {
?>
              <span class="<?= e($claseEnlacePublicidad) ?> is-disabled" role="img" aria-label="<?= e($redPublicidad['etiqueta']) ?> sin configurar para <?= e($nombrePublicidad) ?>" aria-disabled="true" title="<?= e($redPublicidad['etiqueta']) ?> sin configurar">
                <svg viewBox="<?= e($redPublicidad['view_box']) ?>" <?= $redPublicidad['relleno'] ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' ?> aria-hidden="true"><?= $redPublicidad['svg'] ?></svg>
              </span>
<?php
        continue;
    }
    $urlDestinoPublicidad = $urlRedPublicidad;
    if (!empty($publicidad['id'])) {
        $urlDestinoPublicidad = url_portal('publicidad-click.php?' . http_build_query([
            'id' => (int) $publicidad['id'],
            'destino' => $redPublicidad['campo'],
        ]));
    }
?>
              <a class="<?= e($claseEnlacePublicidad) ?>" href="<?= e($urlDestinoPublicidad) ?>" target="_blank" rel="noopener noreferrer sponsored" aria-label="<?= e($redPublicidad['etiqueta']) ?> de <?= e($nombrePublicidad) ?>" title="<?= e($redPublicidad['etiqueta']) ?>">
                <svg viewBox="<?= e($redPublicidad['view_box']) ?>" <?= $redPublicidad['relleno'] ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' ?> aria-hidden="true"><?= $redPublicidad['svg'] ?></svg>
              </a>
<?php endforeach; ?>
            </nav>
