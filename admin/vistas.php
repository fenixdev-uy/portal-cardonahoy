<?php
/** Análisis de vistas y compartidos por rango, más tasa mensual del año actual. */

require_once __DIR__ . '/includes/funciones.php';

const ACTIVIDAD_TOPE = 8;
const TASA_COMPARTIDOS_REFERENCIA = 5.0;

$hoy = new DateTimeImmutable('today');
$desdePredeterminado = $hoy->modify('first day of this month');

$leerFecha = static function (mixed $valor, DateTimeImmutable $respaldo): DateTimeImmutable {
    $texto = trim((string) $valor);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) return $respaldo;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
    return $fecha instanceof DateTimeImmutable && $fecha->format('Y-m-d') === $texto ? $fecha : $respaldo;
};

$desde = $leerFecha($_GET['desde'] ?? '', $desdePredeterminado);
$hasta = $leerFecha($_GET['hasta'] ?? '', $hoy);
if ($hasta > $hoy) $hasta = $hoy;
if ($desde > $hasta) $desde = $hasta;

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT n.id, n.titulo,
            COALESCE(v.vistas, 0) AS vistas,
            COALESCE(s.compartidas, 0) AS compartidas,
            COALESCE(v.vistas, 0) + COALESCE(s.compartidas, 0) AS total,
            c.nombre AS categoria_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
       LEFT JOIN (
            SELECT noticia_id, COUNT(*) AS vistas
              FROM noticias_vistas
             WHERE fecha BETWEEN ? AND ?
             GROUP BY noticia_id
       ) v ON v.noticia_id = n.id
       LEFT JOIN (
            SELECT noticia_id, SUM(cantidad) AS compartidas
              FROM noticias_compartidos_diarios
             WHERE fecha BETWEEN ? AND ?
             GROUP BY noticia_id
       ) s ON s.noticia_id = n.id
      WHERE COALESCE(v.vistas, 0) > 0 OR COALESCE(s.compartidas, 0) > 0
      ORDER BY total DESC, vistas DESC, n.id DESC'
);
$stmt->execute([
    $desde->format('Y-m-d'), $hasta->format('Y-m-d'),
    $desde->format('Y-m-d'), $hasta->format('Y-m-d'),
]);
$noticiasActividad = $stmt->fetchAll();
cargar_categorias_noticias($noticiasActividad);

$totalNoticias = count($noticiasActividad);
$totalVistas = array_sum(array_column($noticiasActividad, 'vistas'));
$totalCompartidas = array_sum(array_column($noticiasActividad, 'compartidas'));
$tasaCompartidosReferenciaTexto = number_format(TASA_COMPARTIDOS_REFERENCIA, 0, ',', '.') . '%';
$enGrafico = array_slice($noticiasActividad, 0, ACTIVIDAD_TOPE);
$ocultas = $totalNoticias - count($enGrafico);

// Esta serie es deliberadamente independiente de Desde/Hasta: siempre toma
// enero hasta el mes corriente del año actual y conserva meses sin actividad.
$anioActual = (int) $hoy->format('Y');
$mesActual = (int) $hoy->format('n');
$inicioAnio = $hoy->setDate($anioActual, 1, 1)->format('Y-m-d');
$inicioMesSiguiente = $hoy->modify('first day of next month')->format('Y-m-d');
$nombresMeses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$stmtVistasMensuales = $pdo->prepare(
    "SELECT MONTH(fecha) AS mes, COUNT(*) AS cantidad
       FROM noticias_vistas
      WHERE fecha >= ? AND fecha < ?
      GROUP BY MONTH(fecha)"
);
$stmtVistasMensuales->execute([$inicioAnio, $inicioMesSiguiente]);
$vistasPorMes = [];
foreach ($stmtVistasMensuales->fetchAll() as $fila) {
    $vistasPorMes[(int) $fila['mes']] = (int) $fila['cantidad'];
}

