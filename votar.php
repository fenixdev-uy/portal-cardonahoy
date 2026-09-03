<?php
/**
 * Endpoint publico de votos del feed. Devuelve JSON.
 *
 * Vive en la raiz y no exige login, a diferencia de los endpoints de admin/.
 * Un voto por visitante y por noticia, definitivo: si ya habia votado, se
 * responde el estado actual sin modificar nada.
 */

require_once __DIR__ . '/admin/includes/funciones.php';
require_once __DIR__ . '/admin/includes/votos.php';
exigir_portal_disponible('json');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function votos_responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    votos_responder(405, ['error' => 'Metodo no permitido.']);
}

$noticiaId = (int) ($_POST['noticia_id'] ?? 0);
$valor = (int) ($_POST['valor'] ?? 0);

if ($noticiaId <= 0 || ($valor !== 1 && $valor !== -1)) {
    votos_responder(400, ['error' => 'Datos de voto invalidos.']);
}

$pdo = db();

if (voto_limite_excedido($pdo, (string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
    votos_responder(429, ['error' => 'Demasiados votos. Intenta mas tarde.']);
}

// La cookie se crea en este momento si el visitante todavia no tenia una.
$visitante = visitante_id(true);

try {
    $estado = registrar_voto($pdo, $noticiaId, $valor, $visitante);
} catch (Throwable $e) {
    error_log('Portal voto failed: ' . $e->getMessage());
    votos_responder(500, ['error' => 'No se pudo registrar el voto.']);
}

if ($estado === null) {
    votos_responder(404, ['error' => 'La noticia no existe.']);
}

votos_responder(200, $estado);
