<?php
/**
 * Migración idempotente para la configuración general del portal.
 * Uso exclusivo por CLI: php install/configuracion-v1.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion (
      clave VARCHAR(100) NOT NULL,
      valor TEXT NOT NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->beginTransaction();
    $stmtConfiguracion = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = valor'
    );
    $stmtConfiguracion->execute(['marca_agua_ruta', 'imagenes/Logo2027v2.png']);
    $stmtConfiguracion->execute(['marca_agua_opacidad', '15']);
    $stmtConfiguracion->execute(['marca_agua_tamano', '36']);
    $stmtConfiguracion->execute(['logo_login_ruta', 'imagenes/Logo2027v3.png']);
    $stmtConfiguracion->execute(['logo_portal_ruta', 'imagenes/Logo2027v2.png']);

    $stmtPermiso = $pdo->prepare(
        'INSERT INTO permisos (clave, nombre, descripcion) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)'
    );
    $stmtPermiso->execute([
        'configuracion.gestionar',
        'Gestionar configuracion',
        'Cambiar ajustes generales y la marca de agua del portal.',
    ]);

    $pdo->exec(
        "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
         SELECT r.id, p.id
           FROM roles r
           JOIN permisos p ON p.clave = 'configuracion.gestionar'
          WHERE r.slug = 'admin'"
    );

    $pdo->commit();
    echo "[OK] Configuracion y permiso de administrador disponibles.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "[ERROR] No se pudo aplicar configuracion-v1.\n");
    exit(1);
}
