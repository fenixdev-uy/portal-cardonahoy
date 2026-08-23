<?php
require_once __DIR__ . '/includes/funciones.php';

$actual = exigir_login();
exigir_permiso('usuarios.gestionar');
$pdo = db();
$errores = [];
$editar = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'estado') {
        $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;
        if ($id === (int) $actual['id'] && $activo === 0) {
            flash('danger', 'No podes desactivar tu propio usuario.');
        } elseif ($activo === 1) {
            $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            if ((string) $stmt->fetchColumn() === '') {
                flash('danger', 'Antes de activar este perfil debes editarlo y asignarle una contrasena nueva.');
                redirigir('usuarios.php');
            }
            $stmt = $pdo->prepare('UPDATE usuarios SET activo = 1 WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', 'Usuario activado.');
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', 'Usuario desactivado.');
        }
        redirigir('usuarios.php');
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
    $rolId = (int) ($_POST['rol_id'] ?? 0);
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingresa un correo valido.';
    if ($rolId <= 0) $errores[] = 'Selecciona un rol.';
    if ($id === 0 && strlen($password) < 12) $errores[] = 'La contrasena inicial debe tener al menos 12 caracteres.';
    if ($password !== '' && strlen($password) < 12) $errores[] = 'La contrasena debe tener al menos 12 caracteres.';

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $id]);
    if ((int) $stmt->fetchColumn() > 0) $errores[] = 'Ya existe un usuario con ese correo.';

    $stmt = $pdo->prepare('SELECT slug FROM roles WHERE id = ?');
    $stmt->execute([$rolId]);
    $rolSlug = (string) $stmt->fetchColumn();
    if ($rolSlug === '') $errores[] = 'El rol seleccionado no existe.';
    if ($id === (int) $actual['id'] && ($activo === 0 || $rolSlug !== 'admin')) {
        $errores[] = 'No podes desactivar ni quitar el rol administrador a tu propia cuenta.';
    }

    if (!$errores) {
        if ($id > 0) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE usuarios SET rol_id=?, nombre=?, email=?, password_hash=?, bio=?, activo=?, debe_cambiar_password=1 WHERE id=?');
                $stmt->execute([$rolId, $nombre, $email, password_hash($password, PASSWORD_DEFAULT), $bio ?: null, $activo, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE usuarios SET rol_id=?, nombre=?, email=?, bio=?, activo=? WHERE id=?');
                $stmt->execute([$rolId, $nombre, $email, $bio ?: null, $activo, $id]);
            }
            flash('success', 'Usuario actualizado correctamente.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO usuarios (rol_id,nombre,email,password_hash,bio,activo,debe_cambiar_password) VALUES (?,?,?,?,?,?,1)');
            $stmt->execute([$rolId, $nombre, $email, password_hash($password, PASSWORD_DEFAULT), $bio ?: null, $activo]);
            flash('success', 'Usuario creado. Debera cambiar su contrasena al ingresar.');
        }
        redirigir('usuarios.php');
    }

    // Conserva los datos ingresados si hay errores y vuelve a abrir el panel.
    $editar = [
        'id' => $id,
        'rol_id' => $rolId,
        'nombre' => $nombre,
        'email' => $email,
        'bio' => $bio,
        'activo' => $activo,
    ];
}

if ($editar === null && isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT id,rol_id,nombre,email,bio,activo FROM usuarios WHERE id=?');
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch() ?: null;
}
$roles = $pdo->query('SELECT id,nombre,descripcion FROM roles ORDER BY id')->fetchAll();
$usuarios = $pdo->query(
    'SELECT u.id,u.nombre,u.email,u.bio,u.activo,u.debe_cambiar_password,u.ultimo_acceso_at,(u.password_hash <> \'\') AS tiene_clave,r.nombre AS rol_nombre,r.slug AS rol_slug,
            (SELECT COUNT(*) FROM noticias n WHERE n.usuario_id=u.id) AS total_noticias
       FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.activo DESC,u.nombre'
)->fetchAll();