$stmtCompartidasMensuales = $pdo->prepare(
    "SELECT MONTH(fecha) AS mes, COALESCE(SUM(cantidad), 0) AS cantidad
       FROM noticias_compartidos_diarios
      WHERE fecha >= ? AND fecha < ?
      GROUP BY MONTH(fecha)"
);
$stmtCompartidasMensuales->execute([$inicioAnio, $inicioMesSiguiente]);
$compartidasPorMes = [];
foreach ($stmtCompartidasMensuales->fetchAll() as $fila) {
    $compartidasPorMes[(int) $fila['mes']] = (int) $fila['cantidad'];
}

$tasasMensuales = [];
$tasaMensualMaxima = TASA_COMPARTIDOS_REFERENCIA;
for ($mes = 1; $mes <= $mesActual; $mes++) {
    $vistasMes = $vistasPorMes[$mes] ?? 0;
    $compartidasMes = $compartidasPorMes[$mes] ?? 0;
    $tasaMes = $vistasMes > 0 ? ($compartidasMes / $vistasMes) * 100 : null;
    if ($tasaMes !== null) $tasaMensualMaxima = max($tasaMensualMaxima, $tasaMes);
    $tasasMensuales[] = [
        'mes' => $mes,
        'nombre' => $nombresMeses[$mes],
        'abreviado' => mb_substr($nombresMeses[$mes], 0, 3),
        'vistas' => $vistasMes,
        'compartidas' => $compartidasMes,
        'tasa' => $tasaMes,
        'texto' => $tasaMes !== null ? number_format($tasaMes, 1, ',', '.') . '%' : '—',
    ];
}
$escalaTasasMensuales = max(10, (int) ceil($tasaMensualMaxima / 5) * 5);
$periodoTasasMensuales = 'Enero a ' . $nombresMeses[$mesActual] . ' de ' . $anioActual;
$tasaCompartidosAria = 'Tasa mensual de compartidos. ' . $periodoTasasMensuales . '. Referencia histórica ' . $tasaCompartidosReferenciaTexto . '.';

$datosGrafico = [];
foreach ($enGrafico as $i => $noticia) {
    $datosGrafico[] = [
        'puesto' => $i + 1,
        'titulo' => (string) $noticia['titulo'],
        'categoria' => (string) ($noticia['categoria_nombre'] ?? ''),
        'vistas' => (int) $noticia['vistas'],
        'compartidas' => (int) $noticia['compartidas'],
        'total' => (int) $noticia['total'],
    ];
}

$seriesGrafico = [
    ['clave' => 'vistas', 'nombre' => 'Vistas', 'variable' => '--viz-vistas', 'id' => 'vistas'],
    ['clave' => 'compartidas', 'nombre' => 'Compartidas', 'variable' => '--viz-compartidas', 'id' => 'compartidas'],
];
$rangoVisible = $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');

$titulo = 'Vistas';
$active = 'vistas';
require __DIR__ . '/includes/header.php';
?>
      <div class="users-page-heading">
        <h1>Vistas</h1>
        <p>Compará las vistas únicas y las veces que se compartió cada noticia dentro del período seleccionado.</p>
      </div>

      <div class="viz-resumen">
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Noticias con actividad</span>
          <strong><?= (int) $totalNoticias ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Vistas</span>
          <strong><?= (int) $totalVistas ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Compartidas</span>
          <strong><?= (int) $totalCompartidas ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Período</span>
          <strong class="viz-resumen-periodo"><?= e($rangoVisible) ?></strong>
        </div>
      </div>

      <div class="viz-barra viz-barra-actividad">
        <div class="viz-controles-principales">
          <div class="viz-modo" role="group" aria-label="Tipo de gráfico">
            <button type="button" class="viz-modo-btn is-activo" data-modo="area" aria-pressed="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 17l5-6 4 3 5-7 4 4v6H3z"></path></svg>
              Área
            </button>
            <button type="button" class="viz-modo-btn" data-modo="columnas" aria-pressed="false">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="4" height="9"></rect><rect x="10" y="5" width="4" height="15"></rect><rect x="16" y="14" width="4" height="6"></rect></svg>
              Columnas
            </button>
          </div>

          <div class="viz-series" role="group" aria-label="Métricas visibles">
            <button type="button" class="viz-serie-btn is-activo" data-serie="vistas" aria-pressed="true"><i class="viz-swatch viz-swatch-vistas" aria-hidden="true"></i>Vistas</button>
            <button type="button" class="viz-serie-btn is-activo" data-serie="compartidas" aria-pressed="true"><i class="viz-swatch viz-swatch-compartidas" aria-hidden="true"></i>Compartidas</button>
          </div>

          <form class="viz-fechas" method="get" action="vistas.php">
            <label>Desde<input type="date" name="desde" value="<?= e($desde->format('Y-m-d')) ?>" max="<?= e($hoy->format('Y-m-d')) ?>"></label>
            <label>Hasta<input type="date" name="hasta" value="<?= e($hasta->format('Y-m-d')) ?>" max="<?= e($hoy->format('Y-m-d')) ?>"></label>
            <button type="submit" class="viz-fechas-btn">Aplicar</button>
          </form>
        </div>

