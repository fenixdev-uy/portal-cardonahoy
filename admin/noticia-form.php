<?php
/**
 * Formulario para crear o editar una noticia.
 */

require_once __DIR__ . '/includes/funciones.php';

$usuarioSesion = exigir_login();
$pdo = db();
$categorias = obtener_categorias();
$usuariosFirma = obtener_usuarios_para_noticias();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$editando = $id > 0;
exigir_permiso($editando ? 'noticias.editar' : 'noticias.crear');

$noticia = [
    'id' => 0,
    'categoria_id' => '',
    'usuario_id' => '',
    'titulo' => '',
    'descripcion' => '',
    'youtube' => '',
];

$fotos = [];

if ($editando) {
    $stmt = $pdo->prepare('SELECT * FROM noticias WHERE id = ?');
    $stmt->execute([$id]);
    $existente = $stmt->fetch();

    if (!$existente) {
        flash('danger', 'La noticia no existe.');
        redirigir('index.php');
    }

    $noticia = $existente;
    $fotos = obtener_fotos_noticia($id);
}

$errores = [];

// Procesamiento del formulario
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    // Re-leer id desde el formulario (para crear es 0)
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $editando = $id > 0;

    $categoriaId = $_POST['categoria_id'] ?? '';
    $categoriaId = $categoriaId !== '' ? (int) $categoriaId : null;

    $usuarioId = $_POST['usuario_id'] ?? '';
    $usuarioId = $usuarioId !== '' ? (int) $usuarioId : null;

    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $descripcion = sanitizar_html((string) ($_POST['descripcion'] ?? ''));
    $youtube = trim((string) ($_POST['youtube'] ?? ''));

    // Solo se conserva si es una URL de YouTube válida; en caso contrario queda vacía.
    if ($youtube !== '' && youtube_embed_url($youtube) === '') {
        $youtube = '';
    }

    $noticia = [
        'id' => $id,
        'categoria_id' => $categoriaId ?: '',
        'usuario_id' => $usuarioId ?: '',
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'youtube' => $youtube,
    ];

    if ($titulo === '') {
        $errores[] = 'El título es obligatorio.';
    }
    if ($descripcion === '') {
        $errores[] = 'La descripción es obligatoria.';
    }
    if ($categoriaId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id=?');
        $stmt->execute([$categoriaId]);
        if (!(int) $stmt->fetchColumn()) $errores[] = 'La categoría seleccionada no existe.';
    }
    if ($usuarioId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id=?');
        $stmt->execute([$usuarioId]);
        if (!(int) $stmt->fetchColumn()) $errores[] = 'El usuario seleccionado no existe.';
    }

    // La galería llega como JSON ordenado: [{ "id": N } | { "url": "..." }].
    // Las fotos nuevas ya se subieron por AJAX al momento de elegirlas.
    $fotosJson = json_decode((string) ($_POST['fotos_json'] ?? '[]'), true);
    if (!is_array($fotosJson)) {
        $fotosJson = [];
    }

    if (empty($errores)) {
      $archivosAEliminar = [];
      try {
        $pdo->beginTransaction();
        if ($editando) {
            $stmt = $pdo->prepare('UPDATE noticias SET categoria_id=?, usuario_id=?, titulo=?, descripcion=?, youtube=? WHERE id=?');
            $stmt->execute([$categoriaId, $usuarioId, $titulo, $descripcion, $youtube, $id]);
            if ($stmt->rowCount() === 0) {
                $comprobar = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE id=?');
                $comprobar->execute([$id]);
                if (!(int)$comprobar->fetchColumn()) throw new RuntimeException('La noticia ya no existe.');
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO noticias (categoria_id,usuario_id,titulo,descripcion,youtube) VALUES (?,?,?,?,?)');
            $stmt->execute([$categoriaId, $usuarioId, $titulo, $descripcion, $youtube]);
            $id = (int) $pdo->lastInsertId();
        }

        // ===== Gestión de la galería =====

        $existentes = obtener_fotos_noticia($id);

        // IDs presentes en el formulario.
        $idsEnviados = [];
        foreach ($fotosJson as $item) {
            if (isset($item['id']) && (int) $item['id'] > 0) {
                $idsEnviados[] = (int) $item['id'];
            }
        }

        // 1) Eliminar las fotos existentes que ya no están en la lista.
        foreach ($existentes as $f) {
            if (!in_array((int) $f['id'], $idsEnviados, true)) {
                $archivosAEliminar[] = $f['ruta'];
                $stmtDel = $pdo->prepare('DELETE FROM noticias_fotos WHERE id = ?');
                $stmtDel->execute([(int) $f['id']]);
            }
        }

        // 2) Aplicar el orden (posicion = índice) e insertar las fotos nuevas.
        $pos = 0;
        $stmtUpd = $pdo->prepare('UPDATE noticias_fotos SET posicion = ? WHERE id = ? AND noticia_id = ?');
        $stmtIns = $pdo->prepare('INSERT INTO noticias_fotos (noticia_id, ruta, posicion) VALUES (?, ?, ?)');

        foreach ($fotosJson as $item) {
            if (isset($item['id']) && (int) $item['id'] > 0) {
                $stmtUpd->execute([$pos, (int) $item['id'], $id]);
                $pos++;
            } elseif (isset($item['url']) && is_string($item['url']) && ruta_imagen_subida_valida($item['url'])) {
                $stmtIns->execute([$id, $item['url'], $pos]);
                $pos++;
            }
        }

        $pdo->commit();
        foreach ($archivosAEliminar as $ruta) eliminar_imagen($ruta);

        flash('success', $editando ? 'Noticia actualizada correctamente.' : 'Noticia creada correctamente.');
        redirigir('index.php');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = 'No se pudo guardar la noticia. No se aplicaron cambios parciales.';
      }
    }
}

