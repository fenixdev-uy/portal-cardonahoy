<?php
/** Card reutilizable del SEO de la Home para Configuración y Páginas. */
if ($seoPortadaPanel === null || $seoAutomaticoPanel === null) return;
$seoPortadaContexto = $seoPortadaContexto ?? 'configuracion';
$seoPortadaEditable = $seoPortadaContexto === 'pagina';
$seoPaginaNombre = 'la Página Principal';
?>
<form class="settings-card settings-seo-card<?= $seoPortadaContexto === 'pagina' ? ' page-seo-card' : '' ?>" id="homeSeoSettingsForm" enctype="multipart/form-data"
      data-default-title="<?= e($seoAutomaticoPanel['titulo']) ?>"
      data-default-description="<?= e($seoAutomaticoPanel['descripcion']) ?>"
      data-default-image="<?= e(url_portal('imagenes/Logo2027v3.png')) ?>"
      data-public-url="<?= e($seoPortadaPanel['url']) ?>"
      data-page-name="<?= e($seoPaginaNombre) ?>"
      data-context="<?= e($seoPortadaContexto) ?>">
  <?= csrf_input() ?>
  <input type="hidden" name="pagina" value="home">
  <input type="hidden" id="homeSeoTitleMode" name="titulo_personalizado" value="<?= ($seoPortadaEditable || $seoPortadaPanel['titulo_personalizado']) ? '1' : '0' ?>">
  <input type="hidden" id="homeSeoDescriptionMode" name="descripcion_personalizada" value="<?= ($seoPortadaEditable || $seoPortadaPanel['descripcion_personalizada']) ? '1' : '0' ?>">
  <input type="hidden" id="homeSeoImageAutomatic" name="imagen_automatica" value="0">

  <div class="settings-card-heading">
    <span class="settings-card-icon settings-card-icon-seo" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path><path d="M8 11h6M11 8v6"></path></svg>
    </span>
    <div class="settings-card-heading-copy">
      <h3>SEO de <?= e($seoPaginaNombre) ?></h3>
      <p>Configurá cómo aparece esta página en Google y al compartir.</p>
    </div>
    <span class="seo-mode-badge<?= ($seoPortadaEditable || $seoPortadaPanel['titulo_personalizado'] || $seoPortadaPanel['descripcion_personalizada'] || $seoPortadaPanel['imagen_personalizada']) ? ' is-custom' : '' ?>" id="homeSeoModeBadge"><?= $seoPortadaEditable ? 'Editable' : (($seoPortadaPanel['titulo_personalizado'] || $seoPortadaPanel['descripcion_personalizada'] || $seoPortadaPanel['imagen_personalizada']) ? 'Personalizado' : 'Automático') ?></span>
    <button type="button" class="settings-card-toggle" id="homeSeoCardToggle" aria-expanded="true" aria-controls="homeSeoCardContent" aria-label="Contraer ajustes SEO de <?= e($seoPaginaNombre) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
    </button>
  </div>

  <div class="settings-card-content settings-seo-content" id="homeSeoCardContent">
    <div class="seo-field-group" data-home-seo-field="title">
      <div class="seo-field-heading"><label for="homeSeoTitle">Título SEO</label><?php if ($seoPortadaEditable): ?><span>Editable</span><?php else: ?><button type="button" class="seo-auto-action" data-home-seo-auto="title"><?= $seoPortadaPanel['titulo_personalizado'] ? 'Volver a automático' : 'Personalizar' ?></button><?php endif; ?></div>
      <input class="form-control" type="text" id="homeSeoTitle" name="titulo" value="<?= e($seoPortadaPanel['titulo']) ?>" maxlength="255"<?= ($seoPortadaEditable || $seoPortadaPanel['titulo_personalizado']) ? '' : ' readonly' ?> required>
      <span class="seo-counter" id="homeSeoTitleCounter"><?= mb_strlen($seoPortadaPanel['titulo']) ?> caracteres</span>
    </div>

    <div class="seo-field-group" data-home-seo-field="description">
      <div class="seo-field-heading"><label for="homeSeoDescription">Descripción SEO</label><?php if ($seoPortadaEditable): ?><span>Editable</span><?php else: ?><button type="button" class="seo-auto-action" data-home-seo-auto="description"><?= $seoPortadaPanel['descripcion_personalizada'] ? 'Volver a automático' : 'Personalizar' ?></button><?php endif; ?></div>
      <textarea class="form-control seo-description-input" id="homeSeoDescription" name="descripcion" maxlength="500"<?= ($seoPortadaEditable || $seoPortadaPanel['descripcion_personalizada']) ? '' : ' readonly' ?> required><?= e($seoPortadaPanel['descripcion']) ?></textarea>
      <span class="seo-counter" id="homeSeoDescriptionCounter"><?= mb_strlen($seoPortadaPanel['descripcion']) ?> caracteres</span>
    </div>

    <div class="seo-field-group">
      <div class="seo-field-heading"><label for="homeSeoImage">Imagen SEO/social</label><span>Recomendado 1200 × 630 px</span></div>
      <div class="home-seo-image-row">
        <div class="home-seo-image-preview"><img id="homeSeoImagePreview" src="<?= e($seoPortadaPanel['imagen_url_versionada']) ?>" alt="Imagen SEO actual de la portada"></div>
        <div class="home-seo-image-actions">
          <label class="btn btn-outline" for="homeSeoImage">Cambiar imagen</label>
          <button type="button" class="seo-auto-action" id="homeSeoImageAuto"<?= $seoPortadaPanel['imagen_personalizada'] ? '' : ' hidden' ?>>Volver a automática</button>
        </div>
        <input type="file" id="homeSeoImage" name="imagen" accept="image/jpeg,image/png,image/webp" hidden>
      </div>
      <span class="media-upload-status" id="homeSeoImageStatus" aria-live="polite"><?= $seoPortadaPanel['imagen_personalizada'] ? 'Imagen personalizada' : 'Imagen automática del portal' ?></span>
    </div>

    <div class="home-seo-preview-block">
      <div class="seo-preview-tabs" role="tablist" aria-label="Tipo de vista previa SEO de portada">
        <button type="button" role="tab" aria-selected="true" data-home-seo-tab="social">Al compartir</button>
        <button type="button" role="tab" aria-selected="false" data-home-seo-tab="google">En Google</button>
      </div>
      <div class="seo-social-preview" data-home-seo-panel="social">
        <div class="seo-social-image"><img id="homeSeoSocialImage" src="<?= e($seoPortadaPanel['imagen_url_versionada']) ?>" alt=""></div>
        <div class="seo-social-copy"><span><?= e((string) parse_url($seoPortadaPanel['url'], PHP_URL_HOST)) ?></span><strong id="homeSeoSocialTitle"><?= e($seoPortadaPanel['titulo']) ?></strong><p id="homeSeoSocialDescription"><?= e($seoPortadaPanel['descripcion']) ?></p><small><?= e($seoPortadaPanel['url']) ?></small></div>
      </div>
      <div class="seo-google-preview" data-home-seo-panel="google" hidden>
        <span><?= e($seoPortadaPanel['url']) ?></span>
        <strong id="homeSeoGoogleTitle"><?= e($seoPortadaPanel['titulo']) ?></strong>
        <p id="homeSeoGoogleDescription"><?= e($seoPortadaPanel['descripcion']) ?></p>
      </div>
      <p class="seo-preview-note">La vista es orientativa: cada plataforma puede recortar imágenes o textos de forma diferente.</p>
    </div>

    <span class="settings-save-status" id="homeSeoSaveStatus" aria-live="polite"></span>
    <div class="settings-form-actions">
      <button type="button" class="btn btn-outline" id="homeSeoCancel">Cancelar</button>
      <button type="submit" class="btn btn-primary" id="homeSeoSaveButton">Guardar SEO</button>
    </div>
  </div>
</form>
