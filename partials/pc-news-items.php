<?php
$noticiasPc = array_values($noticiasPc ?? []);
$totalNoticiasPc = count($noticiasPc);
$indiceNoticiaPc = 0;
$indiceFilaMixta = (int) ($indiceFilaMixtaInicial ?? 0);
$mostrarBotonNotaCompletaPc = true;
$semillaPortadaPc = (int) ($semillaPortadaPc ?? 0);
$desplazamientoPosicion = $semillaPortadaPc % 3;
$sentidoPosicion = intdiv($semillaPortadaPc, 3) % 2 === 0 ? 1 : -1;
$totalPublicidadesPc = count($filaPublicidadPc);
$desplazamientoPublicidad = $totalPublicidadesPc > 0 ? $semillaPortadaPc % $totalPublicidadesPc : 0;
$sentidoPublicidad = $totalPublicidadesPc > 0 && intdiv($semillaPortadaPc, $totalPublicidadesPc) % 2 !== 0 ? -1 : 1;
?>
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
            <img src="<?= e(url_imagen_front($publicidad['imagen'])) ?>" alt="<?= e($publicidad['alt']) ?>" loading="lazy" decoding="async">
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
<?php $mostrarBotonNotaCompletaPc = false; ?>
