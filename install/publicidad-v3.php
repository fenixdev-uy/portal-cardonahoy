<?php
/**
 * Migración idempotente para el contador interno de clics publicitarios.
 * Uso exclusivo por CLI y con entorno explícito:
 *   php install/publicidad-v3.php --environment=development
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
$stmt->execute(['anuncios', 'clics']);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE anuncios ADD COLUMN clics INT UNSIGNED NOT NULL DEFAULT 0 AFTER activo');
    echo "- anuncios.clics agregado con valor inicial 0.\n";
} else {
    echo "- anuncios.clics ya existe, se omite.\n";
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM anuncios')->fetchColumn();
$clics = (int) $pdo->query('SELECT COALESCE(SUM(clics), 0) FROM anuncios')->fetchColumn();
echo "[OK] Publicidad v3 aplicada en $entorno: $total anuncios y $clics clics acumulados.\n";
