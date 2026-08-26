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
$identidadesNoticiasPc = array_map(static function (array $noticia): string {
    return (string) $noticia['id'] . ':' . (string) $noticia['slug'];
}, $noticiasPc);
$semillaPortadaPc = (int) sprintf('%u', crc32(implode('|', $identidadesNoticiasPc)));
$desplazamientoPosicion = $semillaPortadaPc % 3;
$sentidoPosicion = intdiv($semillaPortadaPc, 3) % 2 === 0 ? 1 : -1;
$totalPublicidadesPc = count($filaPublicidadPc);
$desplazamientoPublicidad = $totalPublicidadesPc > 0 ? $semillaPortadaPc % $totalPublicidadesPc : 0;
$sentidoPublicidad = $totalPublicidadesPc > 0 && intdiv($semillaPortadaPc, $totalPublicidadesPc) % 2 !== 0 ? -1 : 1;
$redesPublicidadPc = [
    ['campo' => 'facebook_url', 'etiqueta' => 'Facebook', 'svg' => '<path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"></path>', 'view_box' => '0 0 512 512', 'relleno' => true],
    ['campo' => 'instagram_url', 'etiqueta' => 'Instagram', 'svg' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4.25"></circle><circle cx="17.4" cy="6.6" r="1" fill="currentColor" stroke="none"></circle>', 'view_box' => '0 0 24 24', 'relleno' => false],
    ['campo' => 'whatsapp_url', 'etiqueta' => 'WhatsApp', 'svg' => '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path>', 'view_box' => '0 0 24 24', 'relleno' => true],
    ['campo' => 'sitio_web_url', 'etiqueta' => 'Sitio web', 'svg' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>', 'view_box' => '0 0 24 24', 'relleno' => false],
];
?>
  <section class="pc-feed" aria-label="Noticias">
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
            <nav class="pc-news-ad-social" aria-label="Enlaces de <?= e($nombrePublicidad) ?>">
<?php foreach ($redesPublicidadPc as $redPublicidad): ?>
<?php
    $urlRedPublicidad = trim((string) ($publicidad[$redPublicidad['campo']] ?? ''));
    $esUrlSegura = filter_var($urlRedPublicidad, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($urlRedPublicidad, PHP_URL_SCHEME)), ['http', 'https'], true);
    if (!$esUrlSegura) {
?>
              <span class="pc-news-ad-social-link is-disabled" role="img" aria-label="<?= e($redPublicidad['etiqueta']) ?> sin configurar para <?= e($nombrePublicidad) ?>" aria-disabled="true" title="<?= e($redPublicidad['etiqueta']) ?> sin configurar">
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
              <a class="pc-news-ad-social-link" href="<?= e($urlDestinoPublicidad) ?>" target="_blank" rel="noopener noreferrer sponsored" aria-label="<?= e($redPublicidad['etiqueta']) ?> de <?= e($nombrePublicidad) ?>" title="<?= e($redPublicidad['etiqueta']) ?>">
                <svg viewBox="<?= e($redPublicidad['view_box']) ?>" <?= $redPublicidad['relleno'] ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' ?> aria-hidden="true"><?= $redPublicidad['svg'] ?></svg>
              </a>
<?php endforeach; ?>
            </nav>
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
  </section>
