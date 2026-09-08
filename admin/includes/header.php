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
$fotoUsuarioPanel = trim((string) ($usuarioPanel['foto'] ?? ''));
$fotoUsuarioPanelValida = $fotoUsuarioPanel !== '' && imagen_usuario_disponible($fotoUsuarioPanel);
// Las URLs del HTML se resuelven respecto a la ubicación de la página (landing/admin/),
// no respecto a la carpeta includes/. Por eso $BASE queda vacío.
$BASE = '';
$adminCssVersion = (string) (filemtime(__DIR__ . '/../assets/admin.css') ?: '1');
$puedeConfigurarPanel = tiene_permiso('configuracion.gestionar');
$puedeGestionarPaginas = tiene_permiso('paginas.gestionar');
$puedeGestionarMantenimiento = tiene_permiso('mantenimiento.gestionar');
$puedeGestionarUsuarios = tiene_permiso('usuarios.gestionar');
$puedeGestionarRoles = tiene_permiso('roles.gestionar');
$puedeGestionarPublicidad = tiene_permiso('publicidad.gestionar');
$grupoUsuariosVisible = $puedeGestionarUsuarios || $puedeGestionarRoles;
$grupoNoticiasActivo = in_array($activo, ['noticias', 'categorias'], true);
$grupoPaginasActivo = $activo === 'paginas-home';
$grupoUsuariosActivo = in_array($activo, ['usuarios', 'roles'], true);
$grupoPublicidadActivo = in_array($activo, ['publicidad-anuncios', 'publicidad-popups'], true);
$grupoAnalisisActivo = in_array($activo, ['publicaciones', 'votaciones', 'vistas'], true);
$marcaAguaPanel = null;
$logoLoginPanel = null;
$logoPortalPanel = null;
$logoAdminPanel = null;
$nombreSitioPanel = null;
$seoPortadaPanel = null;
$seoAutomaticoPanel = null;
$codigoHeaderPanel = null;
$mantenimientoPanel = null;
$logoPortalRuta = configuracion_logo_portal();
$archivoLogoPortal = dirname(__DIR__, 2) . '/' . $logoPortalRuta;
$versionLogoPortal = is_file($archivoLogoPortal) ? (string) filemtime($archivoLogoPortal) : '1';
$identidadLogoAdmin = configuracion_logo_admin();
$archivoLogoAdmin = $identidadLogoAdmin['ruta'] !== null ? dirname(__DIR__, 2) . '/' . $identidadLogoAdmin['ruta'] : null;
$versionLogoAdmin = $archivoLogoAdmin !== null && is_file($archivoLogoAdmin) ? (string) filemtime($archivoLogoAdmin) : '1';
$archivoFaviconPortal = $identidadLogoAdmin['favicon_ruta'] !== null
    ? dirname(__DIR__, 2) . '/' . $identidadLogoAdmin['favicon_ruta']
    : dirname(__DIR__, 2) . '/imagenes/Logo2027v2.png';
