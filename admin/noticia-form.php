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
    'slug' => '',
    'descripcion' => '',
    'seo_titulo' => '',
    'seo_descripcion' => '',
    'seo_imagen' => '',
    'youtube' => '',
    'youtube_2' => '',
    'youtube_3' => '',
    'audio_1' => '',
    'audio_2' => '',
    'audio_3' => '',
    'portada' => 0,
];

$fotos = [];
$audiosOriginales = [];
$slugOriginal = '';
$seoImagenOriginal = '';
$seoFuenteOriginal = '';
$categoriaIds = [];

if ($editando) {
    $stmt = $pdo->prepare('SELECT * FROM noticias WHERE id = ?');
    $stmt->execute([$id]);
    $existente = $stmt->fetch();

    if (!$existente) {
        flash('danger', 'La noticia no existe.');
        redirigir('index.php');
    }

    $noticia = $existente;
    $slugOriginal = (string) ($existente['slug'] ?? '');
    $seoImagenOriginal = (string) ($existente['seo_imagen'] ?? '');
    $categoriaIds = array_column(obtener_categorias_noticia($id), 'id');
    if (!$categoriaIds && !empty($existente['categoria_id'])) {
        $categoriaIds = [(int) $existente['categoria_id']];
    }
    $fotos = obtener_fotos_noticia($id);
    $seoFuenteOriginal = $seoImagenOriginal !== '' ? $seoImagenOriginal : (string) ($fotos[0]['ruta'] ?? '');
    $audiosOriginales = array_filter([
        (string) ($existente['audio_1'] ?? ''),
        (string) ($existente['audio_2'] ?? ''),
        (string) ($existente['audio_3'] ?? ''),
    ]);
}

$errores = [];

