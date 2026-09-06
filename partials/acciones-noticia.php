<?php
/**
 * Bloque de acciones de una noticia: voto y compartir.
 * Compartido por la noticia individual y la vista completa movil para no
 * duplicar el marcado.
 *
 * Espera en el ambito:
 *   $n         noticia actual (usa id, me_gusta, no_me_gusta)
 *   $misVotos  array noticia_id => valor con los votos de este visitante
 *   $prefijo   'article' o 'story-sheet', para las clases del contenedor
 *   $modoAccionesNoticia 'completo', 'votos' o 'compartir'
 */
$noticiaId = (int) $n['id'];
$miVoto = (int) ($misVotos[$noticiaId] ?? 0);
$conteos = [1 => (int) ($n['me_gusta'] ?? 0), -1 => (int) ($n['no_me_gusta'] ?? 0)];
$mostrarCompartir = $mostrarCompartir ?? true;
$urlCompartir = isset($n['slug']) && trim((string) $n['slug']) !== '' ? url_noticia((string) $n['slug']) : url_base_portal();
$usuarioAcciones = is_array($usuarioPublico ?? null) ? $usuarioPublico : usuario_actual_publico();
$puedeCopiarLinkNoticia = $usuarioAcciones !== null && tiene_permiso('noticias.editar');
$modoAccionesSolicitado = $modoAccionesNoticia ?? 'completo';
$modoAccionesNoticia = in_array($modoAccionesSolicitado, ['completo', 'votos', 'compartir'], true)
    ? $modoAccionesSolicitado
    : 'completo';
$mostrarVotosAcciones = $modoAccionesNoticia !== 'compartir';
$mostrarCompartirAcciones = $modoAccionesNoticia !== 'votos';

$opciones = [
    1 => [
        'etiqueta' => 'Me Gusta',
        'corta' => 'Me gusta',
        'svg' => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>',
    ],
    -1 => [
        'etiqueta' => 'No me gusta',
        'corta' => 'No me gusta',
        'svg' => '<path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>',
    ],
];
?>
<?php if ($mostrarCompartirAcciones): ?>
        <div class="<?= e($prefijo) ?>-actions <?= e($prefijo) ?>-actions-<?= e($modoAccionesNoticia) ?>">
<?php endif; ?>
<?php if ($mostrarVotosAcciones): ?>
          <div class="<?= e($prefijo) ?>-vote" data-noticia-id="<?= $noticiaId ?>">
<?php foreach ($opciones as $valor => $opcion): ?>
<?php
    $votado = $miVoto === $valor;
    $conteo = $conteos[$valor];
?>
            <button class="vote-btn<?= $votado ? ' voted' : '' ?>" type="button"
                    data-noticia-id="<?= $noticiaId ?>" data-voto="<?= $valor ?>"
                    <?= $miVoto !== 0 ? 'disabled ' : '' ?>aria-pressed="<?= $votado ? 'true' : 'false' ?>"
                    aria-label="<?= e($opcion['etiqueta']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $opcion['svg'] ?></svg>
              <span class="vote-label"><?= e($opcion['etiqueta']) ?></span>
              <span class="vote-count"><?= $conteo > 0 ? (int) $conteo : '' ?></span>
            </button>
<?php endforeach; ?>
          </div>
<?php endif; ?>
<?php if ($mostrarCompartirAcciones && $mostrarCompartir): ?>
          <div class="<?= e($prefijo) ?>-share">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($urlCompartir)) ?>" class="share-btn share-btn-facebook" target="_blank" rel="noopener noreferrer" data-share-noticia-id="<?= $noticiaId ?>" data-share-destino="facebook" aria-label="Compartir en Facebook">
              <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"></path></svg>
              <span class="share-btn-text">Compartir</span>
            </a>
            <a href="https://wa.me/?text=<?= e(rawurlencode($urlCompartir)) ?>" class="share-btn share-btn-whatsapp" target="_blank" rel="noopener noreferrer" data-share-noticia-id="<?= $noticiaId ?>" data-share-destino="whatsapp" aria-label="Compartir en WhatsApp">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path></svg>
              <span class="share-btn-text">Compartir</span>
            </a>
          </div>
<?php endif; ?>
<?php if ($mostrarCompartirAcciones): ?>
        </div>
<?php endif; ?>
<?php if ($mostrarCompartirAcciones && $puedeCopiarLinkNoticia): ?>
        <div class="news-copy-link-tool">
          <button class="news-copy-link-button" type="button" data-copy-news-url="<?= e($urlCompartir) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
            <span data-copy-news-label aria-live="polite">Copiar link de noticia</span>
          </button>
        </div>
<?php endif; ?>
