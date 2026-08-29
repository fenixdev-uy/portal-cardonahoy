<?php
/**
 * Migración idempotente para la administración y frecuencia de popups.
 * Uso exclusivo por CLI y con entorno explícito:
 *   php install/popups-v1.php --environment=development
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
    "CREATE TABLE IF NOT EXISTS popups (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      nombre VARCHAR(120) NOT NULL,
      imagen_vertical VARCHAR(255) NOT NULL,
      imagen_horizontal VARCHAR(255) NOT NULL,
      facebook_url VARCHAR(500) NULL,
      instagram_url VARCHAR(500) NULL,
      whatsapp_url VARCHAR(500) NULL,
      sitio_web_url VARCHAR(500) NULL,
      fecha_vencimiento DATE NULL,
      activo TINYINT(1) NOT NULL DEFAULT 1,
      segundos_aparicion SMALLINT UNSIGNED NOT NULL DEFAULT 3,
      limite_diario_por_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 1,
      clics INT UNSIGNED NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_popups_vencimiento (fecha_vencimiento),
      KEY idx_popups_publicacion (activo, fecha_vencimiento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS popups_impresiones (
      popup_id INT UNSIGNED NOT NULL,
      visitante CHAR(36) NOT NULL,
      fecha DATE NOT NULL,
      cantidad SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      ultima_impresion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (popup_id, visitante, fecha),
      KEY idx_popups_impresiones_fecha (fecha),
      KEY idx_popups_impresiones_visitante (visitante, fecha),
      CONSTRAINT fk_popups_impresiones_popup
        FOREIGN KEY (popup_id) REFERENCES popups(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$totalPopups = (int) $pdo->query('SELECT COUNT(*) FROM popups')->fetchColumn();
$totalImpresiones = (int) $pdo->query('SELECT COUNT(*) FROM popups_impresiones')->fetchColumn();

echo "[OK] Popups v1 aplicada en $entorno: $totalPopups popups y $totalImpresiones contadores diarios.\n";