// Procesamiento del formulario
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    // Re-leer id desde el formulario (para crear es 0)
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $editando = $id > 0;

    $categoriaIds = normalizar_ids_categorias($_POST['categoria_ids'] ?? []);
    $categoriaId = $categoriaIds[0] ?? null;

    $usuarioId = $_POST['usuario_id'] ?? '';
    $usuarioId = $usuarioId !== '' ? (int) $usuarioId : null;

    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $descripcion = sanitizar_html((string) ($_POST['descripcion'] ?? ''));
    $slugEnviado = trim((string) ($_POST['slug'] ?? ''));
    if ($slugEnviado === '') {
        $slug = $editando && $slugOriginal !== ''
            ? $slugOriginal
            : generar_slug_noticia_unico($pdo, $titulo, $id);
    } else {
        $slug = normalizar_slug_noticia($slugEnviado);
        if (slug_noticia_en_uso($pdo, $slug, $id)) {
            $errores[] = 'La URL elegida ya pertenece a otra noticia o a una redirección anterior.';
        }
    }

    $seoTituloPersonalizado = ($_POST['seo_titulo_personalizado'] ?? '') === '1';
    $seoDescripcionPersonalizada = ($_POST['seo_descripcion_personalizada'] ?? '') === '1';
    $seoTitulo = $seoTituloPersonalizado ? trim(strip_tags((string) ($_POST['seo_titulo'] ?? ''))) : null;
    $seoDescripcion = $seoDescripcionPersonalizada ? trim(strip_tags((string) ($_POST['seo_descripcion'] ?? ''))) : null;
    $seoImagen = trim((string) ($_POST['seo_imagen'] ?? ''));
    if ($seoTituloPersonalizado && $seoTitulo === '') $errores[] = 'El título SEO personalizado no puede quedar vacío.';
    if ($seoDescripcionPersonalizada && $seoDescripcion === '') $errores[] = 'La descripción SEO personalizada no puede quedar vacía.';
    if ($seoTitulo !== null && mb_strlen($seoTitulo) > 255) $errores[] = 'El título SEO supera los 255 caracteres.';
    if ($seoDescripcion !== null && mb_strlen($seoDescripcion) > 500) $errores[] = 'La descripción SEO supera los 500 caracteres.';
    if ($seoImagen !== '' && !ruta_imagen_subida_valida($seoImagen)) {
        $errores[] = 'La imagen SEO seleccionada no es válida.';
    }
    $videos = [];
    foreach (['youtube', 'youtube_2', 'youtube_3'] as $i => $campo) {
        $valor = trim((string) ($_POST[$campo] ?? ''));
        if ($valor !== '' && youtube_embed_url($valor) === '') {
            $errores[] = 'La URL de YouTube ' . ($i + 1) . ' no es válida.';
        }
        $videos[$campo] = $valor;
    }

    $audios = [];
    foreach (['audio_1', 'audio_2', 'audio_3'] as $i => $campo) {
        $valor = trim((string) ($_POST[$campo] ?? ''));
        $normalizada = normalizar_url_audio($valor);
        if ($valor !== '' && $normalizada === '') {
            $errores[] = 'La URL de audio ' . ($i + 1) . ' no es válida. Usá HTTPS o subí un archivo.';
        }
        $audios[$campo] = $valor === '' ? '' : $normalizada;
    }

    $portada = $editando && isset($_POST['portada']) ? 1 : 0;

    $noticia = [
        'id' => $id,
        'categoria_id' => $categoriaId ?: '',
        'categoria_ids' => $categoriaIds,
        'usuario_id' => $usuarioId ?: '',
        'titulo' => $titulo,
        'slug' => $slug,
        'descripcion' => $descripcion,
        'seo_titulo' => $seoTitulo,
        'seo_descripcion' => $seoDescripcion,
        'seo_imagen' => $seoImagen,
        'youtube' => $videos['youtube'],
        'youtube_2' => $videos['youtube_2'],
        'youtube_3' => $videos['youtube_3'],
        'audio_1' => $audios['audio_1'],
        'audio_2' => $audios['audio_2'],
        'audio_3' => $audios['audio_3'],
        'portada' => $portada,
    ];

    if ($titulo === '') {
        $errores[] = 'El título es obligatorio.';
    }
    if ($descripcion === '') {
        $errores[] = 'La descripción es obligatoria.';
    }
    if ($categoriaIds) {
        $placeholdersCategorias = implode(',', array_fill(0, count($categoriaIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE id IN ($placeholdersCategorias)");
        $stmt->execute($categoriaIds);
        if ((int) $stmt->fetchColumn() !== count($categoriaIds)) {
            $errores[] = 'Una o más categorías seleccionadas no existen.';
        }
    }
    if ($usuarioId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id=? AND activo=1');
        $stmt->execute([$usuarioId]);
        if (!(int) $stmt->fetchColumn()) $errores[] = 'La firma seleccionada no corresponde a un usuario activo.';
    }

    // La galería llega como JSON ordenado: [{ "id": N } | { "url": "..." }].
    // Las fotos nuevas ya se subieron por AJAX al momento de elegirlas.
    $fotosJson = json_decode((string) ($_POST['fotos_json'] ?? '[]'), true);
    if (!is_array($fotosJson)) {
        $fotosJson = [];
    }
    if ($portada === 1 && $fotosJson === []) {
        $errores[] = 'La noticia necesita al menos una foto para mostrarse en el slider de portada.';
    }

    if (empty($errores)) {
      $archivosAEliminar = [];
      $errorImagenSeo = '';
      try {
        $pdo->beginTransaction();
        if ($editando) {
            if ($portada === 1) {
                exigir_cupo_noticia_portada($pdo, $id);
            }
            $stmt = $pdo->prepare(
                'UPDATE noticias
                    SET categoria_id=?, usuario_id=?, titulo=?, slug=?, descripcion=?,
                        seo_titulo=?, seo_descripcion=?, seo_imagen=?,
                        youtube=?, youtube_2=?, youtube_3=?, audio_1=?, audio_2=?, audio_3=?, portada=?
                  WHERE id=?'
            );
            if ($slugOriginal !== '' && $slugOriginal !== $slug) {
                $pdo->prepare('DELETE FROM noticias_slugs_historial WHERE noticia_id=? AND slug=?')->execute([$id, $slug]);
                $pdo->prepare('INSERT INTO noticias_slugs_historial (noticia_id, slug) VALUES (?, ?)')->execute([$id, $slugOriginal]);
            }
            $stmt->execute([
                $categoriaId, $usuarioId, $titulo, $slug, $descripcion,
                $seoTitulo, $seoDescripcion, $seoImagen !== '' ? $seoImagen : null,
                $videos['youtube'], $videos['youtube_2'], $videos['youtube_3'],
                $audios['audio_1'], $audios['audio_2'], $audios['audio_3'], $portada, $id,
            ]);
            if ($stmt->rowCount() === 0) {
                $comprobar = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE id=?');
                $comprobar->execute([$id]);
                if (!(int)$comprobar->fetchColumn()) throw new RuntimeException('La noticia ya no existe.');
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO noticias
                    (categoria_id, usuario_id, titulo, slug, descripcion, seo_titulo, seo_descripcion, seo_imagen,
                     youtube, youtube_2, youtube_3, audio_1, audio_2, audio_3, portada)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $categoriaId, $usuarioId, $titulo, $slug, $descripcion,
                $seoTitulo, $seoDescripcion, $seoImagen !== '' ? $seoImagen : null,
                $videos['youtube'], $videos['youtube_2'], $videos['youtube_3'],
                $audios['audio_1'], $audios['audio_2'], $audios['audio_3'], 0,
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        guardar_categorias_noticia($pdo, $id, $categoriaIds);

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

        $fotosFinales = obtener_fotos_noticia($id);
        $seoFuenteNueva = $seoImagen !== '' ? $seoImagen : (string) ($fotosFinales[0]['ruta'] ?? '');
        if ($seoFuenteNueva !== '') {
            try {
                generar_variantes_imagen_seo($seoFuenteNueva);
            } catch (RuntimeException $e) {
                $errorImagenSeo = $e->getMessage();
                throw $e;
            }
        }

        $pdo->commit();
        foreach ($archivosAEliminar as $ruta) eliminar_imagen($ruta);
        $audiosVigentes = array_filter(array_values($audios));
        foreach ($audiosOriginales as $ruta) {
            if (ruta_audio_subido_valida($ruta) && !in_array($ruta, $audiosVigentes, true)) {
                eliminar_audio($ruta);
            }
        }
        if ($seoImagenOriginal !== '' && $seoImagenOriginal !== $seoImagen
            && ruta_imagen_subida_valida($seoImagenOriginal)
            && !imagen_subida_referenciada($seoImagenOriginal)) {
            eliminar_imagen($seoImagenOriginal);
        }
        if ($seoFuenteOriginal !== '' && $seoFuenteOriginal !== $seoFuenteNueva) {
            eliminar_variantes_imagen_seo($seoFuenteOriginal);
        }

        flash('success', $editando ? 'Noticia actualizada correctamente.' : 'Noticia creada correctamente.');
        redirigir('index.php');
      } catch (DomainException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = $e->getMessage();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = $errorImagenSeo !== ''
            ? 'No se pudo preparar la imagen SEO: ' . $errorImagenSeo
            : 'No se pudo guardar la noticia. No se aplicaron cambios parciales.';
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

$valoresSeoForm = valores_seo_noticia($noticia, $fotos);
$seoImagenPreviewRuta = trim((string) ($noticia['seo_imagen'] ?? ''));
if ($seoImagenPreviewRuta === '' && !empty($fotos[0]['ruta'])) {
    $seoImagenPreviewRuta = (string) $fotos[0]['ruta'];
}
$seoImagenPreviewUrl = $seoImagenPreviewRuta !== '' ? $valoresSeoForm['imagen'] : '';
$seoTituloPersonalizadoForm = trim((string) ($noticia['seo_titulo'] ?? '')) !== '';
$seoDescripcionPersonalizadaForm = trim((string) ($noticia['seo_descripcion'] ?? '')) !== '';
$seoPersonalizadoForm = $seoTituloPersonalizadoForm
    || $seoDescripcionPersonalizadaForm
    || trim((string) ($noticia['seo_imagen'] ?? '')) !== '';
$slugForm = trim((string) ($noticia['slug'] ?? ''));
if ($slugForm === '' && trim((string) ($noticia['titulo'] ?? '')) !== '') {
    $slugForm = normalizar_slug_noticia((string) $noticia['titulo']);
}

$titulo = $editando ? 'Editar noticia' : 'Nueva noticia';
$active = 'noticias';

require __DIR__ . '/includes/header.php';
?>

<div class="form-card noticia-form-card">
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

    <div class="noticia-form-grid">
      <div class="form-group category-multiselect" data-category-multiselect>
        <label id="categoriasLabel">Categorías</label>
        <details>
          <summary class="form-control" aria-labelledby="categoriasLabel">
            <span data-category-summary><?= $categoriaIds ? e(count($categoriaIds) === 1 ? '1 categoría seleccionada' : count($categoriaIds) . ' categorías seleccionadas') : '— Sin categoría —' ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
          </summary>
          <div class="category-multiselect-options">
            <?php foreach ($categorias as $c): ?>
              <label>
                <input type="checkbox" name="categoria_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], array_map('intval', $categoriaIds), true) ? 'checked' : '' ?>>
                <span><?= e($c['nombre']) ?></span>
              </label>
            <?php endforeach; ?>
            <?php if (!$categorias): ?><span class="category-multiselect-empty">No hay categorías creadas.</span><?php endif; ?>
          </div>
        </details>
        <div class="form-hint">Podés marcar varias. La primera seleccionada se conserva como categoría principal para compatibilidad.</div>
      </div>

      <div class="form-group">
        <label for="usuario_id">Firma de la noticia</label>
        <select class="form-control" id="usuario_id" name="usuario_id">
          <option value="">— Sin firma —</option>
          <?php foreach ($usuariosFirma as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= $noticia['usuario_id'] == $a['id'] ? 'selected' : '' ?>>
              <?= e($a['nombre']) ?> · <?= e($a['rol_nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="titulo">Título</label>
      <input class="form-control" type="text" id="titulo" name="titulo" value="<?= e($noticia['titulo']) ?>" maxlength="255" required />
    </div>

    <?php if ($editando): ?>
      <div class="form-group news-cover-form-field">
        <span class="news-cover-field-label">Portada</span>
        <label class="news-cover-form-switch">
          <input type="checkbox" name="portada" value="1" role="switch" <?= (int) ($noticia['portada'] ?? 0) === 1 ? 'checked' : '' ?>>
          <span class="news-cover-switch-track" aria-hidden="true"><span></span></span>
          <span>
            <strong>Mostrar en el slider</strong>
            <small>Al activarla, esta noticia aparecerá en el encabezado. Máximo <?= PORTADA_NOTICIAS_LIMITE ?> noticias.</small>
          </span>
        </label>
      </div>
    <?php endif; ?>

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

        <button type="button" class="toolbar-btn toolbar-btn-ai" id="btnMejorarIa"
                title="Crear una noticia con IA" aria-label="Crear una noticia con IA">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3l1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3z"></path>
            <path d="M18.5 13l.75 2.25L21.5 16l-2.25.75L18.5 19l-.75-2.25L15.5 16l2.25-.75L18.5 13z"></path>
            <path d="M5 3.5l.55 1.45L7 5.5l-1.45.55L5 7.5l-.55-1.45L3 5.5l1.45-.55L5 3.5z"></path>
          </svg>
          <span>Crear con IA</span>
        </button>
      </div>

      <div class="tiptap-editor">
        <div id="editorHtml"></div>
      </div>
      <p class="ia-editor-status" id="iaEditorStatus" aria-live="polite"></p>
    </div>

    <div class="media-fields-grid">
      <section class="media-fields-card" aria-labelledby="audiosHeading">
        <button class="media-fields-head media-fields-toggle" type="button" data-media-toggle aria-expanded="false" aria-controls="audiosFields">
          <span class="media-fields-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
          </span>
          <span class="media-fields-copy"><span class="media-fields-title" id="audiosHeading">Audios</span><span class="media-fields-description">URL HTTPS o archivo MP3, M4A, OGG o WAV.</span></span>
          <span class="media-fields-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
        </button>
        <div class="media-fields-body" id="audiosFields" hidden>
<?php for ($i = 1; $i <= 3; $i++): $campoAudio = 'audio_' . $i; ?>
        <div class="form-group media-url-group">
          <label for="<?= $campoAudio ?>">Audio <?= $i ?></label>
          <div class="media-url-row">
            <input class="form-control" type="text" id="<?= $campoAudio ?>" name="<?= $campoAudio ?>" value="<?= e($noticia[$campoAudio] ?? '') ?>" maxlength="500" placeholder="https://... o subí un archivo" />
            <button class="media-upload-btn" type="button" data-audio-upload="<?= $i ?>" aria-label="Subir audio <?= $i ?>" title="Subir audio <?= $i ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"></path><polyline points="7 9 12 4 17 9"></polyline><path d="M20 15v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4"></path></svg>
            </button>
            <input type="file" data-audio-input="<?= $i ?>" accept="audio/mpeg,audio/mp4,audio/ogg,audio/wav,.mp3,.m4a,.ogg,.wav" hidden />
          </div>
          <span class="media-upload-status" data-audio-status="<?= $i ?>" aria-live="polite"></span>
        </div>
<?php endfor; ?>
        </div>
      </section>

      <section class="media-fields-card" aria-labelledby="videosHeading">
        <button class="media-fields-head media-fields-toggle" type="button" data-media-toggle aria-expanded="false" aria-controls="videosFields">
          <span class="media-fields-icon media-fields-icon-video" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
          </span>
          <span class="media-fields-copy"><span class="media-fields-title" id="videosHeading">Videos de YouTube</span><span class="media-fields-description">Podés agregar hasta tres enlaces.</span></span>
          <span class="media-fields-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
        </button>
        <div class="media-fields-body" id="videosFields" hidden>
<?php foreach (['youtube', 'youtube_2', 'youtube_3'] as $i => $campoVideo): ?>
        <div class="form-group media-url-group">
          <label for="<?= $campoVideo ?>">YouTube <?= $i + 1 ?></label>
          <input class="form-control" type="url" id="<?= $campoVideo ?>" name="<?= $campoVideo ?>" value="<?= e($noticia[$campoVideo] ?? '') ?>" maxlength="255" placeholder="https://www.youtube.com/watch?v=..." />
        </div>
<?php endforeach; ?>
        </div>
      </section>
    </div>

    <div class="media-fields-grid seo-preview-grid" data-seo-root
         data-public-base="<?= e(url_base_portal()) ?>"
         data-seo-current-source="<?= e($seoImagenPreviewRuta) ?>"
         data-seo-current-preview="<?= e($seoImagenPreviewUrl) ?>"
         data-csrf="<?= e(csrf_token()) ?>" data-editing="<?= $editando ? '1' : '0' ?>">
      <section class="media-fields-card seo-fields-card" aria-labelledby="seoHeading">
        <button class="media-fields-head media-fields-toggle" type="button" data-media-toggle aria-expanded="false" aria-controls="seoFields">
          <span class="media-fields-icon media-fields-icon-seo" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path><path d="M8 11h6"></path><path d="M11 8v6"></path></svg>
          </span>
          <span class="media-fields-copy"><span class="media-fields-title" id="seoHeading">SEO</span><span class="media-fields-description">Datos automáticos con personalización opcional.</span></span>
          <span class="seo-mode-badge<?= $seoPersonalizadoForm ? ' is-custom' : '' ?>" id="seoModeBadge"><?= $seoPersonalizadoForm ? 'Personalizado' : 'Automático' ?></span>
          <span class="media-fields-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
        </button>
        <div class="media-fields-body seo-fields-body" id="seoFields" hidden>
          <div class="seo-field-group">
            <div class="seo-field-heading"><label for="slug">URL de la noticia</label><span>Permalink estable</span></div>
            <div class="seo-url-field"><span><?= e(url_base_portal()) ?>/noticia/</span><input type="text" id="slug" name="slug" value="<?= e($slugForm) ?>" maxlength="190" autocomplete="off" /></div>
            <p class="seo-field-help">Si la cambiás después de publicar, la dirección anterior redirigirá automáticamente.</p>
          </div>

          <div class="seo-field-group" data-seo-field="titulo">
            <div class="seo-field-heading"><label for="seo_titulo">Título SEO</label><button type="button" class="seo-auto-action" data-seo-auto="titulo"><?= $seoTituloPersonalizadoForm ? 'Volver a automático' : 'Personalizar' ?></button></div>
            <input type="hidden" id="seo_titulo_personalizado" name="seo_titulo_personalizado" value="<?= $seoTituloPersonalizadoForm ? '1' : '0' ?>" />
            <input class="form-control" type="text" id="seo_titulo" name="seo_titulo" value="<?= e($seoTituloPersonalizadoForm ? $noticia['seo_titulo'] : $valoresSeoForm['titulo']) ?>" maxlength="255" <?= $seoTituloPersonalizadoForm ? '' : 'readonly ' ?>/>
            <span class="seo-counter" data-seo-counter="titulo">0 caracteres</span>
          </div>

          <div class="seo-field-group" data-seo-field="descripcion">
            <div class="seo-field-heading"><label for="seo_descripcion">Descripción SEO</label><button type="button" class="seo-auto-action" data-seo-auto="descripcion"><?= $seoDescripcionPersonalizadaForm ? 'Volver a automático' : 'Personalizar' ?></button></div>
            <input type="hidden" id="seo_descripcion_personalizada" name="seo_descripcion_personalizada" value="<?= $seoDescripcionPersonalizadaForm ? '1' : '0' ?>" />
            <textarea class="form-control seo-description-input" id="seo_descripcion" name="seo_descripcion" maxlength="500" <?= $seoDescripcionPersonalizadaForm ? '' : 'readonly ' ?>><?= e($seoDescripcionPersonalizadaForm ? $noticia['seo_descripcion'] : $valoresSeoForm['descripcion']) ?></textarea>
            <span class="seo-counter" data-seo-counter="descripcion">0 caracteres</span>
          </div>

          <div class="seo-field-group">
            <div class="seo-field-heading"><label for="seo_imagen">Imagen SEO/social</label><span>Se genera a 1200 × 630 px</span></div>
            <div class="seo-image-controls">
              <select class="form-control" id="seo_imagen" name="seo_imagen" data-current-value="<?= e((string) ($noticia['seo_imagen'] ?? '')) ?>">
                <option value="">Automática — usar portada</option>
<?php foreach ($fotos as $indiceFotoSeo => $fotoSeo): ?>
                <option value="<?= e($fotoSeo['ruta']) ?>" <?= (string) ($noticia['seo_imagen'] ?? '') === (string) $fotoSeo['ruta'] ? 'selected' : '' ?>>Foto <?= $indiceFotoSeo + 1 ?><?= $indiceFotoSeo === 0 ? ' — portada' : '' ?></option>
<?php endforeach; ?>
<?php if (($noticia['seo_imagen'] ?? '') !== '' && !in_array((string) $noticia['seo_imagen'], array_column($fotos, 'ruta'), true)): ?>
                <option value="<?= e((string) $noticia['seo_imagen']) ?>" selected>Imagen SEO subida</option>
<?php endif; ?>
              </select>
              <button class="media-upload-btn seo-image-upload" type="button" id="seoImageUpload" aria-label="Subir imagen SEO" title="Subir imagen SEO">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"></path><polyline points="7 9 12 4 17 9"></polyline><path d="M20 15v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4"></path></svg>
              </button>
              <input type="file" id="seoImageInput" accept="image/jpeg,image/png,image/webp" hidden />
            </div>
            <span class="media-upload-status" id="seoImageStatus" aria-live="polite"></span>
            <p class="seo-field-help">La imagen se recorta al centro y se optimiza automáticamente sin modificar la foto original.</p>
          </div>
        </div>
      </section>

      <section class="media-fields-card seo-preview-card" aria-labelledby="vistaPreviaHeading">
        <button class="media-fields-head media-fields-toggle" type="button" data-media-toggle aria-expanded="true" aria-controls="seoPreviewFields">
          <span class="media-fields-icon media-fields-icon-preview" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </span>
          <span class="media-fields-copy"><span class="media-fields-title" id="vistaPreviaHeading">Vista Previa</span><span class="media-fields-description">Resultado efectivo antes de guardar.</span></span>
          <span class="media-fields-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
        </button>
        <div class="media-fields-body seo-preview-body" id="seoPreviewFields">
          <div class="seo-preview-tabs" role="tablist" aria-label="Tipo de vista previa">
            <button type="button" role="tab" aria-selected="true" data-seo-tab="social">Al compartir</button>
            <button type="button" role="tab" aria-selected="false" data-seo-tab="google">En Google</button>
          </div>
          <div class="seo-social-preview" data-seo-panel="social">
            <div class="seo-social-image<?= $seoImagenPreviewUrl === '' ? ' is-empty' : '' ?>" id="seoPreviewImageFrame">
              <img id="seoPreviewImage"<?= $seoImagenPreviewUrl !== '' ? ' src="' . e($seoImagenPreviewUrl) . '"' : '' ?> alt=""<?= $seoImagenPreviewUrl === '' ? ' hidden' : '' ?> />
              <span class="seo-social-image-empty" id="seoPreviewImageEmpty">Sin imagen</span>
            </div>
            <div class="seo-social-copy"><span id="seoPreviewDomain"><?= e((string) parse_url(url_base_portal(), PHP_URL_HOST)) ?></span><strong id="seoPreviewTitle"><?= e($valoresSeoForm['titulo']) ?></strong><p id="seoPreviewDescription"><?= e($valoresSeoForm['descripcion']) ?></p><small id="seoPreviewUrl"><?= e($valoresSeoForm['url']) ?></small></div>
          </div>
          <div class="seo-google-preview" data-seo-panel="google" hidden>
            <span id="seoGoogleUrl"><?= e($valoresSeoForm['url']) ?></span>
            <strong id="seoGoogleTitle"><?= e($valoresSeoForm['titulo']) ?></strong>
            <p id="seoGoogleDescription"><?= e($valoresSeoForm['descripcion']) ?></p>
          </div>
          <p class="seo-preview-note">La vista es orientativa: cada plataforma puede recortar imágenes o textos de forma diferente.</p>
        </div>
      </section>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Guardar</button>
      <a href="index.php" class="btn btn-outline">Cancelar</a>
    </div>
  </form>
</div>

<div class="ia-preview-overlay" id="iaPreviewOverlay" hidden>
  <section class="ia-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="iaPreviewTitle" aria-describedby="iaPreviewDescription">
    <header class="ia-preview-header">
      <div>
        <span class="ia-preview-eyebrow">Asistente editorial</span>
        <h2 id="iaPreviewTitle" aria-label="Crear noticia con IA ✨">
          <span>Crear noticia con IA</span>
          <svg class="ia-title-sparkles" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.35 4.65L18 8l-4.65 1.35L12 14l-1.35-4.65L6 8l4.65-1.35L12 2z"></path><path d="M19 14l.75 2.25L22 17l-2.25.75L19 20l-.75-2.25L16 17l2.25-.75L19 14z"></path></svg>
        </h2>
        <p id="iaPreviewDescription">Sumá información e indicaciones y revisá el resultado.</p>
      </div>
      <button type="button" class="ia-preview-close" id="iaPreviewClose" aria-label="Cerrar vista previa">×</button>
    </header>
    <div class="ia-preview-grid">
      <section class="ia-preview-column ia-source-column">
        <div class="ia-content-mode">
          <label for="iaContentMode">Contenido</label>
          <select id="iaContentMode" class="form-control">
            <option value="url">Extraer de URL</option>
            <option value="texto" selected>Pegar contenido</option>
          </select>
        </div>
        <div class="ia-section-heading">
          <div>
            <h3 id="iaSourceTitle">Información base</h3>
            <p id="iaSourceHelp">Pegá texto crudo, apuntes o fragmentos de otras fuentes.</p>
          </div>
        </div>
        <textarea class="ia-source-input" id="iaSourceInput" maxlength="50000" aria-describedby="iaSourceHelp" placeholder="Pegá acá toda la información disponible para construir la noticia…"></textarea>
        <label class="ia-instructions-label" for="iaInstructionsInput">Indicaciones opcionales</label>
        <textarea class="ia-instructions-input" id="iaInstructionsInput" maxlength="2000" placeholder="Ej.: priorizar el impacto local, usar un tono institucional o destacar determinado aspecto."></textarea>
        <div class="ia-source-actions">
          <span class="ia-generation-status" id="iaGenerationStatus" aria-live="polite"></span>
          <button type="button" class="btn btn-primary ia-action-btn" id="iaGenerate">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.35 4.65L18 8l-4.65 1.35L12 14l-1.35-4.65L6 8l4.65-1.35L12 2z"></path><path d="M19 14l.75 2.25L22 17l-2.25.75L19 20l-.75-2.25L16 17l2.25-.75L19 14z"></path></svg>
            <span id="iaGenerateLabel">Crear noticia</span>
          </button>
        </div>
      </section>
      <section class="ia-preview-column ia-preview-column-proposal" id="iaProposalSection" hidden>
        <div class="ia-section-heading">
          <div>
            <h3>Noticia generada</h3>
            <p>Revisala antes de agregarla al editor.</p>
          </div>
        </div>
        <div class="ia-ideal-title" id="iaIdealTitleBox" hidden>
          <label for="iaIdealTitle">Título ideal</label>
          <div class="ia-ideal-title-row">
            <textarea class="form-control" id="iaIdealTitle" maxlength="255" rows="2"></textarea>
            <button type="button" class="btn btn-outline" id="iaUseIdealTitle">Usar este título</button>
          </div>
          <span class="ia-ideal-title-status" id="iaIdealTitleStatus" aria-live="polite"></span>
        </div>
        <div class="ia-preview-content" id="iaPreviewProposal"></div>
      </section>
    </div>
    <footer class="ia-preview-actions">
      <button type="button" class="btn btn-outline" id="iaPreviewCancel">Cancelar</button>
      <div class="ia-preview-actions-main">
        <button type="button" class="btn btn-outline ia-action-btn" id="iaRegenerate" hidden>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.35 4.65L18 8l-4.65 1.35L12 14l-1.35-4.65L6 8l4.65-1.35L12 2z"></path><path d="M19 14l.75 2.25L22 17l-2.25.75L19 20l-.75-2.25L16 17l2.25-.75L19 14z"></path></svg>
          <span>Crear otra versión</span>
        </button>
        <button type="button" class="btn btn-primary ia-action-btn" id="iaPreviewApply" disabled>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.35 4.65L18 8l-4.65 1.35L12 14l-1.35-4.65L6 8l4.65-1.35L12 2z"></path><path d="M19 14l.75 2.25L22 17l-2.25.75L19 20l-.75-2.25L16 17l2.25-.75L19 14z"></path></svg>
          <span>Agregar al editor</span>
        </button>
      </div>
    </footer>
  </section>
</div>

<script>
(() => {
  const root = document.querySelector('[data-category-multiselect]');
  if (!root) return;
  const details = root.querySelector('details');
  const summary = root.querySelector('[data-category-summary]');
  const checks = Array.from(root.querySelectorAll('input[type="checkbox"]'));

  const actualizarResumen = () => {
    const activas = checks.filter((checkbox) => checkbox.checked);
    if (activas.length === 0) {
      summary.textContent = '— Sin categoría —';
    } else if (activas.length <= 2) {
      summary.textContent = activas.map((checkbox) => checkbox.nextElementSibling.textContent.trim()).join(' · ');
    } else {
      summary.textContent = `${activas.length} categorías seleccionadas`;
    }
  };

  checks.forEach((checkbox) => checkbox.addEventListener('change', actualizarResumen));
  document.addEventListener('click', (event) => {
    if (details.open && !root.contains(event.target)) details.open = false;
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && details.open) {
      details.open = false;
      details.querySelector('summary').focus();
    }
  });
  actualizarResumen();
})();
</script>
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
      document.dispatchEvent(new CustomEvent('noticia-galeria-update', { detail: { items } }));
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
      const urlEliminada = item.getAttribute('data-url') || '';
      const selectorSeo = document.getElementById('seo_imagen');
      if (selectorSeo && selectorSeo.value === urlEliminada) {
        selectorSeo.value = '';
        selectorSeo.dispatchEvent(new Event('change', { bubbles: true }));
      }
      // Si es una foto recién subida (sin id), se borra también el archivo del servidor.
      if (!item.hasAttribute('data-id')) {
        try {
          await fetch('galeria-borrar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'url=' + encodeURIComponent(urlEliminada) + '&csrf_token=' + encodeURIComponent(csrfToken)
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

<script>
  (function () {
    document.querySelectorAll('[data-media-toggle]').forEach((toggle) => {
      const body = document.getElementById(toggle.getAttribute('aria-controls'));
      if (!body) return;

      toggle.addEventListener('click', () => {
        const expandir = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', expandir ? 'true' : 'false');
        body.hidden = !expandir;
        toggle.closest('.media-fields-card')?.classList.toggle('is-expanded', expandir);
      });
    });
  })();
</script>

<script>
  (function () {
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const maxAudioBytes = 25 * 1024 * 1024;

    document.querySelectorAll('[data-audio-upload]').forEach((button) => {
      const slot = button.getAttribute('data-audio-upload');
      const picker = document.querySelector('[data-audio-input="' + slot + '"]');
      const target = document.getElementById('audio_' + slot);
      const status = document.querySelector('[data-audio-status="' + slot + '"]');
      if (!picker || !target || !status) return;

      button.addEventListener('click', () => picker.click());
      picker.addEventListener('change', async () => {
        const file = picker.files && picker.files[0];
        if (!file) return;
        if (file.size > maxAudioBytes) {
          picker.value = '';
          status.textContent = '';
          alert('El audio debe pesar como máximo 25 MB.');
          return;
        }

        button.disabled = true;
        status.textContent = 'Subiendo ' + file.name + '…';
        const data = new FormData();
        data.append('audio', file);
        data.append('csrf_token', csrfToken);

        try {
          const response = await fetch('upload-audio.php', { method: 'POST', body: data });
          const result = await response.json();
          if (!response.ok || result.error) throw new Error(result.error || 'No se pudo subir el audio.');
          target.value = result.url;
          status.textContent = 'Audio cargado';
        } catch (error) {
          status.textContent = '';
          alert(error.message || 'No se pudo subir el audio.');
        } finally {
          button.disabled = false;
          picker.value = '';
        }
      });
    });
  })();
</script>

<script src="assets/seo-noticia.js?v=<?= (int) @filemtime(__DIR__ . '/assets/seo-noticia.js') ?>"></script>

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
  const btnMejorarIa = document.getElementById('btnMejorarIa');
  const iaStatus = document.getElementById('iaEditorStatus');
  const iaOverlay = document.getElementById('iaPreviewOverlay');
  const iaContentMode = document.getElementById('iaContentMode');
  const iaSourceInput = document.getElementById('iaSourceInput');
  const iaSourceTitle = document.getElementById('iaSourceTitle');
  const iaSourceHelp = document.getElementById('iaSourceHelp');
  const iaInstructionsInput = document.getElementById('iaInstructionsInput');
  const iaGenerate = document.getElementById('iaGenerate');
  const iaGenerateLabel = document.getElementById('iaGenerateLabel');
  const iaGenerationStatus = document.getElementById('iaGenerationStatus');
  const iaProposalSection = document.getElementById('iaProposalSection');
  const iaProposal = document.getElementById('iaPreviewProposal');
  const iaIdealTitleBox = document.getElementById('iaIdealTitleBox');
  const iaIdealTitle = document.getElementById('iaIdealTitle');
  const iaUseIdealTitle = document.getElementById('iaUseIdealTitle');
  const iaIdealTitleStatus = document.getElementById('iaIdealTitleStatus');
  const titleInput = document.getElementById('titulo');
  const iaClose = document.getElementById('iaPreviewClose');
  const iaCancel = document.getElementById('iaPreviewCancel');
  const iaRegenerate = document.getElementById('iaRegenerate');
  const iaApply = document.getElementById('iaPreviewApply');
  const csrfTokenIa = <?= json_encode(csrf_token()) ?>;
  const iaInstructionsStorageKey = <?= json_encode('portal_noticias_ia_instrucciones_' . PORTAL_INSTANCE_ID) ?>;
  let propuestaIa = '';
  let iaEnProceso = false;
  let modoContenidoAnterior = iaContentMode.value;
  const contenidoPorModo = { texto: '', url: '' };

  try {
    iaInstructionsInput.value = window.localStorage.getItem(iaInstructionsStorageKey) || '';
  } catch (error) {
    // El asistente sigue funcionando si el navegador bloquea el almacenamiento local.
  }

  iaInstructionsInput.addEventListener('input', () => {
    try {
      const instrucciones = iaInstructionsInput.value;
      if (instrucciones) {
        window.localStorage.setItem(iaInstructionsStorageKey, instrucciones);
      } else {
        window.localStorage.removeItem(iaInstructionsStorageKey);
      }
    } catch (error) {
      // No interrumpir la edición si el almacenamiento local no está disponible.
    }
  });

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
      StarterKit.configure({
        link: false,
        underline: false
      }),
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

  function informarContenidoSeo() {
    document.dispatchEvent(new CustomEvent('noticia-editor-update', {
      detail: { texto: editor.getText().replace(/\s+/g, ' ').trim() }
    }));
  }
  editor.on('update', informarContenidoSeo);
  informarContenidoSeo();

  function textoVisibleEditor() {
    return editor.getText().replace(/\s+/g, ' ').trim();
  }

  function mostrarEstadoIa(mensaje, tipo = '') {
    iaStatus.textContent = mensaje;
    iaStatus.className = 'ia-editor-status' + (tipo ? ' is-' + tipo : '');
  }

  function cambiarEstadoGeneracion(mensaje, tipo = '') {
    iaGenerationStatus.textContent = mensaje;
    iaGenerationStatus.className = 'ia-generation-status' + (tipo ? ' is-' + tipo : '');
  }

  function sincronizarModoContenido() {
    contenidoPorModo[modoContenidoAnterior] = iaSourceInput.value;
    const esUrl = iaContentMode.value === 'url';
    iaSourceInput.value = contenidoPorModo[iaContentMode.value] || '';
    modoContenidoAnterior = iaContentMode.value;
    iaSourceTitle.textContent = esUrl ? 'URL de la noticia' : 'Información base';
    iaSourceHelp.textContent = esUrl
      ? 'Ingresá el enlace completo. Algunos sitios pueden impedir la extracción automática.'
      : 'Pegá texto crudo, apuntes o fragmentos de otras fuentes.';
    iaSourceInput.placeholder = esUrl
      ? 'https://sitio.com/noticia…'
      : 'Pegá acá toda la información disponible para construir la noticia…';
    iaSourceInput.maxLength = esUrl ? 2048 : 50000;
    iaSourceInput.inputMode = esUrl ? 'url' : 'text';
    iaSourceInput.classList.toggle('is-url', esUrl);
    cambiarEstadoGeneracion('');
  }

  function cerrarPreviewIa() {
    iaOverlay.hidden = true;
    document.body.classList.remove('ia-preview-open');
    propuestaIa = '';
    iaProposal.innerHTML = '';
    iaIdealTitle.value = '';
    iaIdealTitleStatus.textContent = '';
    iaIdealTitleBox.hidden = true;
    iaProposalSection.hidden = true;
    iaRegenerate.hidden = true;
    iaApply.disabled = true;
    cambiarEstadoGeneracion('');
    btnMejorarIa.focus();
  }

  function abrirAsistenteIa() {
    if (iaContentMode.value === 'texto' && textoVisibleEditor()) {
      iaSourceInput.value = editor.getText();
    }
    iaOverlay.hidden = false;
    document.body.classList.add('ia-preview-open');
    window.setTimeout(() => iaSourceInput.focus(), 320);
  }

  async function generarConIa(otraVersion = false) {
    if (iaEnProceso) return;
    const contenido = iaSourceInput.value.trim();
    const instrucciones = iaInstructionsInput.value.trim();
    const modoContenido = iaContentMode.value;
    const contieneUrl = /(?:https?:\/\/|www\.)\S+/i.test(contenido + '\n' + instrucciones);
    if (modoContenido === 'texto' && contieneUrl) {
      cambiarEstadoGeneracion('Para garantizar exactitud, copiá y pegá el contenido relevante del enlace en Información base.', 'error');
      iaSourceInput.focus();
      return;
    }
    if (modoContenido === 'url') {
      try {
        const url = new URL(contenido);
        if (!['http:', 'https:'].includes(url.protocol)) throw new Error();
      } catch (error) {
        cambiarEstadoGeneracion('Ingresá una URL válida y completa, por ejemplo https://sitio.com/noticia.', 'error');
        iaSourceInput.focus();
        return;
      }
    } else if (contenido.length < 30) {
      cambiarEstadoGeneracion('Pegá información suficiente para comenzar.', 'error');
      iaSourceInput.focus();
      return;
    }

    iaEnProceso = true;
    iaGenerate.disabled = true;
    iaRegenerate.disabled = true;
    iaApply.disabled = true;
    iaGenerateLabel.textContent = otraVersion ? 'Creando otra…' : 'Creando…';
    cambiarEstadoGeneracion(modoContenido === 'url'
      ? 'Extrayendo el contenido y redactando la noticia…'
      : 'Redactando una noticia profesional…', 'loading');

    const body = new URLSearchParams({
      contenido,
      modo_contenido: modoContenido,
      instrucciones,
      version_anterior: otraVersion ? propuestaIa : '',
      csrf_token: csrfTokenIa
    });

    try {
      const response = await fetch('mejorar-noticia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString()
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.html || !result.titulo) {
        throw new Error(result.error || 'No se pudo generar la propuesta.');
      }
      propuestaIa = result.html;
      iaIdealTitle.value = String(result.titulo).trim();
      iaIdealTitleStatus.textContent = '';
      iaIdealTitleBox.hidden = false;
      iaProposal.innerHTML = aRutaEditor(result.html);
      iaProposalSection.hidden = false;
      iaRegenerate.hidden = false;
      iaApply.disabled = false;
      cambiarEstadoGeneracion(modoContenido === 'url' ? 'Contenido extraído y noticia creada.' : 'Noticia creada.', 'success');
      iaProposalSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      cambiarEstadoGeneracion(error.message || 'No se pudo conectar con DeepSeek. Intentá nuevamente.', 'error');
    } finally {
      iaEnProceso = false;
      iaGenerate.disabled = false;
      iaRegenerate.disabled = false;
      iaApply.disabled = !propuestaIa;
      iaGenerateLabel.textContent = 'Crear noticia';
    }
  }

  btnMejorarIa.addEventListener('click', abrirAsistenteIa);
  iaContentMode.addEventListener('change', sincronizarModoContenido);
  sincronizarModoContenido();
  iaGenerate.addEventListener('click', () => generarConIa(false));
  iaRegenerate.addEventListener('click', () => generarConIa(true));
  iaUseIdealTitle.addEventListener('click', () => {
    const titulo = iaIdealTitle.value.trim();
    if (!titulo) {
      iaIdealTitleStatus.textContent = 'Escribí un título antes de usarlo.';
      iaIdealTitle.focus();
      return;
    }
    titleInput.value = titulo;
    titleInput.dispatchEvent(new Event('input', { bubbles: true }));
    titleInput.dispatchEvent(new Event('change', { bubbles: true }));
    iaIdealTitleStatus.textContent = 'Título agregado al formulario.';
  });
  iaClose.addEventListener('click', cerrarPreviewIa);
  iaCancel.addEventListener('click', () => {
    cerrarPreviewIa();
  });
  iaApply.addEventListener('click', () => {
    if (!propuestaIa) return;
    editor.commands.setContent(aRutaEditor(propuestaIa), { emitUpdate: true });
    cerrarPreviewIa();
    iaSourceInput.value = '';
    contenidoPorModo[iaContentMode.value] = '';
    mostrarEstadoIa('Noticia agregada. Podés deshacerla desde la barra del editor.', 'success');
    editor.commands.focus('end');
  });
  iaOverlay.addEventListener('click', (event) => {
    if (event.target === iaOverlay) cerrarPreviewIa();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !iaOverlay.hidden) cerrarPreviewIa();
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
