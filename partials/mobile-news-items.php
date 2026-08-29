<?php
$noticiasMovil = array_values($noticiasMovil ?? $noticias ?? []);
$offsetNoticiasMovil = (int) ($offsetNoticiasMovil ?? 0);
$totalAnunciosMovil = count($anunciosPublicidadActivos);
?>
<?php foreach ($noticiasMovil as $indiceNoticia => $n): ?>
<?php
    $fotos = $fotosPorNoticia[(int) $n['id']] ?? [];
    $fecha = fecha_larga($n['created_at']);
    $autor = $n['autor_nombre'] ?? '';
    $categoria = $n['categoria_nombre'] ?? '';
    $portada = $fotos[0] ?? null;
    $resumen = html_a_texto($n['descripcion'] ?? '', 280);
    $indiceGlobalMovil = $offsetNoticiasMovil + $indiceNoticia;
    $aviso = $totalAnunciosMovil > 0 ? $anunciosPublicidadActivos[$indiceGlobalMovil % $totalAnunciosMovil] : null;
?>
    <article class="feed-item">
<?php if ($portada): ?>
      <div class="feed-media">
        <img src="<?= e(url_imagen_front($portada['ruta'])) ?>" alt="<?= e($n['titulo']) ?>" loading="lazy" decoding="async">
<?php else: ?>
      <div class="feed-media feed-media-empty">
<?php endif; ?>
        <div class="feed-media-content">
          <?php if ($categoria !== ''): ?><span class="feed-media-tag"><?= e($categoria) ?></span><?php endif; ?>
          <h2 class="feed-media-title"><?= e($n['titulo']) ?></h2>
        </div>
      </div>

      <div class="feed-text">
        <div class="feed-meta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <?php if ($fecha !== ''): ?><span><?= e($fecha) ?></span><?php endif; ?>
          <?php if ($fecha !== '' && $autor !== ''): ?><span>-</span><?php endif; ?>
          <?php if ($autor !== ''): ?><span class="feed-author"><?= e($autor) ?></span><?php endif; ?>
        </div>

        <?php if ($resumen !== ''): ?><p class="feed-summary"><?= e($resumen) ?></p><?php endif; ?>

<?php include __DIR__ . '/boton-nota-completa.php'; ?>
      </div>
    </article>

<?php if ($aviso): ?>
<?php $nombrePublicidad = trim((string) $aviso['nombre']); $publicidad = $aviso; ?>
    <aside class="feed-ad-item" aria-label="Publicidad de <?= e($nombrePublicidad) ?>">
      <div class="feed-ad-card">
        <figure class="feed-ad-media">
          <img src="<?= e(url_imagen_front($aviso['imagen'])) ?>" alt="<?= e($aviso['alt']) ?>" loading="lazy" decoding="async">
        </figure>
        <footer class="feed-ad-footer">
          <span class="feed-ad-label">Publicidad</span>
<?php
    $claseRedesPublicidad = 'feed-ad-social';
    $claseEnlacePublicidad = 'feed-ad-social-link';
    require __DIR__ . '/publicidad-social.php';
?>
        </footer>
      </div>
    </aside>
<?php endif; ?>
<?php endforeach; ?>
