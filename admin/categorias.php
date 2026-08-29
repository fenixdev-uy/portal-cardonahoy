<?php
/**
 * Gestión de categorías (listar, crear, editar, eliminar).
 */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('categorias.gestionar');

$pdo = db();

$accion = $_POST['accion'] ?? '';
$errores = [];
$categoriaEditar = null;

// ===== Crear / Editar / Eliminar =====
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $nombre = trim((string) ($_POST['nombre'] ?? ''));

    if ($accion === 'guardar') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $slug = slugify($nombre);

        if ($nombre === '') {
            $errores[] = 'El nombre de la categoría es obligatorio.';
        } elseif ($slug === '') {
            $errores[] = 'El nombre debe contener al menos una letra o un número.';
        }

        if (!$errores) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE slug = ? AND id <> ?');
            $stmt->execute([$slug, $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errores[] = 'Ya existe una categoría con ese nombre.';
            }
        }

        if (!$errores) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE categorias SET nombre = ?, slug = ? WHERE id = ?');
                    $stmt->execute([$nombre, $slug, $id]);
                    flash('success', 'Categoría actualizada correctamente.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categorias (nombre, slug) VALUES (?, ?)');
                    $stmt->execute([$nombre, $slug]);
                    flash('success', 'Categoría creada correctamente.');
                }
                redirigir('categorias.php');
            } catch (PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    $errores[] = 'Ya existe una categoría con ese nombre.';
                } else {
                    throw $e;
                }
            }
        }

        $categoriaEditar = ['id' => $id, 'nombre' => $nombre, 'slug' => $slug];
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
       FROM noticias_categorias
      GROUP BY categoria_id'
);
$conteos = [];
foreach ($stmt->fetchAll() as $fila) {
    $conteos[(int) $fila['categoria_id']] = (int) $fila['total'];
}

