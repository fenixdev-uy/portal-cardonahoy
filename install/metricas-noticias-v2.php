<?php
/**
 * Agrega el historial diario de clics de compartir para análisis por fechas.
 * Uso: php install/metricas-noticias-v2.php --environment=development
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
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS noticias_compartidos_diarios (
       noticia_id INT UNSIGNED NOT NULL,
       fecha DATE NOT NULL,
       destino VARCHAR(20) NOT NULL,
       cantidad INT UNSIGNED NOT NULL DEFAULT 0,
       updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (noticia_id, fecha, destino),
       KEY idx_noticias_compartidos_fecha (fecha),
       CONSTRAINT fk_noticias_compartidos_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$total = (int) $pdo->query('SELECT COALESCE(SUM(cantidad), 0) FROM noticias_compartidos_diarios')->fetchColumn();
echo "[OK] Métricas de noticias v2 aplicada en $entorno: $total compartidos con fecha.\n";
