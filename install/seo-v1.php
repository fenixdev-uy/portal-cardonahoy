<?php
/** Migración SEO v1. Solo CLI e idempotente. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/includes/funciones.php';
$pdo = db();

function columna_existe_seo(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$tabla, $columna]);
    return (int) $stmt->fetchColumn() > 0;
}

function indice_existe_seo(PDO $pdo, string $tabla, string $indice): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$tabla, $indice]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "=== SEO de noticias v1 ===\n";
$columnas = [
    'slug' => 'VARCHAR(190) NULL AFTER titulo',
    'seo_titulo' => 'VARCHAR(255) NULL AFTER descripcion',
    'seo_descripcion' => 'VARCHAR(500) NULL AFTER seo_titulo',
    'seo_imagen' => 'VARCHAR(255) NULL AFTER seo_descripcion',
];
foreach ($columnas as $columna => $definicion) {
    if (columna_existe_seo($pdo, 'noticias', $columna)) {
        echo "- noticias.$columna ya existe, se omite.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE noticias ADD COLUMN $columna $definicion");
    echo "- noticias.$columna agregada.\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS noticias_slugs_historial (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  noticia_id INT UNSIGNED NOT NULL,
  slug VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_noticias_slugs_historial_slug (slug),
  KEY idx_noticias_slugs_historial_noticia (noticia_id),
  CONSTRAINT fk_noticias_slugs_historial_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "- tabla noticias_slugs_historial lista.\n";

$noticias = $pdo->query("SELECT id, titulo FROM noticias WHERE slug IS NULL OR slug = '' ORDER BY id")->fetchAll();
$actualizar = $pdo->prepare('UPDATE noticias SET slug=?, updated_at=updated_at WHERE id=?');
foreach ($noticias as $noticia) {
    $slug = generar_slug_noticia_unico($pdo, (string) $noticia['titulo'], (int) $noticia['id']);
    $actualizar->execute([$slug, (int) $noticia['id']]);
    echo '- slug generado para noticia #' . (int) $noticia['id'] . ".\n";
}

if (!indice_existe_seo($pdo, 'noticias', 'uq_noticias_slug')) {
    $pdo->exec('ALTER TABLE noticias ADD UNIQUE KEY uq_noticias_slug (slug)');
    echo "- índice único uq_noticias_slug agregado.\n";
}
$pdo->exec('ALTER TABLE noticias MODIFY slug VARCHAR(190) NOT NULL');
echo "Listo. Los overrides SEO permanecen NULL hasta que el redactor los personalice.\n";
