<?php
require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('publicidad.gestionar');

$pdo = db();
$errores = [];
$anuncioEditar = null;

function normalizar_url_anuncio(string $valor, string $campo, array &$errores): ?string
{
    $valor = trim($valor);
    if ($valor === '') return null;
    $esValida = strlen($valor) <= 500 && filter_var($valor, FILTER_VALIDATE_URL) !== false;
    $protocolo = strtolower((string) parse_url($valor, PHP_URL_SCHEME));
    if (!$esValida || !in_array($protocolo, ['http', 'https'], true)) {
        $errores[] = $campo . ' debe ser una URL completa que comience con http:// o https://.';
        return null;
    }
    return $valor;
}

function fecha_anuncio_valida(string $fecha): bool
{
    if ($fecha === '') return true;
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $objeto !== false && $objeto->format('Y-m-d') === $fecha;
}

function icono_destino_anuncio(string $tipo): string
{
    return match ($tipo) {
        'facebook' => '<svg viewBox="0 0 512 512" fill="currentColor" stroke="none" aria-hidden="true"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"></path></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4.25"></circle><circle cx="17.4" cy="6.6" r="1" fill="currentColor" stroke="none"></circle></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path></svg>',
    };
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'eliminar') {
        $stmt = $pdo->prepare('SELECT imagen FROM anuncios WHERE id = ?');
        $stmt->execute([$id]);
        $imagenEliminar = $stmt->fetchColumn();
        if ($imagenEliminar !== false) {
            $pdo->prepare('DELETE FROM anuncios WHERE id = ?')->execute([$id]);
            eliminar_imagen_publicidad((string) $imagenEliminar);
            flash('success', 'Anuncio eliminado correctamente.');
        }
        redirigir('anuncios.php');
    }

    $existente = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM anuncios WHERE id = ?');
        $stmt->execute([$id]);
        $existente = $stmt->fetch() ?: null;
        if ($existente === null) $errores[] = 'El anuncio que intentas editar ya no existe.';
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $facebook = normalizar_url_anuncio((string) ($_POST['facebook_url'] ?? ''), 'Facebook', $errores);
    $instagram = normalizar_url_anuncio((string) ($_POST['instagram_url'] ?? ''), 'Instagram', $errores);
    $whatsapp = normalizar_url_anuncio((string) ($_POST['whatsapp_url'] ?? ''), 'WhatsApp', $errores);
    $sitioWeb = normalizar_url_anuncio((string) ($_POST['sitio_web_url'] ?? ''), 'Web', $errores);
    $fechaVencimiento = trim((string) ($_POST['fecha_vencimiento'] ?? ''));

    if ($nombre === '') $errores[] = 'El nombre del anuncio es obligatorio.';
    if (mb_strlen($nombre, 'UTF-8') > 120) $errores[] = 'El nombre no puede superar 120 caracteres.';
    if (!fecha_anuncio_valida($fechaVencimiento)) $errores[] = 'La fecha de vencimiento no es válida.';

    $imagenActual = (string) ($existente['imagen'] ?? '');
    $hayNuevaImagen = (int) ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($id === 0 && !$hayNuevaImagen) $errores[] = 'La imagen del anuncio es obligatoria.';

    $anuncioEditar = [
        'id' => $id,
        'nombre' => $nombre,
        'imagen' => $imagenActual,
        'facebook_url' => trim((string) ($_POST['facebook_url'] ?? '')),
        'instagram_url' => trim((string) ($_POST['instagram_url'] ?? '')),
        'whatsapp_url' => trim((string) ($_POST['whatsapp_url'] ?? '')),
        'sitio_web_url' => trim((string) ($_POST['sitio_web_url'] ?? '')),
        'fecha_vencimiento' => $fechaVencimiento,
    ];

    $nuevaImagen = null;
    if (!$errores && $hayNuevaImagen) {
        try {
            $nuevaImagen = subir_imagen_publicidad($_FILES['imagen']);
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }

    if (!$errores) {
        $imagenGuardar = $nuevaImagen ?: $imagenActual;
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE anuncios
                        SET nombre = ?, imagen = ?, facebook_url = ?, instagram_url = ?, whatsapp_url = ?,
                            sitio_web_url = ?, fecha_vencimiento = ?
                      WHERE id = ?'
                );
                $stmt->execute([$nombre, $imagenGuardar, $facebook, $instagram, $whatsapp, $sitioWeb, $fechaVencimiento ?: null, $id]);
                if ($nuevaImagen && $imagenActual !== '') eliminar_imagen_publicidad($imagenActual);
                flash('success', 'Anuncio actualizado correctamente.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO anuncios
                        (nombre, imagen, facebook_url, instagram_url, whatsapp_url, sitio_web_url, fecha_vencimiento)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$nombre, $imagenGuardar, $facebook, $instagram, $whatsapp, $sitioWeb, $fechaVencimiento ?: null]);
                flash('success', 'Anuncio creado correctamente.');
            }
            redirigir('anuncios.php');
        } catch (Throwable $e) {
            if ($nuevaImagen) eliminar_imagen_publicidad($nuevaImagen);
            $errores[] = 'No se pudo guardar el anuncio. Intenta nuevamente.';
        }
    }
}

