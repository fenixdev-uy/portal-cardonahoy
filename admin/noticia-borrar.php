<?php
/**
 * Elimina una noticia (y su imagen asociada si fue subida localmente).
 */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('noticias.eliminar');

$pdo = db();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Metodo no permitido.');
}
verificar_csrf();
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    flash('danger', 'Noticia inválida.');
    redirigir('index.php');
}

$stmt = $pdo->prepare('SELECT * FROM noticias WHERE id = ?');
$stmt->execute([$id]);
$noticia = $stmt->fetch();

if (!$noticia) {
    flash('danger', 'La noticia no existe.');
    redirigir('index.php');
}

$fotos = obtener_fotos_noticia($id);
$imagenes = [];
foreach ($fotos as $foto) {
    $ruta = (string) ($foto['ruta'] ?? '');
    if (ruta_imagen_subida_valida($ruta)) $imagenes[$ruta] = true;
}
foreach (imagenes_locales_en_html($noticia['descripcion'] ?? '') as $ruta) {
    $imagenes[$ruta] = true;
}
$imagenSeo = (string) ($noticia['seo_imagen'] ?? '');
if (ruta_imagen_subida_valida($imagenSeo)) $imagenes[$imagenSeo] = true;
$audios = array_filter([
    (string) ($noticia['audio_1'] ?? ''),
    (string) ($noticia['audio_2'] ?? ''),
    (string) ($noticia['audio_3'] ?? ''),
]);
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('DELETE FROM noticias WHERE id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('danger', 'No se pudo eliminar la noticia.');
    redirigir('index.php');
}
// Solo se elimina un archivo si ninguna otra noticia lo sigue usando.
foreach (array_keys($imagenes) as $imagen) {
    if (!imagen_subida_referenciada($imagen)) eliminar_imagen($imagen);
}
foreach ($audios as $audio) {
    if (ruta_audio_subido_valida($audio) && !audio_subido_referenciado($audio)) eliminar_audio($audio);
}

flash('success', 'Noticia eliminada correctamente.');
redirigir('index.php');
