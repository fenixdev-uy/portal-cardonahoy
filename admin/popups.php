<?php
require_once __DIR__ . '/includes/funciones.php';
$ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
exigir_permiso('publicidad.gestionar', $ajax);
$pdo = db();
$errores = [];
$camposError = [];
$editar = null;

function popup_error(array &$errores, array &$campos, string $campo, string $mensaje): void {
    $errores[] = $mensaje;
    if ($campo !== '' && !isset($campos[$campo])) $campos[$campo] = $mensaje;
}
function popup_url(string $valor, string $etiqueta, string $campo, array &$errores, array &$campos): ?string {
    $valor = trim($valor);
    if ($valor === '') return null;
    $protocolo = strtolower((string) parse_url($valor, PHP_URL_SCHEME));
    if (strlen($valor) > 500 || filter_var($valor, FILTER_VALIDATE_URL) === false || !in_array($protocolo, ['http', 'https'], true)) {
        popup_error($errores, $campos, $campo, "$etiqueta debe ser una URL completa que comience con http:// o https://.");
        return null;
    }
    return $valor;
}
function popup_fecha_valida(string $fecha): bool {
    if ($fecha === '') return true;
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $objeto !== false && $objeto->format('Y-m-d') === $fecha;
}
function popup_esta_vencido(?string $fecha): bool { return $fecha !== null && $fecha !== '' && $fecha <= date('Y-m-d'); }
function popup_icono_destino(string $tipo): string {
    return match ($tipo) {
        'facebook' => '<svg viewBox="0 0 512 512" fill="currentColor" stroke="none" aria-hidden="true"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"></path></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4.25"></circle><circle cx="17.4" cy="6.6" r="1" fill="currentColor" stroke="none"></circle></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path></svg>',
    };
}
function popup_json(array $datos, int $estado = 200): never {
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$hoy = date('Y-m-d');
$pdo->prepare('UPDATE popups SET activo = 0 WHERE activo = 1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= ?')->execute([$hoy]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificar_csrf($ajax);
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'estado') {
        $activo = (string) ($_POST['activo'] ?? '0') === '1' ? 1 : 0;
        $stmt = $pdo->prepare('SELECT fecha_vencimiento FROM popups WHERE id = ?');
        $stmt->execute([$id]);
        $fecha = $stmt->fetchColumn();
        if ($fecha === false) {
            if ($ajax) popup_json(['ok' => false, 'error' => 'El popup ya no existe.'], 404);
            flash('danger', 'El popup ya no existe.'); redirigir('popups.php');
        }
        $vencido = $activo === 1 && popup_esta_vencido($fecha !== null ? (string) $fecha : null);
        if ($vencido) $activo = 0;
        $pdo->prepare('UPDATE popups SET activo = ? WHERE id = ?')->execute([$activo, $id]);
        if ($ajax) popup_json(['ok' => true, 'activo' => $activo, 'label' => $activo ? 'Activo' : 'Inactivo', 'notice' => $vencido ? 'Vencido: cambiá o quitá la fecha.' : ($activo ? 'Activado' : 'Desactivado')]);
        flash('success', $activo ? 'Popup activado correctamente.' : 'Popup desactivado correctamente.'); redirigir('popups.php');
    }

    if ($accion === 'eliminar') {
        $stmt = $pdo->prepare('SELECT imagen_vertical, imagen_horizontal FROM popups WHERE id = ?');
        $stmt->execute([$id]);
        if ($popup = $stmt->fetch()) {
            $pdo->prepare('DELETE FROM popups WHERE id = ?')->execute([$id]);
            eliminar_imagen_publicidad((string) $popup['imagen_vertical']);
            eliminar_imagen_publicidad((string) $popup['imagen_horizontal']);
            flash('success', 'Popup eliminado correctamente.');
        }
        redirigir('popups.php');
    }

    $existente = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM popups WHERE id = ?'); $stmt->execute([$id]);
        $existente = $stmt->fetch() ?: null;
        if (!$existente) popup_error($errores, $camposError, '', 'El popup que intentás editar ya no existe.');
    }
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $segundos = filter_var($_POST['segundos_aparicion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3600]]);
    $limite = filter_var($_POST['limite_diario_por_visitante'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    $fecha = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
    $activo = isset($_POST['activo']) && (string) $_POST['activo'] === '1' ? 1 : 0;
    $facebook = popup_url((string) ($_POST['facebook_url'] ?? ''), 'Facebook', 'facebook_url', $errores, $camposError);
    $instagram = popup_url((string) ($_POST['instagram_url'] ?? ''), 'Instagram', 'instagram_url', $errores, $camposError);
    $whatsapp = popup_url((string) ($_POST['whatsapp_url'] ?? ''), 'WhatsApp', 'whatsapp_url', $errores, $camposError);
    $web = popup_url((string) ($_POST['sitio_web_url'] ?? ''), 'Sitio web', 'sitio_web_url', $errores, $camposError);
    if ($nombre === '') popup_error($errores, $camposError, 'nombre', 'El nombre del popup es obligatorio.');
    if (mb_strlen($nombre, 'UTF-8') > 120) popup_error($errores, $camposError, 'nombre', 'El nombre no puede superar 120 caracteres.');
    if ($segundos === false) popup_error($errores, $camposError, 'segundos_aparicion', 'Los segundos deben estar entre 0 y 3600.');
    if ($limite === false) popup_error($errores, $camposError, 'limite_diario_por_visitante', 'La frecuencia debe estar entre 1 y 100.');
    if (!popup_fecha_valida($fecha)) popup_error($errores, $camposError, 'fecha_vencimiento', 'La fecha de vencimiento no es válida.');
    $desactivadoPorFecha = !$errores && $activo === 1 && popup_esta_vencido($fecha);
    if ($desactivadoPorFecha) $activo = 0;

    $verticalActual = (string) ($existente['imagen_vertical'] ?? '');
    $horizontalActual = (string) ($existente['imagen_horizontal'] ?? '');
    $hayVertical = (int) ($_FILES['imagen_vertical']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hayHorizontal = (int) ($_FILES['imagen_horizontal']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($id === 0 && !$hayVertical) popup_error($errores, $camposError, 'imagen_vertical', 'La imagen vertical es obligatoria.');
    if ($id === 0 && !$hayHorizontal) popup_error($errores, $camposError, 'imagen_horizontal', 'La imagen horizontal es obligatoria.');
    $editar = ['id'=>$id, 'nombre'=>$nombre, 'imagen_vertical'=>$verticalActual, 'imagen_horizontal'=>$horizontalActual, 'facebook_url'=>trim((string)($_POST['facebook_url']??'')), 'instagram_url'=>trim((string)($_POST['instagram_url']??'')), 'whatsapp_url'=>trim((string)($_POST['whatsapp_url']??'')), 'sitio_web_url'=>trim((string)($_POST['sitio_web_url']??'')), 'fecha_vencimiento'=>$fecha, 'activo'=>$activo, 'segundos_aparicion'=>$segundos === false ? (string)($_POST['segundos_aparicion']??'') : $segundos, 'limite_diario_por_visitante'=>$limite === false ? (string)($_POST['limite_diario_por_visitante']??'') : $limite];

    $verticalNueva = $horizontalNueva = null;
    if (!$errores) {
        try {
            if ($hayVertical) $verticalNueva = subir_imagen_publicidad($_FILES['imagen_vertical']);
            if ($hayHorizontal) $horizontalNueva = subir_imagen_publicidad($_FILES['imagen_horizontal']);
        } catch (RuntimeException $e) {
            if ($verticalNueva) eliminar_imagen_publicidad($verticalNueva);
            if ($horizontalNueva) eliminar_imagen_publicidad($horizontalNueva);
            $verticalNueva = $horizontalNueva = null;
            popup_error($errores, $camposError, $hayHorizontal ? 'imagen_horizontal' : 'imagen_vertical', $e->getMessage());
        }
    }
    if (!$errores) {
        $vertical = $verticalNueva ?: $verticalActual; $horizontal = $horizontalNueva ?: $horizontalActual;
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE popups SET nombre=?, imagen_vertical=?, imagen_horizontal=?, facebook_url=?, instagram_url=?, whatsapp_url=?, sitio_web_url=?, fecha_vencimiento=?, activo=?, segundos_aparicion=?, limite_diario_por_visitante=? WHERE id=?');
                $stmt->execute([$nombre,$vertical,$horizontal,$facebook,$instagram,$whatsapp,$web,$fecha?:null,$activo,$segundos,$limite,$id]);
                if ($verticalNueva && $verticalActual) eliminar_imagen_publicidad($verticalActual);
                if ($horizontalNueva && $horizontalActual) eliminar_imagen_publicidad($horizontalActual);
                $mensaje = 'Popup actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO popups (nombre,imagen_vertical,imagen_horizontal,facebook_url,instagram_url,whatsapp_url,sitio_web_url,fecha_vencimiento,activo,segundos_aparicion,limite_diario_por_visitante) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$nombre,$vertical,$horizontal,$facebook,$instagram,$whatsapp,$web,$fecha?:null,$activo,$segundos,$limite]);
                $mensaje = 'Popup creado correctamente.';
            }
            if ($desactivadoPorFecha) $mensaje .= ' Quedó inactivo porque su fecha ya venció.';
            flash('success', $mensaje);
            if ($ajax) popup_json(['ok'=>true,'message'=>$mensaje,'redirect'=>'popups.php']);
            redirigir('popups.php');
        } catch (Throwable $e) {
            if ($verticalNueva) eliminar_imagen_publicidad($verticalNueva);
            if ($horizontalNueva) eliminar_imagen_publicidad($horizontalNueva);
            popup_error($errores, $camposError, '', 'No se pudo guardar el popup. Intentá nuevamente.');
        }
    }
    if ($ajax && $errores) popup_json(['ok'=>false,'errors'=>$errores,'fields'=>$camposError], 422);
}

if ($editar === null && isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM popups WHERE id=?'); $stmt->execute([(int)$_GET['editar']]); $editar = $stmt->fetch() ?: null;
    if (!$editar) { flash('danger', 'El popup solicitado no existe.'); redirigir('popups.php'); }
}
$popups = $pdo->query('SELECT * FROM popups ORDER BY created_at DESC,id DESC')->fetchAll();
$titulo = 'Popups'; $active = 'publicidad-popups';
require __DIR__ . '/includes/header.php';
?>
<div class="users-page-heading"><h1>Popups</h1><p>Administrá los avisos emergentes, sus piezas para móvil y escritorio, la frecuencia y el período de publicación.</p></div>
<section class="users-panel ads-panel popup-admin-panel">
  <div class="ads-table-toolbar<?= !$popups?' is-empty':'' ?>">
    <?php if ($popups): ?><label class="ads-search" for="popupsSearch"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg><input type="search" id="popupsSearch" placeholder="Buscar popups..." autocomplete="off"></label><?php endif; ?>
    <a class="btn btn-primary ads-create-btn js-new-popup" href="popups.php?nuevo=1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>Nuevo Popup</a>
    <span class="ads-total" aria-live="polite"><strong id="popupsSearchCount"><?= count($popups) ?></strong><span id="popupsSearchLabel"> <?= count($popups)===1?'popup':'popups' ?></span></span>
  </div>
  <?php if (!$popups): ?><div class="empty ads-empty">Todavía no hay popups creados.</div><?php else: ?>
  <div class="table-wrap users-table-wrap"><table class="table users-table ads-table popups-table"><thead><tr><th>Piezas</th><th>Nombre</th><th>Destinos</th><th>Aparición</th><th>Frecuencia</th><th>Vencimiento</th><th>Estado</th><th>Clics</th><th>Acciones</th></tr></thead><tbody>
  <?php foreach ($popups as $popup): $vence=(string)($popup['fecha_vencimiento']??''); $vencido=$vence!==''&&$vence<=$hoy; $activo=(int)$popup['activo']===1; $enlaces=['facebook'=>['Facebook',$popup['facebook_url']],'instagram'=>['Instagram',$popup['instagram_url']],'whatsapp'=>['WhatsApp',$popup['whatsapp_url']],'web'=>['Web',$popup['sitio_web_url']]]; ?>
  <tr class="popup-row" data-search-name="<?= e($popup['nombre']) ?>">
    <td data-label="Piezas"><button type="button" class="popup-table-pieces js-view-popup" aria-label="Ver detalles de <?= e($popup['nombre']) ?>" title="Ver popup"><img src="<?= e(url_imagen($popup['imagen_vertical'])) ?>" alt="Pieza vertical"><img src="<?= e(url_imagen($popup['imagen_horizontal'])) ?>" alt="Pieza horizontal"></button></td>
    <td data-label="Nombre"><strong><?= e($popup['nombre']) ?></strong><div class="cell-desc">#<?= (int)$popup['id'] ?></div></td><td data-label="Destinos"><div class="ad-destinations"><?php foreach($enlaces as $tipo=>[$etiqueta,$url]): ?><?php if($url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="Abrir <?= e($etiqueta) ?> de <?= e($popup['nombre']) ?>" title="<?= e($etiqueta) ?>" class="ad-destination is-set"><?= popup_icono_destino($tipo) ?></a><?php else: ?><span class="ad-destination" title="<?= e($etiqueta) ?> sin configurar"><?= popup_icono_destino($tipo) ?></span><?php endif; ?><?php endforeach; ?></div></td><td data-label="Aparición"><strong><?= (int)$popup['segundos_aparicion'] ?> s</strong></td><td data-label="Frecuencia"><strong><?= (int)$popup['limite_diario_por_visitante'] ?></strong><div class="cell-desc">por día</div></td><td data-label="Vencimiento"><?= $vence!==''?e(date('d/m/Y',strtotime($vence))):'Sin vencimiento' ?><?php if($vencido):?><div class="ad-status-note">Vencido</div><?php endif;?></td>
    <td data-label="Estado"><form method="post" action="popups.php" class="ad-status-form js-popup-status-form"><?= csrf_input() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$popup['id'] ?>"><label class="ad-status-switch"><input type="checkbox" name="activo" value="1" role="switch" class="js-popup-status-input" <?= $activo?'checked':'' ?> <?= $vencido?'disabled':'' ?>><span class="ad-status-switch-track" aria-hidden="true"><span></span></span><span class="ad-status-switch-text"><?= $activo?'Activo':'Inactivo' ?></span></label><small class="ad-status-feedback" aria-live="polite"></small></form></td><td data-label="Clics"><strong><?= number_format((int)$popup['clics'],0,',','.') ?></strong></td>
    <td data-label="Acciones"><div class="cell-actions user-icon-actions"><a class="action-icon action-icon-edit js-edit-popup" href="popups.php?editar=<?= (int)$popup['id'] ?>" data-id="<?= (int)$popup['id'] ?>" data-name="<?= e($popup['nombre']) ?>" data-vertical="<?= e(url_imagen($popup['imagen_vertical'])) ?>" data-horizontal="<?= e(url_imagen($popup['imagen_horizontal'])) ?>" data-facebook="<?= e($popup['facebook_url']??'') ?>" data-instagram="<?= e($popup['instagram_url']??'') ?>" data-whatsapp="<?= e($popup['whatsapp_url']??'') ?>" data-web="<?= e($popup['sitio_web_url']??'') ?>" data-expires="<?= e($vence) ?>" data-expires-label="<?= $vence!==''?e(date('d/m/Y',strtotime($vence))):'Sin vencimiento' ?>" data-active="<?= $activo?'1':'0' ?>" data-status="<?= $activo?'Activo':'Inactivo' ?>" data-delay="<?= (int)$popup['segundos_aparicion'] ?>" data-daily="<?= (int)$popup['limite_diario_por_visitante'] ?>" data-clicks="<?= (int)$popup['clics'] ?>" data-created="<?= e(date('d/m/Y H:i',strtotime((string)$popup['created_at']))) ?>" data-updated="<?= e(date('d/m/Y H:i',strtotime((string)$popup['updated_at']))) ?>" title="Editar popup"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg></a><form method="post" action="popups.php" onsubmit="return confirm('¿Eliminar este popup?');"><?= csrf_input() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$popup['id'] ?>"><button class="action-icon ads-delete-btn" type="submit" title="Borrar popup"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M19 6v14H5V6m3 0V4h8v2"></path></svg></button></form></div></td>
  </tr><?php endforeach; ?></tbody></table></div><div class="ads-filter-empty" id="popupsFilterEmpty" hidden>No encontramos popups con ese nombre.</div><?php endif; ?>
</section>

<div class="drawer-backdrop" id="popupDrawerBackdrop"></div><aside class="drawer popup-drawer" id="popupDrawer" aria-hidden="true" aria-labelledby="popupDrawerTitle"><header class="drawer-header ad-drawer-header popup-drawer-header"><div><span class="drawer-title-label" id="popupDrawerLabel">Nuevo popup</span><h2 id="popupDrawerTitle">Agregar popup</h2></div><div class="popup-drawer-header-actions"><button type="button" class="btn ad-drawer-edit" id="popupDrawerEdit" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>Editar</button><button type="button" class="drawer-close" id="popupDrawerClose" aria-label="Cerrar panel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"></path></svg></button></div></header><div class="drawer-body popup-drawer-body"><section class="popup-detail" id="popupDetail" aria-label="Datos del popup" hidden>
  <div class="popup-detail-previews"><div><span>Móvil · Vertical</span><div class="popup-detail-preview popup-detail-preview-vertical"><img id="popupDetailVertical" alt="Pieza vertical del popup"></div></div><div><span>PC · Horizontal</span><div class="popup-detail-preview popup-detail-preview-horizontal"><img id="popupDetailHorizontal" alt="Pieza horizontal del popup"></div></div></div>
  <div class="popup-detail-summary"><span class="ad-status" id="popupDetailStatus"><span aria-hidden="true"></span><b></b></span><span id="popupDetailId"></span></div>
  <dl class="ad-detail-list popup-detail-list"><div><dt>Nombre</dt><dd id="popupDetailName"></dd></div><div><dt>Aparece después de</dt><dd id="popupDetailDelay"></dd></div><div><dt>Veces por día</dt><dd id="popupDetailDaily"></dd></div><div><dt>Vencimiento</dt><dd id="popupDetailExpires"></dd></div><div><dt>Facebook</dt><dd id="popupDetailFacebook"></dd></div><div><dt>Instagram</dt><dd id="popupDetailInstagram"></dd></div><div><dt>WhatsApp</dt><dd id="popupDetailWhatsapp"></dd></div><div><dt>Sitio web</dt><dd id="popupDetailWeb"></dd></div><div><dt>Clics</dt><dd id="popupDetailClicks"></dd></div><div><dt>Creado</dt><dd id="popupDetailCreated"></dd></div><div><dt>Actualizado</dt><dd id="popupDetailUpdated"></dd></div></dl>
  <div class="popup-test-copy">Volvé a lanzar cualquiera de las dos piezas tal como aparecerá en el portal.</div><div class="popup-test-actions"><button type="button" class="btn btn-outline" id="popupDetailTestVertical">Probar popup vertical</button><button type="button" class="btn btn-outline" id="popupDetailTestHorizontal">Probar popup horizontal</button></div>
</section>
<form method="post" action="popups.php" enctype="multipart/form-data" id="popupForm" novalidate><?= csrf_input() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" id="popupId" value="<?= (int)($editar['id']??0) ?>">
<?php foreach ([['vertical','móvil','Vertical','imagen_vertical'],['horizontal','escritorio','Horizontal','imagen_horizontal']] as [$orientacion,$dispositivo,$rotulo,$campo]): $valor=(string)($editar[$campo]??''); ?>
<section class="popup-form-preview-group"><div class="popup-form-preview-heading"><h3>Vista previa <?= $dispositivo ?></h3><span><?= $rotulo ?></span></div><div class="popup-form-preview popup-form-preview-<?= $orientacion ?><?= $valor!==''?' has-image':'' ?>" id="popup<?= ucfirst($orientacion) ?>Preview"><img id="popup<?= ucfirst($orientacion) ?>PreviewImage" src="<?= $valor!==''?e(url_imagen($valor)):'' ?>" alt="Vista previa <?= $orientacion ?>"><span>La imagen <?= $orientacion ?> aparecerá aquí</span></div><label class="btn btn-outline popup-form-upload" for="popup<?= ucfirst($orientacion) ?>Input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4H3v-4M17 8l-5-5-5 5M12 3v12"></path></svg>Cargar imagen <?= $orientacion ?></label><input class="popup-file-input" type="file" id="popup<?= ucfirst($orientacion) ?>Input" name="<?= $campo ?>" accept="image/jpeg,image/png,image/webp"><small class="form-hint" id="popup<?= ucfirst($orientacion) ?>Help">JPG, PNG o WEBP, hasta 5 MB.</small></section>
<?php endforeach; ?>
<div class="form-group"><label for="popupName">Nombre del popup</label><input class="form-control" id="popupName" name="nombre" maxlength="120" required value="<?= e($editar['nombre']??'') ?>" placeholder="Ej.: Promoción fin de semana"></div><div class="popup-form-grid"><div class="form-group"><label for="popupDelay">Aparece después de</label><div class="popup-number-field"><input class="form-control" type="number" id="popupDelay" name="segundos_aparicion" min="0" max="3600" required value="<?= e((string)($editar['segundos_aparicion']??3)) ?>"><span>segundos</span></div></div><div class="form-group"><label for="popupDailyLimit">Veces por día por usuario</label><input class="form-control" type="number" id="popupDailyLimit" name="limite_diario_por_visitante" min="1" max="100" required value="<?= e((string)($editar['limite_diario_por_visitante']??1)) ?>"></div></div><div class="form-group"><label for="popupExpires">Fecha de vencimiento</label><input class="form-control" type="date" id="popupExpires" name="fecha_vencimiento" value="<?= e($editar['fecha_vencimiento']??'') ?>"><div class="form-hint">Vacío significa sin vencimiento.</div></div><div class="form-group popup-active-field"><span class="ad-field-label">Estado</span><label class="ad-form-switch" for="popupActive"><input type="checkbox" id="popupActive" name="activo" value="1" role="switch" <?= (int)($editar['activo']??1)===1?'checked':'' ?>><span class="ad-form-switch-track" aria-hidden="true"><span></span></span><span><strong id="popupActiveLabel"><?= (int)($editar['activo']??1)===1?'Activo':'Inactivo' ?></strong><small>Podés suspenderlo manualmente.</small></span></label></div><div class="popup-form-section-title">Destinos del anuncio</div>
<?php foreach ([['Facebook','popupFacebook','facebook_url','https://facebook.com/...'],['Instagram','popupInstagram','instagram_url','https://instagram.com/...'],['WhatsApp','popupWhatsapp','whatsapp_url','https://wa.me/598...'],['Sitio web','popupWeb','sitio_web_url','https://ejemplo.com/']] as [$rotulo,$control,$campo,$placeholder]): ?><div class="form-group"><label for="<?= $control ?>"><?= $rotulo ?></label><input class="form-control" type="url" id="<?= $control ?>" name="<?= $campo ?>" maxlength="500" value="<?= e($editar[$campo]??'') ?>" placeholder="<?= $placeholder ?>"></div><?php endforeach; ?>
<div class="ad-form-errors" id="popupFormErrors" tabindex="-1"<?= $errores?'':' hidden' ?>><strong>Revisá los datos del popup</strong><ul id="popupFormErrorList"><?php foreach($errores as $error):?><li><?= e($error) ?></li><?php endforeach;?></ul></div><button type="submit" class="btn btn-primary popup-save-button" id="popupSubmit"><?= (int)($editar['id']??0)>0?'Guardar cambios':'Guardar popup' ?></button><div class="popup-test-copy">El portal elegirá automáticamente la versión vertical en móvil y la horizontal en PC.</div><div class="popup-test-actions"><button type="button" class="btn btn-outline" id="popupTestVertical">Probar popup vertical</button><button type="button" class="btn btn-outline" id="popupTestHorizontal">Probar popup horizontal</button></div></form></div></aside>
<div class="popup-live-overlay" id="popupLiveOverlay" aria-hidden="true" role="dialog" aria-modal="true"><button type="button" class="popup-live-close" id="popupLiveClose" aria-label="Cerrar prueba"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"></path></svg><span>Cerrar</span></button><div class="popup-live-stage"><img id="popupLiveImage" alt="Prueba visual del popup"></div></div>
<script>
(function(){
const drawer=document.getElementById('popupDrawer'),backdrop=document.getElementById('popupDrawerBackdrop'),form=document.getElementById('popupForm'),detail=document.getElementById('popupDetail'),drawerEdit=document.getElementById('popupDrawerEdit'),submit=document.getElementById('popupSubmit'),errorBox=document.getElementById('popupFormErrors'),errorList=document.getElementById('popupFormErrorList'),active=document.getElementById('popupActive'),activeLabel=document.getElementById('popupActiveLabel'),overlay=document.getElementById('popupLiveOverlay'),liveImage=document.getElementById('popupLiveImage'),liveClose=document.getElementById('popupLiveClose'); let returnFocus=null,currentDetailButton=null;
const images={vertical:{input:document.getElementById('popupVerticalInput'),preview:document.getElementById('popupVerticalPreview'),image:document.getElementById('popupVerticalPreviewImage'),help:document.getElementById('popupVerticalHelp')},horizontal:{input:document.getElementById('popupHorizontalInput'),preview:document.getElementById('popupHorizontalPreview'),image:document.getElementById('popupHorizontalPreviewImage'),help:document.getElementById('popupHorizontalHelp')}}; Object.values(images).forEach(c=>c.src=c.image.getAttribute('src')||'');
const fields={nombre:document.getElementById('popupName'),segundos_aparicion:document.getElementById('popupDelay'),limite_diario_por_visitante:document.getElementById('popupDailyLimit'),fecha_vencimiento:document.getElementById('popupExpires'),facebook_url:document.getElementById('popupFacebook'),instagram_url:document.getElementById('popupInstagram'),whatsapp_url:document.getElementById('popupWhatsapp'),sitio_web_url:document.getElementById('popupWeb'),imagen_vertical:images.vertical.input,imagen_horizontal:images.horizontal.input};
function openDrawer(target,focusTarget){returnFocus=target||document.activeElement;drawer.classList.add('open');backdrop.classList.add('show');drawer.setAttribute('aria-hidden','false');document.body.classList.add('drawer-open');if(focusTarget)setTimeout(()=>focusTarget.focus(),220)} function closeDrawer(){drawer.classList.remove('open');backdrop.classList.remove('show');drawer.setAttribute('aria-hidden','true');document.body.classList.remove('drawer-open');if(returnFocus)returnFocus.focus()}
function clearErrors(){errorBox.hidden=true;errorList.innerHTML='';Object.values(fields).forEach(f=>f&&f.classList.remove('is-invalid'))} function showErrors(errors,map={}){errorList.innerHTML='';errors.forEach(m=>{const li=document.createElement('li');li.textContent=m;errorList.appendChild(li)});Object.keys(map).forEach(k=>fields[k]&&fields[k].classList.add('is-invalid'));errorBox.hidden=false;errorBox.focus()}
function updateTests(){document.getElementById('popupTestVertical').disabled=!images.vertical.src;document.getElementById('popupTestHorizontal').disabled=!images.horizontal.src} function setImage(o,src,text){const c=images[o];c.src=src||'';c.image.src=c.src;c.preview.classList.toggle('has-image',!!c.src);c.help.textContent=text||'JPG, PNG o WEBP, hasta 5 MB.';updateTests()} function readImage(o){const c=images[o],file=c.input.files&&c.input.files[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5242880){c.input.value='';showErrors([file.size>5242880?'La imagen supera el límite de 5 MB.':'La imagen debe ser JPG, PNG o WEBP.'],{['imagen_'+o]:'Imagen inválida'});return}const reader=new FileReader();reader.onload=()=>setImage(o,String(reader.result||''),file.name+' · vista previa lista');reader.onerror=()=>showErrors(['No se pudo leer la imagen.'],{['imagen_'+o]:'Error'});reader.readAsDataURL(file)}
function showForm(){detail.hidden=true;form.hidden=false;drawerEdit.hidden=true} function resetForm(){showForm();currentDetailButton=null;form.reset();document.getElementById('popupId').value='0';fields.segundos_aparicion.value='3';fields.limite_diario_por_visitante.value='1';active.checked=true;activeLabel.textContent='Activo';Object.values(images).forEach(c=>{c.input.required=true;c.input.value=''});setImage('vertical','','JPG, PNG o WEBP, hasta 5 MB. Obligatoria.');setImage('horizontal','','JPG, PNG o WEBP, hasta 5 MB. Obligatoria.');document.getElementById('popupDrawerLabel').textContent='Nuevo popup';document.getElementById('popupDrawerTitle').textContent='Agregar popup';submit.textContent='Guardar popup';clearErrors()}
function loadEdit(b,target){resetForm();document.getElementById('popupId').value=b.dataset.id;fields.nombre.value=b.dataset.name;fields.segundos_aparicion.value=b.dataset.delay;fields.limite_diario_por_visitante.value=b.dataset.daily;fields.fecha_vencimiento.value=b.dataset.expires;fields.facebook_url.value=b.dataset.facebook;fields.instagram_url.value=b.dataset.instagram;fields.whatsapp_url.value=b.dataset.whatsapp;fields.sitio_web_url.value=b.dataset.web;active.checked=b.dataset.active==='1';activeLabel.textContent=active.checked?'Activo':'Inactivo';Object.values(images).forEach(c=>c.input.required=false);setImage('vertical',b.dataset.vertical,'Imagen actual. Elegí otra para reemplazarla.');setImage('horizontal',b.dataset.horizontal,'Imagen actual. Elegí otra para reemplazarla.');document.getElementById('popupDrawerLabel').textContent='Editar popup';document.getElementById('popupDrawerTitle').textContent='Actualizar popup';submit.textContent='Guardar cambios';openDrawer(target||b,fields.nombre)}
function newPopup(e){if(e)e.preventDefault();resetForm();openDrawer(e?e.currentTarget:null,fields.nombre)} function editPopup(e){e.preventDefault();loadEdit(e.currentTarget,e.currentTarget)}
function setDetailText(id,value){const node=document.getElementById(id);node.textContent=value||'Sin configurar';node.classList.toggle('is-empty',!value)}
function setDetailLink(id,value){const node=document.getElementById(id);node.innerHTML='';node.classList.toggle('is-empty',!value);if(!value){node.textContent='Sin configurar';return}const link=document.createElement('a');link.href=value;link.target='_blank';link.rel='noopener noreferrer';link.textContent=value;node.appendChild(link)}
function showDetail(b,target){currentDetailButton=b;form.hidden=true;detail.hidden=false;drawerEdit.hidden=false;document.getElementById('popupDrawerLabel').textContent='Detalle del popup';document.getElementById('popupDrawerTitle').textContent=b.dataset.name;document.getElementById('popupDetailVertical').src=b.dataset.vertical;document.getElementById('popupDetailHorizontal').src=b.dataset.horizontal;const status=document.getElementById('popupDetailStatus');status.className='ad-status '+(b.dataset.active==='1'?'is-current':'is-inactive');status.querySelector('b').textContent=b.dataset.status;setDetailText('popupDetailId','#'+b.dataset.id);setDetailText('popupDetailName',b.dataset.name);setDetailText('popupDetailDelay',b.dataset.delay+' segundos');setDetailText('popupDetailDaily',b.dataset.daily+' por usuario');setDetailText('popupDetailExpires',b.dataset.expiresLabel);setDetailLink('popupDetailFacebook',b.dataset.facebook);setDetailLink('popupDetailInstagram',b.dataset.instagram);setDetailLink('popupDetailWhatsapp',b.dataset.whatsapp);setDetailLink('popupDetailWeb',b.dataset.web);setDetailText('popupDetailClicks',Number(b.dataset.clicks||0).toLocaleString('es-UY'));setDetailText('popupDetailCreated',b.dataset.created);setDetailText('popupDetailUpdated',b.dataset.updated);openDrawer(target,null);setTimeout(()=>drawerEdit.focus(),220)}
function viewPopup(e){const trigger=e.currentTarget,edit=trigger.closest('tr').querySelector('.js-edit-popup');if(edit)showDetail(edit,trigger)}
function openTest(o,src){const imageSource=src||images[o].src;if(!imageSource)return;liveImage.src=imageSource;overlay.dataset.orientation=o;overlay.classList.add('open');overlay.setAttribute('aria-hidden','false');document.body.classList.add('popup-preview-open');setTimeout(()=>liveClose.focus(),80)} function closeTest(){overlay.classList.remove('open');overlay.setAttribute('aria-hidden','true');document.body.classList.remove('popup-preview-open');liveImage.removeAttribute('src')}
images.vertical.input.addEventListener('change',()=>readImage('vertical'));images.horizontal.input.addEventListener('change',()=>readImage('horizontal'));active.addEventListener('change',()=>activeLabel.textContent=active.checked?'Activo':'Inactivo');document.querySelector('.js-new-popup').addEventListener('click',newPopup);document.querySelectorAll('.js-edit-popup').forEach(b=>b.addEventListener('click',editPopup));document.querySelectorAll('.js-view-popup').forEach(b=>b.addEventListener('click',viewPopup));drawerEdit.addEventListener('click',()=>{if(currentDetailButton)loadEdit(currentDetailButton,returnFocus)});document.getElementById('popupDrawerClose').addEventListener('click',closeDrawer);backdrop.addEventListener('click',closeDrawer);document.getElementById('popupTestVertical').addEventListener('click',()=>openTest('vertical'));document.getElementById('popupTestHorizontal').addEventListener('click',()=>openTest('horizontal'));document.getElementById('popupDetailTestVertical').addEventListener('click',()=>currentDetailButton&&openTest('vertical',currentDetailButton.dataset.vertical));document.getElementById('popupDetailTestHorizontal').addEventListener('click',()=>currentDetailButton&&openTest('horizontal',currentDetailButton.dataset.horizontal));liveClose.addEventListener('click',closeTest);overlay.addEventListener('click',e=>{if(e.target===overlay)closeTest()});document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(overlay.classList.contains('open'))closeTest();else if(drawer.classList.contains('open'))closeDrawer()}});
const search=document.getElementById('popupsSearch');if(search)search.addEventListener('input',()=>{const term=search.value.trim().toLocaleLowerCase('es');let count=0;document.querySelectorAll('.popup-row').forEach(row=>{const match=row.dataset.searchName.toLocaleLowerCase('es').includes(term);row.hidden=!match;if(match)count++});document.getElementById('popupsSearchCount').textContent=count;document.getElementById('popupsSearchLabel').textContent=count===1?' popup':' popups';document.getElementById('popupsFilterEmpty').hidden=count!==0});
document.querySelectorAll('.js-popup-status-form').forEach(sf=>{const input=sf.querySelector('.js-popup-status-input'),text=sf.querySelector('.ad-status-switch-text'),feedback=sf.querySelector('.ad-status-feedback');sf.addEventListener('submit',e=>e.preventDefault());input.addEventListener('change',async()=>{const wanted=input.checked;input.disabled=true;feedback.textContent='Guardando…';const data=new FormData(sf);data.set('activo',wanted?'1':'0');try{const response=await fetch(sf.action,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'}),result=await response.json();if(!response.ok||!result.ok)throw new Error();input.checked=result.activo===1;text.textContent=result.label;feedback.textContent=result.notice;const edit=sf.closest('tr').querySelector('.js-edit-popup');if(edit){edit.dataset.active=input.checked?'1':'0';edit.dataset.status=result.label;if(currentDetailButton===edit)showDetail(edit,returnFocus)}}catch(err){input.checked=!wanted;text.textContent=input.checked?'Activo':'Inactivo';feedback.textContent='No se pudo guardar'}finally{input.disabled=false;setTimeout(()=>feedback.textContent='',2400)}})});
form.addEventListener('submit',async e=>{e.preventDefault();clearErrors();const old=submit.textContent;submit.disabled=true;submit.textContent='Guardando…';try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'}),result=await response.json();if(!response.ok||!result.ok){showErrors(result.errors||[result.error||'No se pudo guardar el popup.'],result.fields||{});return}location.href=result.redirect||'popups.php'}catch(err){showErrors(['No pudimos guardar el popup. Verificá la conexión e intentá nuevamente.'])}finally{submit.disabled=false;submit.textContent=old}});updateTests();<?php if($editar!==null||isset($_GET['nuevo'])):?>openDrawer();<?php endif;?><?php if($errores):?>showErrors(<?= json_encode(array_values($errores),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,<?= json_encode($camposError,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>);<?php endif;?>
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
