<?php
/**
 * Migración de medios v1. Solo CLI e idempotente.
 *
 * Conserva noticias.youtube como primer video y agrega dos videos más y tres
 * URLs de audio. No modifica los valores existentes ni updated_at.
 *
 * Uso:
 *   php install/medios-v1.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$pdo = db();

function columna_existe_medios(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$tabla, $columna]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "=== Medios de noticias v1 ===\n";

$columnas = [
    'youtube_2' => 'VARCHAR(255) NULL AFTER youtube',
    'youtube_3' => 'VARCHAR(255) NULL AFTER youtube_2',
    'audio_1' => 'VARCHAR(500) NULL AFTER youtube_3',
    'audio_2' => 'VARCHAR(500) NULL AFTER audio_1',
    'audio_3' => 'VARCHAR(500) NULL AFTER audio_2',
];

foreach ($columnas as $columna => $definicion) {
    if (columna_existe_medios($pdo, 'noticias', $columna)) {
        echo "- noticias.$columna ya existe, se omite.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE noticias ADD COLUMN $columna $definicion");
    echo "- noticias.$columna agregada.\n";
}

echo "Listo. El campo noticias.youtube existente sigue siendo YouTube 1.\n";
