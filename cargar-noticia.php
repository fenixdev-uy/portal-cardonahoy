<?php
declare(strict_types=1);

/** Entrega el template de una noticia para abrirla en el panel lateral. */

require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('json');
require_once __DIR__ . '/admin/includes/votos.php';
require_once __DIR__ . '/partials/portada-paginacion.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$noticiaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($noticiaId === false || $noticiaId === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Noticia inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();
$estadosDisponibles = noticias_estados_disponibles($pdo);
$fechaPublicaSql = $estadosDisponibles ? 'n.publicada_at' : 'n.created_at';
$filtroPublicadaSql = $estadosDisponibles ? " AND n.estado = 'publicada'" : '';
$stmt = $pdo->prepare(
    'SELECT n.id, n.categoria_id, n.titulo, n.slug, n.descripcion,
            n.youtube, n.youtube_2, n.youtube_3,
            n.audio_1, n.audio_2, n.audio_3, ' . $fechaPublicaSql . ' AS created_at,
            n.me_gusta, n.no_me_gusta, n.portada,
            c.nombre AS categoria_nombre,
            u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
       LEFT JOIN usuarios u ON u.id = n.usuario_id
      WHERE n.id = ?' . $filtroPublicadaSql . '
      LIMIT 1'
);
$stmt->execute([(int) $noticiaId]);
$noticia = $stmt->fetch();
if (!$noticia) {
    http_response_code(404);
    echo json_encode(['error' => 'Noticia no encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$noticiasParaTemplates = [$noticia];
cargar_categorias_noticias($noticiasParaTemplates);
$fotosPorNoticia = fotos_noticias_portada($pdo, [(int) $noticiaId]);
$misVotos = votos_del_visitante($pdo, visitante_id());
$usuarioPublico = usuario_actual_publico();
require __DIR__ . '/partials/publicidad.php';

ob_start();
$soloTemplatesNoticias = true;
require __DIR__ . '/partials/nota-completa.php';
$templateHtml = (string) ob_get_clean();

echo json_encode([
    'ok' => true,
    'noticia_id' => (int) $noticiaId,
    'template_html' => $templateHtml,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
