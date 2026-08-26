<?php
/**
 * Registra un clic público sobre un destino del anuncio y redirige a la URL
 * guardada. El destino nunca se acepta desde el navegador para evitar redirects
 * abiertos y métricas sobre enlaces inexistentes.
 */
require_once __DIR__ . '/admin/includes/funciones.php';

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
    exit('Enlace publicitario no disponible.');
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
    http_response_code(404);
    exit('Enlace publicitario no disponible.');
}

$vencido = !empty($anuncio['fecha_vencimiento']) && (string) $anuncio['fecha_vencimiento'] <= $hoy;
if ($vencido && (int) $anuncio['activo'] === 1) {
    $pdo->prepare('UPDATE anuncios SET activo = 0 WHERE id = ? AND activo = 1')->execute([(int) $id]);
}

$url = trim((string) ($anuncio['url'] ?? ''));
$esUrlSegura = filter_var($url, FILTER_VALIDATE_URL) !== false
    && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
if ((int) $anuncio['activo'] !== 1 || $vencido || !$esUrlSegura) {
    http_response_code(404);
    exit('Enlace publicitario no disponible.');
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
    http_response_code(404);
    exit('Enlace publicitario no disponible.');
}

header('Cache-Control: no-store, private');
header('Location: ' . $url, true, 302);
exit;
