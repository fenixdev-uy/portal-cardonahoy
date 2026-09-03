<?php
/**
 * Listado de noticias (panel principal).
 */

require_once __DIR__ . '/includes/funciones.php';
$solicitudPortada = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && ($_POST['accion'] ?? '') === 'portada';
exigir_permiso('noticias.ver', $solicitudPortada);

$pdo = db();

if ($solicitudPortada) {
    exigir_permiso('noticias.editar', true);
    verificar_csrf(true);

    $idPortada = (int) ($_POST['id'] ?? 0);
    $estadoPortada = (string) ($_POST['portada'] ?? '0') === '1' ? 1 : 0;
    try {
        $pdo->beginTransaction();
        $stmtPortada = $pdo->prepare(
            'SELECT n.id,
                    EXISTS (SELECT 1 FROM noticias_fotos f WHERE f.noticia_id = n.id) AS tiene_foto
               FROM noticias n
              WHERE n.id = ?
              FOR UPDATE'
        );
        $stmtPortada->execute([$idPortada]);
        $noticiaPortada = $stmtPortada->fetch();
        if (!$noticiaPortada) {
            throw new DomainException('La noticia ya no existe.');
        }
        if ($estadoPortada === 1 && !(int) $noticiaPortada['tiene_foto']) {
            throw new DomainException('La noticia necesita al menos una foto para mostrarse en el slider.');
        }
        if ($estadoPortada === 1) {
            exigir_cupo_noticia_portada($pdo, $idPortada);
        }

        $stmtPortada = $pdo->prepare('UPDATE noticias SET portada = ?, updated_at = updated_at WHERE id = ?');
        $stmtPortada->execute([$estadoPortada, $idPortada]);
        $totalPortada = (int) $pdo->query('SELECT COUNT(*) FROM noticias WHERE portada = 1')->fetchColumn();
        $pdo->commit();
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No se pudo actualizar la portada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'portada' => $estadoPortada,
        'label' => $estadoPortada === 1 ? 'En portada' : 'Fuera de portada',
        'portada_total' => $totalPortada,
        'portada_limite' => PORTADA_NOTICIAS_LIMITE,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Últimas noticias para el listado
$noticias = $pdo->query(
    'SELECT n.id, n.titulo, n.descripcion, n.created_at, n.portada AS portada_estado,
            n.audio_1, n.audio_2, n.audio_3,
            n.me_gusta, n.no_me_gusta, n.vistas, n.compartidos,
            c.nombre AS categoria_nombre,
            u.nombre AS autor_nombre,
            (SELECT f.ruta FROM noticias_fotos f
              WHERE f.noticia_id = n.id
              ORDER BY f.posicion ASC, f.id ASC LIMIT 1) AS portada
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
       LEFT JOIN usuarios u ON u.id = n.usuario_id
      ORDER BY n.created_at DESC, n.id DESC'
)->fetchAll();
cargar_categorias_noticias($noticias);
$cantidadNoticiasPortada = count(array_filter(
    $noticias,
    static fn(array $noticia): bool => (int) ($noticia['portada_estado'] ?? 0) === 1
));

// Una sola consulta para calcular el peso de todas las galerías, sin N+1.
$fotosPorNoticiaAdmin = [];
foreach ($pdo->query('SELECT noticia_id, ruta FROM noticias_fotos ORDER BY noticia_id, posicion, id') as $foto) {
    $fotosPorNoticiaAdmin[(int) $foto['noticia_id']][] = $foto;
}
foreach ($noticias as &$noticiaListado) {
    $noticiaListado['_peso'] = peso_archivos_noticia(
        $noticiaListado,
        $fotosPorNoticiaAdmin[(int) $noticiaListado['id']] ?? []
    );
}
unset($noticiaListado);

$titulo = 'Noticias';
$active = 'noticias';

require __DIR__ . '/includes/header.php';
?>

<div class="users-page-heading">
  <h1>Noticias</h1>
  <p>Creá, editá y organizá las noticias publicadas en el portal y administrá sus imágenes, audios y videos.</p>
</div>

<div class="alert warning news-cover-limit-alert" id="newsCoverLimitAlert" role="status"<?= $cantidadNoticiasPortada > PORTADA_NOTICIAS_LIMITE ? '' : ' hidden' ?>>
  Hay <strong id="newsCoverLimitTotal"><?= $cantidadNoticiasPortada ?></strong> noticias seleccionadas para Portada. Desmarcá <strong id="newsCoverLimitExcess"><?= max(0, $cantidadNoticiasPortada - PORTADA_NOTICIAS_LIMITE) ?></strong> para respetar el máximo de <?= PORTADA_NOTICIAS_LIMITE ?>.
</div>
<div class="alert danger news-cover-notice" id="newsCoverNotice" role="alert" hidden></div>

<div class="table-wrap noticias-table">
  <div class="noticias-table-toolbar<?= empty($noticias) ? ' is-empty' : '' ?>">
    <?php if (!empty($noticias)): ?>
      <label class="noticias-search" for="noticiasSearch">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
        <input type="search" id="noticiasSearch" placeholder="Buscar noticias..." autocomplete="off" aria-describedby="noticiasSearchStatus">
      </label>
    <?php endif; ?>
    <span class="noticias-search-status" id="noticiasSearchStatus" aria-live="polite"><?= count($noticias) ?> noticias</span>
    <?php if (tiene_permiso('noticias.crear')): ?>
      <a href="noticia-form.php" class="btn btn-primary noticias-create-btn" aria-label="Nueva noticia" title="Nueva noticia">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Nueva noticia</span>
      </a>
    <?php endif; ?>
  </div>
  <?php if (empty($noticias)): ?>
    <div class="empty">
      No hay noticias todavía. Creá la primera con el botón «Nueva noticia».
    </div>
  <?php else: ?>
    <table class="table noticias-list">
      <thead>
        <tr>
          <th>Foto</th>
          <th>Título</th>
          <th>Categoría</th>
          <th class="table-sort-th date-sort-th" aria-sort="descending">
            <button class="table-sort-btn is-desc" type="button" id="dateSortBtn" aria-label="Ordenado por fecha, de más reciente a más antigua. Cambiar a más antigua primero">
              Fecha
              <svg class="table-sort-indicator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th class="table-sort-th weight-sort-th" style="width: 105px;" aria-sort="none">
            <button class="table-sort-btn weight-sort-btn" type="button" id="weightSortBtn" aria-label="Ordenar por peso, primero las noticias más pesadas">
              Peso
              <svg class="table-sort-indicator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th class="table-sort-th metric-header" style="width: 110px;" aria-sort="none">
            <button class="table-sort-btn metric-sort-btn" type="button" id="votesSortBtn" aria-label="Ordenar por votos, primero las noticias más votadas" title="Ordenar por votos">
              <span class="metric-header-icon metric-header-votes" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path></svg>
              </span>
              <svg class="table-sort-indicator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th class="table-sort-th metric-header" style="width: 85px;" aria-sort="none">
            <button class="table-sort-btn metric-sort-btn" type="button" id="viewsSortBtn" aria-label="Ordenar por vistas, primero las noticias más vistas" title="Ordenar por vistas">
              <span class="metric-header-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
              <svg class="table-sort-indicator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th class="table-sort-th metric-header" style="width: 110px;" aria-sort="none">
            <button class="table-sort-btn metric-sort-btn" type="button" id="sharesSortBtn" aria-label="Ordenar por compartidos, primero las noticias más compartidas" title="Ordenar por compartidos">
              <span class="metric-header-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.59 13.51 6.83 3.98"></path><path d="m15.41 6.51-6.82 3.98"></path></svg></span>
              <svg class="table-sort-indicator" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th style="width: 125px;">Portada</th>
          <th style="width: 130px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($noticias as $n): ?>
          <tr class="noticia-row" data-weight="<?= (int) $n['_peso']['total'] ?>" data-date="<?= e((string) strtotime($n['created_at'])) ?>" data-votes="<?= (int) ($n['me_gusta'] ?? 0) + (int) ($n['no_me_gusta'] ?? 0) ?>" data-views="<?= (int) ($n['vistas'] ?? 0) ?>" data-shares="<?= (int) ($n['compartidos'] ?? 0) ?>">
            <td class="td-photo">
              <a href="#" class="thumb-link js-ver-noticia" data-id="<?= (int) $n['id'] ?>" title="Ver noticia completa">
                <?php if (!empty($n['portada'])): ?>
                  <img class="thumb" src="<?= e(url_imagen($n['portada'])) ?>" alt="" />
                <?php else: ?>
                  <span class="thumb" style="background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:0.7rem;">Sin foto</span>
                <?php endif; ?>
              </a>
            </td>
            <td class="td-info">
              <strong class="cell-title"><?= e($n['titulo']) ?></strong>
              <div class="cell-desc"><?= e(html_a_texto($n['descripcion'])) ?></div>
              <div class="cell-author"><?= e($n['autor_nombre'] ?? 'Sin autor') ?></div>
            </td>
            <td class="td-category">
              <?php if (!empty($n['categoria_nombre'])): ?>
                <span class="badge"><?= e($n['categoria_nombre']) ?></span>
              <?php else: ?>
                <span style="color:#94a3b8;">—</span>
              <?php endif; ?>
            </td>
            <td class="td-date"><time datetime="<?= e(date(DATE_ATOM, strtotime($n['created_at']))) ?>" title="Fecha y hora de publicación"><?= e(date('d/m/Y · H:i', strtotime($n['created_at']))) ?> hs.</time></td>
            <td class="td-weight">
              <span class="weight-value" title="Fotos: <?= e(formatear_megabytes((int) $n['_peso']['fotos'])) ?> · Audios: <?= e(formatear_megabytes((int) $n['_peso']['audios'])) ?> · Solo archivos alojados en este servidor">
                <?= e(formatear_megabytes((int) $n['_peso']['total'])) ?>
              </span>
            </td>
            <td class="td-votes">
              <span class="vote-stat" title="Me gusta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                <?= (int) ($n['me_gusta'] ?? 0) ?>
              </span>
              <span class="vote-stat" title="No me gusta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path></svg>
                <?= (int) ($n['no_me_gusta'] ?? 0) ?>
              </span>
            </td>
            <td class="td-views">
              <span class="metric-stat" title="Vistas únicas por día">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <?= (int) ($n['vistas'] ?? 0) ?>
              </span>
            </td>
            <td class="td-shares">
              <span class="metric-stat" title="Veces que se pulsó compartir">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.59 13.51 6.83 3.98"></path><path d="m15.41 6.51-6.82 3.98"></path></svg>
                <?= (int) ($n['compartidos'] ?? 0) ?>
              </span>
            </td>
            <td class="td-news-cover">
              <?php if (tiene_permiso('noticias.editar')): ?>
                <form method="post" action="index.php" class="news-cover-status-form js-news-cover-form">
                  <?= csrf_input() ?>
                  <input type="hidden" name="accion" value="portada">
                  <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                  <label class="news-cover-switch">
                    <input type="checkbox" name="portada" value="1" role="switch" <?= (int) ($n['portada_estado'] ?? 0) === 1 ? 'checked' : '' ?> aria-label="<?= (int) ($n['portada_estado'] ?? 0) === 1 ? 'Quitar de portada' : 'Mostrar en portada' ?>: <?= e($n['titulo']) ?>">
                    <span class="news-cover-switch-track" aria-hidden="true"><span></span></span>
                    <span class="news-cover-switch-text"><?= (int) ($n['portada_estado'] ?? 0) === 1 ? 'Sí' : 'No' ?></span>
                  </label>
                  <span class="news-cover-feedback" aria-live="polite"></span>
                </form>
              <?php else: ?>
                <span class="news-cover-readonly <?= (int) ($n['portada_estado'] ?? 0) === 1 ? 'is-active' : '' ?>"><?= (int) ($n['portada_estado'] ?? 0) === 1 ? 'Sí' : 'No' ?></span>
              <?php endif; ?>
            </td>
            <td class="td-actions">
              <div class="cell-actions">
                <?php if (tiene_permiso('noticias.editar')): ?><a class="btn btn-outline btn-sm" href="noticia-form.php?id=<?= (int) $n['id'] ?>" aria-label="Editar">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </a><?php endif; ?>
                <?php if (tiene_permiso('noticias.eliminar')): ?><form method="post" action="noticia-borrar.php" onsubmit="return confirm('¿Eliminar esta noticia? Esta acción no se puede deshacer.');">
                  <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit" aria-label="Borrar">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </form><?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="noticias-filter-empty" id="noticiasFilterEmpty" hidden>
      No encontramos noticias con esa búsqueda.
    </div>
  <?php endif; ?>
</div>

<script>
  (function () {
    const weightButton = document.getElementById('weightSortBtn');
    const dateButton = document.getElementById('dateSortBtn');
    const votesButton = document.getElementById('votesSortBtn');
    const viewsButton = document.getElementById('viewsSortBtn');
    const sharesButton = document.getElementById('sharesSortBtn');
    const search = document.getElementById('noticiasSearch');
    const status = document.getElementById('noticiasSearchStatus');
    const empty = document.getElementById('noticiasFilterEmpty');
    const tbody = document.querySelector('.noticias-list tbody');
    if (!weightButton || !dateButton || !votesButton || !viewsButton || !sharesButton || !search || !status || !empty || !tbody) return;

    const original = new Map(Array.from(tbody.rows).map((row, index) => [row, index]));
    const controls = {
      date: {
        button: dateButton,
        inactive: 'Ordenar por fecha, primero las noticias más recientes',
        descending: 'Ordenado por fecha, de más reciente a más antigua. Cambiar a más antigua primero',
        ascending: 'Ordenado por fecha, de más antigua a más reciente. Quitar ordenamiento',
      },
      weight: {
        button: weightButton,
        inactive: 'Ordenar por peso, primero las noticias más pesadas',
        descending: 'Ordenado por peso de mayor a menor. Cambiar a menor a mayor',
        ascending: 'Ordenado por peso de menor a mayor. Quitar ordenamiento',
      },
      votes: {
        button: votesButton,
        inactive: 'Ordenar por votos, primero las noticias más votadas',
        descending: 'Ordenado por votos de mayor a menor. Cambiar a menor a mayor',
        ascending: 'Ordenado por votos de menor a mayor. Quitar ordenamiento',
      },
      views: {
        button: viewsButton,
        inactive: 'Ordenar por vistas, primero las noticias más vistas',
        descending: 'Ordenado por vistas de mayor a menor. Cambiar a menor a mayor',
        ascending: 'Ordenado por vistas de menor a mayor. Quitar ordenamiento',
      },
      shares: {
        button: sharesButton,
        inactive: 'Ordenar por compartidos, primero las noticias más compartidas',
        descending: 'Ordenado por compartidos de mayor a menor. Cambiar a menor a mayor',
        ascending: 'Ordenado por compartidos de menor a mayor. Quitar ordenamiento',
      },
    };
    let sortKey = 'date';
    let direction = 'desc';

    const normalize = (value) => value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLocaleLowerCase('es');

    function updateSortControls() {
      Object.entries(controls).forEach(([key, control]) => {
        const active = sortKey === key;
        control.button.closest('th').setAttribute('aria-sort', active ? (direction === 'desc' ? 'descending' : 'ascending') : 'none');
        control.button.classList.remove('is-desc', 'is-asc');
        if (active) control.button.classList.add(direction === 'desc' ? 'is-desc' : 'is-asc');
        control.button.setAttribute('aria-label', active
          ? (direction === 'desc' ? control.descending : control.ascending)
          : control.inactive);
      });
    }

    function sortRows(key, nextDirection) {
      sortKey = key;
      direction = nextDirection;
      const multiplier = direction === 'desc' ? -1 : 1;
      const rows = Array.from(tbody.rows);
      rows.sort((a, b) => {
        const difference = (Number(a.dataset[key]) - Number(b.dataset[key])) * multiplier;
        return difference || original.get(a) - original.get(b);
      });
      rows.forEach((row) => tbody.appendChild(row));
      updateSortControls();
    }

    function restoreOriginalOrder() {
      sortKey = null;
      direction = null;
      Array.from(tbody.rows)
        .sort((a, b) => original.get(a) - original.get(b))
        .forEach((row) => tbody.appendChild(row));
      updateSortControls();
    }

    function filterRows() {
      const term = normalize(search.value.trim());
      let visible = 0;
      Array.from(tbody.rows).forEach((row) => {
        const matches = !term || normalize(row.textContent || '').includes(term);
        row.hidden = !matches;
        if (matches) visible += 1;
      });
      const total = tbody.rows.length;
      status.textContent = term ? `${visible} de ${total} noticias` : `${total} noticias`;
      empty.hidden = visible !== 0;
    }

    Object.entries(controls).forEach(([key, control]) => {
      control.button.addEventListener('click', () => {
        if (sortKey !== key) {
          sortRows(key, 'desc');
        } else if (direction === 'desc') {
          sortRows(key, 'asc');
        } else {
          restoreOriginalOrder();
        }
      });
    });
    search.addEventListener('input', filterRows);
  })();
</script>

<script>
  (function () {
    const notice = document.getElementById('newsCoverNotice');
    const limitAlert = document.getElementById('newsCoverLimitAlert');
    const limitTotal = document.getElementById('newsCoverLimitTotal');
    const limitExcess = document.getElementById('newsCoverLimitExcess');

    function updateLimitAlert(total, limit) {
      if (!limitAlert || !limitTotal || !limitExcess) return;
      limitTotal.textContent = String(total);
      limitExcess.textContent = String(Math.max(0, total - limit));
      limitAlert.hidden = total <= limit;
    }

    document.querySelectorAll('.js-news-cover-form').forEach((form) => {
      const input = form.querySelector('input[name="portada"]');
      const text = form.querySelector('.news-cover-switch-text');
      const feedback = form.querySelector('.news-cover-feedback');
      if (!input || !text || !feedback) return;

      input.addEventListener('change', async () => {
        const requestedState = input.checked;
        input.disabled = true;
        feedback.textContent = 'Guardando…';
        if (notice) notice.hidden = true;

        const payload = new FormData(form);
        payload.set('portada', requestedState ? '1' : '0');

        try {
          const response = await fetch('index.php', {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const result = await response.json();
          if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo actualizar la portada.');

          input.checked = result.portada === 1;
          text.textContent = input.checked ? 'Sí' : 'No';
          input.setAttribute('aria-label', (input.checked ? 'Quitar de portada: ' : 'Mostrar en portada: ')
            + (form.closest('tr')?.querySelector('.cell-title')?.textContent || 'noticia'));
          feedback.textContent = 'Guardado';
          updateLimitAlert(Number(result.portada_total || 0), Number(result.portada_limite || 5));
          window.setTimeout(() => {
            if (feedback.textContent === 'Guardado') feedback.textContent = '';
          }, 1800);
        } catch (error) {
          input.checked = !requestedState;
          text.textContent = input.checked ? 'Sí' : 'No';
          feedback.textContent = 'No se guardó';
          if (notice) {
            notice.textContent = error.message || 'No se pudo guardar.';
            notice.hidden = false;
          }
        } finally {
          input.disabled = false;
        }
      });
    });
  })();
</script>

<!-- ===== Panel lateral de detalle (preview) ===== -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>
<aside class="drawer" id="drawer" aria-hidden="true" aria-label="Vista previa de noticia">
  <header class="drawer-header">
    <span class="drawer-title-label">Vista previa</span>
    <button type="button" class="drawer-close" id="drawerClose" aria-label="Cerrar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </header>

  <div class="drawer-body" id="drawerBody">
    <!-- El contenido se completa vía JavaScript -->
  </div>

  <footer class="drawer-footer">
    <?php if (tiene_permiso('noticias.editar')): ?><a href="#" class="btn btn-primary" id="drawerEdit">Editar</a><?php endif; ?>
    <button type="button" class="btn btn-outline" id="drawerCloseBtn">Cerrar</button>
  </footer>
</aside>

<script>
  (function () {
    const backdrop = document.getElementById('drawerBackdrop');
    const drawer = document.getElementById('drawer');
    const drawerBody = document.getElementById('drawerBody');
    const drawerClose = document.getElementById('drawerClose');
    const drawerCloseBtn = document.getElementById('drawerCloseBtn');
    const drawerEdit = document.getElementById('drawerEdit');

    const calendarIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';

    function openDrawer() {
      drawer.classList.add('open');
      backdrop.classList.add('show');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
      drawer.classList.remove('open');
      backdrop.classList.remove('show');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    function render(noticia) {
      drawerBody.innerHTML =
        '<div class="news-hero"></div>' +
        '<div class="news-body">' +
          '<span class="news-category" style="display:none;"></span>' +
          '<h2 class="news-title"></h2>' +
          '<div class="news-meta" style="display:none;">' + calendarIcon + '<span class="news-fecha"></span><span class="news-meta-sep">·</span><span class="news-author"></span></div>' +
          '<div class="news-gallery" style="display:none;"></div>' +
          '<div class="news-content"></div>' +
          '<div class="news-media" style="display:none;">' +
            '<section class="news-media-group news-audios" style="display:none;"><div class="news-video-title">Audios</div><div class="news-audio-list"></div></section>' +
            '<section class="news-media-group news-videos" style="display:none;"><div class="news-video-title">Videos</div><div class="news-video-list"></div></section>' +
          '</div>' +
        '</div>';

      const hero = drawerBody.querySelector('.news-hero');
      const fotos = Array.isArray(noticia.galeria) && noticia.galeria.length
        ? noticia.galeria
        : (noticia.foto_principal ? [{ url: noticia.foto_principal }] : []);
      const miniaturas = [];
      let fotoActiva = 0;
      let heroImg = null;

      function mostrarFoto(indice) {
        if (!heroImg || !fotos.length) return;
        fotoActiva = (indice + fotos.length) % fotos.length;
        heroImg.classList.add('is-changing');
        window.setTimeout(() => {
          heroImg.src = fotos[fotoActiva].url;
          heroImg.alt = 'Foto ' + (fotoActiva + 1) + ' de ' + fotos.length;
          miniaturas.forEach((img, i) => img.classList.toggle('is-main', i === fotoActiva));
          window.requestAnimationFrame(() => heroImg.classList.remove('is-changing'));
        }, 90);
      }

      if (fotos.length) {
        heroImg = document.createElement('img');
        heroImg.src = fotos[0].url;
        heroImg.alt = 'Foto 1 de ' + fotos.length;
        hero.appendChild(heroImg);

        if (fotos.length > 1) {
          const anterior = document.createElement('button');
          anterior.type = 'button';
          anterior.className = 'news-hero-arrow news-hero-arrow-prev';
          anterior.setAttribute('aria-label', 'Foto anterior');
          anterior.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>';
          anterior.addEventListener('click', () => mostrarFoto(fotoActiva - 1));

          const siguiente = document.createElement('button');
          siguiente.type = 'button';
          siguiente.className = 'news-hero-arrow news-hero-arrow-next';
          siguiente.setAttribute('aria-label', 'Foto siguiente');
          siguiente.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>';
          siguiente.addEventListener('click', () => mostrarFoto(fotoActiva + 1));
          hero.append(anterior, siguiente);
        }
      } else {
        const sinFoto = document.createElement('div');
        sinFoto.className = 'news-no-photo';
        sinFoto.textContent = 'Sin foto';
        hero.appendChild(sinFoto);
      }

      // Título, categoría y fecha se asignan con textContent (diferenciando
      // contenido inyectable de la descripción, ya saneada en el servidor).
      drawerBody.querySelector('.news-title').textContent = noticia.titulo || '';

      const categoria = drawerBody.querySelector('.news-category');
      if (noticia.categoria) {
        categoria.textContent = noticia.categoria;
        categoria.style.display = '';
      }

      const meta = drawerBody.querySelector('.news-meta');
      const fecha = drawerBody.querySelector('.news-fecha');
      const fechaTexto = noticia.fecha_larga || noticia.fecha || '';
      if (fechaTexto) {
        fecha.textContent = fechaTexto;
        meta.style.display = '';
      }

      const autor = drawerBody.querySelector('.news-author');
      if (noticia.autor) {
        autor.textContent = noticia.autor;
      } else {
        autor.style.display = 'none';
      }

      // Galería: miniaturas (solo se muestran si hay más de una foto).
      const galeria = drawerBody.querySelector('.news-gallery');
      if (fotos.length > 1) {
        fotos.forEach((f, i) => {
          const img = document.createElement('img');
          img.src = f.url;
          img.alt = 'Foto ' + (i + 1);
          if (i === 0) img.classList.add('is-main');
          img.tabIndex = 0;
          img.setAttribute('role', 'button');
          img.setAttribute('aria-label', 'Mostrar foto ' + (i + 1));
          img.addEventListener('click', () => mostrarFoto(i));
          img.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              mostrarFoto(i);
            }
          });
          miniaturas.push(img);
          galeria.appendChild(img);
        });
        galeria.style.display = '';
      }

      const content = drawerBody.querySelector('.news-content');
      // Las imágenes del cuerpo se guardan relativas a landing/; en el panel
      // (que vive en landing/admin/) hay que anteponer "../" para mostrarlas.
      const descripcion = (noticia.descripcion || '<p>Sin descripción.</p>')
        .replace(/src="uploads\//g, 'src="../uploads/')
        .replace(/src='uploads\//g, "src='../uploads/");
      content.innerHTML = descripcion;

      // Audios y videos opcionales, debajo de la descripción.
      const media = drawerBody.querySelector('.news-media');
      const audios = Array.isArray(noticia.audios) ? noticia.audios : [];
      if (audios.length) {
        const section = drawerBody.querySelector('.news-audios');
        const list = drawerBody.querySelector('.news-audio-list');
        audios.forEach((url, index) => {
          const item = document.createElement('div');
          item.className = 'news-audio-item';
          const label = document.createElement('span');
          label.textContent = 'Audio ' + (index + 1);
          const player = document.createElement('audio');
          player.controls = true;
          player.preload = 'metadata';
          player.src = url;
          item.append(label, player);
          list.appendChild(item);
        });
        section.style.display = '';
        media.style.display = '';
      }

      const videos = Array.isArray(noticia.videos) ? noticia.videos : (noticia.youtube ? [noticia.youtube] : []);
      if (videos.length) {
        const section = drawerBody.querySelector('.news-videos');
        const list = drawerBody.querySelector('.news-video-list');
        videos.forEach((url, index) => {
          const frame = document.createElement('div');
          frame.className = 'news-video-frame';
          const iframe = document.createElement('iframe');
          iframe.src = url;
          iframe.title = 'Video de YouTube ' + (index + 1);
          iframe.loading = 'lazy';
          iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
          iframe.allowFullscreen = true;
          iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
          frame.appendChild(iframe);
          list.appendChild(frame);
        });
        section.style.display = '';
        media.style.display = '';
      }

      if (drawerEdit) drawerEdit.href = 'noticia-form.php?id=' + noticia.id;
    }

    function showError() {
      drawerBody.innerHTML = '<div class="empty">No se pudo cargar la noticia.</div>';
    }

    async function cargarNoticia(id) {
      openDrawer();
      drawerBody.innerHTML = '<div class="empty">Cargando…</div>';

      try {
        const res = await fetch('noticia-detalle.php?id=' + encodeURIComponent(id));
        if (!res.ok) {
          throw new Error('Respuesta ' + res.status);
        }
        const data = await res.json();
        if (data.error) {
          throw new Error(data.error);
        }
        render(data);
      } catch (e) {
        showError();
      }
    }

    // Abre el panel al hacer clic en la foto
    document.querySelectorAll('.js-ver-noticia').forEach((el) => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        cargarNoticia(el.getAttribute('data-id'));
      });
    });

    drawerClose.addEventListener('click', closeDrawer);
    drawerCloseBtn.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && drawer.classList.contains('open')) {
        closeDrawer();
      }
    });
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
