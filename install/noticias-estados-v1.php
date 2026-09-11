<?php
/**
 * Estados editoriales y fecha real de publicación. Solo CLI e idempotente.
 *
 * Uso:
 *   php install/noticias-estados-v1.php --environment=development
 *   php install/noticias-estados-v1.php --environment=production
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
$servicios = json_decode((string) @file_get_contents($serviciosRuta), true);
$destino = $servicios['databases'][$entorno] ?? null;
if (!is_array($destino)
    || ($destino['host'] ?? '') !== DB_HOST
    || ($destino['name'] ?? '') !== DB_NAME
    || ($destino['username'] ?? '') !== DB_USER) {
    fwrite(STDERR, "[ERROR] El runtime local no corresponde a databases.$entorno.\n");
    exit(3);
}

$pdo = db();
$columnas = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias'"
)->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('estado', $columnas, true)) {
    $pdo->exec("ALTER TABLE noticias ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'publicada' AFTER portada");
    echo "- noticias.estado agregado; las noticias existentes permanecen publicadas.\n";
} else {
    echo "- noticias.estado ya existe, se omite.\n";
}

if (!in_array('publicada_at', $columnas, true)) {
    $pdo->exec('ALTER TABLE noticias ADD COLUMN publicada_at DATETIME NULL AFTER estado');
    echo "- noticias.publicada_at agregado.\n";
} else {
    echo "- noticias.publicada_at ya existe, se omite.\n";
}

$pdo->exec(
    "UPDATE noticias
        SET estado = 'publicada',
            publicada_at = COALESCE(publicada_at, created_at),
            updated_at = updated_at
      WHERE estado = 'publicada' OR estado IS NULL OR estado = ''"
);
$pdo->exec("UPDATE noticias SET portada = 0, updated_at = updated_at WHERE estado <> 'publicada'");
$pdo->exec("ALTER TABLE noticias ALTER COLUMN estado SET DEFAULT 'borrador'");

$indices = $pdo->query(
    "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias'
      GROUP BY INDEX_NAME"
)->fetchAll(PDO::FETCH_KEY_PAIR);
if (($indices['idx_noticias_estado_fecha'] ?? '') !== 'estado,publicada_at,id') {
    if (isset($indices['idx_noticias_estado_fecha'])) {
        $pdo->exec('ALTER TABLE noticias DROP KEY idx_noticias_estado_fecha');
    }
    $pdo->exec('ALTER TABLE noticias ADD KEY idx_noticias_estado_fecha (estado, publicada_at, id)');
    echo "- índice idx_noticias_estado_fecha agregado.\n";
}
if (($indices['idx_noticias_portada_fecha'] ?? '') !== 'portada,estado,publicada_at,id') {
    if (isset($indices['idx_noticias_portada_fecha'])) {
        $pdo->exec('ALTER TABLE noticias DROP KEY idx_noticias_portada_fecha');
    }
    $pdo->exec('ALTER TABLE noticias ADD KEY idx_noticias_portada_fecha (portada, estado, publicada_at, id)');
    echo "- índice idx_noticias_portada_fecha actualizado.\n";
}

$total = (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE estado = 'publicada'")->fetchColumn();
echo "[OK] Estados de noticias v1 aplicados en $entorno: $total publicaciones existentes preservadas.\n";
