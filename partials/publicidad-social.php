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
    $anuncioId = (int) ($publicidad['id'] ?? 0);
    $atributosSeguimiento = $anuncioId > 0
        ? ' data-ad-click-url="' . e(url_portal('publicidad-click.php')) . '"'
            . ' data-ad-id="' . $anuncioId . '"'
            . ' data-ad-destination="' . e($redPublicidad['campo']) . '"'
            . ' data-ad-label="' . e($redPublicidad['etiqueta']) . '"'
            . ' data-ad-name="' . e($nombrePublicidad) . '"'
        : '';
?>
              <a class="<?= e($claseEnlacePublicidad) ?><?= $esUrlSegura ? '' : ' is-disabled' ?>"<?= $esUrlSegura ? ' href="' . e($urlRedPublicidad) . '"' : '' ?><?= $atributosSeguimiento ?> target="_blank" rel="noopener noreferrer sponsored" aria-label="<?= e($esUrlSegura ? $redPublicidad['etiqueta'] . ' de ' . $nombrePublicidad : $redPublicidad['etiqueta'] . ' sin configurar para ' . $nombrePublicidad) ?>" title="<?= e($esUrlSegura ? $redPublicidad['etiqueta'] : $redPublicidad['etiqueta'] . ' sin configurar') ?>"<?= $esUrlSegura ? '' : ' aria-disabled="true" tabindex="-1"' ?>>
                <svg viewBox="<?= e($redPublicidad['view_box']) ?>" <?= $redPublicidad['relleno'] ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' ?> aria-hidden="true"><?= $redPublicidad['svg'] ?></svg>
              </a>
<?php endforeach; ?>
            </nav>
