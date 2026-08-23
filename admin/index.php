<?php
/**
 * Listado de noticias (panel principal).
 */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('noticias.ver');

$pdo = db();

// Totales para las tarjetas de resumen
$totalNoticias = (int) $pdo->query('SELECT COUNT(*) FROM noticias')->fetchColumn();
$totalCategorias = (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();

// Últimas noticias para el listado
$noticias = $pdo->query(
    'SELECT n.id, n.titulo, n.descripcion, n.created_at,
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

$titulo = 'Noticias';
$active = 'noticias';

require __DIR__ . '/includes/header.php';
?>

<div class="cards">
  <div class="card">
    <div class="card-label">Noticias</div>
    <div class="card-value"><?= $totalNoticias ?></div>
  </div>
  <div class="card">
    <div class="card-label">Categorías</div>
    <div class="card-value"><?= $totalCategorias ?></div>
  </div>
</div>

<div class="section-header">
  <h3>Últimas noticias</h3>
</div>

<div class="table-wrap noticias-table">
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
          <th>Fecha</th>
          <th style="width: 110px;">Votos</th>
          <th style="width: 130px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($noticias as $n): ?>
          <tr class="noticia-row">
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
  <?php endif; ?>
</div>

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
          '<div class="news-video" style="display:none;">' +
            '<div class="news-video-title">Video</div>' +
            '<div class="news-video-frame"></div>' +
          '</div>' +
        '</div>';

      const hero = drawerBody.querySelector('.news-hero');
      if (noticia.foto_principal) {
        const heroImg = document.createElement('img');
        heroImg.src = noticia.foto_principal;
        heroImg.alt = '';
        hero.appendChild(heroImg);
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
      if (noticia.galeria && noticia.galeria.length > 1) {
        noticia.galeria.forEach((f, i) => {
          const img = document.createElement('img');
          img.src = f.url;
          img.alt = 'Foto ' + (i + 1);
          if (i === 0) img.classList.add('is-main');
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

      // Reproductor de YouTube embebido (debajo de la descripción).
      const video = drawerBody.querySelector('.news-video');
      if (noticia.youtube) {
        const frame = drawerBody.querySelector('.news-video-frame');
        const iframe = document.createElement('iframe');
        iframe.src = noticia.youtube;
        iframe.title = 'Video de YouTube';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        frame.appendChild(iframe);
        video.style.display = '';
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
