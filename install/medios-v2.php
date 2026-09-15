<?php
/**
 * Títulos opcionales para los tres audios de cada noticia. Solo CLI e idempotente.
 *
 * Uso:
 *   php install/medios-v2.php --environment=development
 *   php install/medios-v2.php --environment=production
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

$servicios = json_decode((string) @file_get_contents(dirname(__DIR__) . '/servicios.local.json'), true);
$destino = $servicios['databases'][$entorno] ?? null;
$puertoRuntime = defined('DB_PORT') ? (int) DB_PORT : 3306;
if (!is_array($destino)
    || ($destino['configured'] ?? true) !== true
    || ($destino['host'] ?? '') !== DB_HOST
    || (int) ($destino['port'] ?? 3306) !== $puertoRuntime
    || ($destino['name'] ?? '') !== DB_NAME
    || ($destino['username'] ?? '') !== DB_USER) {
    fwrite(STDERR, "[ERROR] El runtime local no corresponde a databases.$entorno.\n");
    exit(3);
}

$pdo = db();
$columnas = [
    'audio_titulo_1' => 'VARCHAR(180) NULL AFTER audio_1',
    'audio_titulo_2' => 'VARCHAR(180) NULL AFTER audio_2',
    'audio_titulo_3' => 'VARCHAR(180) NULL AFTER audio_3',
];

$existe = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
foreach ($columnas as $columna => $definicion) {
    $existe->execute(['noticias', $columna]);
    if ((int) $existe->fetchColumn() > 0) {
        echo "- noticias.$columna ya existe, se omite.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE noticias ADD COLUMN $columna $definicion");
    echo "- noticias.$columna agregada.\n";
}

$verificar = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias'
        AND COLUMN_NAME IN ('audio_titulo_1', 'audio_titulo_2', 'audio_titulo_3')
        AND DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH = 180 AND IS_NULLABLE = 'YES'"
)->fetchColumn();
if ((int) $verificar !== 3) {
    fwrite(STDERR, "[ERROR] La estructura de títulos de audio no quedó completa.\n");
    exit(4);
}

$noticias = (int) $pdo->query('SELECT COUNT(*) FROM noticias')->fetchColumn();
$titulos = (int) $pdo->query(
    "SELECT COUNT(*) FROM noticias
      WHERE COALESCE(audio_titulo_1, '') <> ''
         OR COALESCE(audio_titulo_2, '') <> ''
         OR COALESCE(audio_titulo_3, '') <> ''"
)->fetchColumn();
echo "[OK] Medios v2 aplicado en $entorno: 3 columnas, $noticias noticias preservadas y $titulos con títulos de audio.\n";
