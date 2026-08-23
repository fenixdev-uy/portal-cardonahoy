<?php
require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('roles.gestionar');
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $rolId = (int) ($_POST['rol_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT slug FROM roles WHERE id=?');
    $stmt->execute([$rolId]);
    $slug = (string) $stmt->fetchColumn();
    if ($slug === 'admin') {
        flash('danger', 'El perfil Administrador siempre conserva todos los permisos.');
    } elseif ($slug !== '') {
        $seleccionados = array_values(array_unique(array_map('intval', $_POST['permisos'] ?? [])));
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM rol_permisos WHERE rol_id=?')->execute([$rolId]);
            $stmt = $pdo->prepare('INSERT INTO rol_permisos (rol_id,permiso_id) SELECT ?,id FROM permisos WHERE id=?');
            foreach ($seleccionados as $permisoId) $stmt->execute([$rolId,$permisoId]);
            $pdo->commit();
            flash('success', 'Permisos del rol actualizados.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', 'No se pudieron actualizar los permisos.');
        }
    }
    redirigir('roles.php');
}

$roles = $pdo->query('SELECT id,nombre,slug,descripcion FROM roles ORDER BY id')->fetchAll();
$permisos = $pdo->query('SELECT id,clave,nombre,descripcion FROM permisos ORDER BY id')->fetchAll();
$asignaciones = [];
foreach ($pdo->query('SELECT rol_id,permiso_id FROM rol_permisos') as $fila) $asignaciones[(int)$fila['rol_id']][(int)$fila['permiso_id']] = true;
$titulo='Perfiles de roles'; $active='roles';
require __DIR__ . '/includes/header.php';
?>
<div class="section-header"><div><h3>Perfiles de roles</h3><p class="section-copy">Define qué puede hacer cada tipo de usuario. El Administrador mantiene siempre acceso total.</p></div></div>
<div class="roles-grid">
<?php foreach($roles as $rol): $bloqueado=$rol['slug']==='admin'; ?>
  <form method="post" class="form-card role-card">
    <?= csrf_input() ?><input type="hidden" name="rol_id" value="<?= (int)$rol['id'] ?>">
    <div class="role-card-head"><div><h4><?= e($rol['nombre']) ?></h4><p><?= e($rol['descripcion']) ?></p></div><?php if($bloqueado): ?><span class="badge">Protegido</span><?php endif; ?></div>
    <div class="permissions-list"><?php foreach($permisos as $permiso): ?><label class="permission-row"><input type="checkbox" name="permisos[]" value="<?= (int)$permiso['id'] ?>" <?= isset($asignaciones[(int)$rol['id']][(int)$permiso['id']])?'checked':'' ?> <?= $bloqueado?'disabled':'' ?>><span><strong><?= e($permiso['nombre']) ?></strong><small><?= e($permiso['descripcion']) ?></small></span></label><?php endforeach; ?></div>
    <?php if(!$bloqueado): ?><button class="btn btn-primary" type="submit">Guardar permisos</button><?php endif; ?>
  </form>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