// Estado de la galería que se renderiza en el formulario.
// - POST con errores de validación: se conserva lo que el usuario ya había cargado.
// - GET (edición): se carga desde la base de datos.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $fotosJsonRender = (string) ($_POST['fotos_json'] ?? '[]');
    $tmp = json_decode($fotosJsonRender, true);
    if (!is_array($tmp)) {
        $fotosJsonRender = '[]';
    } else {
        $fotosJsonRender = json_encode($tmp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
} else {
    $fotosJsonRender = json_encode(
        array_map(fn($f) => ['id' => (int) $f['id'], 'url' => $f['ruta']], $fotos),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

$titulo = $editando ? 'Editar noticia' : 'Nueva noticia';
$active = 'noticias';

require __DIR__ . '/includes/header.php';
?>

<div class="form-card">
  <form method="post" action="noticia-form.php" enctype="multipart/form-data" id="noticiaForm">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int) $noticia['id'] ?>" />
    <?php /* Campo oculto con el HTML del editor, enviado al servidor */ ?>
    <input type="hidden" name="descripcion" id="descripcionInput" value="<?= e($noticia['descripcion']) ?>" />

    <?php if (!empty($errores)): ?>
      <div class="flash">
        <?php foreach ($errores as $error): ?>
          <div class="alert danger"><?= e($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="categoria_id">Categoría</label>
      <select class="form-control" id="categoria_id" name="categoria_id">
        <option value="">— Sin categoría —</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $noticia['categoria_id'] == $c['id'] ? 'selected' : '' ?>>
            <?= e($c['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="usuario_id">Firma de la noticia</label>
      <select class="form-control" id="usuario_id" name="usuario_id">
        <option value="">— Sin firma —</option>
        <?php foreach ($usuariosFirma as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= $noticia['usuario_id'] == $a['id'] ? 'selected' : '' ?>>
            <?= e($a['nombre']) ?> · <?= e($a['rol_nombre']) ?><?= (int)$a['activo']===0 ? ' (inactivo)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="titulo">Título</label>
      <input class="form-control" type="text" id="titulo" name="titulo" value="<?= e($noticia['titulo']) ?>" maxlength="255" required />
    </div>

    <div class="form-group">
      <label for="editorHtml">Descripción</label>
      <div class="tiptap-toolbar" id="toolbar">
        <button type="button" class="toolbar-btn" data-tiptap="bold" title="Negrita (Ctrl+B)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4h8a4 4 0 0 1 0 8H6z"></path><path d="M6 12h9a4 4 0 0 1 0 8H6z"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="italic" title="Cursiva (Ctrl+I)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="4" x2="10" y2="4"></line><line x1="14" y1="20" x2="5" y2="20"></line><line x1="15" y1="4" x2="9" y2="20"></line></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="underline" title="Subrayado">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"></path><line x1="4" y1="21" x2="20" y2="21"></line></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="strike" title="Tachado">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="16" y1="4" x2="8" y2="20"></line><line x1="4" y1="12" x2="20" y2="12"></line></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="highlight" title="Resaltar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 11-6 6v3h9l3-3"></path><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="color" title="Color de texto">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path><circle cx="8.5" cy="7.5" r=".5"></circle><circle cx="13.5" cy="6.5" r=".5"></circle><circle cx="17.5" cy="10.5" r=".5"></circle><circle cx="6.5" cy="12.5" r=".5"></circle></svg>
        </button>

        <span class="toolbar-divider"></span>

        <button type="button" class="toolbar-btn" data-tiptap="alignLeft" title="Alinear a la izquierda">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" y1="6" x2="3" y2="6"></line><line x1="15" y1="12" x2="3" y2="12"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="alignCenter" title="Centrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" y1="6" x2="3" y2="6"></line><line x1="17" y1="12" x2="7" y2="12"></line><line x1="19" y1="18" x2="5" y2="18"></line></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="alignRight" title="Alinear a la derecha">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="12" x2="9" y2="12"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="alignJustify" title="Justificar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="12" x2="3" y2="12"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg>
        </button>

        <span class="toolbar-divider"></span>

        <button type="button" class="toolbar-btn" data-tiptap="subscript" title="Subíndice">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 5 8 8"></path><path d="m12 13 8-8"></path><path d="M20 19h-4c0-1.5.44-2 1.5-2.5S20 15.33 20 14c0-.47-.17-.93-.48-1.29a2.11 2.11 0 0 0-3.02 0c-.32.36-.5.82-.5 1.29"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="superscript" title="Superíndice">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 19 8-8"></path><path d="m12 21 8-8"></path><path d="M20 8h-4c0-1.5.44-2 1.5-2.5S20 3.33 20 2c0-.47-.17-.93-.48-1.29a2.11 2.11 0 0 0-3.02 0c-.32.36-.5.82-.5 1.29"></path></svg>
        </button>

        <span class="toolbar-divider"></span>

        <button type="button" class="toolbar-btn" data-tiptap="h2" title="Título">
          <strong style="font-size: 0.7rem;">H2</strong>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="h3" title="Subtítulo">
          <strong style="font-size: 0.7rem;">H3</strong>
        </button>

        <span class="toolbar-divider"></span>

        <button type="button" class="toolbar-btn" data-tiptap="bulletList" title="Lista con viñetas">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="9" y1="6" x2="20" y2="6"></line><line x1="9" y1="12" x2="20" y2="12"></line><line x1="9" y1="18" x2="20" y2="18"></line><circle cx="4" cy="6" r="1"></circle><circle cx="4" cy="12" r="1"></circle><circle cx="4" cy="18" r="1"></circle></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="orderedList" title="Lista numerada">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="blockquote" title="Cita">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"></path><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="code" title="Código en línea">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="codeBlock" title="Bloque de código">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><path d="M6 8l3 3-3 3"></path><path d="M18 8l-3 3 3 3"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="horizontalRule" title="Línea separadora">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </button>

        <span class="toolbar-divider"></span>

        <button type="button" class="toolbar-btn" data-tiptap="link" title="Enlace">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="image" title="Insertar imagen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
        </button>

        <span class="toolbar-divider"></span>

        <button type="button" class="toolbar-btn" data-tiptap="undo" title="Deshacer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v6h6"></path><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path></svg>
        </button>
        <button type="button" class="toolbar-btn" data-tiptap="redo" title="Rehacer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 7v6h-6"></path><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"></path></svg>
        </button>
      </div>

      <div class="tiptap-editor">
        <div id="editorHtml"></div>
      </div>
    </div>

    <div class="form-group">
      <label for="youtube">YouTube</label>
      <input class="form-control" type="url" id="youtube" name="youtube" value="<?= e($noticia['youtube']) ?>" maxlength="255" placeholder="https://www.youtube.com/watch?v=..." />
      <div class="form-hint">Pegá la URL de un video de YouTube. Se mostrará como reproductor embebido debajo de la descripción.</div>
    </div>

    <div class="form-group">
      <label>Galería de fotos</label>

      <div class="gallery" id="galeria"></div>

      <div class="gallery-upload">
        <button type="button" class="btn btn-outline" id="btnAgregarFotos">＋ Agregar fotos</button>
        <span class="gallery-status" id="galeriaEstado"></span>
      </div>
      <input type="file" id="galeriaInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none;" />

      <div class="form-hint">Cada foto se sube al momento de elegirla y se muestra su miniatura. La primera es la portada. Reordená con las flechas y eliminá con la ×. El orden se guarda al guardar la noticia.</div>

      <input type="hidden" name="fotos_json" id="fotos_json" value="<?= e($fotosJsonRender) ?>" />
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Guardar</button>
      <a href="index.php" class="btn btn-outline">Cancelar</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
  (function () {
    const galeria = document.getElementById('galeria');
    const inputFile = document.getElementById('galeriaInput');
    const btnAgregar = document.getElementById('btnAgregarFotos');
    const estado = document.getElementById('galeriaEstado');
    const jsonInput = document.getElementById('fotos_json');
    const form = document.getElementById('noticiaForm');
    const csrfToken = <?= json_encode(csrf_token()) ?>;

    // Resuelve la URL visible: las rutas guardadas son relativas a landing/,
    // y el panel vive en landing/admin/, por eso se antepone "../".
    function displaySrc(url) {
      if (!url) return '';
      if (/^(https?:)?\/\//i.test(url) || url.startsWith('/')) return url;
      return '../' + url;
    }

    function itemsEnDom() {
      return Array.from(galeria.querySelectorAll('.gallery-item'));
    }

    function actualizarBadges() {
      itemsEnDom().forEach((item, i) => {
        let badge = item.querySelector('.gallery-badge');
        if (i === 0) {
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'gallery-badge';
            badge.textContent = 'Portada';
            item.appendChild(badge);
          }
        } else if (badge) {
          badge.remove();
        }
      });
    }

    function sincronizarJson() {
      const items = itemsEnDom().map((item) => {
        if (item.hasAttribute('data-id')) {
          return { id: parseInt(item.getAttribute('data-id'), 10) };
        }
        return { url: item.getAttribute('data-url') || '' };
      });
      jsonInput.value = JSON.stringify(items);
    }

    function crearItem({ id, url }) {
      const item = document.createElement('div');
      item.className = 'gallery-item';
      if (id) item.setAttribute('data-id', id);
      item.setAttribute('data-url', url || '');

      const img = document.createElement('img');
      img.src = displaySrc(url);
      img.alt = '';

      const grip = document.createElement('span');
      grip.className = 'gallery-grip';
      grip.title = 'Arrastrar para reordenar';
      grip.textContent = '⠿';

      const actions = document.createElement('div');
      actions.className = 'gallery-actions';

      const btnDel = document.createElement('button');
      btnDel.type = 'button';
      btnDel.className = 'gallery-btn gallery-btn-danger';
      btnDel.setAttribute('data-del', '');
      btnDel.title = 'Eliminar';
      btnDel.textContent = '×';

      actions.append(btnDel);
      item.append(img, grip, actions);
      galeria.appendChild(item);
      actualizarBadges();
      sincronizarJson();
      return item;
    }

    // Render inicial desde fotos_json.
    try {
      const inicial = JSON.parse(jsonInput.value || '[]');
      if (Array.isArray(inicial)) {
        inicial.forEach((it) => crearItem(it));
      }
    } catch (e) {
      jsonInput.value = '[]';
    }

    // Arrastrar y soltar para reordenar (SortableJS).
    if (typeof Sortable !== 'undefined') {
      Sortable.create(galeria, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        filter: '.gallery-btn',
        onEnd: () => {
          actualizarBadges();
          sincronizarJson();
        }
      });
    }

    // Sube los archivos de a uno, mostrando la miniatura apenas termina cada uno.
    async function subirArchivos(archivos) {
      btnAgregar.disabled = true;
      for (const archivo of archivos) {
        estado.textContent = 'Subiendo ' + archivo.name + '…';
        const fd = new FormData();
        fd.append('imagen', archivo);
        fd.append('csrf_token', csrfToken);
        try {
          const res = await fetch('upload-imagen.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!res.ok || data.error) {
            alert(data.error || 'No se pudo subir ' + archivo.name);
            continue;
          }
          crearItem({ url: data.url });
        } catch (err) {
          alert('Error de red al subir ' + archivo.name);
        }
      }
      estado.textContent = '';
      btnAgregar.disabled = false;
      inputFile.value = '';
    }

    btnAgregar.addEventListener('click', () => inputFile.click());
    inputFile.addEventListener('change', () => {
      if (inputFile.files && inputFile.files.length) {
        subirArchivos(Array.from(inputFile.files));
      }
    });

    // Eliminar foto.
    galeria.addEventListener('click', async (e) => {
      const btn = e.target.closest('.gallery-btn');
      if (!btn) return;
      const item = btn.closest('.gallery-item');

      if (!btn.hasAttribute('data-del')) return;

      if (!confirm('¿Eliminar esta foto de la galería?')) return;
      // Si es una foto recién subida (sin id), se borra también el archivo del servidor.
      if (!item.hasAttribute('data-id')) {
        const url = item.getAttribute('data-url') || '';
        try {
          await fetch('galeria-borrar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'url=' + encodeURIComponent(url) + '&csrf_token=' + encodeURIComponent(csrfToken)
          });
        } catch (err) {
          /* se ignora: el archivo quedaría huérfano en el servidor */
        }
      }
      item.remove();
      actualizarBadges();
      sincronizarJson();
    });

    // Sincroniza el JSON antes de enviar el formulario.
    form.addEventListener('submit', () => sincronizarJson());
  })();
</script>

<script type="importmap">
{
  "imports": {
    "@tiptap/core": "https://esm.sh/@tiptap/core@3.30.2",
    "@tiptap/starter-kit": "https://esm.sh/@tiptap/starter-kit@3.30.2",
    "@tiptap/extension-link": "https://esm.sh/@tiptap/extension-link@3.30.2",
    "@tiptap/extension-underline": "https://esm.sh/@tiptap/extension-underline@3.30.2",
    "@tiptap/extension-placeholder": "https://esm.sh/@tiptap/extension-placeholder@3.30.2",
    "@tiptap/extension-text-style": "https://esm.sh/@tiptap/extension-text-style@3.30.2",
    "@tiptap/extension-highlight": "https://esm.sh/@tiptap/extension-highlight@3.30.2",
    "@tiptap/extension-text-align": "https://esm.sh/@tiptap/extension-text-align@3.30.2",
    "@tiptap/extension-subscript": "https://esm.sh/@tiptap/extension-subscript@3.30.2",
    "@tiptap/extension-superscript": "https://esm.sh/@tiptap/extension-superscript@3.30.2",
    "@tiptap/extension-image": "https://esm.sh/@tiptap/extension-image@3.30.2"
  }
}
</script>

<script type="module">
  import { Editor } from '@tiptap/core';
  import StarterKit from '@tiptap/starter-kit';
  import Underline from '@tiptap/extension-underline';
  import Link from '@tiptap/extension-link';
  import Placeholder from '@tiptap/extension-placeholder';
  import { TextStyle, Color } from '@tiptap/extension-text-style';
  import Highlight from '@tiptap/extension-highlight';
  import TextAlign from '@tiptap/extension-text-align';
  import Subscript from '@tiptap/extension-subscript';
  import Superscript from '@tiptap/extension-superscript';
  import Image from '@tiptap/extension-image';

  const editorEl = document.getElementById('editorHtml');
  const input = document.getElementById('descripcionInput');
  const form = document.getElementById('noticiaForm');

  // Las imágenes se guardan con una ruta relativa a la raíz de landing/
  // (ej. "uploads/noticias/..."). Como el editor vive en landing/admin/,
  // se antepone "../" solo para que se vean en la vista previa del editor.
  function aRutaEditor(html) {
    return (html || '')
      .replace(/src="uploads\//g, 'src="../uploads/')
      .replace(/src='uploads\//g, "src='../uploads/");
  }
  function aRutaAlmacenada(html) {
    return (html || '')
      .replace(/src="\.\.\/uploads\//g, 'src="uploads/')
      .replace(/src='\.\.\/uploads\//g, "src='uploads/");
  }

  const editor = new Editor({
    element: editorEl,
    extensions: [
      StarterKit,
      Underline,
      Link.configure({
        openOnClick: false,
        autolink: true
      }),
      TextStyle,
      Color,
      Highlight,
      TextAlign.configure({
        types: ['heading', 'paragraph']
      }),
      Subscript,
      Superscript,
      Image.configure({
        allowBase64: false
      }),
      Placeholder.configure({
        placeholder: 'Escribí la descripción de la noticia…'
      })
    ],
    content: aRutaEditor(input.value)
  });

  // Sincroniza el HTML hacia el campo oculto antes de enviar el formulario.
  form.addEventListener('submit', () => {
    input.value = aRutaAlmacenada(editor.getHTML());
  });

  // ===== Toolbar =====
  const toolbar = document.getElementById('toolbar');

  // Selector de color (input nativo oculto).
  const colorPicker = document.createElement('input');
  colorPicker.type = 'color';
  colorPicker.value = '#000000';
  colorPicker.style.display = 'none';
  document.body.appendChild(colorPicker);

  colorPicker.addEventListener('input', () => {
    editor.chain().focus().setColor(colorPicker.value).run();
    setActiveStates();
  });

  // Selector de imagen (input de archivo oculto).
  const imagePicker = document.createElement('input');
  imagePicker.type = 'file';
  imagePicker.accept = 'image/jpeg,image/png,image/webp';
  imagePicker.style.display = 'none';
  document.body.appendChild(imagePicker);

  imagePicker.addEventListener('change', async () => {
    const file = imagePicker.files && imagePicker.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('imagen', file);
    formData.append('csrf_token', <?= json_encode(csrf_token()) ?>);

    try {
      const res = await fetch('upload-imagen.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (!res.ok || data.error) {
        alert(data.error || 'No se pudo subir la imagen.');
        return;
      }
      editor.chain().focus().setImage({ src: '../' + data.url, alt: '' }).run();
    } catch (err) {
      alert('Error de red al subir la imagen.');
    } finally {
      imagePicker.value = '';
    }
  });

  function setActiveStates() {
    toolbar.querySelectorAll('[data-tiptap]').forEach((btn) => {
      const key = btn.getAttribute('data-tiptap');
      let active = false;

      if (key === 'undo') {
        active = editor.can().undo();
      } else if (key === 'redo') {
        active = editor.can().redo();
      } else if (key === 'color') {
        active = !!editor.getAttributes('textStyle').color;
      } else if (key === 'alignLeft') {
        active = editor.isActive({ textAlign: 'left' });
      } else if (key === 'alignCenter') {
        active = editor.isActive({ textAlign: 'center' });
      } else if (key === 'alignRight') {
        active = editor.isActive({ textAlign: 'right' });
      } else if (key === 'alignJustify') {
        active = editor.isActive({ textAlign: 'justify' });
      } else if (key === 'link') {
        active = editor.isActive('link');
      } else if (['bold', 'italic', 'underline', 'strike', 'highlight', 'subscript', 'superscript', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'code', 'codeBlock'].includes(key)) {
        active = editor.isActive(key);
      }

      btn.classList.toggle('is-active', active);

      // Deshabilitar undo/redo cuando no hay historial.
      if (key === 'undo' || key === 'redo') {
        btn.disabled = !active;
        btn.style.opacity = active ? '1' : '0.4';
      }
    });
  }

  toolbar.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-tiptap]');
    if (!btn) return;

    const key = btn.getAttribute('data-tiptap');

    switch (key) {
      case 'bold':          editor.chain().focus().toggleBold().run(); break;
      case 'italic':        editor.chain().focus().toggleItalic().run(); break;
      case 'underline':     editor.chain().focus().toggleUnderline().run(); break;
      case 'strike':        editor.chain().focus().toggleStrike().run(); break;
      case 'highlight':     editor.chain().focus().toggleHighlight().run(); break;
      case 'color':         colorPicker.click(); break;
      case 'subscript':     editor.chain().focus().toggleSubscript().run(); break;
      case 'superscript':   editor.chain().focus().toggleSuperscript().run(); break;
      case 'alignLeft':     editor.chain().focus().setTextAlign('left').run(); break;
      case 'alignCenter':   editor.chain().focus().setTextAlign('center').run(); break;
      case 'alignRight':    editor.chain().focus().setTextAlign('right').run(); break;
      case 'alignJustify':  editor.chain().focus().setTextAlign('justify').run(); break;
      case 'h2':            editor.chain().focus().toggleHeading({ level: 2 }).run(); break;
      case 'h3':            editor.chain().focus().toggleHeading({ level: 3 }).run(); break;
      case 'bulletList':    editor.chain().focus().toggleBulletList().run(); break;
      case 'orderedList':   editor.chain().focus().toggleOrderedList().run(); break;
      case 'blockquote':    editor.chain().focus().toggleBlockquote().run(); break;
      case 'code':          editor.chain().focus().toggleCode().run(); break;
      case 'codeBlock':     editor.chain().focus().toggleCodeBlock().run(); break;
      case 'horizontalRule': editor.chain().focus().setHorizontalRule().run(); break;
      case 'image':         imagePicker.click(); break;
      case 'undo':          editor.chain().focus().undo().run(); break;
      case 'redo':          editor.chain().focus().redo().run(); break;
      case 'link': {
        const previousUrl = editor.getAttributes('link').href || '';
        const url = window.prompt('Ingresá la URL del enlace:', previousUrl);
        if (url === null) return;

        if (url === '') {
          editor.chain().focus().extendMarkRange('link').unsetLink().run();
          return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        break;
      }
    }

    setActiveStates();
  });

  editor.on('transaction', setActiveStates);
  editor.on('selectionUpdate', setActiveStates);

  setActiveStates();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
