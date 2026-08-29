<?php
/**
 * Migración idempotente para seleccionar un anuncio de encabezado y uno de pie.
 * Uso exclusivo por CLI y con entorno explícito:
 *   php install/publicidad-v4.php --environment=development
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
$columnas = [
    'en_encabezado' => 'activo',
    'en_pie' => 'en_encabezado',
];
$stmtColumna = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
foreach ($columnas as $columna => $despuesDe) {
    $stmtColumna->execute(['anuncios', $columna]);
    if ((int) $stmtColumna->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE anuncios ADD COLUMN $columna TINYINT(1) NOT NULL DEFAULT 0 AFTER $despuesDe");
        echo "- anuncios.$columna agregado.\n";
    } else {
        echo "- anuncios.$columna ya existe, se omite.\n";
    }
}

$indices = [
    'idx_anuncios_encabezado' => 'en_encabezado',
    'idx_anuncios_pie' => 'en_pie',
];
$stmtIndice = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
);
foreach ($indices as $indice => $columna) {
    $stmtIndice->execute(['anuncios', $indice]);
    if ((int) $stmtIndice->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE anuncios ADD KEY $indice ($columna)");
        echo "- $indice agregado.\n";
    } else {
        echo "- $indice ya existe, se omite.\n";
    }
}

// Repara estados antiguos o manipulados: conserva solo el registro más nuevo
// en cada ubicación para respetar el contrato de selección exclusiva.
foreach (array_values($indices) as $columna) {
    $ids = $pdo->query("SELECT id FROM anuncios WHERE $columna = 1 ORDER BY updated_at DESC, id DESC")->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) > 1) {
        $conservar = (int) array_shift($ids);
        $stmt = $pdo->prepare("UPDATE anuncios SET $columna = 0 WHERE $columna = 1 AND id <> ?");
        $stmt->execute([$conservar]);
    }
}

$encabezados = (int) $pdo->query('SELECT COUNT(*) FROM anuncios WHERE en_encabezado = 1')->fetchColumn();
$pies = (int) $pdo->query('SELECT COUNT(*) FROM anuncios WHERE en_pie = 1')->fetchColumn();
echo "[OK] Publicidad v4 aplicada en $entorno: $encabezados encabezado y $pies pie.\n";
