<?php
require_once __DIR__ . '/admin/includes/funciones.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

try {
    $pdo = db();
    require __DIR__ . '/partials/publicidad.php';

    $renderizarUbicacion = static function (?array $anuncio) use ($redesPublicidadPortal): string {
        if ($anuncio === null) return '';
        ob_start();
        $publicidad = $anuncio;
        $claseTarjetaPublicidad = 'story-sheet-placement-ad article-ad-card';
        require __DIR__ . '/partials/anuncio-card.php';
        return (string) ob_get_clean();
    };

    echo json_encode([
        'encabezado_html' => $renderizarUbicacion($anuncioPublicidadEncabezado),
        'pie_html' => $renderizarUbicacion($anuncioPublicidadPie),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron actualizar las ubicaciones publicitarias.'], JSON_UNESCAPED_UNICODE);
}
