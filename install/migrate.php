<?php
/**
 * Migración v2:
 *  - Crea la tabla `autores` (usuarios que son autores de noticias).
 *  - Crea la tabla `noticias_fotos` (galería de fotos por noticia, con orden).
 *  - Agrega `autor_id` a `noticias`.
 *  - Siembra autores de fantasía (solo si no hay ninguno).
 *  - Migra el campo `foto_principal` a la galería (posicion 0 = portada).
 *  - Asigna un autor a las noticias existentes según su categoría.
 *  - Elimina la columna `foto_principal` (reemplazada por la galería).
 *
 * Es idempotente: se puede ejecutar varias veces sin romper datos.
 *
 * Uso:  php install/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();

$seguridadAplicada = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios'")->fetchColumn();
if ((int) $seguridadAplicada > 0) {
    exit("La base ya usa usuarios y roles. Esta migracion historica no debe volver a ejecutarse.\n");
}

function columna_existe(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$tabla, $columna]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "=== Migración v2 ===\n";

// 1) Tabla autores
$pdo->exec("CREATE TABLE IF NOT EXISTS autores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  bio VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_autores_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] Tabla autores lista.\n";

// 2) Tabla noticias_fotos
$pdo->exec("CREATE TABLE IF NOT EXISTS noticias_fotos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  noticia_id INT UNSIGNED NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  posicion INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_noticias_fotos_noticia (noticia_id),
  CONSTRAINT fk_noticias_fotos_noticia FOREIGN KEY (noticia_id)
    REFERENCES noticias (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  [OK] Tabla noticias_fotos lista.\n";

// 3) Columna autor_id en noticias
if (!columna_existe($pdo, 'noticias', 'autor_id')) {
    $pdo->exec("ALTER TABLE noticias
        ADD COLUMN autor_id INT UNSIGNED NULL AFTER youtube,
        ADD KEY idx_noticias_autor (autor_id),
        ADD CONSTRAINT fk_noticias_autor FOREIGN KEY (autor_id)
          REFERENCES autores (id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "  [OK] Columna noticias.autor_id agregada.\n";
} else {
    echo "  [--] Columna noticias.autor_id ya existía.\n";
}

// 4) Siembra de autores de fantasía (solo si la tabla está vacía)
$totalAutores = (int) $pdo->query('SELECT COUNT(*) FROM autores')->fetchColumn();
if ($totalAutores === 0) {
    $passwordDemo = password_hash('demo1234', PASSWORD_DEFAULT);
    $autores = [
        ['Redacción Tecnología', 'redaccion.tecnologia@radiosur.com', 'Cobertura de tecnología e innovación.'],
        ['Marta Gómez', 'marta.gomez@radiosur.com', 'Periodista de economía y finanzas.'],
        ['Diego Fernández', 'diego.fernandez@radiosur.com', 'Redactor de la sección deportes.'],
        ['Laura Pérez', 'laura.perez@radiosur.com', 'Editora general de noticias.'],
    ];
    $stmt = $pdo->prepare('INSERT INTO autores (nombre, email, password_hash, bio) VALUES (?, ?, ?, ?)');
    foreach ($autores as [$nombre, $email, $bio]) {
        $stmt->execute([$nombre, $email, $passwordDemo, $bio]);
    }
    echo "  [OK] Autores de fantasía creados (password demo: demo1234).\n";
} else {
    echo "  [--] Ya existían autores, no se siembran duplicados.\n";
}

// 5) Migrar foto_principal -> galería (solo si la columna todavía existe)
if (columna_existe($pdo, 'noticias', 'foto_principal')) {
    $noticias = $pdo->query(
        'SELECT id, foto_principal FROM noticias
          WHERE foto_principal IS NOT NULL AND foto_principal <> \'\''
    )->fetchAll();

    $stmtExiste = $pdo->prepare('SELECT COUNT(*) FROM noticias_fotos WHERE noticia_id = ?');
    $stmtInsert = $pdo->prepare('INSERT INTO noticias_fotos (noticia_id, ruta, posicion) VALUES (?, ?, 0)');

    $migradas = 0;
    foreach ($noticias as $n) {
        $stmtExiste->execute([(int) $n['id']]);
        if ((int) $stmtExiste->fetchColumn() > 0) {
            continue; // ya tiene fotos de galería
        }
        $stmtInsert->execute([(int) $n['id'], $n['foto_principal']]);
        $migradas++;
    }
    echo "  [OK] {$migradas} foto(s) principal(es) migradas a la galería.\n";

    // 6) Asignar autor según categoría (solo a noticias sin autor)
    $mapeo = [
        'tecnologia' => 'Redacción Tecnología',
        'economia'   => 'Marta Gómez',
        'deportes'   => 'Diego Fernández',
    ];
    $stmtAutor = $pdo->prepare('SELECT id FROM autores WHERE nombre = ?');
    $stmtAsignar = $pdo->prepare('UPDATE noticias SET autor_id = ? WHERE id = ?');

    $sinAutor = $pdo->query(
        'SELECT n.id, c.slug
           FROM noticias n
           LEFT JOIN categorias c ON c.id = n.categoria_id
          WHERE n.autor_id IS NULL'
    )->fetchAll();

    $asignadas = 0;
    foreach ($sinAutor as $n) {
        $nombre = $mapeo[$n['slug'] ?? ''] ?? 'Laura Pérez';
        $stmtAutor->execute([$nombre]);
        $autorId = $stmtAutor->fetchColumn();
        if ($autorId) {
            $stmtAsignar->execute([(int) $autorId, (int) $n['id']]);
            $asignadas++;
        }
    }
    echo "  [OK] {$asignadas} noticia(s) con autor asignado.\n";

    // 7) Eliminar la columna foto_principal (reemplazada por la galería)
    $pdo->exec('ALTER TABLE noticias DROP COLUMN foto_principal');
    echo "  [OK] Columna noticias.foto_principal eliminada (reemplazada por la galería).\n";
} else {
    echo "  [--] La columna foto_principal ya fue eliminada en una migración anterior.\n";
}

echo "=== Migración completada ===\n";
