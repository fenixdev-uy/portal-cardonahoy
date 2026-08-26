<?php
/**
 * Vista previa lateral reutilizable de una noticia.
 *
 * La abre cualquier elemento .js-ver-noticia que exponga data-id. El detalle
 * se obtiene desde noticia-detalle.php sin navegar al formulario de edición.
 */
?>
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
      const descripcion = (noticia.descripcion || '<p>Sin descripción.</p>')
        .replace(/src="uploads\//g, 'src="../uploads/')
        .replace(/src='uploads\//g, "src='../uploads/");
      content.innerHTML = descripcion;

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
        if (!res.ok) throw new Error('Respuesta ' + res.status);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        render(data);
      } catch (e) {
        showError();
      }
    }

    document.querySelectorAll('.js-ver-noticia').forEach((el) => {
      el.addEventListener('click', (event) => {
        event.preventDefault();
        cargarNoticia(el.getAttribute('data-id'));
      });
    });

    drawerClose.addEventListener('click', closeDrawer);
    drawerCloseBtn.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });
  })();
</script>
