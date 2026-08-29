<?php
/**
 * Devuelve una noticia en formato JSON para el panel lateral (preview).
 */

require_once __DIR__ . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
exigir_permiso('noticias.ver', true);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$stmt = db()->prepare(
    'SELECT n.id, n.titulo, n.descripcion,
            n.youtube, n.youtube_2, n.youtube_3,
            n.audio_1, n.audio_2, n.audio_3, n.created_at,
            n.me_gusta, n.no_me_gusta, n.vistas, n.compartidos,
            c.nombre AS categoria_nombre,
            u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
       LEFT JOIN usuarios u ON u.id = n.usuario_id
      WHERE n.id = ?'
);
$stmt->execute([$id]);
$n = $stmt->fetch();

if (!$n) {
    http_response_code(404);
    echo json_encode(['error' => 'Noticia no encontrada']);
    exit;
}

$noticiaDetalle = [$n];
cargar_categorias_noticias($noticiaDetalle);
$n = $noticiaDetalle[0];

$fotos = obtener_fotos_noticia((int) $n['id']);
$galeria = [];
foreach ($fotos as $f) {
    $galeria[] = [
        'id'  => (int) $f['id'],
        'url' => url_imagen($f['ruta']),
    ];
}

$videos = array_values(array_filter(array_map(
    static fn($url) => youtube_embed_url((string) $url),
    [$n['youtube'] ?? '', $n['youtube_2'] ?? '', $n['youtube_3'] ?? '']
)));
$audios = array_values(array_filter(array_map(
    static function ($url): string {
        $normalizada = normalizar_url_audio((string) $url);
        return $normalizada === '' ? '' : url_audio_panel($normalizada);
    },
    [$n['audio_1'] ?? '', $n['audio_2'] ?? '', $n['audio_3'] ?? '']
)));

echo json_encode([
    'id'             => (int) $n['id'],
    'titulo'         => $n['titulo'],
    'descripcion'    => $n['descripcion'],
    'foto_principal' => $galeria[0]['url'] ?? '',
    'galeria'        => $galeria,
    'categoria'      => $n['categoria_nombre'] ?: null,
    'categorias'     => $n['categorias'] ?? [],
    'autor'          => $n['autor_nombre'] ?? null,
    'fecha'          => $n['created_at'] ? date('d/m/Y', strtotime($n['created_at'])) : null,
    'fecha_larga'    => fecha_larga($n['created_at']),
    'youtube'        => $videos[0] ?? '',
    'videos'         => $videos,
    'audios'         => $audios,
    'me_gusta'       => (int) ($n['me_gusta'] ?? 0),
    'no_me_gusta'    => (int) ($n['no_me_gusta'] ?? 0),
    'vistas'         => (int) ($n['vistas'] ?? 0),
    'compartidos'    => (int) ($n['compartidos'] ?? 0),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