if ($categoriaEditar === null && isset($_GET['editar'])) {
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

<div class="users-page-heading">
  <h1>Categorías</h1>
  <p>Organizá las noticias por temática para facilitar la navegación y mantener ordenado el contenido del portal.</p>
</div>

<?php if ($errores): ?><div class="flash"><?php foreach ($errores as $error): ?><div class="alert danger"><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

<section class="users-panel categories-panel">
  <div class="categories-table-toolbar<?= empty($categorias) ? ' is-empty' : '' ?>">
    <?php if (!empty($categorias)): ?>
      <label class="categories-search" for="categoriesSearch">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
        <input type="search" id="categoriesSearch" placeholder="Buscar categorías..." autocomplete="off" aria-describedby="categoriesSearchStatus">
      </label>
    <?php endif; ?>
    <a class="btn btn-primary categories-create-btn js-new-category" href="categorias.php?nuevo=1">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      Nueva categoría
    </a>
    <span class="categories-search-status" id="categoriesSearchStatus" aria-live="polite" aria-label="<?= count($categorias) ?> <?= count($categorias) === 1 ? 'categoría' : 'categorías' ?>"><span id="categoriesSearchCount"><?= count($categorias) ?></span><span class="categories-count-label" id="categoriesSearchLabel"> <?= count($categorias) === 1 ? 'categoría' : 'categorías' ?></span></span>
  </div>

  <?php if (empty($categorias)): ?>
    <div class="empty">No hay categorías creadas.</div>
  <?php else: ?>
    <div class="table-wrap users-table-wrap"><table class="table users-table categories-list">
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
          <tr class="category-row">
            <td data-label="Nombre"><strong><?= e($c['nombre']) ?></strong></td>
            <td data-label="Slug" style="color:#64748b;"><?= e($c['slug']) ?></td>
            <td data-label="Noticias"><?= $conteos[(int) $c['id']] ?? 0 ?></td>
            <td data-label="Acciones">
              <div class="cell-actions">
                <a class="action-icon action-icon-edit js-edit-category" href="categorias.php?editar=<?= (int) $c['id'] ?>" data-id="<?= (int) $c['id'] ?>" data-name="<?= e($c['nombre']) ?>" aria-label="Editar <?= e($c['nombre']) ?>" title="Editar categoría">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </a>
                <form method="post" action="categorias.php" onsubmit="return confirm('¿Eliminar esta categoría? Las noticias asociadas quedarán sin categoría.');">
                  <?= csrf_input() ?>
                  <input type="hidden" name="accion" value="eliminar" />
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>" />
                  <button type="submit" class="action-icon categories-delete-btn" aria-label="Borrar <?= e($c['nombre']) ?>" title="Borrar categoría">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="categories-filter-empty" id="categoriesFilterEmpty" hidden>No encontramos categorías con esa búsqueda.</div>
  <?php endif; ?>
</section>

<div class="drawer-backdrop" id="categoryDrawerBackdrop"></div>
<aside class="drawer category-drawer" id="categoryDrawer" aria-hidden="true" aria-labelledby="categoryDrawerTitle">
  <header class="drawer-header category-drawer-header">
    <div>
      <span class="drawer-title-label" id="categoryDrawerLabel"><?= $categoriaEditar && (int) ($categoriaEditar['id'] ?? 0) > 0 ? 'Editar categoría' : 'Nueva categoría' ?></span>
      <h2 id="categoryDrawerTitle"><?= $categoriaEditar && (int) ($categoriaEditar['id'] ?? 0) > 0 ? 'Actualizar categoría' : 'Agregar categoría' ?></h2>
    </div>
    <button type="button" class="drawer-close" id="categoryDrawerClose" aria-label="Cerrar panel">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </header>
  <div class="drawer-body category-drawer-body">
    <form method="post" action="categorias.php" id="categoryForm">
      <?= csrf_input() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" id="categoryId" value="<?= (int) ($categoriaEditar['id'] ?? 0) ?>">
      <div class="form-group">
        <label for="categoryName">Nombre de la categoría</label>
        <input class="form-control" type="text" id="categoryName" name="nombre" value="<?= e($categoriaEditar['nombre'] ?? '') ?>" maxlength="100" required placeholder="Ej.: Política">
        <div class="form-hint">El slug para la URL se genera automáticamente a partir del nombre.</div>
      </div>
      <div class="form-actions category-form-actions">
        <button type="submit" class="btn btn-primary" id="categorySubmit"><?= $categoriaEditar && (int) ($categoriaEditar['id'] ?? 0) > 0 ? 'Guardar cambios' : 'Crear categoría' ?></button>
        <button type="button" class="btn btn-outline" id="categoryDrawerCancel">Cancelar</button>
      </div>
    </form>
  </div>
</aside>

<script>
  (function () {
    const drawer = document.getElementById('categoryDrawer');
    const backdrop = document.getElementById('categoryDrawerBackdrop');
    const closeButton = document.getElementById('categoryDrawerClose');
    const cancelButton = document.getElementById('categoryDrawerCancel');
    const newButton = document.querySelector('.js-new-category');
    const form = document.getElementById('categoryForm');
    const idInput = document.getElementById('categoryId');
    const nameInput = document.getElementById('categoryName');
    const title = document.getElementById('categoryDrawerTitle');
    const label = document.getElementById('categoryDrawerLabel');
    const submit = document.getElementById('categorySubmit');
    const search = document.getElementById('categoriesSearch');
    const status = document.getElementById('categoriesSearchStatus');
    const statusCount = document.getElementById('categoriesSearchCount');
    const statusLabel = document.getElementById('categoriesSearchLabel');
    const empty = document.getElementById('categoriesFilterEmpty');
    const rows = Array.from(document.querySelectorAll('.category-row'));
    let returnFocus = null;

    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');

    function openDrawer(trigger) {
      returnFocus = trigger || document.activeElement;
      drawer.classList.add('open');
      backdrop.classList.add('show');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      window.setTimeout(() => nameInput.focus(), 250);
    }

    function closeDrawer() {
      drawer.classList.remove('open');
      backdrop.classList.remove('show');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (window.location.search) window.history.replaceState({}, '', 'categorias.php');
      if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
    }

    function prepareNew(event) {
      if (event) event.preventDefault();
      form.reset();
      idInput.value = '0';
      nameInput.value = '';
      label.textContent = 'Nueva categoría';
      title.textContent = 'Agregar categoría';
      submit.textContent = 'Crear categoría';
      openDrawer(event && event.currentTarget);
    }

    function prepareEdit(event) {
      event.preventDefault();
      const button = event.currentTarget;
      form.reset();
      idInput.value = button.dataset.id || '0';
      nameInput.value = button.dataset.name || '';
      label.textContent = 'Editar categoría';
      title.textContent = 'Actualizar categoría';
      submit.textContent = 'Guardar cambios';
      openDrawer(button);
      window.history.replaceState({}, '', button.href);
    }

    function filterRows() {
      const term = normalize(search.value.trim());
      let visible = 0;
      rows.forEach((row) => {
        const matches = !term || normalize(row.textContent || '').includes(term);
        row.hidden = !matches;
        if (matches) visible += 1;
      });
      const total = rows.length;
      const statusText = term ? `${visible} de ${total} categorías` : `${total} ${total === 1 ? 'categoría' : 'categorías'}`;
      statusCount.textContent = String(term ? visible : total);
      statusLabel.textContent = term ? ` de ${total} categorías` : ` ${total === 1 ? 'categoría' : 'categorías'}`;
      status.setAttribute('aria-label', statusText);
      if (empty) empty.hidden = visible !== 0;
    }

    newButton.addEventListener('click', prepareNew);
    document.querySelectorAll('.js-edit-category').forEach((button) => button.addEventListener('click', prepareEdit));
    closeButton.addEventListener('click', closeDrawer);
    cancelButton.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    if (search) search.addEventListener('input', filterRows);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });

    <?php if ($categoriaEditar !== null || isset($_GET['nuevo'])): ?>
    <?= $categoriaEditar === null ? 'prepareNew();' : 'openDrawer();' ?>
    <?php endif; ?>
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
