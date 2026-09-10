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

    if ($accion === 'eliminar') {
        $transferirA = (int) ($_POST['transferir_a'] ?? 0);
        if ($id <= 0) {
            flash('danger', 'El usuario indicado no es válido.');
            redirigir('usuarios.php');
        }
        if ($id === (int) $actual['id']) {
            flash('danger', 'No podes eliminar tu propio usuario.');
            redirigir('usuarios.php');
        }

        $fotoUsuario = '';
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id, nombre, foto FROM usuarios WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $usuarioEliminar = $stmt->fetch();
            if (!$usuarioEliminar) {
                throw new DomainException('El usuario que intentas eliminar ya no existe.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE usuario_id = ?');
            $stmt->execute([$id]);
            $totalNoticias = (int) $stmt->fetchColumn();
            $nombreDestino = '';

            if ($totalNoticias > 0) {
                if ($transferirA <= 0 || $transferirA === $id) {
                    throw new DomainException('Selecciona otro usuario activo para recibir las noticias.');
                }
                $stmt = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE id = ? AND activo = 1 FOR UPDATE');
                $stmt->execute([$transferirA]);
                $usuarioDestino = $stmt->fetch();
                if (!$usuarioDestino) {
                    throw new DomainException('El usuario elegido para recibir las noticias no existe o está inactivo.');
                }
                $nombreDestino = (string) $usuarioDestino['nombre'];
                $stmt = $pdo->prepare('UPDATE noticias SET usuario_id = ? WHERE usuario_id = ?');
                $stmt->execute([$transferirA, $id]);
                if ($stmt->rowCount() !== $totalNoticias) {
                    throw new RuntimeException('No se pudo transferir toda la autoría.');
                }
            }

            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('No se pudo eliminar el usuario.');
            }

            $fotoUsuario = trim((string) ($usuarioEliminar['foto'] ?? ''));
            $nombreEliminado = (string) $usuarioEliminar['nombre'];
            $pdo->commit();

            eliminar_imagen_usuario($fotoUsuario);
            $mensaje = $totalNoticias > 0
                ? "Usuario eliminado. Sus {$totalNoticias} " . ($totalNoticias === 1 ? 'noticia fue transferida' : 'noticias fueron transferidas') . " a {$nombreDestino}."
                : "Usuario {$nombreEliminado} eliminado correctamente.";
            flash('success', $mensaje);
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', $e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', 'No se pudo eliminar el usuario ni transferir sus noticias. No se aplicaron cambios parciales.');
        }
        redirigir('usuarios.php');
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
    $rolId = (int) ($_POST['rol_id'] ?? 0);
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $fotoAnterior = '';
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT foto FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $fotoAnterior = trim((string) $stmt->fetchColumn());
    }

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

    $fotoNueva = null;
    if (!$errores) {
        try {
            $fotoNueva = subir_imagen_usuario($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }

    if (!$errores) {
        $fotoFinal = $fotoNueva ?? $fotoAnterior;
        try {
            $pdo->beginTransaction();
            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE usuarios SET rol_id=?, nombre=?, email=?, password_hash=?, bio=?, foto=?, activo=?, debe_cambiar_password=1 WHERE id=?');
                    $stmt->execute([$rolId, $nombre, $email, password_hash($password, PASSWORD_DEFAULT), $bio ?: null, $fotoFinal ?: null, $activo, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE usuarios SET rol_id=?, nombre=?, email=?, bio=?, foto=?, activo=? WHERE id=?');
                    $stmt->execute([$rolId, $nombre, $email, $bio ?: null, $fotoFinal ?: null, $activo, $id]);
                }
                $mensaje = 'Usuario actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO usuarios (rol_id,nombre,email,password_hash,bio,foto,activo,debe_cambiar_password) VALUES (?,?,?,?,?,?,?,1)');
                $stmt->execute([$rolId, $nombre, $email, password_hash($password, PASSWORD_DEFAULT), $bio ?: null, $fotoFinal ?: null, $activo]);
                $mensaje = 'Usuario creado. Debera cambiar su contrasena al ingresar.';
            }
            $pdo->commit();
            if ($fotoNueva !== null && $fotoAnterior !== '' && $fotoAnterior !== $fotoNueva) {
                eliminar_imagen_usuario($fotoAnterior);
            }
            flash('success', $mensaje);
            redirigir('usuarios.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($fotoNueva !== null) eliminar_imagen_usuario($fotoNueva);
            $errores[] = 'No se pudo guardar el usuario. Intenta nuevamente.';
        }
    }

    // Conserva los datos ingresados si hay errores y vuelve a abrir el panel.
    $editar = [
        'id' => $id,
        'rol_id' => $rolId,
        'nombre' => $nombre,
        'email' => $email,
        'bio' => $bio,
        'foto' => $fotoAnterior,
        'activo' => $activo,
    ];
}

if ($editar === null && isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT id,rol_id,nombre,email,bio,foto,activo FROM usuarios WHERE id=?');
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch() ?: null;
}
$roles = $pdo->query('SELECT id,nombre,descripcion FROM roles ORDER BY id')->fetchAll();
$usuarios = $pdo->query(
    'SELECT u.id,u.nombre,u.email,u.bio,u.foto,u.activo,u.debe_cambiar_password,u.ultimo_acceso_at,(u.password_hash <> \'\') AS tiene_clave,r.nombre AS rol_nombre,r.slug AS rol_slug,
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
  <div class="users-panel-header users-management-header">
    <div class="users-management-copy">
      <h2>Usuarios registrados</h2>
      <p><?= count($usuarios) ?> <?= count($usuarios) === 1 ? 'usuario registrado' : 'usuarios registrados' ?></p>
    </div>
    <a class="btn btn-primary js-new-user users-create-btn" href="usuarios.php?nuevo=1">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      Nuevo usuario
    </a>
    <?php if (tiene_permiso('roles.gestionar')): ?><a class="btn btn-outline users-roles-btn" href="roles.php">Roles - Permisos</a><?php endif; ?>
  </div>

  <div class="table-wrap users-table-wrap"><table class="table users-table"><thead><tr><th>Usuario</th><th>Rol</th><th>Noticias</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead><tbody>
  <?php foreach($usuarios as $u):
    $fotoFila = trim((string) ($u['foto'] ?? ''));
    $fotoFilaValida = $fotoFila !== '' && imagen_usuario_disponible($fotoFila);
    $inicialFila = mb_strtoupper(mb_substr(trim((string) $u['nombre']) ?: 'U', 0, 1, 'UTF-8'), 'UTF-8');
  ?>
    <tr>
      <td data-label="Usuario">
        <div class="user-table-identity">
          <span class="user-table-avatar" aria-hidden="true">
            <?php if ($fotoFilaValida): ?><img src="<?= e(url_imagen($fotoFila)) ?>" alt=""><?php else: ?><?= e($inicialFila) ?><?php endif; ?>
          </span>
          <span><strong><?= e($u['nombre']) ?></strong><span class="cell-desc"><?= e($u['email']) ?></span></span>
        </div>
      </td>
      <td data-label="Rol"><span class="badge"><?= e($u['rol_nombre']) ?></span></td>
      <td data-label="Noticias"><?= (int)$u['total_noticias'] ?></td>
      <td data-label="Estado"><span class="user-status <?= (int)$u['activo']===1?'is-active':'is-inactive' ?>"><span aria-hidden="true"></span><?= (int)$u['activo']===1?'Activo':'Inactivo' ?></span><div class="user-access-note"><?= (int)$u['tiene_clave']===0?'Sin contraseña':((int)$u['debe_cambiar_password']===1?'Contraseña temporal':'Acceso configurado') ?></div></td>
      <td data-label="Último acceso"><?= $u['ultimo_acceso_at']?e(date('d/m/Y H:i',strtotime($u['ultimo_acceso_at']))):'—' ?></td>
      <td data-label="Acciones"><div class="cell-actions user-icon-actions"><a class="action-icon action-icon-edit" href="usuarios.php?editar=<?= (int)$u['id'] ?>" aria-label="Editar a <?= e($u['nombre']) ?>" title="Editar usuario"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg></a><?php if((int)$u['id']!==(int)$actual['id']): ?><form method="post" action="usuarios.php" onsubmit="return confirm('¿<?= (int)$u['activo']===1?'Desactivar':'Activar' ?> a <?= e($u['nombre']) ?>?')"><?= csrf_input() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="activo" value="<?= (int)$u['activo']===1?0:1 ?>"><button class="action-icon <?= (int)$u['activo']===1?'action-icon-deactivate':'action-icon-activate' ?>" type="submit" aria-label="<?= (int)$u['activo']===1?'Desactivar':'Activar' ?> a <?= e($u['nombre']) ?>" title="<?= (int)$u['activo']===1?'Desactivar':'Activar' ?> usuario"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg></button></form><button class="action-icon action-icon-delete js-delete-user" type="button" data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= e($u['nombre']) ?>" data-user-news="<?= (int)$u['total_noticias'] ?>" aria-label="Eliminar a <?= e($u['nombre']) ?>" title="Eliminar usuario"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button><?php endif; ?></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>

<dialog class="user-delete-dialog" id="userDeleteDialog" aria-labelledby="userDeleteTitle">
  <form method="post" action="usuarios.php" id="userDeleteForm">
    <?= csrf_input() ?>
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="deleteUserId" value="">
    <div class="user-delete-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
    </div>
    <h2 id="userDeleteTitle">Eliminar usuario</h2>
    <p class="user-delete-summary" id="userDeleteSummary"></p>
    <div class="form-group user-delete-transfer" id="userDeleteTransfer">
      <label for="transferir_a">Transferir autoría de las noticias a</label>
      <select class="form-control" id="transferir_a" name="transferir_a">
        <option value="">Seleccionar usuario</option>
        <?php foreach ($usuarios as $destino): ?>
          <?php if ((int) $destino['activo'] === 1): ?>
            <option value="<?= (int) $destino['id'] ?>"><?= e($destino['nombre']) ?> · <?= e($destino['rol_nombre']) ?></option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
      <div class="form-hint">Todas las noticias conservarán su contenido y pasarán a mostrar esta firma.</div>
    </div>
    <p class="user-delete-warning">Esta acción elimina definitivamente la cuenta y su foto de perfil. No elimina noticias.</p>
    <div class="form-actions user-delete-actions">
      <button class="btn btn-outline" type="button" id="userDeleteCancel">Cancelar</button>
      <button class="btn btn-danger" type="submit" id="userDeleteSubmit">Transferir y eliminar</button>
    </div>
  </form>
</dialog>

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
    <form method="post" action="usuarios.php" id="userForm" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" id="userId" value="<?= (int) ($editar['id'] ?? 0) ?>">
      <?php
        $fotoEditar = trim((string) ($editar['foto'] ?? ''));
        $fotoEditarValida = $fotoEditar !== '' && imagen_usuario_disponible($fotoEditar);
        $inicialEditar = mb_strtoupper(mb_substr(trim((string) ($editar['nombre'] ?? '')) ?: 'U', 0, 1, 'UTF-8'), 'UTF-8');
      ?>
      <div class="user-photo-field">
        <span class="user-photo-preview<?= $fotoEditarValida ? ' has-image' : '' ?>" id="userPhotoPreview" aria-hidden="true">
          <?php if ($fotoEditarValida): ?><img src="<?= e(url_imagen($fotoEditar)) ?>" alt=""><?php else: ?><span class="user-photo-initial"><?= e($inicialEditar) ?></span><?php endif; ?>
        </span>
        <span class="user-photo-controls">
          <label class="btn btn-outline user-photo-button" for="foto">Seleccionar foto</label>
          <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
          <small id="userPhotoHelp" aria-live="polite">JPG, PNG o WEBP. Máximo 3 MB; se recomienda una imagen cuadrada.</small>
        </span>
      </div>
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
    const photoInput = document.getElementById('foto');
    const photoPreview = document.getElementById('userPhotoPreview');
    const photoHelp = document.getElementById('userPhotoHelp');
    const nameInput = document.getElementById('nombre');
    const defaultPhotoHelp = photoHelp.textContent;
    const deleteDialog = document.getElementById('userDeleteDialog');
    const deleteUserId = document.getElementById('deleteUserId');
    const deleteSummary = document.getElementById('userDeleteSummary');
    const deleteTransfer = document.getElementById('userDeleteTransfer');
    const deleteRecipient = document.getElementById('transferir_a');
    const deleteSubmit = document.getElementById('userDeleteSubmit');
    const deleteCancel = document.getElementById('userDeleteCancel');
    let originalPhotoNodes = Array.from(photoPreview.childNodes, node => node.cloneNode(true));
    let photoPreviewSequence = 0;

    function clearPhotoState() {
      photoPreview.classList.remove('is-loading', 'has-error', 'has-image');
    }

    function restoreOriginalPhoto(message = defaultPhotoHelp) {
      clearPhotoState();
      photoPreview.replaceChildren(...originalPhotoNodes.map(node => node.cloneNode(true)));
      if (photoPreview.querySelector('img')) photoPreview.classList.add('has-image');
      photoHelp.textContent = message;
    }

    function showPhotoInitial(message = defaultPhotoHelp) {
      clearPhotoState();
      photoPreview.replaceChildren();
      const initial = document.createElement('span');
      initial.className = 'user-photo-initial';
      initial.textContent = (nameInput.value.trim().charAt(0) || 'U').toLocaleUpperCase('es');
      photoPreview.appendChild(initial);
      photoHelp.textContent = message;
    }

    photoInput.addEventListener('change', () => {
      const sequence = ++photoPreviewSequence;
      const file = photoInput.files && photoInput.files[0];
      if (!file) {
        restoreOriginalPhoto();
        return;
      }
      const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
      const allowedName = /\.(?:jpe?g|png|webp)$/i.test(file.name);
      if (!allowedTypes.includes(file.type) && !allowedName) {
        photoInput.value = '';
        restoreOriginalPhoto('Elegí una imagen JPG, PNG o WEBP.');
        photoPreview.classList.add('has-error');
        return;
      }
      if (file.size > 3 * 1024 * 1024) {
        photoInput.value = '';
        restoreOriginalPhoto('La foto no puede superar los 3 MB.');
        photoPreview.classList.add('has-error');
        return;
      }

      clearPhotoState();
      photoPreview.classList.add('is-loading');
      photoHelp.textContent = 'Preparando la vista previa…';
      const reader = new FileReader();
      reader.addEventListener('load', () => {
        if (sequence !== photoPreviewSequence) return;
        if (typeof reader.result !== 'string') {
          restoreOriginalPhoto('No pudimos leer esta imagen. Elegí otra.');
          photoPreview.classList.add('has-error');
          return;
        }
        const probe = new Image();
        probe.addEventListener('load', () => {
          if (sequence !== photoPreviewSequence) return;
          const image = document.createElement('img');
          image.src = reader.result;
          image.alt = '';
          photoPreview.replaceChildren(image);
          photoPreview.classList.remove('is-loading', 'has-error');
          photoPreview.classList.add('has-image');
          photoHelp.textContent = file.name + ' · vista previa lista';
        });
        probe.addEventListener('error', () => {
          if (sequence !== photoPreviewSequence) return;
          restoreOriginalPhoto('La imagen no pudo mostrarse. Elegí otra.');
          photoPreview.classList.add('has-error');
        });
        probe.src = reader.result;
      });
      reader.addEventListener('error', () => {
        if (sequence !== photoPreviewSequence) return;
        restoreOriginalPhoto('No pudimos leer esta imagen. Elegí otra.');
        photoPreview.classList.add('has-error');
      });
      reader.readAsDataURL(file);
    });

    nameInput.addEventListener('input', () => {
      if (photoPreview.querySelector('.user-photo-initial')) showPhotoInitial(photoHelp.textContent);
    });

    document.querySelectorAll('.js-delete-user').forEach(button => {
      button.addEventListener('click', () => {
        const userId = button.dataset.userId || '';
        const userName = button.dataset.userName || 'este usuario';
        const newsCount = Number.parseInt(button.dataset.userNews || '0', 10) || 0;
        deleteUserId.value = userId;
        deleteRecipient.value = '';
        Array.from(deleteRecipient.options).forEach(option => {
          option.disabled = option.value === userId;
          option.hidden = option.value === userId;
        });
        const hasNews = newsCount > 0;
        deleteTransfer.hidden = !hasNews;
        deleteRecipient.required = hasNews;
        deleteSummary.textContent = hasNews
          ? `${userName} tiene ${newsCount} ${newsCount === 1 ? 'noticia publicada' : 'noticias publicadas'}. Elegí quién conservará su autoría.`
          : `${userName} no tiene noticias publicadas y puede eliminarse directamente.`;
        deleteSubmit.textContent = hasNews ? 'Transferir y eliminar' : 'Eliminar usuario';
        deleteDialog.showModal();
      });
    });
    deleteCancel.addEventListener('click', () => deleteDialog.close());
    deleteDialog.addEventListener('click', event => {
      if (event.target === deleteDialog) deleteDialog.close();
    });

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
      photoInput.value = '';
      photoPreviewSequence += 1;
      showPhotoInitial();
      originalPhotoNodes = Array.from(photoPreview.childNodes, node => node.cloneNode(true));
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