$versionFaviconPortal = is_file($archivoFaviconPortal) ? (string) filemtime($archivoFaviconPortal) : $versionLogoPortal;
$fotoDemoMarcaAgua = '../imagenes/Publicidad-intendencia.jpg';
$configuracionMantenimientoActual = configuracion_mantenimiento();
if ($puedeGestionarMantenimiento) {
    $archivoLogoMantenimiento = dirname(__DIR__, 2) . '/' . $configuracionMantenimientoActual['logo_ruta'];
    $versionLogoMantenimiento = is_file($archivoLogoMantenimiento) ? (string) filemtime($archivoLogoMantenimiento) : '1';
    $mantenimientoPanel = $configuracionMantenimientoActual;
    $mantenimientoPanel['logo_url'] = url_imagen($configuracionMantenimientoActual['logo_ruta']) . '?v=' . rawurlencode($versionLogoMantenimiento);
}
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
    $rutaLogoLogin = configuracion_logo_login();
    $archivoLogoLogin = dirname(__DIR__, 2) . '/' . $rutaLogoLogin;
    $versionLogoLogin = is_file($archivoLogoLogin) ? (string) filemtime($archivoLogoLogin) : '1';
    $logoLoginPanel = [
        'ruta' => $rutaLogoLogin,
        'url' => url_imagen($rutaLogoLogin) . '?v=' . rawurlencode($versionLogoLogin),
        'tamano' => configuracion_logo_login_tamano(),
    ];
    $logoPortalPanel = [
        'url' => url_imagen($logoPortalRuta) . '?v=' . rawurlencode($versionLogoPortal),
        'tamano' => configuracion_logo_portal_tamano(),
    ];
    $logoAdminPanel = [
        'url' => $identidadLogoAdmin['ruta'] !== null ? url_imagen($identidadLogoAdmin['ruta']) . '?v=' . rawurlencode($versionLogoAdmin) : null,
        'favicon_url' => '../favicon.php?v=' . rawurlencode($versionFaviconPortal),
    ];
    $nombreSitioPanel = configuracion_nombre_sitio();
    $seoAutomaticoPanel = valores_seo_portada_automaticos($nombreSitioPanel);
    $seoPortadaPanel = configuracion_seo_portada();
    $archivoSeoPortada = dirname(__DIR__, 2) . '/' . $seoPortadaPanel['imagen_ruta'];
    $versionSeoPortada = is_file($archivoSeoPortada) ? (string) filemtime($archivoSeoPortada) : '1';
    $seoPortadaPanel['imagen_url_versionada'] = $seoPortadaPanel['imagen_url'] . '?v=' . rawurlencode($versionSeoPortada);
    $codigoHeaderPanel = configuracion_codigo_header();
}
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($titulo) ?> · Panel Noticias</title>
  <link rel="icon" href="../favicon.php?v=<?= e(rawurlencode($versionFaviconPortal)) ?>" type="image/x-icon" />
  <link rel="stylesheet" href="<?= $BASE ?>assets/admin.css?v=<?= e($adminCssVersion) ?>" />
</head>
<body>
<div class="admin">

  <!-- Fondo oscuro para móvil -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- ===== Menú lateral ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-mark" id="adminSidebarBrandMark">
<?php if ($identidadLogoAdmin['ruta'] !== null): ?>
        <img src="<?= e(url_imagen($identidadLogoAdmin['ruta'])) ?>?v=<?= e(rawurlencode($versionLogoAdmin)) ?>" alt="Logo del Admin">
<?php else: ?>
        <span>N</span>
<?php endif; ?>
      </div>
      <div>
        <h1>Noticias</h1>
        <span class="subtitle">Panel de administración</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-label">Contenido</span>

      <div class="nav-group<?= $grupoNoticiasActivo ? ' has-active' : '' ?>">
        <button type="button" class="nav-link nav-group-toggle" data-nav-group-toggle aria-expanded="<?= $grupoNoticiasActivo ? 'true' : 'false' ?>" aria-controls="newsNavSubmenu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"></path><path d="M18 14h-8"></path><path d="M15 18h-5"></path><path d="M10 6h8v4h-8V6z"></path></svg>
          <span>Noticias</span>
          <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <div class="nav-submenu" id="newsNavSubmenu"<?= $grupoNoticiasActivo ? '' : ' hidden' ?>>
          <a href="<?= $BASE ?>index.php" class="nav-sub-link <?= $activo === 'noticias' ? 'active' : '' ?>">Noticias</a>
<?php if (tiene_permiso('categorias.gestionar')): ?>
          <a href="<?= $BASE ?>categorias.php" class="nav-sub-link <?= $activo === 'categorias' ? 'active' : '' ?>">Categorías</a>
<?php endif; ?>
        </div>
      </div>

<?php if ($puedeGestionarPaginas): ?>
      <div class="nav-group<?= $grupoPaginasActivo ? ' has-active' : '' ?>">
        <button type="button" class="nav-link nav-group-toggle" data-nav-group-toggle aria-expanded="<?= $grupoPaginasActivo ? 'true' : 'false' ?>" aria-controls="pagesNavSubmenu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"></path><path d="M14 2v6h6M8 13h8M8 17h6"></path></svg>
          <span>Páginas</span>
          <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <div class="nav-submenu" id="pagesNavSubmenu"<?= $grupoPaginasActivo ? '' : ' hidden' ?>>
          <a href="<?= $BASE ?>paginas.php" class="nav-sub-link <?= $activo === 'paginas-home' ? 'active' : '' ?>">Home</a>
        </div>
      </div>
<?php endif; ?>

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

