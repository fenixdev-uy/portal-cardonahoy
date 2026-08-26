<?php
/**
 * Cabecera del layout del panel (sidebar + topbar).
 *
 * Variables esperadas:
 *  - string $titulo   Título de la página/sección.
 *  - string $active   Clave del menú activo (noticias, categorias, ...).
 */

require_once __DIR__ . '/funciones.php';
$usuarioPanel = exigir_login();
$flashes = obtener_flash();

$activo = $active ?? '';
$titulo = $titulo ?? 'Panel';
$nombreUsuarioPanel = trim((string) ($usuarioPanel['nombre'] ?? 'Usuario'));
$inicialUsuarioPanel = mb_strtoupper(mb_substr($nombreUsuarioPanel !== '' ? $nombreUsuarioPanel : 'U', 0, 1, 'UTF-8'), 'UTF-8');
// Las URLs del HTML se resuelven respecto a la ubicación de la página (landing/admin/),
// no respecto a la carpeta includes/. Por eso $BASE queda vacío.
$BASE = '';
$adminCssVersion = (string) (filemtime(__DIR__ . '/../assets/admin.css') ?: '1');
$puedeConfigurarPanel = tiene_permiso('configuracion.gestionar');
$puedeGestionarUsuarios = tiene_permiso('usuarios.gestionar');
$puedeGestionarRoles = tiene_permiso('roles.gestionar');
$grupoUsuariosVisible = $puedeGestionarUsuarios || $puedeGestionarRoles;
$grupoUsuariosActivo = in_array($activo, ['usuarios', 'roles'], true);
$grupoPublicidadActivo = in_array($activo, ['publicidad-anuncios', 'publicidad-popups'], true);
$marcaAguaPanel = null;
$fotoDemoMarcaAgua = '../imagenes/Publicidad-intendencia.jpg';
if ($puedeConfigurarPanel) {
    $marcaAguaPanel = configuracion_marca_agua();
    $rutaMarcaCompleta = dirname(__DIR__, 2) . '/' . $marcaAguaPanel['ruta'];
    $versionMarca = is_file($rutaMarcaCompleta) ? (string) filemtime($rutaMarcaCompleta) : '1';
    $marcaAguaPanel['url'] = url_imagen($marcaAguaPanel['ruta']) . '?v=' . rawurlencode($versionMarca);
    try {
        $rutaDemo = (string) db()->query('SELECT ruta FROM noticias_fotos ORDER BY created_at ASC, id ASC LIMIT 1')->fetchColumn();
        if ($rutaDemo !== '') $fotoDemoMarcaAgua = url_imagen($rutaDemo);
    } catch (PDOException $e) {
        // La imagen de respaldo mantiene disponible la vista previa.
    }
}
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($titulo) ?> · Panel Noticias</title>
  <link rel="stylesheet" href="<?= $BASE ?>assets/admin.css?v=<?= e($adminCssVersion) ?>" />
</head>
<body>
<div class="admin">

  <!-- Fondo oscuro para móvil -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- ===== Menú lateral ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-mark">N</div>
      <div>
        <h1>Noticias</h1>
        <span class="subtitle">Panel de administración</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-label">Contenido</span>

      <a href="<?= $BASE ?>index.php" class="nav-link <?= $activo === 'noticias' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"></path><path d="M18 14h-8"></path><path d="M15 18h-5"></path><path d="M10 6h8v4h-8V6z"></path></svg>
        Noticias
      </a>

      <?php if (tiene_permiso('categorias.gestionar')): ?><a href="<?= $BASE ?>categorias.php" class="nav-link <?= $activo === 'categorias' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
        Categorías
      </a><?php endif; ?>

      <a href="<?= $BASE ?>votaciones.php" class="nav-link <?= $activo === 'votaciones' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"></path><rect x="7" y="12" width="3" height="6"></rect><rect x="12.5" y="8" width="3" height="10"></rect><rect x="18" y="14" width="3" height="4"></rect></svg>
        Votaciones
      </a>

      <span class="nav-label">Administración</span>

<?php if ($grupoUsuariosVisible): ?>
      <div class="nav-group<?= $grupoUsuariosActivo ? ' has-active' : '' ?>">
        <button type="button" class="nav-link nav-group-toggle" data-nav-group-toggle aria-expanded="<?= $grupoUsuariosActivo ? 'true' : 'false' ?>" aria-controls="usersNavSubmenu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>Usuarios</span>
          <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <div class="nav-submenu" id="usersNavSubmenu"<?= $grupoUsuariosActivo ? '' : ' hidden' ?>>
<?php if ($puedeGestionarUsuarios): ?>
          <a href="<?= $BASE ?>usuarios.php" class="nav-sub-link <?= $activo === 'usuarios' ? 'active' : '' ?>">Usuarios</a>
<?php endif; ?>
<?php if ($puedeGestionarRoles): ?>
          <a href="<?= $BASE ?>roles.php" class="nav-sub-link <?= $activo === 'roles' ? 'active' : '' ?>">Roles</a>
<?php endif; ?>
        </div>
      </div>
