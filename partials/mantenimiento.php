<?php
$rutaLogoMantenimiento = (string) $configuracionMantenimiento['logo_ruta'];
$archivoLogoMantenimiento = dirname(__DIR__) . '/' . $rutaLogoMantenimiento;
$versionLogoMantenimiento = is_file($archivoLogoMantenimiento) ? (string) filemtime($archivoLogoMantenimiento) : '1';
$versionCssMantenimiento = (string) (filemtime(dirname(__DIR__) . '/assets/css/mantenimiento.css') ?: '1');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#020817">
  <title><?= e($configuracionMantenimiento['mensaje']) ?></title>
  <link rel="icon" href="<?= e(url_portal('favicon.php')) ?>" type="image/x-icon">
  <link rel="stylesheet" href="<?= e(url_portal('assets/css/mantenimiento.css')) ?>?v=<?= e(rawurlencode($versionCssMantenimiento)) ?>">
</head>
<body class="maintenance-page">
<?php if ($configuracionMantenimiento['mostrar_login']): ?>
  <a class="maintenance-login" href="<?= e(url_portal('admin/login.php')) ?>" aria-label="Ingresar al panel" title="Ingresar al panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path></svg>
  </a>
<?php endif; ?>
  <main class="maintenance-main">
    <div class="maintenance-identity" style="--maintenance-logo-width: <?= (int) $configuracionMantenimiento['logo_tamano'] ?>%;">
      <img src="<?= e(url_recurso_portal($rutaLogoMantenimiento)) ?>?v=<?= e(rawurlencode($versionLogoMantenimiento)) ?>" alt="Logo del portal">
      <p><?= e($configuracionMantenimiento['mensaje']) ?></p>
    </div>
  </main>
</body>
</html>
