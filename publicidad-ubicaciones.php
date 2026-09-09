<?php
require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('json');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

try {
    $pdo = db();
    require __DIR__ . '/partials/publicidad.php';

    $destinosPorAnuncio = [];
    foreach ($anunciosPublicidadActivos as $anuncioActivo) {
        $anuncioId = (int) ($anuncioActivo['id'] ?? 0);
        if ($anuncioId <= 0) continue;
        foreach ($redesPublicidadPortal as $redPublicidad) {
            $campo = (string) $redPublicidad['campo'];
            $url = trim((string) ($anuncioActivo[$campo] ?? ''));
            $esSegura = filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
            $destinosPorAnuncio[(string) $anuncioId][$campo] = $esSegura ? $url : '';
        }
    }

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
        'anuncios_destinos' => $destinosPorAnuncio,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron actualizar las ubicaciones publicitarias.'], JSON_UNESCAPED_UNICODE);
}
