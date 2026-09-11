<?php
require_once __DIR__ . '/admin/includes/funciones.php';
exigir_portal_disponible('json');
require_once __DIR__ . '/admin/includes/votos.php';
require_once __DIR__ . '/partials/portada-paginacion.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

try {
    $vistaEntrada = $_GET['vista'] ?? '';
    $vista = is_string($vistaEntrada) ? $vistaEntrada : '';
    if (!in_array($vista, ['pc', 'mobile'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Vista inválida.']);
        exit;
    }

    $cursor = decodificar_cursor_portada(is_string($_GET['cursor'] ?? null) ? $_GET['cursor'] : '');
    if ($cursor === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Cursor inválido.']);
        exit;
    }

    $semillaEntrada = $_GET['semilla_publicidad'] ?? '';
    $semillaPublicidad = is_string($semillaEntrada) && preg_match('/^\d{1,10}$/', $semillaEntrada)
        ? min(2147483647, (int) $semillaEntrada)
        : random_int(0, 2147483647);

    $pdo = db();
    $opciones = ['cursor' => $cursor, 'limite' => PORTAL_NOTICIAS_POR_BLOQUE];
    if ($vista === 'pc') {
        $categoriasValidas = array_map('intval', array_column(
            $pdo->query('SELECT id FROM categorias')->fetchAll(),
            'id'
        ));
        $categorias = array_values(array_intersect(
            normalizar_ids_categorias($_GET['categoria'] ?? []),
            $categoriasValidas
        ));
        $buscar = is_string($_GET['buscar'] ?? null) ? trim((string) $_GET['buscar']) : '';
        $validarFecha = static function (mixed $valor): string {
            if (!is_string($valor)) return '';
            $fecha = trim($valor);
            $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            return $objeto && $objeto->format('Y-m-d') === $fecha ? $fecha : '';
        };
        $opciones += [
            'buscar' => mb_substr($buscar, 0, 150),
            'categorias' => $categorias,
            'desde' => $validarFecha($_GET['desde'] ?? ''),
            'hasta' => $validarFecha($_GET['hasta'] ?? ''),
        ];
    }

    $pagina = consultar_bloque_portada($pdo, $opciones);
    $fotosPorNoticia = $pagina['fotos'];
    $misVotos = votos_del_visitante($pdo, visitante_id());
    require __DIR__ . '/partials/publicidad.php';

    ob_start();
    if ($vista === 'pc') {
        $noticiasPc = $pagina['noticias'];
        $offsetAnterior = $cursor['offset'];
        $indiceFilaMixtaInicial = intdiv($offsetAnterior, 2);
        $semillaPortadaPc = $semillaPublicidad;
        require __DIR__ . '/partials/pc-news-items.php';
    } else {
        $noticiasMovil = $pagina['noticias'];
        $offsetNoticiasMovil = $cursor['offset'];
        require __DIR__ . '/partials/mobile-news-items.php';
    }
    $itemsHtml = (string) ob_get_clean();

    ob_start();
    $noticiasParaTemplates = $pagina['noticias'];
    $soloTemplatesNoticias = true;
    require __DIR__ . '/partials/nota-completa.php';
    $templatesHtml = (string) ob_get_clean();

    echo json_encode([
        'items_html' => $itemsHtml,
        'templates_html' => $templatesHtml,
        'cursor' => $pagina['cursor'],
        'hay_mas' => $pagina['hay_mas'],
        'cantidad' => count($pagina['noticias']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar más noticias.']);
}
