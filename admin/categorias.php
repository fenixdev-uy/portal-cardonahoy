<?php
/**
 * Gestión de categorías (listar, crear, editar, eliminar).
 */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('categorias.gestionar');

$pdo = db();

$accion = $_POST['accion'] ?? '';

// ===== Crear / Editar / Eliminar =====
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $nombre = trim((string) ($_POST['nombre'] ?? ''));

    if ($accion === 'guardar') {
        if ($nombre === '') {
            flash('danger', 'El nombre de la categoría es obligatorio.');
        } else {
            $slug = slugify($nombre);
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE categorias SET nombre = ?, slug = ? WHERE id = ?');
                $stmt->execute([$nombre, $slug, $id]);
                flash('success', 'Categoría actualizada correctamente.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO categorias (nombre, slug) VALUES (?, ?)');
                $stmt->execute([$nombre, $slug]);
                flash('success', 'Categoría creada correctamente.');
            }
        }
        redirigir('categorias.php');
    }

    if ($accion === 'eliminar') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', 'Categoría eliminada correctamente.');
        }
        redirigir('categorias.php');
    }
}

$categorias = obtener_categorias();

// Por cada categoría, contar cuántas noticias la usan
$stmt = $pdo->query(
    'SELECT categoria_id, COUNT(*) AS total
       FROM noticias
      WHERE categoria_id IS NOT NULL
      GROUP BY categoria_id'
);
$conteos = [];
foreach ($stmt->fetchAll() as $fila) {
    $conteos[(int) $fila['categoria_id']] = (int) $fila['total'];
}

$categoriaEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    foreach ($categorias as $c) {
        if ((int) $c['id'] === $idEditar) {
            $categoriaEditar = $c;
            break;
        }
    }
}

$titulo = 'Categorías';
$active = 'categorias';

require __DIR__ . '/includes/header.php';
?>

<div class="section-header">
  <h3>Categorías</h3>
</div>

<div class="form-card" style="margin-bottom: 28px;">
  <form method="post" action="categorias.php">
    <?= csrf_input() ?>
    <input type="hidden" name="accion" value="guardar" />
    <input type="hidden" name="id" value="<?= $categoriaEditar ? (int) $categoriaEditar['id'] : 0 ?>" />

    <div class="form-group">
      <label for="nombre">Nombre de la categoría</label>
      <input class="form-control" type="text" id="nombre" name="nombre" value="<?= e($categoriaEditar['nombre'] ?? '') ?>" maxlength="100" required placeholder="Ej.: Política" />
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $categoriaEditar ? 'Actualizar' : 'Agregar' ?></button>
      <?php if ($categoriaEditar): ?>
        <a href="categorias.php" class="btn btn-outline">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="table-wrap">
  <?php if (empty($categorias)): ?>
    <div class="empty">No hay categorías creadas.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Slug</th>
          <th>Noticias</th>
          <th style="width: 130px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categorias as $c): ?>
          <tr>
            <td><strong><?= e($c['nombre']) ?></strong></td>
            <td style="color:#64748b;"><?= e($c['slug']) ?></td>
            <td><?= $conteos[(int) $c['id']] ?? 0 ?></td>
            <td>
              <div class="cell-actions">
                <a class="btn btn-outline btn-sm" href="categorias.php?editar=<?= (int) $c['id'] ?>" aria-label="Editar">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </a>
                <form method="post" action="categorias.php" onsubmit="return confirm('¿Eliminar esta categoría? Las noticias asociadas quedarán sin categoría.');" style="display:inline-flex;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="accion" value="eliminar" />
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                  <button type="submit" class="btn btn-danger btn-sm" aria-label="Borrar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
