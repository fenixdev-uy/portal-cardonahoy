<?php
/**
 * Migración idempotente para la primera administración de publicidad.
 * Uso exclusivo por CLI: php install/publicidad-v1.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS anuncios (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      nombre VARCHAR(120) NOT NULL,
      imagen VARCHAR(255) NOT NULL,
      facebook_url VARCHAR(500) NULL,
      instagram_url VARCHAR(500) NULL,
      whatsapp_url VARCHAR(500) NULL,
      sitio_web_url VARCHAR(500) NULL,
      fecha_vencimiento DATE NULL,
      activo TINYINT(1) NOT NULL DEFAULT 1,
      clics INT UNSIGNED NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_anuncios_vencimiento (fecha_vencimiento),
      KEY idx_anuncios_publicacion (activo, fecha_vencimiento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO permisos (clave, nombre, descripcion) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)'
    );
    $stmt->execute([
        'publicidad.gestionar',
        'Gestionar publicidad',
        'Crear, editar y eliminar anuncios y popups.',
    ]);
    $pdo->exec(
        "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
         SELECT r.id, p.id
           FROM roles r
           JOIN permisos p ON p.clave = 'publicidad.gestionar'
          WHERE r.slug = 'admin'"
    );
    $pdo->commit();
    echo "[OK] Tabla de anuncios y permiso de publicidad disponibles.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "[ERROR] No se pudo aplicar publicidad-v1.\n");
    exit(1);
}