$titulo = 'Usuarios';
$active = 'usuarios';
require __DIR__ . '/includes/header.php';
?>
<div class="users-page-heading">
  <h1>Gestión de Usuarios</h1>
  <p>Administrá las cuentas, sus datos de acceso, estado y perfiles dentro del portal.</p>
</div>

<?php if ($errores): ?><div class="flash"><?php foreach ($errores as $error): ?><div class="alert danger"><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

<section class="users-panel">
  <div class="users-panel-header">
    <div>
      <h2>Usuarios registrados</h2>
      <p><?= count($usuarios) ?> <?= count($usuarios) === 1 ? 'usuario registrado' : 'usuarios registrados' ?></p>
    </div>
    <div class="users-panel-actions">
      <?php if (tiene_permiso('roles.gestionar')): ?><a class="btn btn-outline" href="roles.php">Roles y permisos</a><?php endif; ?>
      <a class="btn btn-primary js-new-user" href="usuarios.php?nuevo=1">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Nuevo usuario
      </a>
    </div>
  </div>

  <div class="table-wrap users-table-wrap"><table class="table users-table"><thead><tr><th>Usuario</th><th>Rol</th><th>Noticias</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead><tbody>
  <?php foreach($usuarios as $u): ?><tr><td data-label="Usuario"><strong><?= e($u['nombre']) ?></strong><div class="cell-desc"><?= e($u['email']) ?></div></td><td data-label="Rol"><span class="badge"><?= e($u['rol_nombre']) ?></span></td><td data-label="Noticias"><?= (int)$u['total_noticias'] ?></td><td data-label="Estado"><span class="user-status <?= (int)$u['activo']===1?'is-active':'is-inactive' ?>"><span aria-hidden="true"></span><?= (int)$u['activo']===1?'Activo':'Inactivo' ?></span><div class="user-access-note"><?= (int)$u['tiene_clave']===0?'Sin contraseña':((int)$u['debe_cambiar_password']===1?'Contraseña temporal':'Acceso configurado') ?></div></td><td data-label="Último acceso"><?= $u['ultimo_acceso_at']?e(date('d/m/Y H:i',strtotime($u['ultimo_acceso_at']))):'—' ?></td><td data-label="Acciones"><div class="cell-actions user-icon-actions"><a class="action-icon action-icon-edit" href="usuarios.php?editar=<?= (int)$u['id'] ?>" aria-label="Editar a <?= e($u['nombre']) ?>" title="Editar usuario"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg></a><?php if((int)$u['id']!==(int)$actual['id']): ?><form method="post" action="usuarios.php" onsubmit="return confirm('¿<?= (int)$u['activo']===1?'Desactivar':'Activar' ?> a <?= e($u['nombre']) ?>?')"><?= csrf_input() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="activo" value="<?= (int)$u['activo']===1?0:1 ?>"><button class="action-icon <?= (int)$u['activo']===1?'action-icon-deactivate':'action-icon-activate' ?>" type="submit" aria-label="<?= (int)$u['activo']===1?'Desactivar':'Activar' ?> a <?= e($u['nombre']) ?>" title="<?= (int)$u['activo']===1?'Desactivar':'Activar' ?> usuario"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg></button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<div class="drawer-backdrop" id="userDrawerBackdrop"></div>
