<?php
/**
 * Visor ampliado de galerías, compartido por el feed PC y el móvil.
 *
 * En PC se navega con las flechas o el teclado y se amplía con la rueda del
 * mouse. En móvil se navega deslizando y se amplía con pinza (dos dedos).
 */
?>
  <div class="gallery-lightbox" id="pcGalleryLightbox" role="dialog" aria-modal="true" aria-label="Galería de imágenes ampliada" aria-hidden="true">
    <button class="pc-lightbox-close" id="pcLightboxClose" type="button" aria-label="Cerrar galería">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
    <button class="pc-lightbox-arrow pc-lightbox-prev" id="pcLightboxPrev" type="button" aria-label="Imagen anterior">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </button>
    <div class="pc-lightbox-stage" id="pcLightboxStage">
      <img id="pcLightboxImage" src="" alt="" draggable="false">
    </div>
    <button class="pc-lightbox-arrow pc-lightbox-next" id="pcLightboxNext" type="button" aria-label="Imagen siguiente">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>
    <div class="pc-lightbox-counter" id="pcLightboxCounter" aria-live="polite"></div>
    <div class="pc-lightbox-zoom" id="pcLightboxZoom">Rueda del mouse para ampliar · 100%</div>
  </div>
