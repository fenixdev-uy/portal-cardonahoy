<?php
/**
 * Agrega vistas unicas diarias y clics de compartir a cada noticia.
 * Uso: php install/metricas-noticias-v1.php --environment=development
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
$columnas = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'noticias'"
)->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('vistas', $columnas, true)) {
    $pdo->exec('ALTER TABLE noticias ADD COLUMN vistas INT UNSIGNED NOT NULL DEFAULT 0 AFTER no_me_gusta');
}
if (!in_array('compartidos', $columnas, true)) {
    $pdo->exec('ALTER TABLE noticias ADD COLUMN compartidos INT UNSIGNED NOT NULL DEFAULT 0 AFTER vistas');
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS noticias_vistas (
       noticia_id INT UNSIGNED NOT NULL,
       visitante CHAR(36) NOT NULL,
       fecha DATE NOT NULL,
       created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (noticia_id, visitante, fecha),
       KEY idx_noticias_vistas_fecha (fecha),
       KEY idx_noticias_vistas_visitante (visitante, fecha),
       CONSTRAINT fk_noticias_vistas_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$totalNoticias = (int) $pdo->query('SELECT COUNT(*) FROM noticias')->fetchColumn();
$totalVistas = (int) $pdo->query('SELECT COALESCE(SUM(vistas), 0) FROM noticias')->fetchColumn();
$totalCompartidos = (int) $pdo->query('SELECT COALESCE(SUM(compartidos), 0) FROM noticias')->fetchColumn();

echo "[OK] Métricas de noticias v1 aplicada en $entorno: $totalNoticias noticias, $totalVistas vistas y $totalCompartidos compartidos.\n";
