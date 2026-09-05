<?php
/**
 * Inicializa el nombre editorial público. Solo CLI e idempotente.
 * Uso: php install/identidad-sitio-v1.php --environment=development --site-name="CardonaHoy"
 * Sin manifiesto privado, DEV exige además --confirm-instance=<instancia>.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$entorno = null;
$nombreSitio = null;
$instanciaConfirmada = null;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--environment=')) $entorno = substr($argumento, 14);
    if (str_starts_with($argumento, '--site-name=')) $nombreSitio = substr($argumento, 12);
    if (str_starts_with($argumento, '--confirm-instance=')) $instanciaConfirmada = substr($argumento, 19);
}
if (!in_array($entorno, ['development', 'production'], true)) {
    fwrite(STDERR, "[ERROR] Indicá --environment=development o --environment=production.\n");
    exit(2);
}
$nombreSitio = trim((string) $nombreSitio);
if (mb_strlen($nombreSitio, 'UTF-8') < 2 || mb_strlen($nombreSitio, 'UTF-8') > 120) {
    fwrite(STDERR, "[ERROR] Indicá --site-name con un nombre de 2 a 120 caracteres.\n");
    exit(3);
}

require_once __DIR__ . '/../admin/config.php';

$serviciosRuta = dirname(__DIR__) . '/servicios.local.json';
if (is_file($serviciosRuta) && is_readable($serviciosRuta)) {
    $servicios = json_decode((string) file_get_contents($serviciosRuta), true);
    $destino = $servicios['databases'][$entorno] ?? null;
    if (!is_array($destino)
        || ($destino['host'] ?? '') !== DB_HOST
        || ($destino['name'] ?? '') !== DB_NAME
        || ($destino['username'] ?? '') !== DB_USER) {
        fwrite(STDERR, "[ERROR] El runtime local no corresponde a databases.$entorno.\n");
        exit(4);
    }
} else {
    if ($entorno !== 'development'
        || !defined('PORTAL_INSTANCE_ID')
        || $instanciaConfirmada !== (string) PORTAL_INSTANCE_ID) {
        fwrite(STDERR, "[ERROR] Sin servicios.local.json solo se admite DEV confirmando --confirm-instance=<instancia>.\n");
        exit(5);
    }
    echo '[AVISO] DEV validado por la identidad local ' . PORTAL_INSTANCE_ID . "; PROD continúa bloqueado sin manifiesto.\n";
}

$pdo = db();
$guardar = $pdo->prepare(
    'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE valor=valor'
);
$guardar->execute(['nombre_sitio', $nombreSitio]);
$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'nombre_sitio' LIMIT 1");
$stmt->execute();
$nombreGuardado = trim((string) $stmt->fetchColumn());
if ($nombreGuardado === '') {
    fwrite(STDERR, "[ERROR] No se pudo verificar nombre_sitio.\n");
    exit(6);
}

echo '[OK] Identidad del sitio disponible en ' . $entorno . ': ' . $nombreGuardado . ".\n";
