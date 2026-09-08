<?php
/** Gestión del contenido visible y SEO de la Home. */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('paginas.gestionar');

$titulo = 'Páginas · Home';
$active = 'paginas-home';
$seoPortadaEnPagina = true;
require __DIR__ . '/includes/header.php';
$seoAutomaticoPanel = valores_seo_portada_automaticos($nombreSitioPanel);
$seoPortadaPanel = configuracion_seo_portada();
$archivoSeoPagina = dirname(__DIR__) . '/' . $seoPortadaPanel['imagen_ruta'];
$versionSeoPagina = is_file($archivoSeoPagina) ? (string) filemtime($archivoSeoPagina) : '1';
$seoPortadaPanel['imagen_url_versionada'] = $seoPortadaPanel['imagen_url'] . '?v=' . rawurlencode($versionSeoPagina);
$seoPortadaContexto = 'pagina';
?>

<header class="pages-heading">
  <div>
    <span class="pages-eyebrow">Páginas del sitio</span>
    <h1>Home</h1>
    <p>Revisá el contenido publicado y configurá cómo aparece la página principal en buscadores y redes sociales.</p>
  </div>
  <a class="btn btn-outline pages-open-public" href="../index.php" target="_blank" rel="noopener">Abrir página pública</a>
</header>

<div class="page-editor-grid">
  <section class="page-preview-card" aria-labelledby="pagePreviewTitle">
    <div class="page-preview-toolbar">
      <div>
        <span>Vista previa del contenido</span>
        <strong id="pagePreviewTitle">Home publicada</strong>
      </div>
      <button class="page-preview-refresh" type="button" id="pagePreviewRefresh" aria-label="Actualizar vista previa" title="Actualizar vista previa">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5"></path><path d="M4 13a8.1 8.1 0 0 0 15.5 2M20 20v-5h-5"></path></svg>
      </button>
    </div>
    <div class="page-preview-browser">
      <div class="page-preview-browser-bar" aria-hidden="true"><span></span><span></span><span></span><small><?= e($seoPortadaPanel['url']) ?></small></div>
      <iframe id="pageContentPreview" title="Vista previa de Home" src="../index.php" data-preview-src="../index.php" loading="eager" sandbox="allow-same-origin"></iframe>
    </div>
    <p class="page-preview-note">La vista es navegable de forma segura, sin ejecutar scripts ni registrar interacciones publicitarias.</p>
  </section>

  <section class="page-seo-column" aria-label="Configuración SEO de Home">
    <?php require __DIR__ . '/includes/seo-portada-form.php'; ?>
  </section>
</div>

<script>
  (function () {
    const button = document.getElementById('pagePreviewRefresh');
    const frame = document.getElementById('pageContentPreview');
    if (!button || !frame) return;
    button.addEventListener('click', () => {
      button.classList.add('is-loading');
      const separator = frame.dataset.previewSrc.includes('?') ? '&' : '?';
      frame.src = frame.dataset.previewSrc + separator + 'preview_refresh=' + Date.now();
    });
    frame.addEventListener('load', () => button.classList.remove('is-loading'));
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
