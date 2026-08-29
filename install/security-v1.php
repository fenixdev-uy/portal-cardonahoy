<?php
/**
 * Migracion de seguridad v1. Solo CLI.
 *
 * Convierte autores en usuarios, incorpora roles/permisos y crea el primer admin.
 * Los autores existentes conservan su ID y quedan inactivos para preservar las noticias.
 *
 * Uso:
 *   php install/security-v1.php --name="Administrador" --email="admin@cliente.com"
 * Si no se pasa --password, genera una clave temporal fuerte y la muestra una sola vez.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$opciones = getopt('', ['name:', 'email:', 'password::']);
$nombreAdmin = trim((string) ($opciones['name'] ?? 'Administrador'));
$emailAdmin = mb_strtolower(trim((string) ($opciones['email'] ?? '')), 'UTF-8');
$passwordAdmin = (string) ($opciones['password'] ?? '');
$passwordProvista = $passwordAdmin !== '';
$passwordGenerada = false;

if ($emailAdmin === '' || !filter_var($emailAdmin, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El email del administrador no es valido.\n");
    exit(1);
}
if ($passwordAdmin !== '' && strlen($passwordAdmin) < 12) {
    fwrite(STDERR, "La contrasena debe tener al menos 12 caracteres.\n");
    exit(1);
}
if ($passwordAdmin === '') {
    $passwordAdmin = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    $passwordGenerada = true;
}

$pdo = db();

function tabla_existe_seguridad(PDO $pdo, string $tabla): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$tabla]);
    return (int) $stmt->fetchColumn() > 0;
}

function columna_existe_seguridad(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tabla, $columna]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "=== Seguridad v1 ===\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  es_sistema TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS permisos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(100) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_permisos_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS rol_permisos (
  rol_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$roles = [
    ['Administrador', 'admin', 'Acceso total al portal.', 1],
    ['Editor', 'editor', 'Gestion editorial sin administracion de usuarios.', 1],
    ['Autor', 'autor', 'Perfil para redactores y firma de noticias.', 1],
];
$stmtRol = $pdo->prepare('INSERT INTO roles (nombre, slug, descripcion, es_sistema) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)');
foreach ($roles as $rol) {
    $stmtRol->execute($rol);
}

$permisos = [
    ['noticias.ver', 'Ver noticias', 'Acceder al listado y vista previa.'],
    ['noticias.crear', 'Crear noticias', 'Crear noticias y subir su contenido.'],
    ['noticias.editar', 'Editar noticias', 'Modificar noticias existentes.'],
    ['noticias.eliminar', 'Eliminar noticias', 'Eliminar noticias y sus fotos.'],
    ['categorias.gestionar', 'Gestionar categorias', 'Crear, editar y eliminar categorias.'],
    ['usuarios.gestionar', 'Gestionar usuarios', 'Crear, editar, activar y desactivar usuarios.'],
    ['roles.gestionar', 'Gestionar roles', 'Configurar permisos de roles.'],
    ['archivos.subir', 'Subir archivos', 'Subir y retirar imagenes del portal.'],
    ['configuracion.gestionar', 'Gestionar configuracion', 'Cambiar ajustes generales y la marca de agua del portal.'],
    ['publicidad.gestionar', 'Gestionar publicidad', 'Crear, editar y eliminar anuncios y popups.'],
];
$stmtPermiso = $pdo->prepare('INSERT INTO permisos (clave, nombre, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)');
foreach ($permisos as $permiso) {
    $stmtPermiso->execute($permiso);
}

$mapa = [
    'admin' => array_column($permisos, 0),
    'editor' => ['noticias.ver', 'noticias.crear', 'noticias.editar', 'noticias.eliminar', 'categorias.gestionar', 'archivos.subir'],
    'autor' => ['noticias.ver', 'noticias.crear', 'noticias.editar', 'archivos.subir'],
];
$stmtRolId = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
$stmtPermisoId = $pdo->prepare('SELECT id FROM permisos WHERE clave = ?');
$stmtAsignar = $pdo->prepare('INSERT IGNORE INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)');
foreach ($mapa as $slug => $claves) {
    $stmtRolId->execute([$slug]);
    $rolId = (int) $stmtRolId->fetchColumn();
    foreach ($claves as $clave) {
        $stmtPermisoId->execute([$clave]);
        $stmtAsignar->execute([$rolId, (int) $stmtPermisoId->fetchColumn()]);
    }
}

if (!tabla_existe_seguridad($pdo, 'usuarios')) {
    if (!tabla_existe_seguridad($pdo, 'autores')) {
        throw new RuntimeException('No existe la tabla autores necesaria para la migracion.');
    }
    $pdo->exec('RENAME TABLE autores TO usuarios');
    echo "  [OK] autores renombrada a usuarios.\n";
}

$stmtRolId->execute(['autor']);
$rolAutorId = (int) $stmtRolId->fetchColumn();
if (!columna_existe_seguridad($pdo, 'usuarios', 'rol_id')) {
    $pdo->exec("ALTER TABLE usuarios
      ADD COLUMN rol_id INT UNSIGNED NULL AFTER id,
      ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 0 AFTER bio,
      ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0 AFTER activo,
      ADD COLUMN ultimo_acceso_at DATETIME NULL AFTER debe_cambiar_password,
      ADD KEY idx_usuarios_rol (rol_id),
      ADD CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON UPDATE CASCADE");
    // Las claves de demostracion de la version anterior no se conservan.
    // El administrador debera asignar una nueva antes de activar cada perfil.
    $stmt = $pdo->prepare("UPDATE usuarios SET rol_id = ?, activo = 0, password_hash = '', debe_cambiar_password = 1");
    $stmt->execute([$rolAutorId]);
    $pdo->exec('ALTER TABLE usuarios MODIFY rol_id INT UNSIGNED NOT NULL');
    echo "  [OK] perfiles existentes conservados como usuarios autores inactivos.\n";
}

if (!columna_existe_seguridad($pdo, 'usuarios', 'foto')) {
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) NULL AFTER bio');
    echo "  [OK] usuarios.foto agregada.\n";
}

if (columna_existe_seguridad($pdo, 'noticias', 'autor_id') && !columna_existe_seguridad($pdo, 'noticias', 'usuario_id')) {
    $pdo->exec('ALTER TABLE noticias CHANGE COLUMN autor_id usuario_id INT UNSIGNED NULL');
    echo "  [OK] noticias.autor_id renombrada a usuario_id.\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS intentos_login (
  clave_hash CHAR(64) NOT NULL,
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  primer_intento_at DATETIME NOT NULL,
  bloqueado_hasta DATETIME NULL,
  PRIMARY KEY (clave_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmtRolId->execute(['admin']);
$rolAdminId = (int) $stmtRolId->fetchColumn();
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$emailAdmin]);
$adminId = (int) $stmt->fetchColumn();
$hash = password_hash($passwordAdmin, PASSWORD_DEFAULT);
if ($adminId > 0) {
    if ($passwordProvista) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?, rol_id=?, password_hash=?, activo=1, debe_cambiar_password=1 WHERE id=?');
        $stmt->execute([$nombreAdmin, $rolAdminId, $hash, $adminId]);
    } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?, rol_id=?, activo=1 WHERE id=?');
        $stmt->execute([$nombreAdmin, $rolAdminId, $adminId]);
        $passwordGenerada = false;
    }
} else {
    $stmt = $pdo->prepare('INSERT INTO usuarios (rol_id, nombre, email, password_hash, bio, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, NULL, 1, 1)');
    $stmt->execute([$rolAdminId, $nombreAdmin, $emailAdmin, $hash]);
}

echo "  [OK] Administrador activo: {$emailAdmin}\n";
if ($passwordGenerada) {
    echo "  Clave temporal (se muestra una sola vez): {$passwordAdmin}\n";
}
echo "=== Migracion completada ===\n";
