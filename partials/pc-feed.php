<?php
/**
 * Portada de noticias - versión PC.
 * Grilla editorial resumida que reutiliza las noticias y portadas ya cargadas
 * por index.php. La experiencia móvil continúa en mobile-feed.php.
 */
require_once __DIR__ . '/publicidad.php';
$noticiasPc = array_values($noticias);
$totalNoticiasPc = count($noticiasPc);
$indiceNoticiaPc = 0;
$indiceFilaMixta = 0;
?>
  <section class="pc-feed" aria-label="Noticias">
    <div class="pc-news-grid">
<?php while ($indiceNoticiaPc < $totalNoticiasPc): ?>
<?php for ($cantidadNoticias = 0; $cantidadNoticias < 3 && $indiceNoticiaPc < $totalNoticiasPc; $cantidadNoticias++, $indiceNoticiaPc++): ?>
<?php $n = $noticiasPc[$indiceNoticiaPc]; ?>
<?php require __DIR__ . '/pc-news-card.php'; ?>
<?php endfor; ?>
<?php if ($cantidadNoticias === 3 && $indiceNoticiaPc < $totalNoticiasPc): ?>
<?php
    $n = $noticiasPc[$indiceNoticiaPc++];
    $semillaFila = (int) sprintf('%u', crc32((string) $n['id'] . '|' . (string) $n['slug'] . '|' . $indiceFilaMixta));
    $posicionNoticia = $semillaFila % 3;
    $totalPublicidades = count($filaPublicidadPc);
    $inicioPublicidad = ($indiceFilaMixta * 2) % $totalPublicidades;
    $publicidadesFila = [
        $filaPublicidadPc[$inicioPublicidad],
        $filaPublicidadPc[($inicioPublicidad + 1) % $totalPublicidades],
    ];
    if ($semillaFila % 2 === 1) {
        $publicidadesFila = array_reverse($publicidadesFila);
    }
    $indicePublicidad = 0;
?>
      <div class="pc-news-mixed-row" aria-label="Noticias y publicidad">
<?php for ($posicion = 0; $posicion < 3; $posicion++): ?>
<?php if ($posicion === $posicionNoticia): ?>
<?php require __DIR__ . '/pc-news-card.php'; ?>
<?php else: ?>
<?php $publicidad = $publicidadesFila[$indicePublicidad++]; ?>
        <aside class="pc-news-ad-card">
          <figure class="pc-news-ad-media">
            <img src="<?= e($publicidad['imagen']) ?>" alt="<?= e($publicidad['alt']) ?>" loading="lazy" decoding="async">
          </figure>
          <span class="pc-news-ad-label">Publicidad</span>
        </aside>
<?php endif; ?>
<?php endfor; ?>
      </div>
<?php $indiceFilaMixta++; ?>
<?php endif; ?>
<?php endwhile; ?>
    </div>
  </section>
