<?php

const PORTAL_NOTICIAS_POR_BLOQUE = 6;

/** @return array{fecha:string,id:int,offset:int}|null */
function decodificar_cursor_portada(?string $cursor): ?array
{
    $cursor = trim((string) $cursor);
    if ($cursor === '' || strlen($cursor) > 240) return null;
    $relleno = (4 - strlen($cursor) % 4) % 4;
    $json = base64_decode(strtr($cursor . str_repeat('=', $relleno), '-_', '+/'), true);
    if ($json === false) return null;
    $datos = json_decode($json, true);
    if (!is_array($datos)) return null;
    $fecha = (string) ($datos['fecha'] ?? '');
    $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $offset = filter_var($datos['offset'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fecha) || $id === false || $offset === false) return null;
    return ['fecha' => $fecha, 'id' => (int) $id, 'offset' => (int) $offset];
}
function codificar_cursor_portada(array $noticia, int $offset): string
{
    $json = json_encode([
        'fecha' => (string) $noticia['created_at'],
        'id' => (int) $noticia['id'],
        'offset' => $offset,
    ], JSON_UNESCAPED_SLASHES);
    return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
}

/** @return array<int,array<string,mixed>> */
function fotos_noticias_portada(PDO $pdo, array $noticiaIds): array
{
    $noticiaIds = array_values(array_unique(array_filter(array_map('intval', $noticiaIds))));
    if (!$noticiaIds) return [];
    $placeholders = implode(',', array_fill(0, count($noticiaIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, noticia_id, ruta, posicion
           FROM noticias_fotos
          WHERE noticia_id IN ($placeholders)
          ORDER BY noticia_id, posicion, id"
    );
    $stmt->execute($noticiaIds);
    $fotos = [];
    foreach ($stmt->fetchAll() as $foto) {
        $fotos[(int) $foto['noticia_id']][] = $foto;
    }
    return $fotos;
}

/**
 * @param array{buscar?:string,categorias?:array<int,int>,desde?:string,hasta?:string,cursor?:array{fecha:string,id:int,offset:int}|null,limite?:int} $opciones
 * @return array{noticias:array<int,array<string,mixed>>,fotos:array<int,array<int,array<string,mixed>>>,hay_mas:bool,cursor:string,offset:int}
 */
function consultar_bloque_portada(PDO $pdo, array $opciones = []): array
{
    $limite = max(1, min(24, (int) ($opciones['limite'] ?? PORTAL_NOTICIAS_POR_BLOQUE)));
    $buscar = trim((string) ($opciones['buscar'] ?? ''));
    $categorias = array_values(array_unique(array_filter(array_map('intval', $opciones['categorias'] ?? []))));
    $desde = (string) ($opciones['desde'] ?? '');
    $hasta = (string) ($opciones['hasta'] ?? '');
    $cursor = is_array($opciones['cursor'] ?? null) ? $opciones['cursor'] : null;
    $condiciones = [];
    $parametros = [];

    if ($buscar !== '') {
        $terminos = preg_split('/\s+/u', mb_substr($buscar, 0, 150), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_slice($terminos, 0, 12) as $indice => $termino) {
            $clave = ':buscar' . $indice;
            $condiciones[] = "CONCAT_WS(' ', n.titulo, n.descripcion) LIKE $clave ESCAPE '\\\\'";
            $parametros[$clave] = '%' . addcslashes($termino, '\\%_') . '%';
        }
    }

    if ($categorias) {
        $placeholders = [];
        foreach ($categorias as $indice => $categoriaId) {
            $clave = ':categoria' . $indice;
            $placeholders[] = $clave;
            $parametros[$clave] = $categoriaId;
        }
        $condiciones[] = 'EXISTS (SELECT 1 FROM noticias_categorias nc WHERE nc.noticia_id = n.id AND nc.categoria_id IN (' . implode(',', $placeholders) . '))';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $condiciones[] = 'n.created_at >= :desde';
        $parametros[':desde'] = $desde . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $condiciones[] = 'n.created_at < :hasta';
        $parametros[':hasta'] = (new DateTimeImmutable($hasta))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }
    if ($cursor) {
        $condiciones[] = '(n.created_at < :cursor_fecha_1 OR (n.created_at = :cursor_fecha_2 AND n.id < :cursor_id))';
        $parametros[':cursor_fecha_1'] = $cursor['fecha'];
        $parametros[':cursor_fecha_2'] = $cursor['fecha'];
        $parametros[':cursor_id'] = $cursor['id'];
    }

    $sql = 'SELECT n.id, n.categoria_id, n.titulo, n.slug, n.descripcion,
                   n.youtube, n.youtube_2, n.youtube_3,
                   n.audio_1, n.audio_2, n.audio_3, n.created_at,
                   n.me_gusta, n.no_me_gusta, n.portada,
                   c.nombre AS categoria_nombre,
                   u.nombre AS autor_nombre
              FROM noticias n
              LEFT JOIN categorias c ON c.id = n.categoria_id
              LEFT JOIN usuarios u ON u.id = n.usuario_id';
    if ($condiciones) $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    $sql .= ' ORDER BY n.created_at DESC, n.id DESC LIMIT ' . ($limite + 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $noticias = $stmt->fetchAll();
    $hayMas = count($noticias) > $limite;
    if ($hayMas) array_pop($noticias);
    cargar_categorias_noticias($noticias);
    $offsetAnterior = (int) ($cursor['offset'] ?? 0);
    $offset = $offsetAnterior + count($noticias);
    $cursorSiguiente = $hayMas && $noticias ? codificar_cursor_portada($noticias[array_key_last($noticias)], $offset) : '';
    $fotos = fotos_noticias_portada($pdo, array_column($noticias, 'id'));

    return [
        'noticias' => $noticias,
        'fotos' => $fotos,
        'hay_mas' => $hayMas,
        'cursor' => $cursorSiguiente,
        'offset' => $offset,
    ];
}
