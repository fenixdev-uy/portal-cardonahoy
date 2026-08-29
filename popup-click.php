<?php
/** Registra el clic sobre un destino habilitado del popup y redirige con seguridad. */
declare(strict_types=1);

require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$destino = (string) ($_GET['destino'] ?? '');
$camposPermitidos = [
    'facebook_url' => 'facebook_url',
    'instagram_url' => 'instagram_url',
    'whatsapp_url' => 'whatsapp_url',
    'sitio_web_url' => 'sitio_web_url',
];

if ($id === false || $id === null || !isset($camposPermitidos[$destino])) {
    http_response_code(404);
    exit('Enlace del popup no disponible.');
}

$pdo = db();
$hoy = date('Y-m-d');
$campoUrl = $camposPermitidos[$destino];
$stmt = $pdo->prepare("SELECT $campoUrl AS url, activo, fecha_vencimiento FROM popups WHERE id = ?");
$stmt->execute([(int) $id]);
$popup = $stmt->fetch();

if (!$popup) {
    http_response_code(404);
    exit('Enlace del popup no disponible.');
}

$vencido = !empty($popup['fecha_vencimiento']) && (string) $popup['fecha_vencimiento'] <= $hoy;
if ($vencido && (int) $popup['activo'] === 1) {
    $pdo->prepare('UPDATE popups SET activo = 0 WHERE id = ? AND activo = 1')->execute([(int) $id]);
}

$url = trim((string) ($popup['url'] ?? ''));
$esUrlSegura = filter_var($url, FILTER_VALIDATE_URL) !== false
    && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
if ((int) $popup['activo'] !== 1 || $vencido || !$esUrlSegura) {
    http_response_code(404);
    exit('Enlace del popup no disponible.');
}

$stmt = $pdo->prepare(
    'UPDATE popups
        SET clics = clics + 1,
            updated_at = updated_at
      WHERE id = ?
        AND activo = 1
        AND (fecha_vencimiento IS NULL OR fecha_vencimiento > ?)'
);
$stmt->execute([(int) $id, $hoy]);
if ($stmt->rowCount() !== 1) {
    http_response_code(404);
    exit('Enlace del popup no disponible.');
}

header('Cache-Control: no-store, private');
header('Location: ' . $url, true, 302);
exit;