<?php endif; ?>

      <div class="nav-group<?= $grupoPublicidadActivo ? ' has-active' : '' ?>">
        <button type="button" class="nav-link nav-group-toggle" data-nav-group-toggle aria-expanded="<?= $grupoPublicidadActivo ? 'true' : 'false' ?>" aria-controls="advertisingNavSubmenu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11v2a2 2 0 0 0 2 2h2l4 5V4L7 9H5a2 2 0 0 0-2 2Z"></path><path d="M15.5 8.5a5 5 0 0 1 0 7M18 6a8.5 8.5 0 0 1 0 12"></path></svg>
          <span>Publicidad</span>
          <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <div class="nav-submenu" id="advertisingNavSubmenu"<?= $grupoPublicidadActivo ? '' : ' hidden' ?>>
          <a href="<?= $BASE ?>anuncios.php" class="nav-sub-link <?= $activo === 'publicidad-anuncios' ? 'active' : '' ?>">Anuncios</a>
          <a href="<?= $BASE ?>popups.php" class="nav-sub-link <?= $activo === 'publicidad-popups' ? 'active' : '' ?>">Popups</a>
        </div>
      </div>

      <a href="<?= $BASE ?>../index.php" class="nav-link" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><path d="M15 3h6v6"></path><path d="M10 14L21 3"></path></svg>
        Ver sitio
      </a>
    </nav>

<?php if ($puedeConfigurarPanel): ?>
    <div class="sidebar-settings">
      <button type="button" class="nav-link sidebar-settings-button" id="settingsMenuButton">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.14.36.36.69.66.94.3.25.68.39 1.07.4H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51.66Z"></path></svg>
        <span>Configuración</span>
      </button>
    </div>
<?php endif; ?>

    <div class="sidebar-footer">
      <span class="sidebar-user-avatar" aria-hidden="true"><?= e($inicialUsuarioPanel) ?></span>
      <span class="sidebar-user-data">
        <strong><?= e($nombreUsuarioPanel) ?></strong>
        <small><?= e($usuarioPanel['rol_nombre']) ?></small>
      </span>
      <form method="post" action="<?= $BASE ?>logout.php" class="sidebar-logout-form">
        <?= csrf_input() ?>
        <button type="submit" class="sidebar-logout-btn" aria-label="Cerrar sesión" title="Cerrar sesión">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg>
        </button>
      </form>
    </div>
  </aside>

<?php if ($puedeConfigurarPanel && $marcaAguaPanel !== null): ?>
  <div class="drawer-backdrop settings-drawer-backdrop" id="settingsDrawerBackdrop"></div>
  <aside class="drawer settings-drawer" id="settingsDrawer" aria-hidden="true" aria-labelledby="settingsDrawerTitle">
    <header class="drawer-header settings-drawer-header">
      <div>
        <span class="drawer-title-label">Ajustes del portal</span>
        <h2 id="settingsDrawerTitle">Configuración</h2>
        <p>Personalizá los recursos generales del sitio.</p>
      </div>
      <button type="button" class="drawer-close" id="settingsDrawerClose" aria-label="Cerrar configuración">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </header>
    <div class="drawer-body settings-drawer-body">
      <form class="settings-card" id="watermarkSettingsForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.2-3.2a2 2 0 0 0-2.8 0L6 21"></path><path d="M13 5h6M16 2v6"></path></svg>
          </span>
          <div>
            <h3>Marca de Agua</h3>
            <p>Se aplicará centrada sobre todas las imágenes nuevas.</p>
          </div>
        </div>

        <div class="watermark-preview" aria-label="Vista previa de la marca de agua">
          <img class="watermark-preview-photo" src="<?= e($fotoDemoMarcaAgua) ?>" alt="Fotografía de demostración">
          <img class="watermark-preview-logo" id="watermarkPreviewLogo" src="<?= e($marcaAguaPanel['url']) ?>" alt="Marca de agua actual" style="opacity: <?= e(number_format($marcaAguaPanel['opacidad'] / 100, 2, '.', '')) ?>">
          <span class="watermark-preview-label">Vista previa</span>
        </div>

        <div class="watermark-upload-row">
          <div>
            <strong>Imagen de marca</strong>
            <span id="watermarkFileName">PNG transparente, máximo 2 MB.</span>
          </div>
          <label class="btn btn-outline watermark-upload-button" for="watermarkFile">Cambiar imagen</label>
          <input type="file" id="watermarkFile" name="marca_agua" accept="image/png" hidden>
        </div>

        <div class="watermark-range-group">
          <div class="watermark-range-heading">
            <label for="watermarkOpacity">Transparencia / intensidad</label>
            <output id="watermarkOpacityValue" for="watermarkOpacity"><?= (int) $marcaAguaPanel['opacidad'] ?>%</output>
          </div>
          <input type="range" id="watermarkOpacity" name="opacidad" min="5" max="100" step="1" value="<?= (int) $marcaAguaPanel['opacidad'] ?>">
          <div class="watermark-range-scale"><span>Más suave</span><span>Más visible</span></div>
          <p>Un porcentaje bajo deja la marca más transparente. La vista previa cambia mientras movés el control.</p>
        </div>

        <div class="settings-card-notice">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
          <span>Los cambios se aplican a próximas subidas. Las fotos publicadas no se modifican.</span>
        </div>

        <span class="settings-save-status" id="watermarkSaveStatus" aria-live="polite"></span>
        <div class="settings-form-actions">
          <button type="button" class="btn btn-outline" id="settingsDrawerCancel">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="watermarkSaveButton">Guardar configuración</button>
        </div>
      </form>
    </div>
  </aside>
<?php endif; ?>

  <!-- ===== Contenido principal ===== -->
  <div class="main">
    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      <h2><?= e($titulo) ?></h2>
      <span class="topbar-spacer" aria-hidden="true"></span>
    </header>

    <main class="content">
      <?php if ($flashes): ?>
        <div class="flash">
          <?php foreach ($flashes as $f): ?>
            <div class="alert <?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
