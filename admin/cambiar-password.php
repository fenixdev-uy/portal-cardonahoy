<?php
require_once __DIR__ . '/includes/funciones.php';

$usuario = exigir_login();
$errores = [];
$identidadAdminPassword = configuracion_logo_admin();
$faviconPasswordArchivo = $identidadAdminPassword['favicon_ruta'] !== null
    ? dirname(__DIR__) . '/' . $identidadAdminPassword['favicon_ruta']
    : dirname(__DIR__) . '/imagenes/Logo2027v2.png';
$faviconPasswordVersion = is_file($faviconPasswordArchivo) ? (string) filemtime($faviconPasswordArchivo) : '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $actual = (string) ($_POST['password_actual'] ?? '');
    $nueva = (string) ($_POST['password_nueva'] ?? '');
    $repetida = (string) ($_POST['password_repetida'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $stmt->execute([(int) $usuario['id']]);
    $hash = (string) $stmt->fetchColumn();
    if (!password_verify($actual, $hash)) {
        $errores[] = 'La contrasena actual no es correcta.';
    }
    if (strlen($nueva) < 12) {
        $errores[] = 'La nueva contrasena debe tener al menos 12 caracteres.';
    }
    if ($nueva !== $repetida) {
        $errores[] = 'Las nuevas contrasenas no coinciden.';
    }
    if (!$errores) {
        $stmt = db()->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_password = 0 WHERE id = ?');
        $stmt->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $usuario['id']]);
        iniciar_sesion_usuario((int) $usuario['id']);
        flash('success', 'Contrasena actualizada correctamente.');
        redirigir('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Cambiar contraseña</title>
  <link rel="icon" href="../favicon.php?v=<?= e(rawurlencode($faviconPasswordVersion)) ?>" type="image/x-icon">
  <link rel="stylesheet" href="assets/login.css">
</head>
<body class="password-page">
  <main class="password-card">
    <img src="../imagenes/Logo2027v2.png" alt="Portal de noticias" class="password-logo">
    <h1>Cambiar contraseña</h1>
    <p>Por seguridad, reemplaza la clave temporal antes de continuar.</p>
    <?php foreach ($errores as $error): ?><div class="login-alert" role="alert"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" class="login-form">
      <?= csrf_input() ?>
      <label for="actual">Contraseña actual</label>
      <input class="plain-input" type="password" id="actual" name="password_actual" autocomplete="current-password" required>
      <label for="nueva">Nueva contraseña</label>
      <input class="plain-input" type="password" id="nueva" name="password_nueva" autocomplete="new-password" minlength="12" required>
      <label for="repetida">Repetir nueva contraseña</label>
      <input class="plain-input" type="password" id="repetida" name="password_repetida" autocomplete="new-password" minlength="12" required>
      <button type="submit" class="login-submit">Guardar contraseña</button>
    </form>
  </main>
</body>
</html>
