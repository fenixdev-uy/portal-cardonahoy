<?php
/**
 * Agrega el token que permite una sola sesión activa por usuario.
 * Uso: php install/sesion-unica-v1.php --environment=development
 * Sin manifiesto privado, DEV exige además --confirm-instance=<instancia>.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$entorno = null;
$instanciaConfirmada = null;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--environment=')) $entorno = substr($argumento, 14);
    if (str_starts_with($argumento, '--confirm-instance=')) $instanciaConfirmada = substr($argumento, 19);
}
if (!in_array($entorno, ['development', 'production'], true)) {
    fwrite(STDERR, "[ERROR] Indicá --environment=development o --environment=production.\n");
    exit(2);
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
        exit(3);
    }
    echo "[AVISO] DEV validado por la identidad local " . PORTAL_INSTANCE_ID . "; PROD continúa bloqueado sin manifiesto.\n";
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$stmt->execute(['usuarios', 'sesion_token_hash']);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN sesion_token_hash CHAR(64) NULL AFTER ultimo_acceso_at');
    echo "- usuarios.sesion_token_hash agregado.\n";
} else {
    echo "- usuarios.sesion_token_hash ya existe, se omite.\n";
}

$invalidas = (int) $pdo->query(
    "SELECT COUNT(*) FROM usuarios
      WHERE sesion_token_hash IS NOT NULL
        AND (CHAR_LENGTH(sesion_token_hash) <> 64 OR sesion_token_hash REGEXP '[^0-9a-f]')"
)->fetchColumn();
if ($invalidas !== 0) {
    fwrite(STDERR, "[ERROR] Se detectaron tokens de sesión con formato inválido.\n");
    exit(5);
}

echo "[OK] Sesión única v1 aplicada en $entorno; las sesiones previas deberán volver a ingresar.\n";
