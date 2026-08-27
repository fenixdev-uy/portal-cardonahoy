<?php
/**
 * Portada de noticias - versión PC.
 * Grilla editorial resumida que reutiliza las noticias y portadas ya cargadas
 * por index.php. La experiencia móvil continúa en mobile-feed.php.
 */
require_once __DIR__ . '/publicidad.php';
$noticiasPc = isset($noticiasPc) ? array_values($noticiasPc) : array_values($noticias);
$totalNoticiasPc = count($noticiasPc);
$indiceNoticiaPc = 0;
$indiceFilaMixta = 0;
$identidadesNoticiasPc = array_map(static function (array $noticia): string {
    return (string) $noticia['id'] . ':' . (string) $noticia['slug'];
}, $noticiasPc);
$semillaPortadaPc = (int) sprintf('%u', crc32(implode('|', $identidadesNoticiasPc)));
$desplazamientoPosicion = $semillaPortadaPc % 3;
$sentidoPosicion = intdiv($semillaPortadaPc, 3) % 2 === 0 ? 1 : -1;
$totalPublicidadesPc = count($filaPublicidadPc);
$desplazamientoPublicidad = $totalPublicidadesPc > 0 ? $semillaPortadaPc % $totalPublicidadesPc : 0;
$sentidoPublicidad = $totalPublicidadesPc > 0 && intdiv($semillaPortadaPc, $totalPublicidadesPc) % 2 !== 0 ? -1 : 1;
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

      <label class="pc-news-filter pc-news-filter-category">
        <select name="categoria" aria-label="Seleccionar categoría">
          <option value="">Todas las categorías</option>
<?php foreach (($categoriasFiltroPc ?? []) as $categoriaFiltro): ?>
          <option value="<?= (int) $categoriaFiltro['id'] ?>"<?= ($categoriaPc ?? null) === (int) $categoriaFiltro['id'] ? ' selected' : '' ?>><?= e($categoriaFiltro['nombre']) ?></option>
<?php endforeach; ?>
        </select>
      </label>

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
<?php while ($indiceNoticiaPc < $totalNoticiasPc): ?>
<?php $noticiasFila = array_slice($noticiasPc, $indiceNoticiaPc, 2); ?>
<?php $indiceNoticiaPc += count($noticiasFila); ?>
<?php if (count($noticiasFila) < 2 || $totalPublicidadesPc === 0): ?>
<?php foreach ($noticiasFila as $n): ?>
<?php require __DIR__ . '/pc-news-card.php'; ?>
<?php endforeach; ?>
<?php else: ?>
<?php
    $posicionPublicidad = (($desplazamientoPosicion + ($sentidoPosicion * $indiceFilaMixta)) % 3 + 3) % 3;
    $indicePublicidad = (($desplazamientoPublicidad + ($sentidoPublicidad * $indiceFilaMixta)) % $totalPublicidadesPc + $totalPublicidadesPc) % $totalPublicidadesPc;
    $publicidad = $filaPublicidadPc[$indicePublicidad];
    $nombrePublicidad = trim((string) ($publicidad['nombre'] ?? $publicidad['alt']));
    $indiceNoticiaFila = 0;
?>
      <div class="pc-news-mixed-row" aria-label="Dos noticias y publicidad">
<?php for ($posicion = 0; $posicion < 3; $posicion++): ?>
<?php if ($posicion === $posicionPublicidad): ?>
        <aside class="pc-news-ad-card">
          <figure class="pc-news-ad-media">
            <img src="<?= e($publicidad['imagen']) ?>" alt="<?= e($publicidad['alt']) ?>" loading="lazy" decoding="async">
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
<?php else: ?>
<?php $n = $noticiasFila[$indiceNoticiaFila++]; ?>
<?php require __DIR__ . '/pc-news-card.php'; ?>
<?php endif; ?>
<?php endfor; ?>
      </div>
<?php $indiceFilaMixta++; ?>
<?php endif; ?>
<?php endwhile; ?>
      </div>
<?php endif; ?>
    </div>
  </section>