<?php if ($noticiasActividad): ?>
        <div class="viz-acciones">
          <div class="viz-acciones-botones">
            <button type="button" class="viz-exportar-btn" id="vizExportarBtn" aria-label="Copiar filtros y gráficos como imagen PNG" title="Copiar filtros y gráficos como imagen PNG" disabled>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4 16 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3l1.5-3z"></path><circle cx="12" cy="13" r="3.5"></circle></svg>
            </button>
            <button type="button" class="viz-tabla-btn" id="vizTablaBtn" aria-expanded="false" aria-controls="vizTabla">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M3 15h18M9 3v18"></path></svg>
              Ver tabla
            </button>
          </div>
          <span class="viz-exportar-estado" id="vizExportarEstado" role="status" aria-live="polite"></span>
        </div>
<?php endif; ?>
      </div>

      <div class="users-panel viz-panel viz-actividad-panel">
        <div class="viz-actividad-distribucion">
          <div class="viz-actividad-principal">
<?php if (!$noticiasActividad): ?>
            <div class="viz-vacio viz-vacio-actividad">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              <h2>No hay actividad en este período</h2>
              <p>Probá ampliando las fechas. La tasa mensual de la derecha conserva siempre el año actual.</p>
            </div>
<?php else: ?>
            <div class="viz-panel-head">
              <div>
                <h2>Noticias con más vistas y compartidas</h2>
                <p><?= $ocultas > 0
                      ? 'Se muestran las ' . count($enGrafico) . ' primeras de ' . (int) $totalNoticias . '; las ' . (int) $ocultas . ' restantes están en la tabla.'
                      : 'Actividad registrada del ' . e($rangoVisible) . '.' ?></p>
              </div>
              <div class="viz-leyenda" aria-label="Leyenda">
                <span class="viz-leyenda-item" data-serie="vistas"><i class="viz-swatch viz-swatch-vistas"></i>Vistas</span>
                <span class="viz-leyenda-item" data-serie="compartidas"><i class="viz-swatch viz-swatch-compartidas"></i>Compartidas</span>
              </div>
            </div>

            <figure class="viz-figura viz-actividad" id="vizFigura">
              <div class="viz-lienzo" id="vizLienzo">
                <svg id="vizSvg" role="img" aria-label="Gráfico de vistas y noticias compartidas"></svg>
                <div class="viz-tooltip" id="vizTooltip" role="status" aria-live="polite"></div>
              </div>
              <figcaption>Eje vertical en cantidad de eventos. Podés mostrar una métrica o comparar ambas.</figcaption>
            </figure>
<?php endif; ?>
          </div>

          <aside class="viz-tasa-compartidos" aria-label="<?= e($tasaCompartidosAria) ?>">
            <div class="viz-tasa-encabezado">
              <span class="viz-tasa-kicker">Porcentaje de distribución</span>
              <h3>Tasa mensual <?= $anioActual ?></h3>
              <p>Cada mes muestra compartidas ÷ vistas. No cambia con Desde/Hasta.</p>
            </div>

            <div class="viz-tasa-meses" role="img" aria-label="<?= e($tasaCompartidosAria) ?>" style="--viz-tasa-referencia: <?= number_format(TASA_COMPARTIDOS_REFERENCIA / $escalaTasasMensuales * 100, 3, '.', '') ?>%;">
