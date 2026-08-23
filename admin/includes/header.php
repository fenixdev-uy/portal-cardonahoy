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
// Las URLs del HTML se resuelven respecto a la ubicación de la página (landing/admin/),
// no respecto a la carpeta includes/. Por eso $BASE queda vacío.
$BASE = '';
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($titulo) ?> · Panel Noticias</title>
  <link rel="stylesheet" href="<?= $BASE ?>assets/admin.css" />
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

      <?php if (tiene_permiso('usuarios.gestionar')): ?><span class="nav-label">Administración</span>
      <a href="<?= $BASE ?>usuarios.php" class="nav-link <?= $activo === 'usuarios' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        Usuarios
      </a><?php endif; ?>

      <?php if (tiene_permiso('roles.gestionar')): ?><a href="<?= $BASE ?>roles.php" class="nav-link <?= $activo === 'roles' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
        Roles y permisos
      </a><?php endif; ?>

      <a href="<?= $BASE ?>../index.php" class="nav-link" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><path d="M15 3h6v6"></path><path d="M10 14L21 3"></path></svg>
        Ver sitio
      </a>
    </nav>

    <div class="sidebar-footer">
      <strong><?= e($usuarioPanel['nombre']) ?></strong><br>
      <span><?= e($usuarioPanel['rol_nombre']) ?></span>
    </div>
  </aside>

  <!-- ===== Contenido principal ===== -->
  <div class="main">
    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      <h2><?= e($titulo) ?></h2>
      <div class="topbar-actions">
      <?php if (tiene_permiso('noticias.crear')): ?><a href="<?= $BASE ?>noticia-form.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Nueva noticia
      </a><?php endif; ?>
      <form method="post" action="<?= $BASE ?>logout.php" class="logout-form">
        <?= csrf_input() ?>
        <button type="submit" class="btn btn-outline">Salir</button>
      </form>
      </div>
    </header>

    <main class="content">
      <?php if ($flashes): ?>
        <div class="flash">
          <?php foreach ($flashes as $f): ?>
            <div class="alert <?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
