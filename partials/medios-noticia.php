<?php
/** Reproductores opcionales compartidos por los feeds PC y móvil. */
$audiosNoticia = [];
for ($i = 1; $i <= 3; $i++) {
    $urlAudio = normalizar_url_audio((string) ($n['audio_' . $i] ?? ''));
    if ($urlAudio === '') continue;
    $tituloAudio = trim((string) ($n['audio_titulo_' . $i] ?? ''));
    $audiosNoticia[] = [
        'url' => $urlAudio,
        'titulo' => $tituloAudio !== '' ? $tituloAudio : 'Audio ' . $i,
    ];
}
$videosNoticia = array_values(array_filter(array_map(
    static fn($url) => youtube_embed_url((string) $url),
    [$n['youtube'] ?? '', $n['youtube_2'] ?? '', $n['youtube_3'] ?? '']
)));
?>
<?php if ($audiosNoticia || $videosNoticia): ?>
        <div class="story-media">
<?php if ($audiosNoticia): ?>
          <section class="story-media-group" aria-label="Audios de la noticia">
            <h3 class="story-media-heading">Audios</h3>
<?php foreach ($audiosNoticia as $audio): ?>
            <div class="story-audio">
              <span><?= e($audio['titulo']) ?></span>
              <audio controls preload="metadata" src="<?= e(url_recurso_portal($audio['url'])) ?>">Tu navegador no puede reproducir este audio.</audio>
            </div>
<?php endforeach; ?>
          </section>
<?php endif; ?>
<?php if ($videosNoticia): ?>
          <section class="story-media-group" aria-label="Videos de la noticia">
            <h3 class="story-media-heading">Videos</h3>
<?php foreach ($videosNoticia as $i => $video): ?>
            <div class="story-video">
              <iframe src="<?= e($video) ?>" title="Video de YouTube <?= $i + 1 ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
<?php endforeach; ?>
          </section>
<?php endif; ?>
        </div>
<?php endif; ?>
