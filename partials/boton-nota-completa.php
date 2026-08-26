<?php
/** Disparador compartido de la vista ampliada de una noticia. */
$noticiaIdBoton = (int) ($n['id'] ?? 0);
?>
        <button class="story-sheet-trigger" type="button"
                data-story-id="<?= $noticiaIdBoton ?>"
                aria-controls="storySheet" aria-haspopup="dialog">
          Ver nota completa
        </button>
