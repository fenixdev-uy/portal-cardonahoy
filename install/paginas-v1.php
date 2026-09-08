<?php
/**
 * Incorpora el permiso independiente para gestionar páginas públicas.
 *
 * Uso:
 *   php install/paginas-v1.php --environment=development
 *   php install/paginas-v1.php --environment=production
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$entorno = null;
$instanciaConfirmada = null;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--environment=')) $entorno = substr($argumento, 14);
    if (str_starts_with($argumento, '--confirm-instance=')) $instanciaConfirmada = substr($argumento, 19);
}
if (!in_array($entorno, ['development', 'production'], true)) {
    fwrite(STDERR, "[ERROR] Indicá --environment=development o --environment=production.\n");
    exit(2);
}

require_once __DIR__ . '/../admin/config.php';

$serviciosRuta = dirname(__DIR__) . '/servicios.local.json';
if (is_file($serviciosRuta) && is_readable($serviciosRuta)) {
    $servicios = json_decode((string) file_get_contents($serviciosRuta), true);
    $destino = $servicios['databases'][$entorno] ?? null;
    if (!is_array($destino)
        || ($destino['host'] ?? '') !== DB_HOST
        || ($destino['name'] ?? '') !== DB_NAME
        || ($destino['username'] ?? '') !== DB_USER) {
        fwrite(STDERR, "[ERROR] El runtime local no corresponde a databases.$entorno.\n");
        exit(4);
    }
} else {
    if ($entorno !== 'development'
        || !defined('PORTAL_INSTANCE_ID')
        || $instanciaConfirmada !== (string) PORTAL_INSTANCE_ID) {
        fwrite(STDERR, "[ERROR] Sin servicios.local.json solo se admite DEV confirmando --confirm-instance=<instancia>.\n");
        exit(3);
    }
    echo '[AVISO] DEV validado por la identidad local ' . PORTAL_INSTANCE_ID . "; PROD continúa bloqueado sin manifiesto.\n";
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO permisos (clave, nombre, descripcion) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)'
    );
    $stmt->execute([
        'paginas.gestionar',
        'Gestionar páginas',
        'Ver y editar el SEO de la Home.',
    ]);
    $pdo->exec(
        "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
         SELECT r.id, p.id
           FROM roles r
           JOIN permisos p ON p.clave = 'paginas.gestionar'
          WHERE r.slug = 'admin'"
    );
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "[ERROR] No se pudo incorporar el permiso de páginas.\n");
    exit(5);
}

$permiso = $pdo->prepare('SELECT id, nombre, descripcion FROM permisos WHERE clave = ? LIMIT 1');
$permiso->execute(['paginas.gestionar']);
$fila = $permiso->fetch();
$administradores = (int) $pdo->query(
    "SELECT COUNT(*)
       FROM rol_permisos rp
       JOIN roles r ON r.id = rp.rol_id AND r.slug = 'admin'
       JOIN permisos p ON p.id = rp.permiso_id AND p.clave = 'paginas.gestionar'"
)->fetchColumn();

if (!$fila || $administradores < 1) {
    fwrite(STDERR, "[ERROR] La verificación final del permiso no coincide.\n");
    exit(6);
}

echo "[OK] paginas.gestionar disponible y asignado al rol Administrador en $entorno.\n";