<?php if ($puedeGestionarPublicidad): ?>
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
<?php endif; ?>

      <div class="nav-group<?= $grupoAnalisisActivo ? ' has-active' : '' ?>">
        <button type="button" class="nav-link nav-group-toggle" data-nav-group-toggle aria-expanded="<?= $grupoAnalisisActivo ? 'true' : 'false' ?>" aria-controls="analyticsNavSubmenu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"></path><path d="m7 16 4-5 4 3 5-7"></path></svg>
          <span>Análisis</span>
          <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <div class="nav-submenu" id="analyticsNavSubmenu"<?= $grupoAnalisisActivo ? '' : ' hidden' ?>>
          <a href="<?= $BASE ?>publicaciones.php" class="nav-sub-link has-icon <?= $activo === 'publicaciones' ? 'active' : '' ?>">
            <svg class="nav-sub-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M8 2v4M16 2v4M3 10h18"></path><path d="M8 14h2M14 14h2M8 18h2"></path></svg>
            <span>Publicaciones</span>
          </a>
          <a href="<?= $BASE ?>votaciones.php" class="nav-sub-link has-icon <?= $activo === 'votaciones' ? 'active' : '' ?>">
            <svg class="nav-sub-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12H3V10h4Z"></path><path d="M7 20h10.8a2 2 0 0 0 1.96-1.61l1.2-6A2 2 0 0 0 19 10h-5l1-4.57A2 2 0 0 0 13.05 3H12l-5 7v10Z"></path></svg>
            <span>Votaciones</span>
          </a>
          <a href="<?= $BASE ?>vistas.php" class="nav-sub-link has-icon <?= $activo === 'vistas' ? 'active' : '' ?>">
            <svg class="nav-sub-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <span>Vistas</span>
          </a>
        </div>
      </div>

      <a href="<?= $BASE ?>../index.php" class="nav-link" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><path d="M15 3h6v6"></path><path d="M10 14L21 3"></path></svg>
        Ver sitio
      </a>
    </nav>

<?php if ($puedeGestionarMantenimiento || $puedeConfigurarPanel): ?>
    <div class="sidebar-settings">
<?php if ($puedeGestionarMantenimiento && $mantenimientoPanel !== null): ?>
      <form class="sidebar-maintenance-form" id="maintenanceQuickForm">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="estado">
        <span class="sidebar-maintenance-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z"></path></svg>
        </span>
        <label for="maintenanceQuickToggle">Mantenimiento</label>
        <span class="sidebar-maintenance-state" id="maintenanceQuickState" aria-live="polite"><?= $mantenimientoPanel['activo'] ? 'Activo' : 'Inactivo' ?></span>
        <label class="sidebar-maintenance-switch" for="maintenanceQuickToggle">
          <input type="checkbox" id="maintenanceQuickToggle" name="activo" value="1" role="switch"<?= $mantenimientoPanel['activo'] ? ' checked' : '' ?> aria-label="Activar modo mantenimiento">
          <span aria-hidden="true"><span></span></span>
        </label>
      </form>
<?php endif; ?>
<?php if ($puedeConfigurarPanel): ?>
      <button type="button" class="nav-link sidebar-settings-button" id="settingsMenuButton">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.14.36.36.69.66.94.3.25.68.39 1.07.4H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51.66Z"></path></svg>
        <span>Configuración</span>
      </button>
<?php endif; ?>
    </div>
