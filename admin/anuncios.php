<?php
require_once __DIR__ . '/includes/funciones.php';
$solicitudAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
exigir_permiso('publicidad.gestionar', $solicitudAjax);

$pdo = db();
$errores = [];
$erroresCampos = [];
$anuncioEditar = null;

function agregar_error_anuncio(array &$errores, array &$erroresCampos, string $campo, string $mensaje): void
{
    $errores[] = $mensaje;
    if ($campo !== '' && !isset($erroresCampos[$campo])) $erroresCampos[$campo] = $mensaje;
}

function normalizar_url_anuncio(string $valor, string $etiqueta, string $campo, array &$errores, array &$erroresCampos): ?string
{
    $valor = trim($valor);
    if ($valor === '') return null;
    $esValida = strlen($valor) <= 500 && filter_var($valor, FILTER_VALIDATE_URL) !== false;
    $protocolo = strtolower((string) parse_url($valor, PHP_URL_SCHEME));
    if (!$esValida || !in_array($protocolo, ['http', 'https'], true)) {
        agregar_error_anuncio($errores, $erroresCampos, $campo, $etiqueta . ' debe ser una URL completa que comience con http:// o https://.');
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
    verificar_csrf($solicitudAjax);
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'estado') {
        $activoEstado = (string) ($_POST['activo'] ?? '0') === '1' ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE anuncios SET activo = ? WHERE id = ?');
        $stmt->execute([$activoEstado, $id]);
        if ($stmt->rowCount() === 0) {
            $existe = $pdo->prepare('SELECT COUNT(*) FROM anuncios WHERE id = ?');
            $existe->execute([$id]);
            if ((int) $existe->fetchColumn() === 0) {
                if ($solicitudAjax) {
                    http_response_code(404);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'error' => 'El anuncio ya no existe.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                flash('danger', 'El anuncio ya no existe.');
                redirigir('anuncios.php');
            }
        }
        $mensajeEstado = $activoEstado === 1 ? 'Anuncio activado correctamente.' : 'Anuncio desactivado correctamente.';
        $stmt = $pdo->prepare('SELECT updated_at FROM anuncios WHERE id = ?');
        $stmt->execute([$id]);
        $actualizadoEstado = (string) $stmt->fetchColumn();
        if ($solicitudAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'activo' => $activoEstado,
                'label' => $activoEstado === 1 ? 'Activo' : 'Inactivo',
                'updated' => $actualizadoEstado !== '' ? date('d/m/Y H:i', strtotime($actualizadoEstado)) : '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('success', $mensajeEstado);
        redirigir('anuncios.php');
    }

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
        if ($existente === null) agregar_error_anuncio($errores, $erroresCampos, '', 'El anuncio que intentas editar ya no existe.');
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $facebook = normalizar_url_anuncio((string) ($_POST['facebook_url'] ?? ''), 'Facebook', 'facebook_url', $errores, $erroresCampos);
    $instagram = normalizar_url_anuncio((string) ($_POST['instagram_url'] ?? ''), 'Instagram', 'instagram_url', $errores, $erroresCampos);
    $whatsapp = normalizar_url_anuncio((string) ($_POST['whatsapp_url'] ?? ''), 'WhatsApp', 'whatsapp_url', $errores, $erroresCampos);
    $sitioWeb = normalizar_url_anuncio((string) ($_POST['sitio_web_url'] ?? ''), 'Web', 'sitio_web_url', $errores, $erroresCampos);
    $fechaVencimiento = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
    $activo = isset($_POST['activo']) && (string) $_POST['activo'] === '1' ? 1 : 0;

    if ($nombre === '') agregar_error_anuncio($errores, $erroresCampos, 'nombre', 'El nombre del anuncio es obligatorio.');
    if (mb_strlen($nombre, 'UTF-8') > 120) agregar_error_anuncio($errores, $erroresCampos, 'nombre', 'El nombre no puede superar 120 caracteres.');
    if (!fecha_anuncio_valida($fechaVencimiento)) agregar_error_anuncio($errores, $erroresCampos, 'fecha_vencimiento', 'La fecha de vencimiento no es válida.');

    $imagenActual = (string) ($existente['imagen'] ?? '');
    $hayNuevaImagen = (int) ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($id === 0 && !$hayNuevaImagen) agregar_error_anuncio($errores, $erroresCampos, 'imagen', 'La imagen del anuncio es obligatoria.');

    $anuncioEditar = [
        'id' => $id,
        'nombre' => $nombre,
        'imagen' => $imagenActual,
        'facebook_url' => trim((string) ($_POST['facebook_url'] ?? '')),
        'instagram_url' => trim((string) ($_POST['instagram_url'] ?? '')),
        'whatsapp_url' => trim((string) ($_POST['whatsapp_url'] ?? '')),
        'sitio_web_url' => trim((string) ($_POST['sitio_web_url'] ?? '')),
        'fecha_vencimiento' => $fechaVencimiento,
        'activo' => $activo,
    ];

    $nuevaImagen = null;
    if (!$errores && $hayNuevaImagen) {
        try {
            $nuevaImagen = subir_imagen_publicidad($_FILES['imagen']);
        } catch (RuntimeException $e) {
            agregar_error_anuncio($errores, $erroresCampos, 'imagen', $e->getMessage());
        }
    }

    if (!$errores) {
        $imagenGuardar = $nuevaImagen ?: $imagenActual;
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE anuncios
                        SET nombre = ?, imagen = ?, facebook_url = ?, instagram_url = ?, whatsapp_url = ?,
                            sitio_web_url = ?, fecha_vencimiento = ?, activo = ?
                      WHERE id = ?'
                );
                $stmt->execute([$nombre, $imagenGuardar, $facebook, $instagram, $whatsapp, $sitioWeb, $fechaVencimiento ?: null, $activo, $id]);
                if ($nuevaImagen && $imagenActual !== '') eliminar_imagen_publicidad($imagenActual);
                $mensajeExito = 'Anuncio actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO anuncios
                        (nombre, imagen, facebook_url, instagram_url, whatsapp_url, sitio_web_url, fecha_vencimiento, activo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$nombre, $imagenGuardar, $facebook, $instagram, $whatsapp, $sitioWeb, $fechaVencimiento ?: null, $activo]);
                $mensajeExito = 'Anuncio creado correctamente.';
            }
            flash('success', $mensajeExito);
            if ($solicitudAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => $mensajeExito, 'redirect' => 'anuncios.php'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            redirigir('anuncios.php');
        } catch (Throwable $e) {
            if ($nuevaImagen) eliminar_imagen_publicidad($nuevaImagen);
            agregar_error_anuncio($errores, $erroresCampos, '', 'No se pudo guardar el anuncio. Intenta nuevamente.');
        }
    }

    if ($solicitudAjax && $errores) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'errors' => $errores, 'fields' => $erroresCampos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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
          $activoManual = (int) ($anuncio['activo'] ?? 1) === 1;
          $enlaces = [
              'facebook' => ['Facebook', $anuncio['facebook_url']],
              'instagram' => ['Instagram', $anuncio['instagram_url']],
              'whatsapp' => ['WhatsApp', $anuncio['whatsapp_url']],
              'web' => ['Web', $anuncio['sitio_web_url']],
          ];
      ?>
        <tr>
          <td data-label="Imagen"><button type="button" class="ad-table-image-button js-view-ad" data-ad-id="<?= (int) $anuncio['id'] ?>" aria-label="Ver datos de <?= e($anuncio['nombre']) ?>" title="Ver anuncio"><img class="ad-table-image" src="<?= e(url_imagen($anuncio['imagen'])) ?>" alt="Vista previa de <?= e($anuncio['nombre']) ?>"></button></td>
          <td data-label="Nombre"><strong><?= e($anuncio['nombre']) ?></strong><div class="cell-desc">#<?= (int) $anuncio['id'] ?></div></td>
          <td data-label="Destinos"><div class="ad-destinations">
            <?php foreach ($enlaces as $tipo => [$etiqueta, $url]): ?>
              <?php if ($url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="Abrir <?= e($etiqueta) ?> de <?= e($anuncio['nombre']) ?>" title="<?= e($etiqueta) ?>" class="ad-destination is-set"><?= icono_destino_anuncio($tipo) ?></a><?php else: ?><span class="ad-destination" title="<?= e($etiqueta) ?> sin configurar"><?= icono_destino_anuncio($tipo) ?></span><?php endif; ?>
            <?php endforeach; ?>
          </div></td>
          <td data-label="Vencimiento"><?= $vence !== '' ? e(date('d/m/Y', strtotime($vence))) : 'Sin vencimiento' ?></td>
          <td data-label="Creado"><?= e(date('d/m/Y H:i', strtotime((string) $anuncio['created_at']))) ?></td>
          <td data-label="Estado">
            <form method="post" action="anuncios.php" class="ad-status-form js-ad-status-form">
              <?= csrf_input() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $anuncio['id'] ?>">
              <label class="ad-status-switch">
                <input type="checkbox" name="activo" value="1" role="switch" class="js-ad-status-input" <?= $activoManual ? 'checked' : '' ?> aria-label="<?= $activoManual ? 'Desactivar' : 'Activar' ?> <?= e($anuncio['nombre']) ?>">
                <span class="ad-status-switch-track" aria-hidden="true"><span></span></span>
                <span class="ad-status-switch-text"><?= $activoManual ? 'Activo' : 'Inactivo' ?></span>
              </label>
              <?php if ($vencido): ?><small class="ad-status-note">Vencido</small><?php endif; ?>
              <small class="ad-status-feedback" aria-live="polite"></small>
            </form>
          </td>
          <td data-label="Acciones"><div class="cell-actions user-icon-actions">
            <a class="action-icon action-icon-edit js-edit-ad" href="anuncios.php?editar=<?= (int) $anuncio['id'] ?>"
               data-id="<?= (int) $anuncio['id'] ?>" data-name="<?= e($anuncio['nombre']) ?>" data-image="<?= e(url_imagen($anuncio['imagen'])) ?>"
               data-facebook="<?= e($anuncio['facebook_url'] ?? '') ?>" data-instagram="<?= e($anuncio['instagram_url'] ?? '') ?>"
               data-whatsapp="<?= e($anuncio['whatsapp_url'] ?? '') ?>" data-web="<?= e($anuncio['sitio_web_url'] ?? '') ?>"
               data-expires="<?= e($vence) ?>" data-expires-label="<?= $vence !== '' ? e(date('d/m/Y', strtotime($vence))) : 'Sin vencimiento' ?>"
               data-created="<?= e(date('d/m/Y H:i', strtotime((string) $anuncio['created_at']))) ?>"
               data-updated="<?= e(date('d/m/Y H:i', strtotime((string) $anuncio['updated_at']))) ?>"
               data-active="<?= $activoManual ? '1' : '0' ?>"
               data-status="<?= $activoManual ? 'Activo' : 'Inactivo' ?>" data-status-class="<?= $activoManual ? 'is-current' : 'is-inactive' ?>"
               aria-label="Editar <?= e($anuncio['nombre']) ?>" title="Editar anuncio">
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
    <div class="ad-drawer-header-actions">
      <button type="button" class="btn ad-drawer-edit" id="adDrawerEdit" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>Editar</button>
      <button type="button" class="drawer-close" id="adDrawerClose" aria-label="Cerrar panel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
    </div>
  </header>
  <div class="drawer-body ad-drawer-body">
    <section class="ad-detail" id="adDetail" aria-label="Datos del anuncio" hidden>
      <div class="ad-detail-image"><img id="adDetailImage" src="" alt=""></div>
      <div class="ad-detail-summary"><span class="ad-status" id="adDetailStatus"><span aria-hidden="true"></span><b></b></span><span id="adDetailId"></span></div>
      <dl class="ad-detail-list">
        <div><dt>Nombre</dt><dd id="adDetailName"></dd></div>
        <div><dt>Facebook</dt><dd id="adDetailFacebook"></dd></div>
        <div><dt>Instagram</dt><dd id="adDetailInstagram"></dd></div>
        <div><dt>WhatsApp</dt><dd id="adDetailWhatsapp"></dd></div>
        <div><dt>Sitio web</dt><dd id="adDetailWeb"></dd></div>
        <div><dt>Fecha de vencimiento</dt><dd id="adDetailExpires"></dd></div>
        <div><dt>Fecha de creación</dt><dd id="adDetailCreated"></dd></div>
        <div><dt>Última actualización</dt><dd id="adDetailUpdated"></dd></div>
      </dl>
    </section>
    <form method="post" action="anuncios.php" enctype="multipart/form-data" id="adForm" novalidate>
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
      <div class="form-group ad-active-field">
        <span class="ad-field-label">Estado</span>
        <label class="ad-form-switch" for="adActive">
          <input type="checkbox" id="adActive" name="activo" value="1" role="switch" <?= (int) ($anuncioEditar['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span class="ad-form-switch-track" aria-hidden="true"><span></span></span>
          <span><strong id="adActiveLabel"><?= (int) ($anuncioEditar['activo'] ?? 1) === 1 ? 'Activo' : 'Inactivo' ?></strong><small>Podés suspenderlo manualmente aunque no tenga vencimiento.</small></span>
        </label>
      </div>

      <div class="ad-form-errors" id="adFormErrors" role="alert" tabindex="-1"<?= $errores ? '' : ' hidden' ?>>
        <strong>Revisá los datos del anuncio</strong>
        <ul id="adFormErrorList"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
      <div class="form-actions ad-form-actions"><button type="submit" class="btn btn-primary" id="adSubmit">Crear anuncio</button><button type="button" class="btn btn-outline" id="adDrawerCancel">Cancelar</button></div>
    </form>
  </div>
</aside>

<script>
(function () {
  const drawer = document.getElementById('adDrawer');
  const backdrop = document.getElementById('adDrawerBackdrop');
  const closeButton = document.getElementById('adDrawerClose');
  const editDrawerButton = document.getElementById('adDrawerEdit');
  const cancelButton = document.getElementById('adDrawerCancel');
  const newButton = document.querySelector('.js-new-ad');
  const form = document.getElementById('adForm');
  const detail = document.getElementById('adDetail');
  const imageInput = document.getElementById('adImageInput');
  const preview = document.getElementById('adImagePreview');
  const previewImage = document.getElementById('adImagePreviewImg');
  const previewPlaceholder = document.getElementById('adImagePlaceholder');
  const imageHelp = document.getElementById('adImageHelp');
  const label = document.getElementById('adDrawerLabel');
  const title = document.getElementById('adDrawerTitle');
  const submit = document.getElementById('adSubmit');
  const activeInput = document.getElementById('adActive');
  const activeLabel = document.getElementById('adActiveLabel');
  const formErrors = document.getElementById('adFormErrors');
  const formErrorList = document.getElementById('adFormErrorList');
  const fieldsByName = {
    imagen: imageInput,
    nombre: document.getElementById('adName'),
    facebook_url: document.getElementById('adFacebook'),
    instagram_url: document.getElementById('adInstagram'),
    whatsapp_url: document.getElementById('adWhatsapp'),
    sitio_web_url: document.getElementById('adWeb'),
    fecha_vencimiento: document.getElementById('adExpires')
  };
  const defaultImageHelp = imageHelp.textContent;
  let previewSequence = 0;
  let returnFocus = null;
  let drawerFocusTimer = null;
  let currentEditButton = null;

  function updateActiveLabel() {
    activeLabel.textContent = activeInput.checked ? 'Activo' : 'Inactivo';
  }

  function clearFormErrors() {
    formErrors.hidden = true;
    formErrorList.replaceChildren();
    Object.values(fieldsByName).forEach((field) => {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
    });
    preview.classList.remove('is-field-invalid');
  }
  function showFormErrors(messages, fieldErrors = {}) {
    clearFormErrors();
    const normalizedMessages = Array.isArray(messages) && messages.length
      ? messages
      : ['Revisá los datos ingresados e intentá nuevamente.'];
    normalizedMessages.forEach((message) => {
      const item = document.createElement('li');
      item.textContent = message;
      formErrorList.appendChild(item);
    });
    formErrors.hidden = false;

    Object.keys(fieldErrors).forEach((name) => {
      const field = fieldsByName[name];
      if (!field) return;
      field.classList.add('is-invalid');
      field.setAttribute('aria-invalid', 'true');
      if (name === 'imagen') preview.classList.add('is-field-invalid');
    });

    const firstFieldName = Object.keys(fieldErrors).find((name) => fieldsByName[name]);
    const firstField = firstFieldName ? fieldsByName[firstFieldName] : null;
    const scrollTarget = firstFieldName === 'imagen' ? preview : (firstField || formErrors);
    window.setTimeout(() => {
      (firstField || formErrors).focus({ preventScroll: true });
      scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 40);
  }

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
  function openDrawer(trigger, focusTarget = imageInput) {
    returnFocus = trigger || document.activeElement;
    drawer.classList.add('open');
    backdrop.classList.add('show');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    window.clearTimeout(drawerFocusTimer);
    drawerFocusTimer = window.setTimeout(() => focusTarget.focus(), 250);
  }
  function closeDrawer() {
    window.clearTimeout(drawerFocusTimer);
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
    form.hidden = false;
    detail.hidden = true;
    editDrawerButton.hidden = true;
    currentEditButton = null;
    clearFormErrors();
    document.getElementById('adId').value = '0';
    activeInput.checked = true;
    updateActiveLabel();
    imageInput.required = true;
    clearPreview();
    imageHelp.textContent = defaultImageHelp;
    label.textContent = 'Nuevo anuncio';
    title.textContent = 'Agregar anuncio';
    submit.textContent = 'Crear anuncio';
    openDrawer(event && event.currentTarget);
  }
  function loadEditForm(button, trigger = button) {
    form.hidden = false;
    detail.hidden = true;
    editDrawerButton.hidden = true;
    form.reset();
    clearFormErrors();
    document.getElementById('adId').value = button.dataset.id || '0';
    document.getElementById('adName').value = button.dataset.name || '';
    document.getElementById('adFacebook').value = button.dataset.facebook || '';
    document.getElementById('adInstagram').value = button.dataset.instagram || '';
    document.getElementById('adWhatsapp').value = button.dataset.whatsapp || '';
    document.getElementById('adWeb').value = button.dataset.web || '';
    document.getElementById('adExpires').value = button.dataset.expires || '';
    activeInput.checked = button.dataset.active !== '0';
    updateActiveLabel();
    imageInput.required = false;
    setPreview(button.dataset.image || '');
    imageHelp.textContent = defaultImageHelp;
    label.textContent = 'Editar anuncio';
    title.textContent = 'Actualizar anuncio';
    submit.textContent = 'Guardar cambios';
    window.history.replaceState({}, '', button.href);
    openDrawer(trigger);
  }
  function prepareEdit(event) {
    event.preventDefault();
    loadEditForm(event.currentTarget);
  }
  function setDetailLink(element, value) {
    element.replaceChildren();
    if (!value) {
      element.textContent = 'Sin configurar';
      element.classList.add('is-empty');
      return;
    }
    element.classList.remove('is-empty');
    const link = document.createElement('a');
    link.href = value;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = value;
    element.appendChild(link);
  }
  function prepareView(event) {
    const trigger = event.currentTarget;
    const editButton = document.querySelector('.js-edit-ad[data-id="' + trigger.dataset.adId + '"]');
    if (!editButton) return;
    currentEditButton = editButton;
    form.hidden = true;
    detail.hidden = false;
    editDrawerButton.hidden = false;
    label.textContent = 'Detalle del anuncio';
    title.textContent = editButton.dataset.name || 'Anuncio';
    document.getElementById('adDetailImage').src = editButton.dataset.image || '';
    document.getElementById('adDetailImage').alt = 'Imagen de ' + (editButton.dataset.name || 'anuncio');
    document.getElementById('adDetailName').textContent = editButton.dataset.name || '—';
    document.getElementById('adDetailId').textContent = '#' + (editButton.dataset.id || '');
    document.getElementById('adDetailExpires').textContent = editButton.dataset.expiresLabel || 'Sin vencimiento';
    document.getElementById('adDetailCreated').textContent = editButton.dataset.created || '—';
    document.getElementById('adDetailUpdated').textContent = editButton.dataset.updated || '—';
    const status = document.getElementById('adDetailStatus');
    status.classList.remove('is-current', 'is-expired', 'is-inactive');
    status.classList.add(editButton.dataset.statusClass || 'is-current');
    status.querySelector('b').textContent = editButton.dataset.status || 'Vigente';
    setDetailLink(document.getElementById('adDetailFacebook'), editButton.dataset.facebook || '');
    setDetailLink(document.getElementById('adDetailInstagram'), editButton.dataset.instagram || '');
    setDetailLink(document.getElementById('adDetailWhatsapp'), editButton.dataset.whatsapp || '');
    setDetailLink(document.getElementById('adDetailWeb'), editButton.dataset.web || '');
    openDrawer(trigger, editDrawerButton);
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
      showFormErrors(['La imagen debe ser un archivo JPG, PNG o WEBP.'], { imagen: 'Formato no admitido.' });
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      imageInput.value = '';
      clearPreview('La imagen supera el límite de 5 MB.');
      preview.classList.add('has-error');
      imageHelp.textContent = 'Elegí una imagen de hasta 5 MB.';
      showFormErrors(['La imagen no puede superar los 5 MB.'], { imagen: 'Archivo demasiado grande.' });
      return;
    }
    clearFormErrors();
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
  activeInput.addEventListener('change', updateActiveLabel);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    window.clearTimeout(drawerFocusTimer);
    clearFormErrors();
    const submitText = submit.textContent;
    submit.disabled = true;
    submit.textContent = 'Guardando…';
    form.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      let result = null;
      try {
        result = await response.json();
      } catch (error) {
        throw new Error('La respuesta del servidor no fue válida.');
      }
      if (!response.ok || !result.ok) {
        showFormErrors(result.errors || [result.error || 'No se pudo validar el anuncio.'], result.fields || {});
        return;
      }
      window.location.href = result.redirect || 'anuncios.php';
    } catch (error) {
      showFormErrors(['No pudimos guardar el anuncio. Verificá la conexión e intentá nuevamente.']);
    } finally {
      submit.disabled = false;
      submit.textContent = submitText;
      form.removeAttribute('aria-busy');
    }
  });
  newButton.addEventListener('click', prepareNew);
  document.querySelectorAll('.js-view-ad').forEach((button) => button.addEventListener('click', prepareView));
  document.querySelectorAll('.js-edit-ad').forEach((button) => button.addEventListener('click', prepareEdit));
  editDrawerButton.addEventListener('click', () => { if (currentEditButton) loadEditForm(currentEditButton, returnFocus); });
  document.querySelectorAll('.js-ad-status-form').forEach((statusForm) => {
    const input = statusForm.querySelector('.js-ad-status-input');
    const text = statusForm.querySelector('.ad-status-switch-text');
    const feedback = statusForm.querySelector('.ad-status-feedback');
    statusForm.addEventListener('submit', (event) => event.preventDefault());
    input.addEventListener('change', async () => {
      const requestedState = input.checked;
      input.disabled = true;
      feedback.textContent = 'Guardando…';
      const payload = new FormData(statusForm);
      payload.set('activo', requestedState ? '1' : '0');
      try {
        const response = await fetch(statusForm.action, {
          method: 'POST',
          body: payload,
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo actualizar el estado.');
        input.checked = result.activo === 1;
        text.textContent = result.label;
        input.setAttribute('aria-label', (result.activo === 1 ? 'Desactivar ' : 'Activar ') + text.closest('tr').querySelector('td:nth-child(2) strong').textContent);
        feedback.textContent = result.activo === 1 ? 'Activado' : 'Desactivado';
        const id = statusForm.querySelector('input[name="id"]').value;
        const rowEditButton = document.querySelector('.js-edit-ad[data-id="' + id + '"]');
        if (rowEditButton) {
          rowEditButton.dataset.active = result.activo === 1 ? '1' : '0';
          rowEditButton.dataset.status = result.label;
          rowEditButton.dataset.statusClass = result.activo === 1 ? 'is-current' : 'is-inactive';
          if (result.updated) rowEditButton.dataset.updated = result.updated;
        }
        if (!detail.hidden && currentEditButton === rowEditButton) {
          const detailStatus = document.getElementById('adDetailStatus');
          detailStatus.classList.remove('is-current', 'is-inactive');
          detailStatus.classList.add(result.activo === 1 ? 'is-current' : 'is-inactive');
          detailStatus.querySelector('b').textContent = result.label;
          if (result.updated) document.getElementById('adDetailUpdated').textContent = result.updated;
        }
      } catch (error) {
        input.checked = !requestedState;
        text.textContent = input.checked ? 'Activo' : 'Inactivo';
        feedback.textContent = 'No se pudo guardar';
      } finally {
        input.disabled = false;
        window.setTimeout(() => { feedback.textContent = ''; }, 2200);
      }
    });
  });
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
  <?php if ($errores): ?>
  showFormErrors(
    <?= json_encode(array_values($errores), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    <?= json_encode($erroresCampos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
  );
  <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
