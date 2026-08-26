<?php
/**
 * Migración idempotente para el estado manual de los anuncios.
 * Uso exclusivo por CLI y con entorno explícito:
 *   php install/publicidad-v2.php --environment=development
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$entorno = null;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--environment=')) $entorno = substr($argumento, 14);
}
if (!in_array($entorno, ['development', 'production'], true)) {
    fwrite(STDERR, "[ERROR] Indicá --environment=development o --environment=production.\n");
    exit(2);
}

require_once __DIR__ . '/../admin/config.php';

$serviciosRuta = dirname(__DIR__) . '/servicios.local.json';
$servicios = json_decode((string) file_get_contents($serviciosRuta), true);
$destino = $servicios['databases'][$entorno] ?? null;
if (!is_array($destino)
    || ($destino['host'] ?? '') !== DB_HOST
    || ($destino['name'] ?? '') !== DB_NAME
    || ($destino['username'] ?? '') !== DB_USER) {
    fwrite(STDERR, "[ERROR] El runtime local no corresponde a databases.$entorno.\n");
    exit(3);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$stmt->execute(['anuncios', 'activo']);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE anuncios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER fecha_vencimiento');
    echo "- anuncios.activo agregado con valor inicial activo.\n";
} else {
    echo "- anuncios.activo ya existe, se omite.\n";
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
);
$stmt->execute(['anuncios', 'idx_anuncios_publicacion']);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE anuncios ADD KEY idx_anuncios_publicacion (activo, fecha_vencimiento)');
    echo "- idx_anuncios_publicacion agregado.\n";
} else {
    echo "- idx_anuncios_publicacion ya existe, se omite.\n";
}

$activos = (int) $pdo->query('SELECT COUNT(*) FROM anuncios WHERE activo = 1')->fetchColumn();
$total = (int) $pdo->query('SELECT COUNT(*) FROM anuncios')->fetchColumn();
echo "[OK] Publicidad v2 aplicada en $entorno: $activos de $total anuncios activos.\n";
