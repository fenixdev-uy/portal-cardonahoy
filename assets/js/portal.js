    const portalScript = document.currentScript;
    const portalConfig = {
      voteUrl: portalScript?.dataset.voteUrl || 'votar.php',
    };

    (function () {
      const slider = document.getElementById('slider');
      const slides = Array.from(slider.querySelectorAll('.slide'));
      const dotsContainer = document.getElementById('dots');

      const INTERVAL = 5000; // ms entre imágenes
      let current = 0;
      let timer = null;

      // Genera los puntos (píldoras) dinámicamente según la cantidad de slides
      slides.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.className = 'dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Ir a la imagen ' + (i + 1));
        dotsContainer.appendChild(dot);
      });

      const dots = Array.from(dotsContainer.querySelectorAll('.dot'));

      function goTo(index) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');

        current = index;

        slides[current].classList.add('active');
        dots[current].classList.add('active');
      }

      function next() {
        goTo((current + 1) % slides.length);
      }

      function start() {
        stop();
        timer = setInterval(next, INTERVAL);
      }

      function stop() {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
      }

      // Al hacer clic en un punto, cambia la imagen y reinicia el autoplay
      dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
          goTo(i);
          start();
        });
      });

      start();
    })();

    // ===== Menú desplegable =====
    const hamburger = document.getElementById('hamburger');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenuBtn = document.getElementById('closeMenu');
    const menuLinks = Array.from(document.querySelectorAll('.menu-link'));

    function openMenu() {
      hamburger.classList.add('active');
      hamburger.setAttribute('aria-expanded', 'true');
      menuOverlay.classList.add('open');
      menuOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      hamburger.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      menuOverlay.classList.remove('open');
      menuOverlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', () => {
      menuOverlay.classList.contains('open') ? closeMenu() : openMenu();
    });

    closeMenuBtn.addEventListener('click', closeMenu);

    // Cierra el menú al hacer clic en un enlace
    menuLinks.forEach((link) => {
      link.addEventListener('click', closeMenu);
    });

    // Cierra el menú con la tecla Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && menuOverlay.classList.contains('open')) {
        closeMenu();
      }
    });

    // Fondo del menú al hacer scroll (para mantenerlo legible sobre el feed blanco)
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 10);
    });

    // Nota completa compartida: panel inferior en móvil y drawer derecho en PC.
    // Se carga desde templates inertes para no duplicar medios al abrir el feed.
    const storySheet = document.getElementById('storySheet');
    const storySheetPanel = storySheet?.querySelector('.story-sheet-panel');
    const storySheetHeader = storySheet?.querySelector('.story-sheet-header');
    const storySheetBackdrop = storySheet?.querySelector('.story-sheet-backdrop');
    const storySheetContent = document.getElementById('storySheetContent');
    let storySheetTrigger = null;
    let storySheetPreviousOverflow = '';
    let storySheetClearTimer = null;
    let storySheetDragPointer = null;
    let storySheetDragStartY = 0;
    let storySheetDragStartTime = 0;
    let storySheetDragOffset = 0;
    let storySheetGalleryTimer = null;
    let startStorySheetGalleryAutoplay = null;

    function clearStorySheetDragStyles() {
      storySheet?.classList.remove('dragging');
      if (storySheetPanel) storySheetPanel.style.transform = '';
      if (storySheetBackdrop) storySheetBackdrop.style.backgroundColor = '';
    }

    function stopStorySheetGalleryAutoplay() {
      if (!storySheetGalleryTimer) return;
      window.clearInterval(storySheetGalleryTimer);
      storySheetGalleryTimer = null;
    }

    function initializeStorySheetGallery() {
      stopStorySheetGalleryAutoplay();
      startStorySheetGalleryAutoplay = null;
      const gallery = storySheetContent?.querySelector('.story-sheet-gallery');
      const track = gallery?.querySelector('.story-sheet-gallery-track');
      const frames = Array.from(gallery?.querySelectorAll('.story-sheet-photo') || []);
      const dotsContainer = gallery?.querySelector('.story-sheet-gallery-dots');
      if (!gallery || !track || !dotsContainer || frames.length < 2) return;

      const dots = frames.map((_, index) => {
        const dot = document.createElement('span');
        dot.className = 'story-sheet-gallery-dot' + (index === 0 ? ' active' : '');
        dotsContainer.appendChild(dot);
        return dot;
      });

      const updateActivePhoto = () => {
        const index = Math.max(0, Math.min(frames.length - 1, Math.round(track.scrollLeft / Math.max(1, track.clientWidth))));
        gallery.dataset.activeIndex = String(index);
        dots.forEach((dot, dotIndex) => dot.classList.toggle('active', dotIndex === index));
      };

      track.addEventListener('scroll', updateActivePhoto, { passive: true });
      startStorySheetGalleryAutoplay = () => {
        stopStorySheetGalleryAutoplay();
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        storySheetGalleryTimer = window.setInterval(() => {
          if (!storySheet?.classList.contains('open')) return;
          if (document.getElementById('pcGalleryLightbox')?.classList.contains('open')) return;
          const actual = Number(gallery.dataset.activeIndex || 0);
          const siguiente = (actual + 1) % frames.length;
          track.scrollTo({ left: siguiente * track.clientWidth, behavior: 'smooth' });
        }, 3200);
      };

      track.addEventListener('pointerdown', stopStorySheetGalleryAutoplay);
      track.addEventListener('pointerup', () => startStorySheetGalleryAutoplay?.());
      track.addEventListener('pointercancel', () => startStorySheetGalleryAutoplay?.());
      updateActivePhoto();
      startStorySheetGalleryAutoplay();
    }

    function openStorySheet(storyId, trigger) {
      const template = document.querySelector(`template[data-story-template="${storyId}"]`);
      if (!storySheet || !storySheetPanel || !storySheetContent || !template) return;

      if (storySheetClearTimer) {
        window.clearTimeout(storySheetClearTimer);
        storySheetClearTimer = null;
      }

      storySheetTrigger = trigger;
      storySheetPreviousOverflow = document.body.style.overflow;
      storySheetDragPointer = null;
      storySheetDragOffset = 0;
      clearStorySheetDragStyles();
      storySheetContent.replaceChildren(template.content.cloneNode(true));
      storySheetContent.scrollTop = 0;
      storySheet.classList.add('open');
      storySheet.setAttribute('aria-hidden', 'false');
      document.body.classList.add('story-sheet-open');
      document.body.style.overflow = 'hidden';
      initializeStorySheetGallery();
      storySheet.querySelector('.story-sheet-close')?.focus();
    }

    function closeStorySheet(dragOffset = 0) {
      if (!storySheet?.classList.contains('open')) return;

      storySheetDragPointer = null;
      storySheetDragOffset = 0;
      stopStorySheetGalleryAutoplay();
      startStorySheetGalleryAutoplay = null;

      // Si el cierre nace del gesto, la animación continúa desde el punto
      // exacto en que quedó el dedo hasta desaparecer por debajo de la pantalla.
      if (dragOffset > 0 && storySheetPanel) {
        storySheet.classList.remove('dragging');
        storySheetPanel.style.transform = `translateY(${dragOffset}px)`;
        void storySheetPanel.offsetHeight;
        storySheet.classList.remove('open');
        storySheetPanel.style.transform = 'translateY(105%)';
        if (storySheetBackdrop) storySheetBackdrop.style.backgroundColor = 'rgba(15, 23, 42, 0)';
      } else {
        clearStorySheetDragStyles();
        storySheet.classList.remove('open');
      }

      storySheet.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('story-sheet-open');
      document.body.style.overflow = storySheetPreviousOverflow;
      if (storySheetTrigger) storySheetTrigger.focus();

      // Espera a que termine la salida para detener videos y audios sin cortar
      // visualmente la animación del panel.
      storySheetClearTimer = window.setTimeout(() => {
        clearStorySheetDragStyles();
        storySheetContent?.replaceChildren();
        storySheetClearTimer = null;
      }, 430);
    }

    function restoreStorySheetPosition() {
      if (!storySheet || !storySheetPanel) return;

      storySheet.classList.remove('dragging');
      storySheetPanel.style.transform = `translateY(${storySheetDragOffset}px)`;
      if (storySheetBackdrop) {
        const progreso = Math.min(1, storySheetDragOffset / (storySheetPanel.offsetHeight * 0.75));
        storySheetBackdrop.style.backgroundColor = `rgba(15, 23, 42, ${0.48 * (1 - progreso)})`;
      }
      void storySheetPanel.offsetHeight;
      storySheetPanel.style.transform = '';
      if (storySheetBackdrop) storySheetBackdrop.style.backgroundColor = '';
      storySheetDragOffset = 0;
    }

    if (storySheetHeader && storySheetPanel) {
      storySheetHeader.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || !storySheet?.classList.contains('open')) return;
        if (event.target.closest('[data-story-close]')) return;

        storySheetDragPointer = event.pointerId;
        storySheetDragStartY = event.clientY;
        storySheetDragStartTime = performance.now();
        storySheetDragOffset = 0;
        storySheet.classList.add('dragging');
        storySheetHeader.setPointerCapture(event.pointerId);
        event.preventDefault();
      });

      storySheetHeader.addEventListener('pointermove', (event) => {
        if (event.pointerId !== storySheetDragPointer) return;

        storySheetDragOffset = Math.max(0, event.clientY - storySheetDragStartY);
        storySheetPanel.style.transform = `translateY(${storySheetDragOffset}px)`;
        if (storySheetBackdrop) {
          const progreso = Math.min(1, storySheetDragOffset / (storySheetPanel.offsetHeight * 0.75));
          storySheetBackdrop.style.backgroundColor = `rgba(15, 23, 42, ${0.48 * (1 - progreso)})`;
        }
        event.preventDefault();
      });

      storySheetHeader.addEventListener('pointerup', (event) => {
        if (event.pointerId !== storySheetDragPointer) return;

        const duracion = Math.max(1, performance.now() - storySheetDragStartTime);
        const velocidad = storySheetDragOffset / duracion;
        const umbral = Math.min(120, storySheetPanel.offsetHeight * 0.18);
        const debeCerrar = storySheetDragOffset >= umbral || (storySheetDragOffset >= 42 && velocidad >= 0.65);
        const offsetFinal = storySheetDragOffset;
        storySheetDragPointer = null;
        storySheetHeader.releasePointerCapture(event.pointerId);

        if (debeCerrar) {
          closeStorySheet(offsetFinal);
        } else {
          restoreStorySheetPosition();
        }
      });

      storySheetHeader.addEventListener('pointercancel', (event) => {
        if (event.pointerId !== storySheetDragPointer) return;
        storySheetDragPointer = null;
        restoreStorySheetPosition();
      });
    }

    document.addEventListener('click', (event) => {
      const pcTrigger = event.target.closest('.pc-news-card-link[data-story-id]');
      if (pcTrigger && window.matchMedia('(min-width: 769px)').matches) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        openStorySheet(pcTrigger.dataset.storyId, pcTrigger);
        return;
      }

      const trigger = event.target.closest('.story-sheet-trigger[data-story-id]');
      if (trigger) {
        openStorySheet(trigger.dataset.storyId, trigger);
        return;
      }

      if (event.target.closest('[data-story-close]')) closeStorySheet();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && storySheet?.classList.contains('open')) {
        if (document.getElementById('pcGalleryLightbox')?.classList.contains('open')) return;
        closeStorySheet();
      }
    });

    // Accesibilidad de lectura: escala solo el cuerpo editorial de la nota.
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.story-reading-button[data-reading-adjust]');
      if (!button || button.disabled) return;

      const tools = button.closest('.story-reading-tools');
      const article = button.closest('.story-sheet-article');
      const content = article?.querySelector('.story-sheet-body');
      if (!tools || !content) return;

      const current = Number(content.dataset.readingScale || 1);
      const adjustment = Number(button.dataset.readingAdjust || 0);
      const next = Math.min(1.4, Math.max(0.9, Math.round((current + adjustment) * 10) / 10));
      content.dataset.readingScale = String(next);
      content.style.setProperty('--reading-scale', String(next));

      tools.querySelectorAll('.story-reading-button').forEach((control) => {
        const delta = Number(control.dataset.readingAdjust || 0);
        control.disabled = (delta < 0 && next <= 0.9) || (delta > 0 && next >= 1.4);
      });

      const status = tools.querySelector('.story-reading-status');
      if (status) status.textContent = `Tamaño de texto ${Math.round(next * 100)}%`;
    });

    // Votos: un solo listener delegado cubre los dos feeds, así que no hace
    // falta cablear nada por noticia. El voto es definitivo: al confirmarse, los
    // dos botones de esa noticia quedan bloqueados.
    document.addEventListener('click', async (event) => {
      const boton = event.target.closest('.vote-btn[data-noticia-id]');
      if (!boton || boton.disabled || boton.classList.contains('enviando')) return;

      const botones = Array.from(document.querySelectorAll(
        `.vote-btn[data-noticia-id="${boton.dataset.noticiaId}"]`
      ));

      botones.forEach((b) => b.classList.add('enviando'));

      try {
        const cuerpo = new URLSearchParams({
          noticia_id: boton.dataset.noticiaId,
          valor: boton.dataset.voto,
        });
        const respuesta = await fetch(portalConfig.voteUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: cuerpo,
          credentials: 'same-origin',
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || 'Error al votar');

        // El servidor es la fuente de verdad, incluso si ya se había votado
        // antes desde otra pestaña: los números y el voto vienen de ahí.
        botones.forEach((b) => {
          const valor = Number(b.dataset.voto);
          const conteo = valor === 1 ? datos.me_gusta : datos.no_me_gusta;
          const marca = b.querySelector('.vote-count');
          if (marca) marca.textContent = conteo > 0 ? String(conteo) : '';
          const votado = valor === datos.mi_voto;
          b.classList.toggle('voted', votado);
          b.setAttribute('aria-pressed', votado ? 'true' : 'false');
          b.disabled = true;
        });
      } catch (error) {
        // Sin cambios optimistas que revertir: los números solo se tocan con la
        // respuesta del servidor, así que un fallo deja todo como estaba.
        console.error(error);
      } finally {
        botones.forEach((b) => b.classList.remove('enviando'));
      }
    });

    // Galerías del feed móvil: puntos sincronizados con el deslizamiento nativo.
    document.querySelectorAll('.feed-gallery').forEach((gallery) => {
      const track = gallery.querySelector('.feed-track');
      const dotsContainer = gallery.querySelector('.feed-dots');
      const frames = Array.from(gallery.querySelectorAll('.feed-frame'));
      if (!track || !dotsContainer || frames.length < 2) return;

      const dots = frames.map((_, i) => {
        const dot = document.createElement('span');
        dot.className = 'feed-dot' + (i === 0 ? ' active' : '');
        dotsContainer.appendChild(dot);
        return dot;
      });

      // El observador marca el punto de la foto que ocupa el centro del track.
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const index = frames.indexOf(entry.target);
          if (index < 0) return;
          dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
          gallery.dataset.activeIndex = String(index);
        });
      }, { root: track, threshold: 0.6 });

      frames.forEach((frame) => observer.observe(frame));
    });

    // Visor ampliado de galerías: rueda del mouse en PC, pinza y swipe en móvil.
    const galleryLightbox = document.getElementById('pcGalleryLightbox');
    const lightboxImage = document.getElementById('pcLightboxImage');
    const lightboxStage = document.getElementById('pcLightboxStage');
    const lightboxClose = document.getElementById('pcLightboxClose');
    const lightboxPrev = document.getElementById('pcLightboxPrev');
    const lightboxNext = document.getElementById('pcLightboxNext');
    const lightboxCounter = document.getElementById('pcLightboxCounter');
    const lightboxZoom = document.getElementById('pcLightboxZoom');

    if (galleryLightbox && lightboxImage && lightboxStage) {
      let galleryImages = [];
      let galleryIndex = 0;
      let zoomLevel = 1;
      let panX = 0;
      let panY = 0;
      let previousBodyOverflow = '';
      let lastFocusedElement = null;

      const esTactil = () => window.matchMedia('(max-width: 768px)').matches;

      function limitPan() {
        const maxX = Math.max(0, (lightboxImage.offsetWidth * zoomLevel - lightboxStage.clientWidth) / 2);
        const maxY = Math.max(0, (lightboxImage.offsetHeight * zoomLevel - lightboxStage.clientHeight) / 2);
        panX = Math.min(maxX, Math.max(-maxX, panX));
        panY = Math.min(maxY, Math.max(-maxY, panY));
      }

      function applyImageTransform() {
        lightboxImage.style.transformOrigin = '50% 50%';
        lightboxImage.style.transform = `translate3d(${panX}px, ${panY}px, 0) scale(${zoomLevel})`;
      }

      function updateZoom(nextZoom, originX = 50, originY = 50) {
        const previousZoom = zoomLevel;
        zoomLevel = Math.min(4, Math.max(1, nextZoom));

        if (zoomLevel === 1) {
          panX = 0;
          panY = 0;
        } else if (previousZoom > 0 && zoomLevel !== previousZoom) {
          const bounds = lightboxStage.getBoundingClientRect();
          const originPxX = ((originX / 100) - 0.5) * bounds.width;
          const originPxY = ((originY / 100) - 0.5) * bounds.height;
          const ratio = zoomLevel / previousZoom;
          panX = originPxX - (originPxX - panX) * ratio;
          panY = originPxY - (originPxY - panY) * ratio;
        }

        limitPan();
        applyImageTransform();
        lightboxImage.style.cursor = zoomLevel > 1 ? 'zoom-out' : 'zoom-in';
        const ayuda = esTactil()
          ? (zoomLevel > 1 ? 'Arrastrá con un dedo' : 'Pinza para ampliar')
          : 'Rueda del mouse para ampliar';
        lightboxZoom.textContent = `${ayuda} · ${Math.round(zoomLevel * 100)}%`;
      }

      function panImage(deltaX, deltaY) {
        if (zoomLevel <= 1) return;
        panX += deltaX;
        panY += deltaY;
        limitPan();
        applyImageTransform();
      }

      function showGalleryImage(index) {
        if (!galleryImages.length) return;
        galleryIndex = (index + galleryImages.length) % galleryImages.length;
        updateZoom(1);
        lightboxImage.src = galleryImages[galleryIndex];
        lightboxImage.alt = `Imagen ${galleryIndex + 1} de ${galleryImages.length}`;
        lightboxCounter.textContent = `${galleryIndex + 1} / ${galleryImages.length}`;
      }

      function openGallery(images, startIndex, trigger) {
        galleryImages = images.filter(Boolean);
        if (!galleryImages.length) return;

        lastFocusedElement = trigger;
        if (trigger?.closest('.story-sheet-gallery')) stopStorySheetGalleryAutoplay();
        previousBodyOverflow = document.body.style.overflow;
        galleryLightbox.classList.add('open');
        galleryLightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        showGalleryImage(startIndex > 0 ? startIndex : 0);
        lightboxClose.focus();
      }

      function closeGallery() {
        punteros.clear();
        distanciaInicial = 0;
        galleryLightbox.classList.remove('open', 'pinching', 'panning');
        galleryLightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousBodyOverflow;
        updateZoom(1);
        lightboxImage.removeAttribute('src');
        if (lastFocusedElement) lastFocusedElement.focus();
        if (storySheet?.classList.contains('open')) startStorySheetGalleryAutoplay?.();
      }

      // Disparador del feed móvil: tanto la lupa como un toque directo sobre la
      // foto abren el carrusel en la imagen que está activa.
      function openMobileGallery(gallery, trigger) {
        if (!gallery) return;
        const imagenes = Array.from(gallery.querySelectorAll('.feed-frame img'));
        openGallery(
          imagenes.map((img) => img.currentSrc || img.src),
          Number(gallery.dataset.activeIndex || 0),
          trigger
        );
      }

      function openStorySheetGallery(gallery, trigger) {
        if (!gallery) return;
        const imagenes = Array.from(gallery.querySelectorAll('.story-sheet-photo img'));
        openGallery(
          imagenes.map((img) => img.currentSrc || img.src),
          Number(gallery.dataset.activeIndex || 0),
          trigger
        );
      }

      document.querySelectorAll('.feed-gallery-expand').forEach((button) => {
        button.addEventListener('click', () => {
          openMobileGallery(button.closest('.feed-gallery'), button);
        });
      });

      document.querySelectorAll('.feed-gallery .feed-frame img').forEach((image) => {
        image.addEventListener('click', () => {
          openMobileGallery(image.closest('.feed-gallery'), image);
        });
      });

      document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.story-sheet-gallery-expand, .story-sheet-photo img');
        if (!trigger) return;
        openStorySheetGallery(trigger.closest('.story-sheet-gallery'), trigger);
      });

      lightboxClose.addEventListener('click', closeGallery);
      lightboxPrev.addEventListener('click', () => showGalleryImage(galleryIndex - 1));
      lightboxNext.addEventListener('click', () => showGalleryImage(galleryIndex + 1));

      lightboxStage.addEventListener('wheel', (event) => {
        if (!galleryLightbox.classList.contains('open')) return;
        event.preventDefault();
        const bounds = lightboxStage.getBoundingClientRect();
        const originX = ((event.clientX - bounds.left) / bounds.width) * 100;
        const originY = ((event.clientY - bounds.top) / bounds.height) * 100;
        updateZoom(zoomLevel + (event.deltaY < 0 ? 0.25 : -0.25), originX, originY);
      }, { passive: false });

      // Gestos táctiles: pinza con dos dedos para el zoom. Con zoom activo, un
      // dedo desplaza la imagen; al 100% conserva la navegación/cierre por swipe.
      const punteros = new Map();
      let distanciaInicial = 0;
      let zoomInicial = 1;

      const distanciaEntrePunteros = () => {
        const [a, b] = Array.from(punteros.values());
        return Math.hypot(a.x - b.x, a.y - b.y);
      };

      lightboxStage.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || !galleryLightbox.classList.contains('open')) return;
        punteros.set(event.pointerId, { x: event.clientX, y: event.clientY, inicioX: event.clientX, inicioY: event.clientY });
        if (punteros.size === 2) {
          distanciaInicial = distanciaEntrePunteros();
          zoomInicial = zoomLevel;
          galleryLightbox.classList.remove('panning');
          galleryLightbox.classList.add('pinching');
        } else if (zoomLevel > 1) {
          galleryLightbox.classList.add('panning');
        }
        lightboxStage.setPointerCapture?.(event.pointerId);
      });

      lightboxStage.addEventListener('pointermove', (event) => {
        const puntero = punteros.get(event.pointerId);
        if (!puntero) return;
        const deltaX = event.clientX - puntero.x;
        const deltaY = event.clientY - puntero.y;
        puntero.x = event.clientX;
        puntero.y = event.clientY;

        if (punteros.size === 2 && distanciaInicial > 0) {
          const bounds = lightboxStage.getBoundingClientRect();
          const [a, b] = Array.from(punteros.values());
          const originX = (((a.x + b.x) / 2 - bounds.left) / bounds.width) * 100;
          const originY = (((a.y + b.y) / 2 - bounds.top) / bounds.height) * 100;
          updateZoom(zoomInicial * (distanciaEntrePunteros() / distanciaInicial), originX, originY);
          event.preventDefault();
          return;
        }

        if (punteros.size === 1 && zoomLevel > 1) {
          panImage(deltaX, deltaY);
          event.preventDefault();
        }
      });

      function finPuntero(event) {
        const puntero = punteros.get(event.pointerId);
        if (!puntero) return;
        const eraGestoSimple = punteros.size === 1;
        punteros.delete(event.pointerId);

        if (punteros.size < 2) {
          distanciaInicial = 0;
          galleryLightbox.classList.remove('pinching');
          galleryLightbox.classList.toggle('panning', punteros.size === 1 && zoomLevel > 1);
        }

        if (punteros.size === 0) galleryLightbox.classList.remove('panning');
        if (lightboxStage.hasPointerCapture?.(event.pointerId)) lightboxStage.releasePointerCapture(event.pointerId);

        // Con zoom activo el gesto simple ya desplazó la imagen y no navega.
        if (!eraGestoSimple || zoomLevel > 1) return;

        const desplazamientoX = puntero.x - puntero.inicioX;
        const desplazamientoY = puntero.y - puntero.inicioY;
        if (Math.abs(desplazamientoX) > 50 && Math.abs(desplazamientoX) > Math.abs(desplazamientoY)) {
          // La navegación acompaña el sentido pedido por el gesto: hacia la
          // derecha avanza y hacia la izquierda retrocede.
          showGalleryImage(galleryIndex + (desplazamientoX > 0 ? 1 : -1));
        } else if (desplazamientoY > 90) {
          closeGallery();
        }
      }

      lightboxStage.addEventListener('pointerup', finPuntero);
      lightboxStage.addEventListener('pointercancel', finPuntero);

      galleryLightbox.addEventListener('click', (event) => {
        if (event.target === galleryLightbox) closeGallery();
      });

      document.addEventListener('keydown', (event) => {
        if (!galleryLightbox.classList.contains('open')) return;
        if (event.key === 'Escape') closeGallery();
        if (event.key === 'ArrowLeft') showGalleryImage(galleryIndex - 1);
        if (event.key === 'ArrowRight') showGalleryImage(galleryIndex + 1);
      });
    }
