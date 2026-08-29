<?php
/**
 * Portada de noticias - versión PC.
 * Grilla editorial resumida que reutiliza las noticias y portadas ya cargadas
 * por index.php. La experiencia móvil continúa en mobile-feed.php.
 */
require_once __DIR__ . '/publicidad.php';
$noticiasPc = isset($noticiasPc) ? array_values($noticiasPc) : array_values($noticias);
$totalNoticiasPc = count($noticiasPc);
$semillaPortadaPc = (int) ($semillaPortadaPc ?? sprintf('%u', crc32('portada-pc')));
$indiceFilaMixtaInicial = 0;
$nombresCategoriasSeleccionadasPc = [];
foreach (($categoriasFiltroPc ?? []) as $categoriaFiltro) {
    if (in_array((int) $categoriaFiltro['id'], $categoriaIdsPc ?? [], true)) {
        $nombresCategoriasSeleccionadasPc[] = (string) $categoriaFiltro['nombre'];
    }
}
$resumenCategoriasPc = count($nombresCategoriasSeleccionadasPc) === 0
    ? 'Todas las categorías'
    : (count($nombresCategoriasSeleccionadasPc) === 1
        ? $nombresCategoriasSeleccionadasPc[0]
        : count($nombresCategoriasSeleccionadasPc) . ' categorías');
?>
  <section class="pc-feed" aria-label="Noticias">
    <form class="pc-news-filters" method="get" action="" role="search" aria-label="Buscar y filtrar noticias">
      <label class="pc-news-filter pc-news-filter-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"></circle>
          <path d="m20 20-4-4"></path>
        </svg>
        <input type="search" name="buscar" value="<?= e($buscarPc ?? '') ?>" placeholder="Buscar noticias..." maxlength="150" aria-label="Buscar por palabras">
      </label>

      <div class="pc-news-filter pc-news-filter-category" data-pc-category-filter>
        <details>
          <summary aria-label="Seleccionar categorías">
            <span data-pc-category-summary><?= e($resumenCategoriasPc) ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
          </summary>
          <div class="pc-news-filter-category-options">
<?php foreach (($categoriasFiltroPc ?? []) as $categoriaFiltro): ?>
            <label>
              <input type="checkbox" name="categoria[]" value="<?= (int) $categoriaFiltro['id'] ?>"<?= in_array((int) $categoriaFiltro['id'], $categoriaIdsPc ?? [], true) ? ' checked' : '' ?>>
              <span><?= e($categoriaFiltro['nombre']) ?></span>
            </label>
<?php endforeach; ?>
          </div>
        </details>
      </div>

      <label class="pc-news-filter pc-news-filter-date">
        <span>Desde</span>
        <input type="date" name="desde" value="<?= e($desdePc ?? '') ?>"<?= $hastaPc !== '' ? ' max="' . e($hastaPc) . '"' : '' ?>>
      </label>

      <label class="pc-news-filter pc-news-filter-date">
        <span>Hasta</span>
        <input type="date" name="hasta" value="<?= e($hastaPc ?? '') ?>"<?= $desdePc !== '' ? ' min="' . e($desdePc) . '"' : '' ?>>
      </label>

      <button class="pc-news-filter-submit" type="submit">Filtrar</button>
      <span class="pc-news-filter-status" aria-live="polite"></span>
    </form>

    <div class="pc-news-results" aria-busy="false">
<?php if ($totalNoticiasPc === 0): ?>
      <div class="pc-news-filter-empty" role="status">
      <strong>No encontramos noticias</strong>
      <span>Probá con otras palabras, categoría o fechas.</span>
      </div>
<?php else: ?>
      <div class="pc-news-grid">
<?php require __DIR__ . '/pc-news-items.php'; ?>
      </div>
<?php if (!empty($hayMasNoticiasPc)): ?>
      <div class="news-load-more-wrap">
        <button class="news-load-more-button" type="button" data-news-load-more data-view="pc" data-url="<?= e(url_portal('cargar-noticias.php')) ?>" data-cursor="<?= e($cursorNoticiasPc ?? '') ?>">Ver + Noticias</button>
      </div>
<?php endif; ?>
<?php endif; ?>
    </div>
  </section>

  <footer class="pc-site-footer">
    <p>Software hecho en <a href="https://fenixlab.uno" target="_blank" rel="noopener noreferrer">Fenix</a></p>
  </footer>