<?php foreach ($tasasMensuales as $tasaMensual):
    $anchoTasa = $tasaMensual['tasa'] !== null ? min(100, max(0, $tasaMensual['tasa'] / $escalaTasasMensuales * 100)) : 0;
    $ariaMes = $tasaMensual['nombre'] . ': ' . $tasaMensual['texto'] . ', ' . $tasaMensual['compartidas'] . ' compartidas sobre ' . $tasaMensual['vistas'] . ' vistas';
?>
              <div class="viz-tasa-mes" aria-label="<?= e($ariaMes) ?>" title="<?= e($ariaMes) ?>">
                <span><?= e($tasaMensual['abreviado']) ?></span>
                <div class="viz-tasa-barra">
                  <i style="width: <?= number_format($anchoTasa, 3, '.', '') ?>%;"></i>
                  <b aria-hidden="true"></b>
                </div>
                <strong><?= e($tasaMensual['texto']) ?></strong>
              </div>
<?php endforeach; ?>
            </div>

            <div class="viz-tasa-referencia">
              <span>Media histórica mensual</span>
              <strong><?= e($tasaCompartidosReferenciaTexto) ?></strong>
            </div>
            <p class="viz-tasa-periodo"><?= e($periodoTasasMensuales) ?></p>
            <p class="viz-tasa-nota">Escala 0–<?= (int) $escalaTasasMensuales ?>%. La marca vertical indica la referencia del 5%.</p>
          </aside>
        </div>
      </div>

<?php if ($noticiasActividad): ?>
      <div class="users-panel viz-panel viz-tabla-panel" id="vizTabla" hidden>
        <div class="viz-panel-head">
          <div><h2>Detalle del período</h2><p>Todas las noticias con actividad entre <?= e($rangoVisible) ?>.</p></div>
        </div>
        <div class="users-table-wrap">
          <table class="table users-table viz-datos">
            <thead><tr><th>Noticia</th><th>Categoría</th><th class="viz-num">Vistas</th><th class="viz-num">Compartidas</th></tr></thead>
            <tbody>
<?php foreach ($noticiasActividad as $noticia): ?>
              <tr>
                <td data-label="Noticia"><a href="#" class="js-ver-noticia" data-id="<?= (int) $noticia['id'] ?>" title="Ver noticia completa"><?= e($noticia['titulo']) ?></a></td>
                <td data-label="Categoría"><?php if (!empty($noticia['categoria_nombre'])): ?><span class="badge"><?= e($noticia['categoria_nombre']) ?></span><?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?></td>
                <td class="viz-num" data-label="Vistas"><?= (int) $noticia['vistas'] ?></td>
                <td class="viz-num" data-label="Compartidas"><?= (int) $noticia['compartidas'] ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <script id="vizDatos" type="application/json"><?= json_encode($datosGrafico, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <script id="vizSeries" type="application/json"><?= json_encode($seriesGrafico, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <script id="vizExportarResumen" type="application/json"><?= json_encode([
          'rango' => $rangoVisible,
          'desde' => $desde->format('Y-m-d'),
          'hasta' => $hasta->format('Y-m-d'),
          'noticias' => $totalNoticias,
          'vistas' => $totalVistas,
          'compartidas' => $totalCompartidas,
          'referencia' => TASA_COMPARTIDOS_REFERENCIA,
          'anio_tasas' => $anioActual,
          'periodo_tasas' => $periodoTasasMensuales,
          'escala_tasas' => $escalaTasasMensuales,
          'tasas_mensuales' => $tasasMensuales,
      ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <script src="assets/votaciones.js?v=<?= (int) @filemtime(__DIR__ . '/assets/votaciones.js') ?>" defer></script>
      <script src="assets/vistas-exportar.js?v=<?= (int) @filemtime(__DIR__ . '/assets/vistas-exportar.js') ?>" defer></script>
      <?php require __DIR__ . '/includes/noticia-preview-drawer.php'; ?>
<?php endif; ?>
      <script src="assets/analisis-refresh.js?v=<?= (int) @filemtime(__DIR__ . '/assets/analisis-refresh.js') ?>" data-refresh-seconds="30" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
