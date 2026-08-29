<?php
/** Migración idempotente del modo mantenimiento. Uso: php install/mantenimiento-v1.php */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();
try {
    $pdo->beginTransaction();
    $guardar = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=valor'
    );
    foreach ([
        'mantenimiento_activo' => '0',
        'mantenimiento_logo_ruta' => '',
        'mantenimiento_logo_tamano' => '58',
        'mantenimiento_mensaje' => 'En mantenimiento, ¡volvemos pronto!',
        'mantenimiento_mostrar_login' => '1',
    ] as $clave => $valor) {
        $guardar->execute([$clave, $valor]);
    }

    $permiso = $pdo->prepare(
        'INSERT INTO permisos (clave, nombre, descripcion) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)'
    );
    $permiso->execute([
        'mantenimiento.gestionar',
        'Gestionar mantenimiento',
        'Activar, desactivar y configurar el modo mantenimiento; permite ingresar durante el bloqueo.',
    ]);
    $pdo->exec(
        "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
         SELECT r.id, p.id
           FROM roles r
           JOIN permisos p ON p.clave = 'mantenimiento.gestionar'
          WHERE r.slug = 'admin'"
    );
    $pdo->commit();
    echo "[OK] Modo mantenimiento y permiso de administrador disponibles.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "[ERROR] No se pudo aplicar mantenimiento-v1.\n");
    exit(1);
}