if ($anuncioEditar === null && isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM anuncios WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $anuncioEditar = $stmt->fetch() ?: null;
    if ($anuncioEditar === null) {
        flash('danger', 'El anuncio solicitado no existe.');
        redirigir('anuncios.php');
    }
}

$anuncios = $pdo->query('SELECT * FROM anuncios ORDER BY created_at DESC, id DESC')->fetchAll();
$hoy = date('Y-m-d');
$titulo = 'Anuncios';
$active = 'publicidad-anuncios';
require __DIR__ . '/includes/header.php';
?>

<div class="users-page-heading">
  <h1>Anuncios</h1>
  <p>Administrá las piezas publicitarias, sus destinos sociales y el período durante el que pueden mostrarse en el portal.</p>
</div>

<?php if ($errores): ?><div class="flash"><?php foreach ($errores as $error): ?><div class="alert danger"><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

<section class="users-panel ads-panel">
  <div class="ads-table-toolbar">
    <span class="ads-total"><strong><?= count($anuncios) ?></strong> <?= count($anuncios) === 1 ? 'anuncio' : 'anuncios' ?></span>
    <a class="btn btn-primary ads-create-btn js-new-ad" href="anuncios.php?nuevo=1">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      Nuevo Anuncio
    </a>
    <span class="ads-toolbar-spacer" aria-hidden="true"></span>
  </div>

  <?php if (empty($anuncios)): ?>
    <div class="empty ads-empty">Todavía no hay anuncios creados.</div>
  <?php else: ?>
    <div class="table-wrap users-table-wrap"><table class="table users-table ads-table">
      <thead><tr>
        <th>Imagen</th>
        <th>Nombre</th>
        <th>Destinos</th>
        <th>Vencimiento</th>
        <th>Creado</th>
        <th>Estado</th>
        <th style="width:110px;">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($anuncios as $anuncio):
          $vence = (string) ($anuncio['fecha_vencimiento'] ?? '');
          $vencido = $vence !== '' && $vence <= $hoy;
          $enlaces = [
              'facebook' => ['Facebook', $anuncio['facebook_url']],
              'instagram' => ['Instagram', $anuncio['instagram_url']],
              'whatsapp' => ['WhatsApp', $anuncio['whatsapp_url']],
              'web' => ['Web', $anuncio['sitio_web_url']],
          ];
      ?>
        <tr>
          <td data-label="Imagen"><img class="ad-table-image" src="<?= e(url_imagen($anuncio['imagen'])) ?>" alt="Vista previa de <?= e($anuncio['nombre']) ?>"></td>
          <td data-label="Nombre"><strong><?= e($anuncio['nombre']) ?></strong><div class="cell-desc">#<?= (int) $anuncio['id'] ?></div></td>
          <td data-label="Destinos"><div class="ad-destinations">
            <?php foreach ($enlaces as $tipo => [$etiqueta, $url]): ?>
              <?php if ($url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="Abrir <?= e($etiqueta) ?> de <?= e($anuncio['nombre']) ?>" title="<?= e($etiqueta) ?>" class="ad-destination is-set"><?= icono_destino_anuncio($tipo) ?></a><?php else: ?><span class="ad-destination" title="<?= e($etiqueta) ?> sin configurar"><?= icono_destino_anuncio($tipo) ?></span><?php endif; ?>
            <?php endforeach; ?>
          </div></td>
          <td data-label="Vencimiento"><?= $vence !== '' ? e(date('d/m/Y', strtotime($vence))) : 'Sin vencimiento' ?></td>
          <td data-label="Creado"><?= e(date('d/m/Y H:i', strtotime((string) $anuncio['created_at']))) ?></td>
          <td data-label="Estado"><span class="ad-status <?= $vencido ? 'is-expired' : 'is-current' ?>"><span aria-hidden="true"></span><?= $vencido ? 'Vencido' : 'Vigente' ?></span></td>
          <td data-label="Acciones"><div class="cell-actions user-icon-actions">
            <a class="action-icon action-icon-edit js-edit-ad" href="anuncios.php?editar=<?= (int) $anuncio['id'] ?>"
               data-id="<?= (int) $anuncio['id'] ?>" data-name="<?= e($anuncio['nombre']) ?>" data-image="<?= e(url_imagen($anuncio['imagen'])) ?>"
               data-facebook="<?= e($anuncio['facebook_url'] ?? '') ?>" data-instagram="<?= e($anuncio['instagram_url'] ?? '') ?>"
               data-whatsapp="<?= e($anuncio['whatsapp_url'] ?? '') ?>" data-web="<?= e($anuncio['sitio_web_url'] ?? '') ?>"
               data-expires="<?= e($vence) ?>" aria-label="Editar <?= e($anuncio['nombre']) ?>" title="Editar anuncio">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
            </a>
            <form method="post" action="anuncios.php" onsubmit="return confirm('¿Eliminar este anuncio? Esta acción no se puede deshacer.');">
              <?= csrf_input() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $anuncio['id'] ?>">
              <button type="submit" class="action-icon ads-delete-btn" aria-label="Borrar <?= e($anuncio['nombre']) ?>" title="Borrar anuncio">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              </button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<div class="drawer-backdrop" id="adDrawerBackdrop"></div>
<aside class="drawer ad-drawer" id="adDrawer" aria-hidden="true" aria-labelledby="adDrawerTitle">
  <header class="drawer-header ad-drawer-header">
    <div><span class="drawer-title-label" id="adDrawerLabel">Nuevo anuncio</span><h2 id="adDrawerTitle">Agregar anuncio</h2></div>
    <button type="button" class="drawer-close" id="adDrawerClose" aria-label="Cerrar panel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
  </header>
  <div class="drawer-body ad-drawer-body">
    <form method="post" action="anuncios.php" enctype="multipart/form-data" id="adForm">
      <?= csrf_input() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" id="adId" value="<?= (int) ($anuncioEditar['id'] ?? 0) ?>">

      <div class="ad-image-field">
        <span class="ad-field-label">Imagen</span>
        <div class="ad-image-preview<?= !empty($anuncioEditar['imagen']) ? ' has-image' : '' ?>" id="adImagePreview">
          <img id="adImagePreviewImg" src="<?= !empty($anuncioEditar['imagen']) ? e(url_imagen($anuncioEditar['imagen'])) : '' ?>" alt="Vista previa del anuncio">
          <span id="adImagePlaceholder" aria-live="polite">La vista previa aparecerá aquí</span>
        </div>
        <label class="btn btn-outline ad-image-upload" for="adImageInput">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
          Seleccionar imagen
        </label>
        <input class="ad-image-input" type="file" id="adImageInput" name="imagen" accept="image/jpeg,image/png,image/webp">
        <div class="form-hint" id="adImageHelp">JPG, PNG o WEBP, hasta 5 MB. La imagen es obligatoria al crear.</div>
      </div>

      <div class="form-group"><label for="adName">Nombre</label><input class="form-control" type="text" id="adName" name="nombre" maxlength="120" required value="<?= e($anuncioEditar['nombre'] ?? '') ?>" placeholder="Ej.: Comercio del Centro"></div>
      <div class="form-group"><label for="adFacebook">Facebook</label><input class="form-control" type="url" id="adFacebook" name="facebook_url" maxlength="500" value="<?= e($anuncioEditar['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/..."></div>
      <div class="form-group"><label for="adInstagram">Instagram</label><input class="form-control" type="url" id="adInstagram" name="instagram_url" maxlength="500" value="<?= e($anuncioEditar['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/..."></div>
      <div class="form-group"><label for="adWhatsapp">WhatsApp</label><input class="form-control" type="url" id="adWhatsapp" name="whatsapp_url" maxlength="500" value="<?= e($anuncioEditar['whatsapp_url'] ?? '') ?>" placeholder="https://wa.me/598..."></div>
      <div class="form-group"><label for="adWeb">Web</label><input class="form-control" type="url" id="adWeb" name="sitio_web_url" maxlength="500" value="<?= e($anuncioEditar['sitio_web_url'] ?? '') ?>" placeholder="https://ejemplo.com/"></div>
      <div class="form-group"><label for="adExpires">Fecha de vencimiento</label><input class="form-control" type="date" id="adExpires" name="fecha_vencimiento" value="<?= e($anuncioEditar['fecha_vencimiento'] ?? '') ?>"><div class="form-hint">Al comenzar esta fecha el anuncio deja de estar vigente. Vacío significa sin vencimiento.</div></div>

      <div class="form-actions ad-form-actions"><button type="submit" class="btn btn-primary" id="adSubmit">Crear anuncio</button><button type="button" class="btn btn-outline" id="adDrawerCancel">Cancelar</button></div>
    </form>
  </div>
</aside>

<script>
(function () {
  const drawer = document.getElementById('adDrawer');
  const backdrop = document.getElementById('adDrawerBackdrop');
  const closeButton = document.getElementById('adDrawerClose');
  const cancelButton = document.getElementById('adDrawerCancel');
  const newButton = document.querySelector('.js-new-ad');
  const form = document.getElementById('adForm');
  const imageInput = document.getElementById('adImageInput');
  const preview = document.getElementById('adImagePreview');
  const previewImage = document.getElementById('adImagePreviewImg');
  const previewPlaceholder = document.getElementById('adImagePlaceholder');
  const imageHelp = document.getElementById('adImageHelp');
  const label = document.getElementById('adDrawerLabel');
  const title = document.getElementById('adDrawerTitle');
  const submit = document.getElementById('adSubmit');
  const defaultImageHelp = imageHelp.textContent;
  let previewSequence = 0;
  let returnFocus = null;

  function clearPreview(message = 'La vista previa aparecerá aquí') {
    previewSequence += 1;
    preview.classList.remove('has-image', 'is-loading', 'has-error');
    previewImage.removeAttribute('src');
    previewPlaceholder.textContent = message;
  }
  function setPreview(src, successMessage = '') {
    const sequence = ++previewSequence;
    if (!src) {
      clearPreview();
      return;
    }
    preview.classList.remove('has-image', 'has-error');
    preview.classList.add('is-loading');
    previewImage.removeAttribute('src');
    previewPlaceholder.textContent = 'Preparando vista previa…';
    const probe = new Image();
    probe.onload = () => {
      if (sequence !== previewSequence) return;
      previewImage.src = src;
      preview.classList.remove('is-loading');
      preview.classList.add('has-image');
      previewPlaceholder.textContent = '';
      if (successMessage) imageHelp.textContent = successMessage;
    };
    probe.onerror = () => {
      if (sequence !== previewSequence) return;
      preview.classList.remove('is-loading', 'has-image');
      preview.classList.add('has-error');
      previewPlaceholder.textContent = 'No pudimos mostrar esta imagen.';
      imageHelp.textContent = 'Elegí otra imagen JPG, PNG o WEBP.';
    };
    probe.src = src;
  }
  function openDrawer(trigger) {
    returnFocus = trigger || document.activeElement;
    drawer.classList.add('open');
    backdrop.classList.add('show');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.setTimeout(() => imageInput.focus(), 250);
  }
  function closeDrawer() {
    drawer.classList.remove('open');
    backdrop.classList.remove('show');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    previewSequence += 1;
    if (window.location.search) window.history.replaceState({}, '', 'anuncios.php');
    if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
  }
  function prepareNew(event) {
    if (event) event.preventDefault();
    form.reset();
    document.getElementById('adId').value = '0';
    imageInput.required = true;
    clearPreview();
    imageHelp.textContent = defaultImageHelp;
    label.textContent = 'Nuevo anuncio';
    title.textContent = 'Agregar anuncio';
    submit.textContent = 'Crear anuncio';
    openDrawer(event && event.currentTarget);
  }
  function prepareEdit(event) {
    event.preventDefault();
    const button = event.currentTarget;
    form.reset();
    document.getElementById('adId').value = button.dataset.id || '0';
    document.getElementById('adName').value = button.dataset.name || '';
    document.getElementById('adFacebook').value = button.dataset.facebook || '';
    document.getElementById('adInstagram').value = button.dataset.instagram || '';
    document.getElementById('adWhatsapp').value = button.dataset.whatsapp || '';
    document.getElementById('adWeb').value = button.dataset.web || '';
    document.getElementById('adExpires').value = button.dataset.expires || '';
    imageInput.required = false;
    setPreview(button.dataset.image || '');
    imageHelp.textContent = defaultImageHelp;
    label.textContent = 'Editar anuncio';
    title.textContent = 'Actualizar anuncio';
    submit.textContent = 'Guardar cambios';
    window.history.replaceState({}, '', button.href);
    openDrawer(button);
  }

  imageInput.addEventListener('change', () => {
    const file = imageInput.files && imageInput.files[0];
    if (!file) {
      clearPreview();
      imageHelp.textContent = defaultImageHelp;
      return;
    }
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      imageInput.value = '';
      clearPreview('El archivo no es una imagen compatible.');
      preview.classList.add('has-error');
      imageHelp.textContent = 'Usá una imagen JPG, PNG o WEBP.';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      imageInput.value = '';
      clearPreview('La imagen supera el límite de 5 MB.');
      preview.classList.add('has-error');
      imageHelp.textContent = 'Elegí una imagen de hasta 5 MB.';
      return;
    }
    previewSequence += 1;
    preview.classList.remove('has-image', 'has-error');
    preview.classList.add('is-loading');
    previewPlaceholder.textContent = 'Leyendo imagen…';
    imageHelp.textContent = 'Preparando la vista previa…';
    const reader = new FileReader();
    reader.onload = () => setPreview(typeof reader.result === 'string' ? reader.result : '', file.name + ' · vista previa lista');
    reader.onerror = () => {
      clearPreview('No pudimos leer esta imagen.');
      preview.classList.add('has-error');
      imageHelp.textContent = 'Elegí otra imagen e intentá nuevamente.';
    };
    reader.readAsDataURL(file);
  });
  newButton.addEventListener('click', prepareNew);
  document.querySelectorAll('.js-edit-ad').forEach((button) => button.addEventListener('click', prepareEdit));
  closeButton.addEventListener('click', closeDrawer);
  cancelButton.addEventListener('click', closeDrawer);
  backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer(); });

  <?php if ($anuncioEditar !== null || isset($_GET['nuevo'])): ?>
  <?php if ($anuncioEditar === null): ?>
  prepareNew();
  <?php else: ?>
  imageInput.required = <?= (int) ($anuncioEditar['id'] ?? 0) > 0 ? 'false' : 'true' ?>;
  <?php if ((int) ($anuncioEditar['id'] ?? 0) > 0): ?>
  label.textContent = 'Editar anuncio';
  title.textContent = 'Actualizar anuncio';
  submit.textContent = 'Guardar cambios';
  <?php endif; ?>
  openDrawer();
  <?php endif; ?>
  <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
