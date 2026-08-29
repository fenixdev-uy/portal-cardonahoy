<?php
/** Análisis temporal de noticias publicadas por día. */

require_once __DIR__ . '/includes/funciones.php';

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
$consulta = $pdo->prepare(
    'SELECT DATE(created_at) AS fecha, COUNT(*) AS cantidad
       FROM noticias
      WHERE created_at >= ? AND created_at < ?
      GROUP BY DATE(created_at)
      ORDER BY fecha ASC'
);
$consulta->execute([
    $desde->format('Y-m-d 00:00:00'),
    $hasta->modify('+1 day')->format('Y-m-d 00:00:00'),
]);

$publicacionesPorFecha = [];
foreach ($consulta->fetchAll() as $fila) {
    $publicacionesPorFecha[(string) $fila['fecha']] = (int) $fila['cantidad'];
}

$datosGrafico = [];
$totalPublicaciones = 0;
$diasConPublicaciones = 0;
for ($dia = $desde; $dia <= $hasta; $dia = $dia->modify('+1 day')) {
    $cantidad = $publicacionesPorFecha[$dia->format('Y-m-d')] ?? 0;
    $totalPublicaciones += $cantidad;
    if ($cantidad > 0) $diasConPublicaciones++;
    $datosGrafico[] = [
        'fecha' => $dia->format('Y-m-d'),
        'etiqueta' => $dia->format('d/m'),
        'fechaVisible' => $dia->format('d/m/Y'),
        'publicaciones' => $cantidad,
    ];
}

$cantidadDias = max(1, count($datosGrafico));
$promedioDiario = $totalPublicaciones / $cantidadDias;
$rangoVisible = $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');

$titulo = 'Publicaciones';
$active = 'publicaciones';
require __DIR__ . '/includes/header.php';
?>
      <div class="users-page-heading">
        <h1>Publicaciones</h1>
        <p>Consultá cuántas noticias se publicaron por día dentro del período seleccionado.</p>
      </div>

      <div class="viz-resumen">
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Publicaciones</span>
          <strong><?= (int) $totalPublicaciones ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Días con publicaciones</span>
          <strong><?= (int) $diasConPublicaciones ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Promedio diario</span>
          <strong><?= e(number_format($promedioDiario, 1, ',', '.')) ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Período</span>
          <strong class="viz-resumen-periodo"><?= e($rangoVisible) ?></strong>
        </div>
      </div>

      <div class="viz-barra viz-barra-publicaciones">
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

          <form class="viz-fechas" method="get" action="publicaciones.php">
            <label>Desde<input type="date" name="desde" value="<?= e($desde->format('Y-m-d')) ?>" max="<?= e($hoy->format('Y-m-d')) ?>"></label>
            <label>Hasta<input type="date" name="hasta" value="<?= e($hasta->format('Y-m-d')) ?>" max="<?= e($hoy->format('Y-m-d')) ?>"></label>
            <button type="submit" class="viz-fechas-btn">Aplicar</button>
          </form>
        </div>
      </div>

<?php if ($totalPublicaciones < 1): ?>
      <div class="users-panel viz-vacio">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M8 2v4M16 2v4M3 10h18"></path></svg>
        <h2>No hay publicaciones en este período</h2>
        <p>Probá ampliando las fechas para consultar otros días.</p>
      </div>
<?php else: ?>
      <div class="users-panel viz-panel">
        <div class="viz-panel-head">
          <div>
            <h2>Publicaciones por día</h2>
            <p>Noticias creadas del <?= e($rangoVisible) ?>.</p>
          </div>
          <div class="viz-leyenda" aria-label="Leyenda">
            <span class="viz-leyenda-item"><i class="viz-swatch viz-swatch-publicaciones"></i>Publicaciones</span>
          </div>
        </div>

        <figure class="viz-figura viz-publicaciones" id="vizFigura">
          <div class="viz-lienzo" id="vizLienzo">
            <svg id="vizSvg" role="img" aria-label="Gráfico de noticias publicadas por día"></svg>
            <div class="viz-tooltip" id="vizTooltip" role="status" aria-live="polite"></div>
          </div>
          <figcaption>Eje vertical en cantidad de noticias. Cambiá entre área y columnas para comparar los días.</figcaption>
        </figure>
      </div>

      <script id="vizDatos" type="application/json"><?= json_encode($datosGrafico, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <script src="assets/publicaciones.js?v=<?= (int) @filemtime(__DIR__ . '/assets/publicaciones.js') ?>" defer></script>
<?php endif; ?>
      <script src="assets/analisis-refresh.js?v=<?= (int) @filemtime(__DIR__ . '/assets/analisis-refresh.js') ?>" data-refresh-seconds="30" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
