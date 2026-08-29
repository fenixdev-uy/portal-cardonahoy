<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('json');
require_once __DIR__ . '/admin/includes/votos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function responder_popup_publico(array $datos, int $estado = 200): never
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    responder_popup_publico(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$pdo = db();
$visitante = visitante_id(true);
$hoy = date('Y-m-d');

if ($metodo === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nombre, p.imagen_vertical, p.imagen_horizontal,
                p.facebook_url, p.instagram_url, p.whatsapp_url, p.sitio_web_url,
                p.segundos_aparicion, p.limite_diario_por_visitante,
                COALESCE(i.cantidad, 0) AS impresiones_hoy
           FROM popups p
           LEFT JOIN popups_impresiones i
             ON i.popup_id = p.id AND i.visitante = ? AND i.fecha = ?
          WHERE p.activo = 1
            AND (p.fecha_vencimiento IS NULL OR p.fecha_vencimiento > ?)
            AND COALESCE(i.cantidad, 0) < p.limite_diario_por_visitante
          ORDER BY p.created_at DESC, p.id DESC
          LIMIT 1'
    );
    $stmt->execute([$visitante, $hoy, $hoy]);
    $popup = $stmt->fetch();
    if (!$popup) responder_popup_publico(['ok' => true, 'popup' => null]);

    $destinos = [];
    foreach (['facebook' => 'facebook_url', 'instagram' => 'instagram_url', 'whatsapp' => 'whatsapp_url', 'web' => 'sitio_web_url'] as $tipo => $campo) {
        $url = trim((string) ($popup[$campo] ?? ''));
        $esSegura = filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
        if (!$esSegura) continue;
        $destinos[$tipo] = url_portal('popup-click.php?' . http_build_query([
            'id' => (int) $popup['id'],
            'destino' => $campo,
        ]));
    }

    responder_popup_publico([
        'ok' => true,
        'popup' => [
            'id' => (int) $popup['id'],
            'nombre' => (string) $popup['nombre'],
            'imagen_vertical' => url_recurso_portal((string) $popup['imagen_vertical']),
            'imagen_horizontal' => url_recurso_portal((string) $popup['imagen_horizontal']),
            'segundos' => (int) $popup['segundos_aparicion'],
            'destinos' => $destinos,
        ],
    ]);
}

if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
    responder_popup_publico(['ok' => false, 'error' => 'Solicitud no válida.'], 400);
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) responder_popup_publico(['ok' => false, 'error' => 'Popup no válido.'], 422);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT id, limite_diario_por_visitante
           FROM popups
          WHERE id = ? AND activo = 1
            AND (fecha_vencimiento IS NULL OR fecha_vencimiento > ?)
          FOR UPDATE'
    );
    $stmt->execute([$id, $hoy]);
    $popup = $stmt->fetch();
    if (!$popup) {
        $pdo->rollBack();
        responder_popup_publico(['ok' => true, 'mostrar' => false]);
    }

    $stmt = $pdo->prepare('SELECT cantidad FROM popups_impresiones WHERE popup_id = ? AND visitante = ? AND fecha = ? FOR UPDATE');
    $stmt->execute([$id, $visitante, $hoy]);
    $cantidad = $stmt->fetchColumn();
    $limite = (int) $popup['limite_diario_por_visitante'];
    if ($cantidad !== false && (int) $cantidad >= $limite) {
        $pdo->rollBack();
        responder_popup_publico(['ok' => true, 'mostrar' => false]);
    }

    if ($cantidad === false) {
        $pdo->prepare('INSERT INTO popups_impresiones (popup_id, visitante, fecha, cantidad) VALUES (?, ?, ?, 1)')->execute([$id, $visitante, $hoy]);
    } else {
        $pdo->prepare('UPDATE popups_impresiones SET cantidad = cantidad + 1 WHERE popup_id = ? AND visitante = ? AND fecha = ?')->execute([$id, $visitante, $hoy]);
    }
    $pdo->commit();
    responder_popup_publico(['ok' => true, 'mostrar' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    responder_popup_publico(['ok' => false, 'error' => 'No se pudo registrar la impresión.'], 500);
}
