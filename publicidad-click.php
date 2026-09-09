<?php
/** Registra únicamente activaciones reales de los enlaces publicitarios. */
require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('json');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function responder_clic_publicidad(array $datos, int $estado = 200): never
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($metodo !== 'POST') {
    header('Allow: POST');
    responder_clic_publicidad(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$solicitudAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$origenFetch = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if (!$solicitudAjax || ($origenFetch !== '' && $origenFetch !== 'same-origin')) {
    responder_clic_publicidad(['ok' => false, 'error' => 'Solicitud no válida.'], 400);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$destino = (string) ($_POST['destino'] ?? '');
$camposPermitidos = [
    'facebook_url' => 'facebook_url',
    'instagram_url' => 'instagram_url',
    'whatsapp_url' => 'whatsapp_url',
    'sitio_web_url' => 'sitio_web_url',
];

if ($id === false || $id === null || !isset($camposPermitidos[$destino])) {
    responder_clic_publicidad(['ok' => false, 'error' => 'Enlace publicitario no disponible.'], 404);
}

$pdo = db();
$hoy = date('Y-m-d');
$campoUrl = $camposPermitidos[$destino];
$stmt = $pdo->prepare(
    "SELECT $campoUrl AS url, activo, fecha_vencimiento
       FROM anuncios
      WHERE id = ?"
);
$stmt->execute([(int) $id]);
$anuncio = $stmt->fetch();

if (!$anuncio) {
    responder_clic_publicidad(['ok' => false, 'error' => 'Enlace publicitario no disponible.'], 404);
}

$vencido = !empty($anuncio['fecha_vencimiento']) && (string) $anuncio['fecha_vencimiento'] <= $hoy;
if ($vencido && (int) $anuncio['activo'] === 1) {
    $pdo->prepare('UPDATE anuncios SET activo = 0 WHERE id = ? AND activo = 1')->execute([(int) $id]);
}

$url = trim((string) ($anuncio['url'] ?? ''));
$esUrlSegura = filter_var($url, FILTER_VALIDATE_URL) !== false
    && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
if ((int) $anuncio['activo'] !== 1 || $vencido || !$esUrlSegura) {
    responder_clic_publicidad(['ok' => false, 'error' => 'Enlace publicitario no disponible.'], 404);
}

$stmt = $pdo->prepare(
    'UPDATE anuncios
        SET clics = clics + 1,
            updated_at = updated_at
      WHERE id = ?
        AND activo = 1
        AND (fecha_vencimiento IS NULL OR fecha_vencimiento > ?)'
);
$stmt->execute([(int) $id, $hoy]);
if ($stmt->rowCount() !== 1) {
    responder_clic_publicidad(['ok' => false, 'error' => 'Enlace publicitario no disponible.'], 404);
}

responder_clic_publicidad(['ok' => true]);
