<?php
/**
 * Listado de noticias (panel principal).
 */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('noticias.ver');

$pdo = db();

// Publicaciones por día del mes corriente para el resumen gráfico.
$inicioMes = new DateTimeImmutable('first day of this month 00:00:00');
$inicioMesSiguiente = $inicioMes->modify('first day of next month');
$consultaPublicaciones = $pdo->prepare(
    'SELECT DATE(created_at) AS fecha, COUNT(*) AS cantidad
       FROM noticias
      WHERE created_at >= :inicio AND created_at < :fin
      GROUP BY DATE(created_at)
      ORDER BY fecha ASC'
);
$consultaPublicaciones->execute([
    ':inicio' => $inicioMes->format('Y-m-d H:i:s'),
    ':fin' => $inicioMesSiguiente->format('Y-m-d H:i:s'),
]);

$publicacionesPorFecha = [];
foreach ($consultaPublicaciones->fetchAll() as $fila) {
    $publicacionesPorFecha[(string) $fila['fecha']] = (int) $fila['cantidad'];
}

$datosPublicaciones = [];
$totalPublicacionesMes = 0;
for ($dia = $inicioMes; $dia < $inicioMesSiguiente; $dia = $dia->modify('+1 day')) {
    $cantidad = $publicacionesPorFecha[$dia->format('Y-m-d')] ?? 0;
    $totalPublicacionesMes += $cantidad;
    $datosPublicaciones[] = [
        'dia' => (int) $dia->format('j'),
        'fecha' => $dia->format('Y-m-d'),
        'cantidad' => $cantidad,
    ];
}

$meses = [
    1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];
$nombreMes = $meses[(int) $inicioMes->format('n')] . ' ' . $inicioMes->format('Y');

// Últimas noticias para el listado
$noticias = $pdo->query(
    'SELECT n.id, n.titulo, n.descripcion, n.created_at,
            n.audio_1, n.audio_2, n.audio_3,
            n.me_gusta, n.no_me_gusta,
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
  <p>Creá, editá y organizá las noticias publicadas en el portal. Consultá la actividad del mes y administrá sus imágenes, audios y videos.</p>
</div>

<details class="publication-chart-card" open>
  <summary class="publication-chart-summary">
    <span class="publication-chart-heading">
      <span class="publication-chart-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M7 15l4-4 3 3 5-7"></path></svg>
      </span>
      <span>
        <strong>Publicaciones del mes</strong>
        <small><?= e($nombreMes) ?> · cantidad de noticias por día</small>
      </span>
    </span>
    <span class="publication-chart-meta">
      <span><strong><?= (int) $totalPublicacionesMes ?></strong> en el mes</span>
      <span class="publication-chart-toggle" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
      </span>
    </span>
  </summary>
  <div class="publication-chart-body">
    <figure class="publication-chart-figure">
      <div class="publication-chart-canvas" id="publicationChartCanvas">
        <svg id="publicationChart" role="img" aria-label="Cantidad de noticias publicadas por día durante <?= e($nombreMes) ?>"></svg>
        <div class="publication-chart-tooltip" id="publicationChartTooltip" role="status" aria-live="polite"></div>
      </div>
      <figcaption>Pasá el mouse o usá el teclado sobre un día para ver su cantidad.</figcaption>
    </figure>
  </div>
</details>

<script id="publicationChartData" type="application/json"><?= json_encode($datosPublicaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/noticias-chart.js" defer></script>

<div class="section-header">
  <h3>Últimas noticias</h3>
</div>

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
      <a href="noticia-form.php" class="btn btn-primary noticias-create-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Nueva noticia
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
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th class="table-sort-th weight-sort-th" style="width: 105px;" aria-sort="none">
            <button class="table-sort-btn weight-sort-btn" type="button" id="weightSortBtn" aria-label="Ordenar por peso, primero las noticias más pesadas">
              Peso
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9l4-4 4 4"></path><path d="M16 15l-4 4-4-4"></path></svg>
            </button>
          </th>
          <th style="width: 110px;">Votos</th>
          <th style="width: 130px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($noticias as $n): ?>
          <tr class="noticia-row" data-weight="<?= (int) $n['_peso']['total'] ?>" data-date="<?= e((string) strtotime($n['created_at'])) ?>">
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
            <td class="td-date"><?= e(date('d/m/Y', strtotime($n['created_at']))) ?></td>
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
    const search = document.getElementById('noticiasSearch');
    const status = document.getElementById('noticiasSearchStatus');
    const empty = document.getElementById('noticiasFilterEmpty');
    const tbody = document.querySelector('.noticias-list tbody');
    if (!weightButton || !dateButton || !search || !status || !empty || !tbody) return;

    const original = new Map(Array.from(tbody.rows).map((row, index) => [row, index]));
    const weightHeader = weightButton.closest('th');
    const dateHeader = dateButton.closest('th');
    let sortKey = 'date';
    let direction = 'desc';

    const normalize = (value) => value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLocaleLowerCase('es');

    function updateSortControls() {
      weightHeader.setAttribute('aria-sort', sortKey === 'weight' ? (direction === 'desc' ? 'descending' : 'ascending') : 'none');
      dateHeader.setAttribute('aria-sort', sortKey === 'date' ? (direction === 'desc' ? 'descending' : 'ascending') : 'none');

      [weightButton, dateButton].forEach((button) => {
        button.classList.remove('is-desc', 'is-asc');
      });
      const activeButton = sortKey === 'weight' ? weightButton : dateButton;
      activeButton.classList.add(direction === 'desc' ? 'is-desc' : 'is-asc');

      weightButton.setAttribute('aria-label', sortKey === 'weight'
        ? (direction === 'desc'
          ? 'Ordenado por peso de mayor a menor. Cambiar a menor a mayor'
          : 'Ordenado por peso de menor a mayor. Cambiar a mayor a menor')
        : 'Ordenar por peso, primero las noticias más pesadas');
      dateButton.setAttribute('aria-label', sortKey === 'date'
        ? (direction === 'desc'
          ? 'Ordenado por fecha, de más reciente a más antigua. Cambiar a más antigua primero'
          : 'Ordenado por fecha, de más antigua a más reciente. Cambiar a más reciente primero')
        : 'Ordenar por fecha, primero las noticias más recientes');
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

    weightButton.addEventListener('click', () => {
      sortRows('weight', sortKey === 'weight' && direction === 'desc' ? 'asc' : 'desc');
    });
    dateButton.addEventListener('click', () => {
      sortRows('date', sortKey === 'date' && direction === 'desc' ? 'asc' : 'desc');
    });
    search.addEventListener('input', filterRows);
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
