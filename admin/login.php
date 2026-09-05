<?php
require_once __DIR__ . '/includes/funciones.php';

iniciar_sesion_segura();
header('Cache-Control: no-store, private');
if (usuario_actual()) {
    redirigir('index.php');
}

$error = (($_GET['motivo'] ?? '') === 'sesion-reemplazada')
    ? 'Tu sesión se cerró porque se inició sesión con este usuario en otro dispositivo.'
    : '';
$email = '';
$logoLoginRuta = configuracion_logo_login();
$logoLoginArchivo = dirname(__DIR__) . '/' . $logoLoginRuta;
$logoLoginVersion = is_file($logoLoginArchivo) ? (string) filemtime($logoLoginArchivo) : '1';
$logoLoginUrl = url_imagen($logoLoginRuta) . '?v=' . rawurlencode($logoLoginVersion);
$logoLoginTamano = configuracion_logo_login_tamano();
$logoLoginEscala = $logoLoginTamano / 100;
$logoLoginEstilo = sprintf(
    '--login-logo-min-height:%.2fpx;--login-logo-fluid-height:%.2fvw;--login-logo-max-height:%.2fpx;--login-logo-max-width:%.2fpx;--login-logo-mobile-min-height:%.2fpx;--login-logo-mobile-fluid-height:%.2fsvh;--login-logo-mobile-max-height:%.2fpx;--login-logo-mobile-max-width:%.2fpx;--login-logo-small-height:%.2fpx',
    68 * $logoLoginEscala,
    8 * $logoLoginEscala,
    102 * $logoLoginEscala,
    315 * $logoLoginEscala,
    42 * $logoLoginEscala,
    7 * $logoLoginEscala,
    58 * $logoLoginEscala,
    210 * $logoLoginEscala,
    38 * $logoLoginEscala
);
$identidadAdminLogin = configuracion_logo_admin();
$faviconLoginArchivo = $identidadAdminLogin['favicon_ruta'] !== null
    ? dirname(__DIR__) . '/' . $identidadAdminLogin['favicon_ruta']
    : dirname(__DIR__) . '/imagenes/Logo2027v2.png';
$faviconLoginVersion = is_file($faviconLoginArchivo) ? (string) filemtime($faviconLoginArchivo) : '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
    $password = (string) ($_POST['password'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'sin-ip');
    $claveIntento = hash('sha256', $ip . '|' . $email);

    try {
        $stmt = db()->prepare('SELECT intentos, primer_intento_at, bloqueado_hasta FROM intentos_login WHERE clave_hash = ?');
        $stmt->execute([$claveIntento]);
        $intento = $stmt->fetch();
        $bloqueado = $intento && !empty($intento['bloqueado_hasta']) && strtotime($intento['bloqueado_hasta']) > time();

        if ($bloqueado) {
            $error = 'Demasiados intentos. Espera unos minutos antes de volver a intentar.';
        } else {
            $stmt = db()->prepare(
                'SELECT u.id, u.password_hash, u.activo
                   FROM usuarios u
                  WHERE u.email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            $valido = $usuario && (int) $usuario['activo'] === 1
                && password_verify($password, (string) $usuario['password_hash']);

            if ($valido) {
                $mantenimientoActivo = configuracion_mantenimiento()['activo'];
                $stmtRol = db()->prepare('SELECT rol_id FROM usuarios WHERE id = ? LIMIT 1');
                $stmtRol->execute([(int) $usuario['id']]);
                $rolId = (int) $stmtRol->fetchColumn();
                if ($mantenimientoActivo && !rol_tiene_permiso($rolId, 'mantenimiento.gestionar')) {
                    db()->prepare('DELETE FROM intentos_login WHERE clave_hash = ?')->execute([$claveIntento]);
                    $error = 'Tu rol no tiene permiso para ingresar mientras el portal está en mantenimiento.';
                    $valido = false;
                }
            }

            if ($valido) {
                if (password_needs_rehash((string) $usuario['password_hash'], PASSWORD_DEFAULT)) {
                    $rehash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
                    $upd->execute([$rehash, (int) $usuario['id']]);
                }
                db()->prepare('DELETE FROM intentos_login WHERE clave_hash = ?')->execute([$claveIntento]);
                db()->prepare('UPDATE usuarios SET ultimo_acceso_at = NOW() WHERE id = ?')->execute([(int) $usuario['id']]);
                iniciar_sesion_usuario((int) $usuario['id']);
                redirigir('index.php');
            }

            if ($error === '') {
                // La respuesta es deliberadamente generica para no revelar cuentas.
                $ventanaVigente = $intento && strtotime((string) $intento['primer_intento_at']) >= time() - 15 * 60;
                $intentos = $ventanaVigente ? (int) $intento['intentos'] + 1 : 1;
                $bloqueadoHasta = $intentos >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
                $stmt = db()->prepare(
                    'INSERT INTO intentos_login (clave_hash, intentos, primer_intento_at, bloqueado_hasta)
                     VALUES (?, ?, NOW(), ?)
                     ON DUPLICATE KEY UPDATE
                       intentos = IF(primer_intento_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, VALUES(intentos)),
                       primer_intento_at = IF(primer_intento_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), primer_intento_at),
                       bloqueado_hasta = VALUES(bloqueado_hasta)'
                );
                $stmt->execute([$claveIntento, $intentos, $bloqueadoHasta]);
                usleep(random_int(180000, 320000));
                $error = 'El correo o la contrasena no son correctos.';
            }
        }
    } catch (PDOException $e) {
        $error = 'El acceso al panel aun no esta disponible. Contacta al administrador.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Ingresar al panel</title>
  <link rel="icon" href="../favicon.php?v=<?= e(rawurlencode($faviconLoginVersion)) ?>" type="image/x-icon">
  <link rel="stylesheet" href="assets/login.css?v=<?= (int) filemtime(__DIR__ . '/assets/login.css') ?>">
</head>
<body style="<?= e($logoLoginEstilo) ?>">
  <main class="login-shell">
    <section class="login-visual" aria-label="Estudio de noticias">
      <img src="assets/images/login-newsroom.webp" alt="Estudio profesional de radio y noticias">
    </section>

    <section class="login-panel">
      <div class="login-content">
        <a class="login-brand" href="../index.php" aria-label="Volver al portal">
          <img src="<?= e($logoLoginUrl) ?>" alt="Portal de noticias">
        </a>

        <div class="login-heading">
          <h1>Panel de Gestión</h1>
          <p>Gestiona las noticias y el contenido del portal.</p>
        </div>

        <?php if ($error !== ''): ?>
          <div class="login-alert" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="login-form" autocomplete="on">
          <?= csrf_input() ?>
          <label for="email">Correo electrónico</label>
          <div class="login-control">
            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
          </div>

          <label for="password">Contraseña</label>
          <div class="login-control">
            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Mostrar contraseña" aria-pressed="false">
              <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>

          <button type="submit" class="login-submit">Ingresar</button>
        </form>

        <p class="login-security">
          <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3l8 3v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
          Acceso privado al panel editorial.
        </p>
      </div>
    </section>
  </main>
  <script>
    const toggle = document.getElementById('passwordToggle');
    const password = document.getElementById('password');
    toggle.addEventListener('click', () => {
      const visible = password.type === 'text';
      password.type = visible ? 'password' : 'text';
      toggle.setAttribute('aria-pressed', String(!visible));
      toggle.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
      password.focus();
    });
  </script>
</body>
</html>
