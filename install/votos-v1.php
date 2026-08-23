<?php
/**
 * Migracion de votos v1. Solo CLI.
 *
 * Agrega los contadores "me_gusta" y "no_me_gusta" a noticias, y crea las
 * tablas noticias_votos (un voto por visitante y por noticia) y votos_limite
 * (techo de votos por IP).
 *
 * Es idempotente: se puede correr varias veces sin efectos.
 *
 * Uso:
 *   php install/votos-v1.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();

function columna_existe_votos(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tabla, $columna]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "=== Votos v1 ===\n";

// Los contadores solo se incrementan, nunca se restan, por eso UNSIGNED es seguro.
foreach (['me_gusta', 'no_me_gusta'] as $columna) {
    if (columna_existe_votos($pdo, 'noticias', $columna)) {
        echo "- noticias.$columna ya existe, se omite.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE noticias ADD COLUMN $columna INT UNSIGNED NOT NULL DEFAULT 0");
    echo "- noticias.$columna agregada.\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS noticias_votos (
  noticia_id INT UNSIGNED NOT NULL,
  visitante CHAR(36) NOT NULL,
  valor TINYINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (noticia_id, visitante), KEY idx_noticias_votos_visitante (visitante),
  CONSTRAINT fk_noticias_votos_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "- tabla noticias_votos lista.\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS votos_limite (
  clave_hash CHAR(64) NOT NULL,
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ventana_inicio_at DATETIME NOT NULL,
  PRIMARY KEY (clave_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "- tabla votos_limite lista.\n";

// Reconciliacion: si ya hubiera votos cargados, deja los contadores acordes.
// updated_at = updated_at evita que esto figure como una edicion de la noticia.
$pdo->exec("UPDATE noticias n SET
  n.me_gusta = (SELECT COUNT(*) FROM noticias_votos v WHERE v.noticia_id = n.id AND v.valor = 1),
  n.no_me_gusta = (SELECT COUNT(*) FROM noticias_votos v WHERE v.noticia_id = n.id AND v.valor = -1),
  n.updated_at = n.updated_at");
echo "- contadores reconciliados con la tabla de votos.\n";

echo "Listo.\n";
