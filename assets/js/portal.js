    const portalScript = document.currentScript;
    const portalConfig = {
      voteUrl: portalScript?.dataset.voteUrl || 'votar.php',
      viewUrl: portalScript?.dataset.viewUrl || 'noticia-vista.php',
      shareUrl: portalScript?.dataset.shareUrl || 'noticia-compartir.php',
      adPlacementsUrl: portalScript?.dataset.adPlacementsUrl || 'publicidad-ubicaciones.php',
    };
    const localStoryViews = new Set();

    document.addEventListener('click', (event) => {
      const link = event.target.closest('a[data-ad-click-url][data-ad-id][data-ad-destination]');
      if (!link || !event.isTrusted || !link.href || link.getAttribute('aria-disabled') === 'true') return;
      fetch(link.dataset.adClickUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        body: new URLSearchParams({ id: link.dataset.adId, destino: link.dataset.adDestination }),
        credentials: 'same-origin',
        keepalive: true,
      }).catch(() => {});
    }, { capture: true });

    function recordLocalStoryView(storyId) {
      const normalizedId = String(storyId || '');
      if (!normalizedId || localStoryViews.has(normalizedId)) return;
      localStoryViews.add(normalizedId);
      fetch(portalConfig.viewUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ noticia_id: normalizedId }),
        credentials: 'same-origin',
        keepalive: true,
      }).catch((error) => console.error(error));
    }

    document.addEventListener('click', (event) => {
      const shareLink = event.target.closest('.share-btn[data-share-noticia-id][data-share-destino]');
      if (!shareLink) return;
      fetch(portalConfig.shareUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          noticia_id: shareLink.dataset.shareNoticiaId,
          destino: shareLink.dataset.shareDestino,
        }),
        credentials: 'same-origin',
        keepalive: true,
      }).catch((error) => console.error(error));
    }, { capture: true });

    async function copyNewsUrl(url) {
      if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
        return;
      }
      const helper = document.createElement('textarea');
      helper.value = url;
      helper.setAttribute('readonly', '');
      helper.style.position = 'fixed';
      helper.style.opacity = '0';
      document.body.appendChild(helper);
      helper.select();
      const copied = document.execCommand('copy');
      helper.remove();
      if (!copied) throw new Error('El navegador no permitió copiar el enlace.');
    }

    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-copy-news-url]');
      if (!button || button.disabled) return;
      const label = button.querySelector('[data-copy-news-label]');
      const originalLabel = label?.textContent || 'Copiar link de noticia';
      button.disabled = true;
      try {
        await copyNewsUrl(button.dataset.copyNewsUrl || '');
        button.classList.add('is-copied');
        if (label) label.textContent = 'Link copiado';
      } catch (error) {
        if (label) label.textContent = 'No se pudo copiar';
        console.error(error);
      } finally {
        window.setTimeout(() => {
          button.disabled = false;
          button.classList.remove('is-copied');
          if (label) label.textContent = originalLabel;
        }, 1800);
      }
    });

    let adPlacementsRequest = null;
    async function syncAdPlacements() {
      if (adPlacementsRequest) return adPlacementsRequest;
      adPlacementsRequest = (async () => {
        try {
          const response = await fetch(portalConfig.adPlacementsUrl, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || 'No se pudo actualizar la publicidad.');

          const roots = [document, ...Array.from(document.querySelectorAll('template[data-story-template]'), template => template.content)];
          const htmlByPlacement = {
            encabezado: typeof data.encabezado_html === 'string' ? data.encabezado_html : '',
            pie: typeof data.pie_html === 'string' ? data.pie_html : '',
          };
          roots.forEach((root) => {
            root.querySelectorAll('[data-ad-placement]').forEach((slot) => {
              const html = htmlByPlacement[slot.dataset.adPlacement] ?? '';
              if (slot.innerHTML.trim() !== html.trim()) slot.innerHTML = html;
              slot.hidden = html === '';
            });
            const destinos = data.anuncios_destinos && typeof data.anuncios_destinos === 'object'
              ? data.anuncios_destinos
              : {};
            root.querySelectorAll('a[data-ad-id][data-ad-destination]').forEach((link) => {
              const url = String(destinos[link.dataset.adId]?.[link.dataset.adDestination] || '');
              const label = link.dataset.adLabel || 'Enlace';
              const name = link.dataset.adName || 'anunciante';
              link.classList.toggle('is-disabled', url === '');
              link.tabIndex = url === '' ? -1 : 0;
              if (url === '') {
                link.removeAttribute('href');
                link.setAttribute('aria-disabled', 'true');
                link.setAttribute('aria-label', `${label} sin configurar para ${name}`);
                link.title = `${label} sin configurar`;
              } else {
                link.href = url;
                link.removeAttribute('aria-disabled');
                link.setAttribute('aria-label', `${label} de ${name}`);
                link.title = label;
              }
            });
          });
        } catch (error) {
          console.error(error);
        } finally {
          adPlacementsRequest = null;
        }
      })();
      return adPlacementsRequest;
    }

    window.addEventListener('storage', (event) => {
      if (event.key === 'portal_publicidad_ubicaciones') syncAdPlacements();
    });
    window.addEventListener('focus', syncAdPlacements);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') syncAdPlacements();
    });

    (function () {
      const slider = document.getElementById('slider');
      const dotsContainer = document.getElementById('dots');
      if (!slider || !dotsContainer) return;
      const slides = Array.from(slider.querySelectorAll('.slide'));
      if (slides.length === 0) return;

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

      if (slides.length > 1) start();
    })();

    // ===== Menú desplegable =====
    const hamburger = document.getElementById('hamburger');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenuBtn = document.getElementById('closeMenu');
    const menuLinks = Array.from(document.querySelectorAll('.menu-link'));
    const menuNewsSearch = document.querySelector('[data-menu-news-search]');
    const menuNewsSearchInput = menuNewsSearch?.querySelector('input[type="search"]');
    const menuNewsSearchResults = menuNewsSearch?.querySelector('.menu-news-search-results');
    let menuNewsSearchTimer = null;
    let menuNewsSearchController = null;

    function openMenu() {
      hamburger.classList.add('active');
      hamburger.setAttribute('aria-expanded', 'true');
      menuOverlay.classList.add('open');
      menuOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (window.matchMedia('(min-width: 769px)').matches) {
        window.setTimeout(() => menuNewsSearchInput?.focus(), 180);
      }
    }

    function closeMenu() {
      hamburger.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      menuOverlay.classList.remove('open');
      menuOverlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      menuNewsSearchController?.abort();
      if (menuNewsSearchTimer) window.clearTimeout(menuNewsSearchTimer);
      if (menuNewsSearchInput) {
        menuNewsSearchInput.value = '';
        menuNewsSearchInput.setAttribute('aria-expanded', 'false');
      }
      if (menuNewsSearchResults) {
        menuNewsSearchResults.hidden = true;
        menuNewsSearchResults.replaceChildren();
      }
    }

    hamburger.addEventListener('click', () => {
      menuOverlay.classList.contains('open') ? closeMenu() : openMenu();
    });

    closeMenuBtn.addEventListener('click', closeMenu);

    // Cierra el menú al hacer clic en un enlace
    menuLinks.forEach((link) => {
      link.addEventListener('click', closeMenu);
    });

    function renderMenuNewsSearchResults(resultados, mensaje = '') {
      if (!menuNewsSearchResults || !menuNewsSearchInput) return;
      menuNewsSearchResults.replaceChildren();

      if (mensaje !== '') {
        const estado = document.createElement('span');
        estado.className = 'menu-news-search-state';
        estado.textContent = mensaje;
        menuNewsSearchResults.appendChild(estado);
      } else {
        resultados.slice(0, 5).forEach((noticia) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'menu-news-search-result';
          button.dataset.storyId = String(noticia.id);
          button.dataset.storyUrl = noticia.url || '';

          const media = document.createElement('span');
          media.className = 'menu-news-search-result-media';
          if (noticia.miniatura) {
            const image = document.createElement('img');
            image.src = noticia.miniatura;
            image.alt = '';
            image.loading = 'lazy';
            media.appendChild(image);
          }

          const copy = document.createElement('span');
          copy.className = 'menu-news-search-result-copy';
          const title = document.createElement('strong');
          title.className = 'menu-news-search-result-title';
          title.textContent = noticia.titulo || 'Noticia';
          const description = document.createElement('span');
          description.className = 'menu-news-search-result-description';
          description.textContent = noticia.descripcion || '';
          copy.append(title, description);
          button.append(media, copy);
          menuNewsSearchResults.appendChild(button);
        });
      }

      menuNewsSearchResults.hidden = false;
      menuNewsSearchInput.setAttribute('aria-expanded', 'true');
    }

    async function searchMenuNews() {
      if (!menuNewsSearch || !menuNewsSearchInput || !menuNewsSearchResults) return;
      const query = menuNewsSearchInput.value.trim();
      menuNewsSearchController?.abort();
      if (query.length < 2) {
        menuNewsSearchResults.hidden = true;
        menuNewsSearchResults.replaceChildren();
        menuNewsSearchInput.setAttribute('aria-expanded', 'false');
        return;
      }

      const controller = new AbortController();
      menuNewsSearchController = controller;
      const url = new URL(menuNewsSearch.dataset.searchUrl, window.location.href);
      url.searchParams.set('q', query);

      try {
        const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
        if (!response.ok) throw new Error(`No se pudo buscar (${response.status})`);
        const data = await response.json();
        const resultados = Array.isArray(data.resultados) ? data.resultados : [];
        renderMenuNewsSearchResults(resultados, resultados.length ? '' : 'No encontramos noticias.');
      } catch (error) {
        if (error.name !== 'AbortError') {
          renderMenuNewsSearchResults([], 'No pudimos completar la búsqueda.');
          console.error(error);
        }
      }
    }

    menuNewsSearchInput?.addEventListener('input', () => {
      if (menuNewsSearchTimer) window.clearTimeout(menuNewsSearchTimer);
      menuNewsSearchTimer = window.setTimeout(searchMenuNews, 240);
    });

    menuNewsSearchResults?.addEventListener('click', (event) => {
      const result = event.target.closest('.menu-news-search-result[data-story-id]');
      if (!result) return;
      const storyId = result.dataset.storyId;
      const hasTemplate = !!document.querySelector(`template[data-story-template="${storyId}"]`);
      const fallbackUrl = result.dataset.storyUrl;
      closeMenu();
      if (hasTemplate) {
        window.setTimeout(() => openStorySheet(storyId, hamburger), 420);
      } else if (fallbackUrl) {
        window.location.href = fallbackUrl;
      }
    });

    // Mantiene coherente el rango de fechas de la búsqueda PC antes de enviar.
    const pcNewsFilters = document.querySelector('.pc-news-filters');
    const pcNewsSearch = pcNewsFilters?.querySelector('[name="buscar"]');
    const pcNewsDateFrom = pcNewsFilters?.querySelector('[name="desde"]');
    const pcNewsDateTo = pcNewsFilters?.querySelector('[name="hasta"]');
    const pcNewsFilterStatus = pcNewsFilters?.querySelector('.pc-news-filter-status');
    const pcNewsCategoryFilter = pcNewsFilters?.querySelector('[data-pc-category-filter]');
    const pcNewsCategoryDetails = pcNewsCategoryFilter?.querySelector('details');
    const pcNewsCategorySummary = pcNewsCategoryFilter?.querySelector('[data-pc-category-summary]');
    const pcNewsCategoryChecks = Array.from(pcNewsCategoryFilter?.querySelectorAll('input[type="checkbox"]') || []);
    let pcNewsResults = document.querySelector('.pc-news-results');
    let pcNewsSearchTimer = null;
    let pcNewsFilterController = null;

    function syncPcNewsDateRange() {
      if (!pcNewsDateFrom || !pcNewsDateTo) return;
      pcNewsDateFrom.max = pcNewsDateTo.value;
      pcNewsDateTo.min = pcNewsDateFrom.value;
    }

    pcNewsDateFrom?.addEventListener('change', syncPcNewsDateRange);
    pcNewsDateTo?.addEventListener('change', syncPcNewsDateRange);
    syncPcNewsDateRange();

    function syncPcNewsCategorySummary() {
      if (!pcNewsCategorySummary) return;
      const activas = pcNewsCategoryChecks.filter((checkbox) => checkbox.checked);
      pcNewsCategorySummary.textContent = activas.length === 0
        ? 'Todas las categorías'
        : activas.length === 1
          ? activas[0].nextElementSibling.textContent.trim()
          : `${activas.length} categorías`;
    }

    pcNewsCategoryChecks.forEach((checkbox) => checkbox.addEventListener('change', syncPcNewsCategorySummary));
    document.addEventListener('click', (event) => {
      if (pcNewsCategoryDetails?.open && !pcNewsCategoryFilter.contains(event.target)) {
        pcNewsCategoryDetails.open = false;
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && pcNewsCategoryDetails?.open) {
        pcNewsCategoryDetails.open = false;
        pcNewsCategoryDetails.querySelector('summary')?.focus();
      }
    });
    syncPcNewsCategorySummary();

    function pcNewsFilterUrl() {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(new FormData(pcNewsFilters));
      Array.from(params.entries()).forEach(([key, value]) => {
        if (value.trim() === '') params.delete(key);
      });
      url.search = params.toString();
      return url;
    }

    async function updatePcNewsResults() {
      if (!pcNewsFilters || !pcNewsResults) return;

      if (pcNewsSearchTimer) {
        window.clearTimeout(pcNewsSearchTimer);
        pcNewsSearchTimer = null;
      }
      pcNewsFilterController?.abort();
      const filterController = new AbortController();
      pcNewsFilterController = filterController;

      const url = pcNewsFilterUrl();
      pcNewsFilters.setAttribute('aria-busy', 'true');
      pcNewsResults.setAttribute('aria-busy', 'true');

      try {
        const response = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          signal: filterController.signal,
        });
        if (!response.ok) throw new Error(`No se pudieron filtrar las noticias (${response.status})`);

        const html = await response.text();
        const documentResult = new DOMParser().parseFromString(html, 'text/html');
        const nextResults = documentResult.querySelector('.pc-news-results');
        if (!nextResults) throw new Error('La respuesta no contiene la grilla de noticias');

        const importedResults = document.importNode(nextResults, true);
        pcNewsResults.replaceWith(importedResults);
        pcNewsResults = importedResults;
        const nextTemplates = documentResult.querySelector('#storyTemplates');
        const currentTemplates = document.getElementById('storyTemplates');
        if (nextTemplates && currentTemplates) {
          currentTemplates.replaceChildren(...Array.from(nextTemplates.content?.children || nextTemplates.children).map((node) => document.importNode(node, true)));
        }
        window.history.replaceState({}, '', url);

        const total = pcNewsResults.querySelectorAll('.pc-news-card').length;
        if (pcNewsFilterStatus) {
          pcNewsFilterStatus.textContent = total === 1
            ? '1 noticia encontrada'
            : `${total} noticias encontradas`;
        }
      } catch (error) {
        if (error.name !== 'AbortError') console.error(error);
      } finally {
        if (pcNewsFilterController === filterController) {
          pcNewsFilters.setAttribute('aria-busy', 'false');
          pcNewsResults?.setAttribute('aria-busy', 'false');
        }
      }
    }

    pcNewsFilters?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (pcNewsCategoryDetails) pcNewsCategoryDetails.open = false;
      updatePcNewsResults();
    });

    pcNewsSearch?.addEventListener('input', () => {
      if (pcNewsSearchTimer) window.clearTimeout(pcNewsSearchTimer);
      pcNewsSearchTimer = window.setTimeout(updatePcNewsResults, 280);
    });

    function appendNewsTemplates(html) {
      const container = document.getElementById('storyTemplates');
      if (!container || !html) return;
      const holder = document.createElement('div');
      holder.innerHTML = html;
      holder.querySelectorAll('template[data-story-template]').forEach((template) => {
        const storyId = template.dataset.storyTemplate;
        if (!document.querySelector(`template[data-story-template="${storyId}"]`)) {
          container.appendChild(template);
        }
      });
    }

    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-news-load-more]');
      if (!button || button.disabled) return;
      const view = button.dataset.view;
      const cursor = button.dataset.cursor;
      const endpoint = button.dataset.url;
      if (!endpoint || !cursor || !['pc', 'mobile'].includes(view)) return;

      const wrapper = button.closest('.news-load-more-wrap');
      let status = wrapper?.querySelector('.news-load-more-status');
      if (!status && wrapper) {
        status = document.createElement('span');
        status.className = 'news-load-more-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        wrapper.appendChild(status);
      }

      const url = new URL(endpoint, window.location.href);
      url.searchParams.set('vista', view);
      url.searchParams.set('cursor', cursor);
      if (/^\d+$/.test(button.dataset.adSeed || '')) {
        url.searchParams.set('semilla_publicidad', button.dataset.adSeed);
      }
      if (view === 'pc' && pcNewsFilters) {
        const filterParams = pcNewsFilterUrl().searchParams;
        filterParams.forEach((value, key) => url.searchParams.append(key, value));
      }

      const originalText = button.textContent;
      const scrollPosition = window.scrollY;
      button.disabled = true;
      button.textContent = 'Cargando…';
      if (status) status.textContent = '';

      try {
        const response = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || `No se pudieron cargar noticias (${response.status})`);

        const holder = document.createElement('div');
        holder.innerHTML = data.items_html || '';
        const destination = view === 'pc'
          ? document.querySelector('.pc-news-grid')
          : document.querySelector('.news-feed-items');
        if (!destination) throw new Error('No se encontró el feed de noticias');
        destination.append(...Array.from(holder.children));
        appendNewsTemplates(data.templates_html || '');

        if (view === 'pc' && pcNewsFilterStatus) {
          const total = destination.querySelectorAll('.pc-news-card').length;
          pcNewsFilterStatus.textContent = `${total} noticias cargadas`;
        }

        if (data.hay_mas && data.cursor) {
          button.dataset.cursor = data.cursor;
          button.disabled = false;
          button.textContent = originalText;
          if (status) status.textContent = `${data.cantidad} noticias agregadas.`;
        } else {
          if (status) status.textContent = 'No quedan más noticias.';
          button.remove();
        }
        window.scrollTo({ top: scrollPosition, left: 0, behavior: 'instant' });
      } catch (error) {
        console.error(error);
        button.disabled = false;
        button.textContent = originalText;
        if (status) status.textContent = 'No pudimos cargar más noticias. Intentá nuevamente.';
      }
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

    function trackStoryView(storyId, template) {
      recordLocalStoryView(storyId);
      const title = template.dataset.storyTitle || 'Noticia';
      const url = template.dataset.storyUrl || window.location.href;
      let path = url;
      try {
        const parsed = new URL(url, window.location.href);
        path = parsed.pathname + parsed.search;
      } catch (error) {
        // La URL absoluta generada por el servidor es el fallback suficiente.
      }
      const detail = { id: String(storyId), title, url, path };

      window.dispatchEvent(new CustomEvent('portal:noticia-abierta', { detail }));
      if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({
          event: 'portal_noticia_abierta',
          noticia_id: detail.id,
          noticia_titulo: detail.title,
          noticia_url: detail.url,
        });
      }
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'page_view', {
          page_title: detail.title,
          page_location: detail.url,
          page_path: detail.path,
        });
      }
      if (typeof window.fbq === 'function') {
        window.fbq('track', 'PageView');
        window.fbq('track', 'ViewContent', {
          content_ids: [detail.id],
          content_name: detail.title,
          content_category: 'Noticia',
        });
      }
    }

    function openStorySheet(storyId, trigger) {
      const template = document.querySelector(`template[data-story-template="${storyId}"]`);
      if (!storySheet || !storySheetPanel || !storySheetContent || !template) return;

      const alreadyOpen = storySheet.classList.contains('open');

      if (storySheetClearTimer) {
        window.clearTimeout(storySheetClearTimer);
        storySheetClearTimer = null;
      }

      if (!alreadyOpen) {
        storySheetTrigger = trigger;
        storySheetPreviousOverflow = document.body.style.overflow;
      }
      storySheetDragPointer = null;
      storySheetDragOffset = 0;
      clearStorySheetDragStyles();
      storySheetContent.replaceChildren(template.content.cloneNode(true));
      const latestTemplate = document.getElementById('storyLatestTemplate');
      const latestFragment = latestTemplate?.content.cloneNode(true);
      const latestSection = latestFragment?.querySelector('.story-latest');
      if (latestSection) {
        latestSection.querySelector(`[data-story-recommendation="${storyId}"]`)?.remove();
        Array.from(latestSection.querySelectorAll('.story-latest-entry')).slice(10).forEach((entry) => entry.remove());
        const copy = storySheetContent.querySelector('.story-sheet-copy');
        if (copy && latestSection.querySelector('.story-latest-entry')) copy.appendChild(latestSection);
      }
      storySheetContent.scrollTop = 0;
      storySheet.classList.add('open');
      storySheet.setAttribute('aria-hidden', 'false');
      document.body.classList.add('story-sheet-open');
      document.body.style.overflow = 'hidden';
      initializeStorySheetGallery();
      trackStoryView(storyId, template);
      storySheet.querySelector('.story-sheet-close')?.focus();
    }

    window.addEventListener('portal:abrir-noticia', async (event) => {
      const detail = event.detail || {};
      const storyId = String(detail.storyId || '');
      const trigger = detail.trigger instanceof HTMLElement ? detail.trigger : null;
      const fallbackUrl = typeof detail.fallbackUrl === 'string' ? detail.fallbackUrl : '';
      const templateEndpoint = typeof detail.templateEndpoint === 'string' ? detail.templateEndpoint : '';
      if (!/^\d+$/.test(storyId)) {
        if (fallbackUrl) window.location.href = fallbackUrl;
        return;
      }

      if (!document.querySelector(`template[data-story-template="${storyId}"]`) && templateEndpoint) {
        trigger?.setAttribute('aria-busy', 'true');
        try {
          const url = new URL(templateEndpoint, window.location.href);
          url.searchParams.set('id', storyId);
          const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          });
          const data = await response.json();
          if (!response.ok || typeof data.template_html !== 'string') {
            throw new Error(`No se pudo cargar la noticia (${response.status})`);
          }
          appendNewsTemplates(data.template_html);
        } catch (error) {
          console.error(error);
        } finally {
          trigger?.removeAttribute('aria-busy');
        }
      }

      if (document.querySelector(`template[data-story-template="${storyId}"]`)) {
        openStorySheet(storyId, trigger);
      } else if (fallbackUrl) {
        window.location.href = fallbackUrl;
      }
    });

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
      if (pcTrigger && (window.matchMedia('(min-width: 769px)').matches || pcTrigger.closest('.story-latest'))) {
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
