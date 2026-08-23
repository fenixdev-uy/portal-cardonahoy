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
foreach ($fotos as $foto) eliminar_imagen($foto['ruta']);

flash('success', 'Noticia eliminada correctamente.');
redirigir('index.php');
