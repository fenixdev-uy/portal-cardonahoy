<?php
/**
 * Historial administrativo del asistente público. Solo CLI e idempotente.
 *
 * Uso:
 *   php install/asistente-historial-v1.php --environment=development
 *   php install/asistente-historial-v1.php --environment=production
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
$servicios = json_decode((string) @file_get_contents($serviciosRuta), true);
$destino = $servicios['databases'][$entorno] ?? null;
if (!is_array($destino)
    || ($destino['configured'] ?? true) !== true
    || ($destino['host'] ?? '') !== DB_HOST
    || ($destino['name'] ?? '') !== DB_NAME
    || ($destino['username'] ?? '') !== DB_USER) {
    fwrite(STDERR, "[ERROR] El runtime local no corresponde a databases.$entorno.\n");
    exit(3);
}

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS asistente_conversaciones (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      token_hash CHAR(64) NOT NULL,
      visitante_hash CHAR(64) NOT NULL,
      pregunta_inicial VARCHAR(500) NOT NULL,
      cantidad_mensajes INT UNSIGNED NOT NULL DEFAULT 0,
      ultimo_mensaje_at DATETIME NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_asistente_conversaciones_token (token_hash),
      KEY idx_asistente_conversaciones_actividad (ultimo_mensaje_at, id),
      KEY idx_asistente_conversaciones_visitante (visitante_hash, ultimo_mensaje_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS asistente_mensajes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      conversacion_id BIGINT UNSIGNED NOT NULL,
      rol VARCHAR(20) NOT NULL,
      contenido TEXT NOT NULL,
      noticias_json LONGTEXT NULL,
      modo VARCHAR(30) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_asistente_mensajes_conversacion (conversacion_id, id),
      CONSTRAINT fk_asistente_mensajes_conversacion FOREIGN KEY (conversacion_id)
        REFERENCES asistente_conversaciones(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$permiso = $pdo->prepare(
    'INSERT INTO permisos (clave, nombre, descripcion) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)'
);
$permiso->execute([
    'asistente.ver',
    'Ver conversaciones del asistente',
    'Consultar el historial de preguntas y respuestas del asistente público.',
]);
$pdo->exec(
    "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
     SELECT r.id, p.id
       FROM roles r
       JOIN permisos p ON p.clave = 'asistente.ver'
      WHERE r.slug = 'admin'"
);

$tablas = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('asistente_conversaciones', 'asistente_mensajes')"
)->fetchColumn();
$asignado = (int) $pdo->query(
    "SELECT COUNT(*)
       FROM rol_permisos rp
       JOIN roles r ON r.id = rp.rol_id AND r.slug = 'admin'
       JOIN permisos p ON p.id = rp.permiso_id AND p.clave = 'asistente.ver'"
)->fetchColumn();
if ($tablas !== 2 || $asignado !== 1) {
    fwrite(STDERR, "[ERROR] La estructura o el permiso del historial no quedaron completos.\n");
    exit(4);
}

$conversaciones = (int) $pdo->query('SELECT COUNT(*) FROM asistente_conversaciones')->fetchColumn();
$mensajes = (int) $pdo->query('SELECT COUNT(*) FROM asistente_mensajes')->fetchColumn();
echo "[OK] Historial del asistente v1 aplicado en $entorno: $conversaciones conversaciones y $mensajes mensajes preservados.\n";
