<?php
/** Acceso del encabezado público: login o identidad de la sesión activa. */
$usuarioEncabezado = is_array($usuarioPublico ?? null) ? $usuarioPublico : null;
?>
<?php if ($usuarioEncabezado): ?>
  <?php
    $nombreEncabezado = trim((string) ($usuarioEncabezado['nombre'] ?? 'Usuario')) ?: 'Usuario';
    $rolEncabezado = trim((string) ($usuarioEncabezado['rol_nombre'] ?? '')) ?: 'Usuario';
    $fotoEncabezado = trim((string) ($usuarioEncabezado['foto'] ?? ''));
    $fotoEncabezadoValida = $fotoEncabezado !== '' && imagen_usuario_disponible($fotoEncabezado);
    $inicialEncabezado = mb_strtoupper(mb_substr($nombreEncabezado, 0, 1, 'UTF-8'), 'UTF-8');
  ?>
  <a href="<?= e(url_portal('admin/index.php')) ?>" class="admin-login-link is-authenticated" aria-label="Abrir panel de administración de <?= e($nombreEncabezado) ?>">
    <span class="admin-login-copy">
      <strong><?= e($nombreEncabezado) ?></strong>
      <small><?= e($rolEncabezado) ?></small>
    </span>
    <span class="admin-login-avatar" aria-hidden="true">
      <?php if ($fotoEncabezadoValida): ?>
        <img src="<?= e(url_recurso_portal($fotoEncabezado)) ?>" alt="">
      <?php else: ?>
        <span><?= e($inicialEncabezado) ?></span>
      <?php endif; ?>
    </span>
  </a>
<?php else: ?>
  <a href="<?= e(url_portal('admin/login.php')) ?>" class="admin-login-link is-guest" aria-label="Ingresar al panel de administración">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 21a8 8 0 0 0-16 0"></path>
      <circle cx="12" cy="7" r="4"></circle>
    </svg>
    <span>Ingresar</span>
  </a>
<?php endif; ?>
