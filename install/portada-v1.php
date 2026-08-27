<?php
/**
 * Migración Portada de noticias v1. Solo CLI e idempotente.
 *
 * Agrega el indicador que decide qué noticias aparecen en el slider del
 * encabezado. En la primera ejecución destaca las tres noticias más recientes
 * que tengan al menos una foto, para reemplazar el contenido estático sin dejar
 * vacío el slider. No altera updated_at.
 *
 * Uso:
 *   php install/portada-v1.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();

function columna_existe_portada(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$tabla, $columna]);
    return (int) $stmt->fetchColumn() > 0;
}

function indice_existe_portada(PDO $pdo, string $tabla, string $indice): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$tabla, $indice]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "=== Portada de noticias v1 ===\n";

$columnaAgregada = false;
if (columna_existe_portada($pdo, 'noticias', 'portada')) {
    echo "- noticias.portada ya existe, se omite.\n";
} else {
    $pdo->exec('ALTER TABLE noticias ADD COLUMN portada TINYINT(1) NOT NULL DEFAULT 0 AFTER no_me_gusta');
    $columnaAgregada = true;
    echo "- noticias.portada agregada.\n";
}

if (!indice_existe_portada($pdo, 'noticias', 'idx_noticias_portada_fecha')) {
    $pdo->exec('ALTER TABLE noticias ADD KEY idx_noticias_portada_fecha (portada, created_at, id)');
    echo "- índice idx_noticias_portada_fecha agregado.\n";
} else {
    echo "- índice idx_noticias_portada_fecha ya existe, se omite.\n";
}

if ($columnaAgregada) {
    $pdo->exec(
        'UPDATE noticias
            SET portada = 1, updated_at = updated_at
          WHERE id IN (
                SELECT id FROM (
                    SELECT n.id
                      FROM noticias n
                     WHERE EXISTS (
                           SELECT 1 FROM noticias_fotos f WHERE f.noticia_id = n.id
                     )
                     ORDER BY n.created_at DESC, n.id DESC
                     LIMIT 3
                ) AS noticias_iniciales
          )'
    );
    echo '- ' . $pdo->query('SELECT COUNT(*) FROM noticias WHERE portada = 1')->fetchColumn()
        . " noticias iniciales marcadas para el slider.\n";
}

echo "Listo. El slider se administra con noticias.portada.\n";