<?php endif; ?>

    <div class="sidebar-footer">
      <button type="button" class="sidebar-profile-button" id="profileMenuButton" aria-expanded="false" aria-controls="profileDrawer" title="Editar perfil">
        <span class="sidebar-user-avatar" id="sidebarUserAvatar" aria-hidden="true"><?php if ($fotoUsuarioPanelValida): ?><img src="<?= e(url_imagen($fotoUsuarioPanel)) ?>" alt=""><?php else: ?><?= e($inicialUsuarioPanel) ?><?php endif; ?></span>
        <span class="sidebar-user-data">
          <strong id="sidebarUserName"><?= e($nombreUsuarioPanel) ?></strong>
          <small><?= e($usuarioPanel['rol_nombre']) ?></small>
        </span>
      </button>
      <form method="post" action="<?= $BASE ?>logout.php" class="sidebar-logout-form">
        <?= csrf_input() ?>
        <button type="submit" class="sidebar-logout-btn" aria-label="Cerrar sesión" title="Cerrar sesión">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg>
        </button>
      </form>

    </div>
  </aside>

  <div class="drawer-backdrop profile-drawer-backdrop" id="profileDrawerBackdrop"></div>
  <aside class="drawer user-drawer profile-drawer" id="profileDrawer" aria-hidden="true" aria-labelledby="profileDrawerTitle">
    <header class="drawer-header user-drawer-header">
      <div>
        <span class="drawer-title-label">Cuenta personal</span>
        <h2 id="profileDrawerTitle">Editar perfil</h2>
      </div>
      <button type="button" class="drawer-close" id="profileDrawerClose" aria-label="Cerrar perfil">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </header>
    <div class="drawer-body user-drawer-body">
      <form id="profileForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="user-photo-field">
          <span class="user-photo-preview<?= $fotoUsuarioPanelValida ? ' has-image' : '' ?>" id="profilePhotoPreview" aria-hidden="true">
            <?php if ($fotoUsuarioPanelValida): ?><img src="<?= e(url_imagen($fotoUsuarioPanel)) ?>" alt=""><?php else: ?><span class="user-photo-initial"><?= e($inicialUsuarioPanel) ?></span><?php endif; ?>
          </span>
          <span class="user-photo-controls">
            <label class="btn btn-outline user-photo-button" for="profilePhoto">Seleccionar foto</label>
            <input type="file" id="profilePhoto" name="foto" accept="image/jpeg,image/png,image/webp">
            <small id="profilePhotoHelp" aria-live="polite">JPG, PNG o WEBP. Máximo 3 MB; se recomienda una imagen cuadrada.</small>
          </span>
        </div>

        <div class="form-group"><label for="profileName">Nombre</label><input class="form-control" id="profileName" name="nombre" maxlength="120" required value="<?= e($nombreUsuarioPanel) ?>" autocomplete="name"></div>
        <div class="form-group"><label for="profileEmail">Correo electrónico</label><input class="form-control" type="email" id="profileEmail" name="email" maxlength="190" required value="<?= e($usuarioPanel['email']) ?>" autocomplete="email"></div>

        <section class="profile-password-section" aria-labelledby="profilePasswordTitle">
          <h3 id="profilePasswordTitle">Cambiar contraseña</h3>
          <p>Dejá estos campos vacíos si querés conservar la contraseña actual.</p>
          <div class="form-group"><label for="profileCurrentPassword">Contraseña actual</label><input class="form-control" type="password" id="profileCurrentPassword" name="password_actual" autocomplete="current-password"></div>
          <div class="form-group"><label for="profileNewPassword">Nueva contraseña</label><input class="form-control" type="password" id="profileNewPassword" name="password_nueva" minlength="12" autocomplete="new-password"><div class="form-hint">Mínimo 12 caracteres.</div></div>
          <div class="form-group"><label for="profileRepeatPassword">Repetir nueva contraseña</label><input class="form-control" type="password" id="profileRepeatPassword" name="password_repetida" minlength="12" autocomplete="new-password"></div>
        </section>

        <div class="profile-save-status" id="profileSaveStatus" role="status" aria-live="polite"></div>
        <div class="form-actions user-form-actions">
          <button class="btn btn-primary" type="submit" id="profileSaveButton">Guardar cambios</button>
          <button class="btn btn-outline" type="button" id="profileDrawerCancel">Cancelar</button>
        </div>
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
<?php if ($puedeGestionarMantenimiento && $mantenimientoPanel !== null): ?>
      <form class="settings-card maintenance-settings-card" id="maintenanceSettingsForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon settings-card-icon-maintenance" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94L14.7 6.3z"></path></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Modo Mantenimiento</h3>
            <p>Bloqueá el Portal y conservá el acceso editorial autorizado.</p>
          </div>
          <span class="maintenance-card-state<?= $mantenimientoPanel['activo'] ? ' is-active' : '' ?>" id="maintenanceCardState"><?= $mantenimientoPanel['activo'] ? 'Activo' : 'Inactivo' ?></span>
          <button type="button" class="settings-card-toggle" id="maintenanceCardToggle" aria-expanded="true" aria-controls="maintenanceCardContent" aria-label="Contraer ajustes de mantenimiento">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content maintenance-card-content" id="maintenanceCardContent">
          <div class="maintenance-admin-preview" id="maintenanceAdminPreview" style="--maintenance-preview-logo-width: <?= (int) $mantenimientoPanel['logo_tamano'] ?>%;">
            <span class="login-logo-preview-label">Vista pública</span>
            <span class="maintenance-preview-user<?= $mantenimientoPanel['mostrar_login'] ? '' : ' is-hidden' ?>" id="maintenancePreviewUser" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path></svg>
            </span>
            <div class="maintenance-preview-identity">
              <img id="maintenanceLogoPreview" src="<?= e($mantenimientoPanel['logo_url']) ?>" alt="Logo de mantenimiento actual">
              <p id="maintenanceMessagePreview"><?= e($mantenimientoPanel['mensaje']) ?></p>
            </div>
          </div>

          <div class="maintenance-control-row">
            <div><strong>Estado del Portal</strong><span>Al activarlo, los visitantes reciben una pantalla 503 sin contenido público.</span></div>
            <label class="header-code-switch ad-form-switch" for="maintenanceActive">
              <input type="checkbox" id="maintenanceActive" name="activo" value="1" role="switch"<?= $mantenimientoPanel['activo'] ? ' checked' : '' ?>>
              <span class="ad-form-switch-track" aria-hidden="true"><span></span></span>
              <span id="maintenanceActiveLabel"><?= $mantenimientoPanel['activo'] ? 'Activado' : 'Desactivado' ?></span>
            </label>
          </div>

          <div class="watermark-upload-row maintenance-logo-upload-row">
            <div><strong>Imagen central</strong><span id="maintenanceLogoFileName">JPG, PNG o WEBP. Máximo 3 MB.</span></div>
            <label class="btn btn-outline watermark-upload-button" for="maintenanceLogoFile">Cambiar imagen</label>
            <input type="file" id="maintenanceLogoFile" name="logo_mantenimiento" accept="image/jpeg,image/png,image/webp" hidden>
          </div>

          <div class="watermark-range-group maintenance-size-group">
            <div class="watermark-range-heading">
              <label for="maintenanceLogoSize">Tamaño de la imagen</label>
              <output id="maintenanceLogoSizeValue" for="maintenanceLogoSize"><?= (int) $mantenimientoPanel['logo_tamano'] ?>%</output>
            </div>
            <input type="range" id="maintenanceLogoSize" name="logo_tamano" min="25" max="80" step="1" value="<?= (int) $mantenimientoPanel['logo_tamano'] ?>">
            <div class="watermark-range-scale"><span>Más chica</span><span>Más grande</span></div>
          </div>

          <div class="form-group maintenance-message-field">
            <div class="maintenance-field-heading"><label for="maintenanceMessage">Mensaje bajo la imagen</label><span id="maintenanceMessageCounter"><?= mb_strlen($mantenimientoPanel['mensaje']) ?>/160</span></div>
            <input class="form-control" type="text" id="maintenanceMessage" name="mensaje" maxlength="160" value="<?= e($mantenimientoPanel['mensaje']) ?>" required>
          </div>

          <label class="maintenance-login-option ad-form-switch" for="maintenanceShowLogin">
            <input type="checkbox" id="maintenanceShowLogin" name="mostrar_login" value="1" role="switch"<?= $mantenimientoPanel['mostrar_login'] ? ' checked' : '' ?>>
            <span class="ad-form-switch-track" aria-hidden="true"><span></span></span>
            <span><strong>Mostrar acceso al Admin</strong><small>Controla el icono público. La URL directa permanece disponible solo para roles autorizados.</small></span>
          </label>

          <div class="settings-card-notice maintenance-card-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
            <span>Los usuarios con el permiso “Gestionar mantenimiento” conservan acceso al panel y pueden revisar el Portal sin desactivar el bloqueo.</span>
          </div>

          <span class="settings-save-status" id="maintenanceSaveStatus" aria-live="polite"></span>
          <div class="settings-form-actions">
            <button type="button" class="btn btn-outline" id="maintenanceCancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="maintenanceSaveButton">Guardar mantenimiento</button>
          </div>
        </div>
      </form>
