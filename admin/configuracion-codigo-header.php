<?php
/** Guarda snippets de integraciones para el <head> público. */

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

$codigo = trim((string) ($_POST['codigo'] ?? ''));
$activo = ($_POST['activo'] ?? '') === '1';

try {
    if (strlen($codigo) > 60_000) throw new RuntimeException('El código supera el máximo de 60 KB.');
    if (str_contains($codigo, "\0")) throw new RuntimeException('El código contiene caracteres no válidos.');
    if ($codigo !== '' && !preg_match('#<(?:script|meta|link|noscript)\b#i', $codigo)) {
        throw new RuntimeException('Pegá un snippet válido que contenga script, meta, link o noscript.');
    }
    if ($codigo !== '' && preg_match('#<\?(?:php|=)|\?>|</?\s*(?:html|head|body)\b#i', $codigo)) {
        throw new RuntimeException('El código no puede abrir o cerrar html, head, body ni contener etiquetas PHP.');
    }
    if ($activo && $codigo === '') throw new RuntimeException('Pegá un código antes de activarlo.');

    $pdo = db();
    $guardar = $pdo->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
    $pdo->beginTransaction();
    if ($codigo === '') {
        $pdo->exec("DELETE FROM configuracion WHERE clave IN ('codigo_header_contenido', 'codigo_header_activo')");
    } else {
        $guardar->execute(['codigo_header_contenido', $codigo]);
        $guardar->execute(['codigo_header_activo', $activo ? '1' : '0']);
    }
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'activo' => $activo && $codigo !== '',
        'caracteres' => mb_strlen($codigo),
        'mensaje' => $codigo === '' ? 'Código del Header eliminado correctamente.' : 'Código del Header guardado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el Código del Header.'], JSON_UNESCAPED_UNICODE);
}
