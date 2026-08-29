<?php
/**
 * Migración idempotente para asignar varias categorías a una noticia.
 *
 * Conserva noticias.categoria_id como categoría principal para mantener
 * compatibilidad y copia cada relación existente a noticias_categorias.
 *
 * Uso exclusivo por CLI y con entorno explícito:
 *   php install/categorias-multiples-v1.php --environment=development
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
    'CREATE TABLE IF NOT EXISTS noticias_categorias (
       noticia_id INT UNSIGNED NOT NULL,
       categoria_id INT UNSIGNED NOT NULL,
       posicion SMALLINT UNSIGNED NOT NULL DEFAULT 0,
       PRIMARY KEY (noticia_id, categoria_id),
       KEY idx_noticias_categorias_categoria (categoria_id, noticia_id),
       CONSTRAINT fk_nc_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE,
       CONSTRAINT fk_nc_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE ON UPDATE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$pdo->exec(
    'INSERT INTO noticias_categorias (noticia_id, categoria_id, posicion)
     SELECT id, categoria_id, 0
       FROM noticias
      WHERE categoria_id IS NOT NULL
     ON DUPLICATE KEY UPDATE posicion = LEAST(posicion, VALUES(posicion))'
);

$totalNoticias = (int) $pdo->query('SELECT COUNT(*) FROM noticias')->fetchColumn();
$totalRelaciones = (int) $pdo->query('SELECT COUNT(*) FROM noticias_categorias')->fetchColumn();
$sinMigrar = (int) $pdo->query(
    'SELECT COUNT(*) FROM noticias n
      WHERE n.categoria_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM noticias_categorias nc
             WHERE nc.noticia_id = n.id AND nc.categoria_id = n.categoria_id
        )'
)->fetchColumn();

if ($sinMigrar !== 0) {
    fwrite(STDERR, "[ERROR] Quedaron $sinMigrar categorías principales sin migrar.\n");
    exit(4);
}

echo "[OK] Categorías múltiples v1 aplicada en $entorno: $totalNoticias noticias y $totalRelaciones relaciones.\n";