<aside class="drawer user-drawer" id="userDrawer" aria-hidden="true" aria-labelledby="userDrawerTitle">
  <header class="drawer-header user-drawer-header">
    <div>
      <span class="drawer-title-label"><?= $editar && (int)($editar['id'] ?? 0) > 0 ? 'Editar cuenta' : 'Nueva cuenta' ?></span>
      <h2 id="userDrawerTitle"><?= $editar && (int)($editar['id'] ?? 0) > 0 ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
    </div>
    <button type="button" class="drawer-close" id="userDrawerClose" aria-label="Cerrar panel">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </header>
  <div class="drawer-body user-drawer-body">
    <form method="post" action="usuarios.php" id="userForm">
      <?= csrf_input() ?>
      <input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" id="userId" value="<?= (int) ($editar['id'] ?? 0) ?>">
      <div class="form-group"><label for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" maxlength="120" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
      <div class="form-group"><label for="email">Correo electrónico</label><input class="form-control" type="email" id="email" name="email" maxlength="190" required value="<?= e($editar['email'] ?? '') ?>"></div>
      <div class="form-group"><label for="rol_id">Rol</label><select class="form-control" id="rol_id" name="rol_id" required><option value="">Seleccionar</option><?php foreach ($roles as $rol): ?><option value="<?= (int) $rol['id'] ?>" <?= (int) ($editar['rol_id'] ?? 0)===(int)$rol['id']?'selected':'' ?>><?= e($rol['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="password" id="passwordLabel"><?= $editar && (int)($editar['id'] ?? 0) > 0 ? 'Nueva contraseña (opcional)' : 'Contraseña inicial' ?></label><input class="form-control" type="password" id="password" name="password" minlength="12" <?= $editar && (int)($editar['id'] ?? 0) > 0?'':'required' ?> autocomplete="new-password"><div class="form-hint">Mínimo 12 caracteres. Al asignarla, el usuario deberá cambiarla en su primer ingreso.</div></div>
      <div class="form-group"><label for="bio">Firma o descripción</label><input class="form-control" id="bio" name="bio" maxlength="255" value="<?= e($editar['bio'] ?? '') ?>" placeholder="Ej.: Periodista de economía"></div>
      <label class="check-row"><input type="checkbox" id="activo" name="activo" value="1" <?= !isset($editar['activo']) || (int)$editar['activo']===1?'checked':'' ?>> Usuario activo</label>
      <div class="form-actions user-form-actions">
        <button class="btn btn-primary" type="submit" id="userSubmit"><?= $editar && (int)($editar['id'] ?? 0) > 0?'Guardar cambios':'Crear usuario' ?></button>
        <?php if ($editar && (int)($editar['id'] ?? 0) > 0 && tiene_permiso('roles.gestionar')): ?><a class="btn btn-outline" href="roles.php">Editar roles</a><?php endif; ?>
        <button class="btn btn-outline" type="button" id="userDrawerCancel">Cancelar</button>
      </div>
    </form>
  </div>
</aside>

<script>
  (function () {
    const drawer = document.getElementById('userDrawer');
    const backdrop = document.getElementById('userDrawerBackdrop');
    const closeButton = document.getElementById('userDrawerClose');
    const cancelButton = document.getElementById('userDrawerCancel');
    const newButton = document.querySelector('.js-new-user');
    const form = document.getElementById('userForm');
    const title = document.getElementById('userDrawerTitle');
    const label = drawer.querySelector('.drawer-title-label');
    const password = document.getElementById('password');
    const passwordLabel = document.getElementById('passwordLabel');
    const submit = document.getElementById('userSubmit');
    const editRoles = form.querySelector('a[href="roles.php"]');

    function openDrawer() {
      drawer.classList.add('open');
      backdrop.classList.add('show');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      window.setTimeout(() => document.getElementById('nombre').focus(), 250);
    }

    function closeDrawer() {
      drawer.classList.remove('open');
      backdrop.classList.remove('show');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (window.location.search) window.history.replaceState({}, '', 'usuarios.php');
    }

    function prepareNewUser(event) {
      if (event) event.preventDefault();
      form.reset();
      document.getElementById('userId').value = '0';
      document.getElementById('nombre').value = '';
      document.getElementById('email').value = '';
      document.getElementById('rol_id').value = '';
      document.getElementById('password').value = '';
      document.getElementById('bio').value = '';
      document.getElementById('activo').checked = true;
      title.textContent = 'Nuevo usuario';
      label.textContent = 'Nueva cuenta';
      passwordLabel.textContent = 'Contraseña inicial';
      password.required = true;
      submit.textContent = 'Crear usuario';
      if (editRoles) editRoles.hidden = true;
      openDrawer();
    }

    newButton.addEventListener('click', prepareNewUser);
    closeButton.addEventListener('click', closeDrawer);
    cancelButton.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });

    <?php if ($editar !== null || isset($_GET['nuevo'])): ?>
    <?= $editar === null ? 'prepareNewUser();' : 'openDrawer();' ?>
    <?php endif; ?>
  })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
