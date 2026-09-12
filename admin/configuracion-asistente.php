<?php
/** Guarda la disponibilidad y los textos publicos del asistente de noticias. */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

exigir_permiso('configuracion.gestionar', true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
verificar_csrf(true);

$limpiarLinea = static function (mixed $valor): string {
    $texto = trim(strip_tags((string) $valor));
    return preg_replace('/\s+/u', ' ', $texto) ?? '';
};

$activo = (string) ($_POST['activo'] ?? '0') === '1';
$titulo = $limpiarLinea($_POST['titulo'] ?? '');
$detalle = $limpiarLinea($_POST['detalle'] ?? '');
$bienvenida = $limpiarLinea($_POST['bienvenida'] ?? '');

try {
    foreach ([
        ['valor' => $titulo, 'nombre' => 'El título', 'maximo' => 24],
        ['valor' => $detalle, 'nombre' => 'El detalle', 'maximo' => 44],
        ['valor' => $bienvenida, 'nombre' => 'El mensaje de bienvenida', 'maximo' => 240],
    ] as $campo) {
        $longitud = mb_strlen($campo['valor'], 'UTF-8');
        if ($longitud < 1 || $longitud > $campo['maximo']) {
            throw new RuntimeException($campo['nombre'] . ' debe tener entre 1 y ' . $campo['maximo'] . ' caracteres.');
        }
        if (preg_match('/[\p{Cc}\p{Cf}]/u', $campo['valor'])) {
            throw new RuntimeException($campo['nombre'] . ' contiene caracteres no válidos.');
        }
    }

    $pdo = db();
    $guardar = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor=VALUES(valor)'
    );
    $pdo->beginTransaction();
    foreach ([
        'asistente_publico_activo' => $activo ? '1' : '0',
        'asistente_publico_titulo' => $titulo,
        'asistente_publico_detalle' => $detalle,
        'asistente_publico_bienvenida' => $bienvenida,
    ] as $clave => $valor) {
        $guardar->execute([$clave, $valor]);
    }
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'activo' => $activo,
        'titulo' => $titulo,
        'detalle' => $detalle,
        'bienvenida' => $bienvenida,
        'mensaje' => 'Configuración del asistente guardada.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'error' => $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se pudo guardar la configuración del asistente.',
    ], JSON_UNESCAPED_UNICODE);
}