<?php endif; ?>

      <?php if ($nombreSitioPanel !== null): ?>
      <form class="settings-card site-identity-card" id="siteIdentitySettingsForm">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon settings-card-icon-site" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M9 18h6"></path></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Identidad del sitio</h3>
            <p>Definí el nombre público y editorial de esta instalación.</p>
          </div>
          <button type="button" class="settings-card-toggle" id="siteIdentityCardToggle" aria-expanded="true" aria-controls="siteIdentityCardContent" aria-label="Contraer identidad del sitio">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content" id="siteIdentityCardContent">
          <div class="form-group">
            <label for="siteIdentityName">Nombre público del sitio</label>
            <input class="form-control" type="text" id="siteIdentityName" name="nombre_sitio" minlength="2" maxlength="120" value="<?= e($nombreSitioPanel) ?>" required autocomplete="organization">
            <div class="form-hint">Se usa como nombre de fuente en Google, asistentes, Open Graph y datos estructurados.</div>
          </div>

          <div class="settings-card-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
            <span>El título, la descripción y la imagen de la portada continúan administrándose en la card SEO.</span>
          </div>

          <span class="settings-save-status" id="siteIdentitySaveStatus" aria-live="polite"></span>
          <div class="settings-form-actions">
            <button type="button" class="btn btn-outline" id="siteIdentityCancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="siteIdentitySaveButton">Guardar identidad</button>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <form class="settings-card" id="watermarkSettingsForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.2-3.2a2 2 0 0 0-2.8 0L6 21"></path><path d="M13 5h6M16 2v6"></path></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Marca de Agua</h3>
            <p>Se aplicará centrada sobre todas las imágenes nuevas.</p>
          </div>
          <button type="button" class="settings-card-toggle" id="watermarkCardToggle" aria-expanded="true" aria-controls="watermarkCardContent" aria-label="Contraer ajustes de marca de agua">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content" id="watermarkCardContent">
        <div class="watermark-preview" aria-label="Vista previa de la marca de agua">
          <img class="watermark-preview-photo" src="<?= e($fotoDemoMarcaAgua) ?>" alt="Fotografía de demostración">
          <img class="watermark-preview-logo" id="watermarkPreviewLogo" src="<?= e($marcaAguaPanel['url']) ?>" alt="Marca de agua actual" style="width: <?= (int) $marcaAguaPanel['tamano'] ?>%; opacity: <?= e(number_format($marcaAguaPanel['opacidad'] / 100, 2, '.', '')) ?>">
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

        <div class="watermark-range-group">
          <div class="watermark-range-heading">
            <label for="watermarkSize">Tamaño de la marca</label>
            <output id="watermarkSizeValue" for="watermarkSize"><?= (int) $marcaAguaPanel['tamano'] ?>%</output>
          </div>
          <input type="range" id="watermarkSize" name="tamano" min="15" max="65" step="1" value="<?= (int) $marcaAguaPanel['tamano'] ?>">
          <div class="watermark-range-scale"><span>Más chica</span><span>Más grande</span></div>
          <p>Define cuánto ancho ocupa la marca sobre cada imagen. La vista previa refleja el tamaño elegido.</p>
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
        </div>
      </form>

      <?php if ($logoLoginPanel !== null): ?>
      <form class="settings-card" id="loginLogoSettingsForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon settings-card-icon-login" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"></rect><path d="M8 9h8M7 15h10"></path></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Logo del Login</h3>
            <p>Personalizá la identidad de la pantalla de ingreso.</p>
          </div>
          <button type="button" class="settings-card-toggle" id="loginLogoCardToggle" aria-expanded="true" aria-controls="loginLogoCardContent" aria-label="Contraer ajustes del logo del login">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content" id="loginLogoCardContent">
          <div class="login-logo-preview" aria-label="Vista previa del logo del login">
            <span class="login-logo-preview-label">Vista previa</span>
            <img id="loginLogoPreview" src="<?= e($logoLoginPanel['url']) ?>" alt="Logo actual del login">
          </div>

          <div class="watermark-upload-row login-logo-upload-row">
            <div>
              <strong>Imagen del logo</strong>
              <span id="loginLogoFileName">PNG transparente, máximo 2 MB.</span>
            </div>
            <label class="btn btn-outline watermark-upload-button" for="loginLogoFile">Cambiar logo</label>
            <input type="file" id="loginLogoFile" name="logo_login" accept="image/png" hidden>
          </div>

          <div class="watermark-range-group logo-size-range-group">
            <div class="watermark-range-heading">
              <label for="loginLogoSize">Tamaño del logo</label>
              <output id="loginLogoSizeValue" for="loginLogoSize"><?= (int) $logoLoginPanel['tamano'] ?>%</output>
            </div>
            <input type="range" id="loginLogoSize" name="tamano" min="60" max="140" step="1" value="<?= (int) $logoLoginPanel['tamano'] ?>">
            <div class="watermark-range-scale"><span>Más chico</span><span>Más grande</span></div>
            <p>Ajusta el tamaño en la pantalla de ingreso, manteniendo su proporción en PC y móvil.</p>
          </div>

          <div class="settings-card-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
            <span>Usá preferentemente un PNG horizontal con fondo transparente. El cambio aparecerá en el próximo ingreso.</span>
          </div>

          <span class="settings-save-status" id="loginLogoSaveStatus" aria-live="polite"></span>
          <div class="settings-form-actions">
            <button type="button" class="btn btn-outline" id="loginLogoCancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="loginLogoSaveButton">Guardar cambios</button>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <?php if ($logoPortalPanel !== null): ?>
      <form class="settings-card" id="portalLogoSettingsForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon settings-card-icon-portal" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Logo del Portal</h3>
            <p>Identidad del encabezado y menú público.</p>
          </div>
          <button type="button" class="settings-card-toggle" id="portalLogoCardToggle" aria-expanded="true" aria-controls="portalLogoCardContent" aria-label="Contraer ajustes del logo del portal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content" id="portalLogoCardContent">
          <div class="portal-logo-preview" aria-label="Vista previa del logo del portal">
            <span class="login-logo-preview-label">Menú público</span>
            <img id="portalLogoPreview" src="<?= e($logoPortalPanel['url']) ?>" alt="Logo actual del portal">
          </div>
          <div class="watermark-upload-row portal-logo-upload-row">
            <div>
              <strong>Imagen principal</strong>
              <span id="portalLogoFileName">PNG transparente, máximo 2 MB.</span>
            </div>
            <label class="btn btn-outline watermark-upload-button" for="portalLogoFile">Cambiar logo</label>
            <input type="file" id="portalLogoFile" name="logo_portal" accept="image/png" hidden>
          </div>

          <div class="watermark-range-group logo-size-range-group">
            <div class="watermark-range-heading">
              <label for="portalLogoSize">Tamaño del logo</label>
              <output id="portalLogoSizeValue" for="portalLogoSize"><?= (int) $logoPortalPanel['tamano'] ?>%</output>
            </div>
            <input type="range" id="portalLogoSize" name="tamano" min="60" max="140" step="1" value="<?= (int) $logoPortalPanel['tamano'] ?>">
            <div class="watermark-range-scale"><span>Más chico</span><span>Más grande</span></div>
            <p>Se aplica al logo centrado del encabezado y al del menú público en todos los dispositivos.</p>
          </div>

          <div class="settings-card-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
            <span>Este PNG se muestra únicamente en el encabezado y en el menú público del Portal.</span>
          </div>

          <span class="settings-save-status" id="portalLogoSaveStatus" aria-live="polite"></span>
          <div class="settings-form-actions">
            <button type="button" class="btn btn-outline" id="portalLogoCancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="portalLogoSaveButton">Guardar cambios</button>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <?php if ($logoAdminPanel !== null): ?>
      <form class="settings-card" id="adminLogoSettingsForm" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon settings-card-icon-admin-logo" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"></rect><path d="M8 15.5 11 12l2.3 2.3L16 11l2 2.5"></path><circle cx="9" cy="8.5" r="1.5"></circle></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Logo del Admin y Favicon</h3>
            <p>Una misma identidad para el menú interno y la pestaña del navegador.</p>
          </div>
          <button type="button" class="settings-card-toggle" id="adminLogoCardToggle" aria-expanded="true" aria-controls="adminLogoCardContent" aria-label="Contraer ajustes del logo del Admin y favicon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content" id="adminLogoCardContent">
          <div class="admin-logo-preview" id="adminLogoPreviewBox" aria-label="Vista previa del logo en el menú interno">
            <span class="login-logo-preview-label">Menú interno</span>
            <div class="admin-logo-preview-brand">
              <span class="brand-mark-preview" id="adminLogoFallback"<?= $logoAdminPanel['url'] !== null ? ' hidden' : '' ?>>N</span>
              <img id="adminLogoPreview" src="<?= e($logoAdminPanel['url'] ?? '') ?>" alt="Logo actual del Admin"<?= $logoAdminPanel['url'] === null ? ' hidden' : '' ?>>
              <div><strong>Noticias</strong><small>Panel de administración</small></div>
            </div>
          </div>

          <div class="portal-favicon-preview">
            <div>
              <strong>Vista de pestaña</strong>
              <span>El favicon se genera automáticamente con esta misma imagen.</span>
            </div>
            <span class="portal-favicon-tile"><img id="adminFaviconPreview" src="<?= e($logoAdminPanel['favicon_url']) ?>" alt="Favicon actual"></span>
          </div>

          <div class="watermark-upload-row admin-logo-upload-row">
            <div>
              <strong>Imagen principal</strong>
              <span id="adminLogoFileName">PNG transparente, máximo 2 MB.</span>
            </div>
            <label class="btn btn-outline watermark-upload-button" for="adminLogoFile">Cambiar logo</label>
            <input type="file" id="adminLogoFile" name="logo_admin" accept="image/png" hidden>
          </div>

          <div class="settings-card-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
            <span>El logo se adapta al espacio superior izquierdo del Admin y genera un favicon cuadrado compatible con navegadores.</span>
          </div>

          <span class="settings-save-status" id="adminLogoSaveStatus" aria-live="polite"></span>
          <div class="settings-form-actions">
            <button type="button" class="btn btn-outline" id="adminLogoCancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="adminLogoSaveButton">Guardar identidad</button>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <?php if ($seoPortadaPanel !== null && $puedeGestionarPaginas && empty($seoPortadaEnPagina)): ?>
        <?php require __DIR__ . '/seo-portada-form.php'; ?>
      <?php endif; ?>

      <?php if ($codigoHeaderPanel !== null): ?>
      <form class="settings-card header-code-card" id="headerCodeSettingsForm">
        <?= csrf_input() ?>
        <div class="settings-card-heading">
          <span class="settings-card-icon settings-card-icon-code" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"></path></svg>
          </span>
          <div class="settings-card-heading-copy">
            <h3>Código del Header</h3>
            <p>Google Analytics, Meta Pixel y otras integraciones públicas.</p>
          </div>
          <span class="header-code-state<?= $codigoHeaderPanel['activo'] ? ' is-active' : '' ?>" id="headerCodeState"><?= $codigoHeaderPanel['activo'] ? 'Activo' : 'Inactivo' ?></span>
          <button type="button" class="settings-card-toggle" id="headerCodeCardToggle" aria-expanded="true" aria-controls="headerCodeCardContent" aria-label="Contraer ajustes del Código del Header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
          </button>
        </div>

        <div class="settings-card-content header-code-content" id="headerCodeCardContent">
          <div class="header-code-toolbar">
            <div><strong>Fragmento HTML / JavaScript</strong><span>Se inserta al final del &lt;head&gt; público.</span></div>
            <label class="header-code-switch ad-form-switch" for="headerCodeActive">
              <input type="checkbox" id="headerCodeActive" name="activo" value="1" role="switch"<?= $codigoHeaderPanel['activo'] ? ' checked' : '' ?>>
              <span class="ad-form-switch-track" aria-hidden="true"><span></span></span>
              <span id="headerCodeActiveLabel"><?= $codigoHeaderPanel['activo'] ? 'Activado' : 'Desactivado' ?></span>
            </label>
          </div>

          <textarea class="header-code-editor" id="headerCodeEditor" name="codigo" maxlength="60000" spellcheck="false" autocomplete="off" placeholder="<!-- Pegá aquí el código de Google Analytics o Meta Pixel -->"><?= e($codigoHeaderPanel['codigo']) ?></textarea>
          <div class="header-code-meta"><span id="headerCodeCounter"><?= mb_strlen($codigoHeaderPanel['codigo']) ?> caracteres</span><span>No se ejecuta dentro del Admin.</span></div>

          <div class="settings-card-notice header-code-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
            <span>El código activo se carga en portada y noticias públicas. Pegá únicamente snippets de proveedores confiables.</span>
          </div>

          <div class="header-code-events">
            <strong>Aperturas de noticias sin recarga</strong>
            <span>El portal enviará automáticamente <code>page_view</code> a Google, <code>PageView</code> y <code>ViewContent</code> a Meta, además del evento <code>portal:noticia-abierta</code>.</span>
          </div>

          <span class="settings-save-status" id="headerCodeSaveStatus" aria-live="polite"></span>
          <div class="settings-form-actions">
            <button type="button" class="btn btn-outline" id="headerCodeCancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="headerCodeSaveButton">Guardar código</button>
          </div>
        </div>
      </form>
      <?php endif; ?>
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
