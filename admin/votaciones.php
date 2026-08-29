<?php
/**
 * Votaciones: ranking de noticias por cantidad de votos.
 *
 * Dos lecturas del mismo dato, a eleccion del usuario: area degradada y
 * columnas. Un solo eje, porque las dos series son votos y comparten unidad.
 *
 * Colores: azul y rojo, el par divergente documentado, que ademas se lee como
 * opuesto igual que me gusta / no me gusta. Validado sobre la superficie real
 * del panel (#ffffff): separacion CVD 21.6 y vision normal 32.3, ambas holgadas.
 */

require_once __DIR__ . '/includes/funciones.php';

const VOTACIONES_TOPE = 8;

$pdo = db();

$noticiasVotadas = $pdo->query(
    'SELECT n.id, n.titulo, n.me_gusta, n.no_me_gusta,
            (n.me_gusta + n.no_me_gusta) AS total,
            c.nombre AS categoria_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
      WHERE n.me_gusta > 0 OR n.no_me_gusta > 0
      ORDER BY total DESC, n.me_gusta DESC, n.id DESC'
)->fetchAll();
cargar_categorias_noticias($noticiasVotadas);

$totalConVotos = count($noticiasVotadas);
$enGrafico = array_slice($noticiasVotadas, 0, VOTACIONES_TOPE);
$ocultas = $totalConVotos - count($enGrafico);

$sumaMeGusta = array_sum(array_column($noticiasVotadas, 'me_gusta'));
$sumaNoMeGusta = array_sum(array_column($noticiasVotadas, 'no_me_gusta'));

// El grafico se dibuja en el cliente a partir de estos datos.
$datosGrafico = [];
foreach ($enGrafico as $i => $n) {
    $datosGrafico[] = [
        'puesto' => $i + 1,
        'titulo' => (string) $n['titulo'],
        'categoria' => (string) ($n['categoria_nombre'] ?? ''),
        'meGusta' => (int) $n['me_gusta'],
        'noMeGusta' => (int) $n['no_me_gusta'],
        'total' => (int) $n['total'],
    ];
}

$titulo = 'Votaciones';
$active = 'votaciones';

require __DIR__ . '/includes/header.php';
?>
      <div class="users-page-heading">
        <h1>Votaciones</h1>
        <p>Noticias ordenadas por cantidad de votos, de mayor a menor. Cada noticia admite un voto por visitante y el voto es definitivo, por lo que los totales solo crecen.</p>
      </div>

<?php if (!$noticiasVotadas): ?>
      <div class="users-panel viz-vacio">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"></path><path d="M7 15l4-4 3 3 5-6"></path></svg>
        <h2>Todavía no hay votos</h2>
        <p>Cuando los lectores empiecen a votar en el sitio, acá vas a ver el ranking de las noticias más votadas.</p>
      </div>
<?php else: ?>
      <div class="viz-resumen">
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Noticias con votos</span>
          <strong><?= (int) $totalConVotos ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Total de votos</span>
          <strong><?= (int) ($sumaMeGusta + $sumaNoMeGusta) ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">Me gusta</span>
          <strong><?= (int) $sumaMeGusta ?></strong>
        </div>
        <div class="viz-resumen-dato">
          <span class="viz-resumen-label">No me gusta</span>
          <strong><?= (int) $sumaNoMeGusta ?></strong>
        </div>
      </div>

      <!-- Los controles van en una sola fila arriba de todo lo que afectan. -->
      <div class="viz-barra">
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

        <button type="button" class="viz-tabla-btn" id="vizTablaBtn" aria-expanded="false" aria-controls="vizTabla">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18M3 15h18M9 3v18"></path></svg>
          Ver tabla
        </button>
      </div>

      <div class="users-panel viz-panel">
        <div class="viz-panel-head">
          <div>
            <h2>Ranking de noticias más votadas</h2>
            <p><?= $ocultas > 0
                  ? 'Se muestran las ' . count($enGrafico) . ' primeras de ' . (int) $totalConVotos . '; las ' . (int) $ocultas . ' restantes están en la tabla.'
                  : 'Se muestran las ' . count($enGrafico) . ' noticias con votos.' ?></p>
          </div>
          <!-- Con dos series la leyenda va siempre: la identidad no depende del color. -->
          <div class="viz-leyenda">
            <span class="viz-leyenda-item"><i class="viz-swatch viz-swatch-si"></i>Me gusta</span>
            <span class="viz-leyenda-item"><i class="viz-swatch viz-swatch-no"></i>No me gusta</span>
          </div>
        </div>

        <figure class="viz-figura" id="vizFigura">
          <div class="viz-lienzo" id="vizLienzo">
            <svg id="vizSvg" role="img" aria-label="Gráfico de noticias más votadas"></svg>
            <div class="viz-tooltip" id="vizTooltip" role="status" aria-live="polite"></div>
          </div>
          <figcaption>Eje vertical en cantidad de votos. Pasá el mouse por el gráfico para ver el detalle de cada noticia.</figcaption>
        </figure>
      </div>

      <!-- Gemelo en tabla: ningun valor queda solo detras del tooltip. -->
      <div class="users-panel viz-panel viz-tabla-panel" id="vizTabla" hidden>
        <div class="viz-panel-head">
          <div>
            <h2>Todos los votos</h2>
            <p>Las <?= (int) $totalConVotos ?> noticias con votos, en el mismo orden que el gráfico.</p>
          </div>
        </div>
        <div class="users-table-wrap">
          <table class="table users-table viz-datos">
            <thead>
              <tr>
                <th>Noticia</th>
                <th>Categoría</th>
                <th>Votos</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($noticiasVotadas as $n): ?>
              <tr>
                <td data-label="Noticia"><a href="#" class="js-ver-noticia" data-id="<?= (int) $n['id'] ?>" title="Ver noticia completa"><?= e($n['titulo']) ?></a></td>
                <td data-label="Categoría">
                  <?php if (!empty($n['categoria_nombre'])): ?>
                    <span class="badge"><?= e($n['categoria_nombre']) ?></span>
                  <?php else: ?>
                    <span style="color:#94a3b8;">—</span>
                  <?php endif; ?>
                </td>
                <td class="td-votes" data-label="Votos">
                  <div class="viz-vote-stats">
                    <span class="vote-stat" title="Me gusta">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                      <?= (int) ($n['me_gusta'] ?? 0) ?>
                    </span>
                    <span class="vote-stat" title="No me gusta">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path></svg>
                      <?= (int) ($n['no_me_gusta'] ?? 0) ?>
                    </span>
                  </div>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <script id="vizDatos" type="application/json"><?= json_encode($datosGrafico, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <script src="assets/votaciones.js?v=<?= (int) @filemtime(__DIR__ . '/assets/votaciones.js') ?>" defer></script>
      <?php require __DIR__ . '/includes/noticia-preview-drawer.php'; ?>
<?php endif; ?>
      <script src="assets/analisis-refresh.js?v=<?= (int) @filemtime(__DIR__ . '/assets/analisis-refresh.js') ?>" data-refresh-seconds="30" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
