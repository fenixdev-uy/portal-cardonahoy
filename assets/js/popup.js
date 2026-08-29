(function () {
  'use strict';
  const script = document.currentScript;
  const endpoint = script && script.dataset.popupEndpoint;
  const popup = document.getElementById('publicPopup');
  const image = document.getElementById('publicPopupImage');
  const close = document.getElementById('publicPopupClose');
  const links = document.getElementById('publicPopupLinks');
  if (!endpoint || !popup || !image || !close || !links) return;

  let candidate = null;
  let timer = 0;
  let previousFocus = null;
  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;
  const orientation = () => isMobile() ? 'vertical' : 'horizontal';
  const source = () => candidate ? (orientation() === 'vertical' ? candidate.imagen_vertical : candidate.imagen_horizontal) : '';

  function syncDestinations() {
    const destinations = candidate && candidate.destinos && typeof candidate.destinos === 'object' ? candidate.destinos : {};
    let visible = 0;
    links.querySelectorAll('[data-popup-destination]').forEach((link) => {
      const url = String(destinations[link.dataset.popupDestination] || '');
      link.hidden = !url;
      if (url) {
        link.href = url;
        visible += 1;
      } else {
        link.removeAttribute('href');
      }
    });
    links.hidden = visible === 0;
  }

  function closePopup() {
    if (!popup.classList.contains('is-open')) return;
    popup.classList.remove('is-open');
    popup.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('public-popup-open');
    image.removeAttribute('src');
    if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
  }

  function syncDeviceImage() {
    if (!candidate) return;
    const nextOrientation = orientation();
    const nextSource = source();
    popup.dataset.orientation = nextOrientation;
    if (nextSource && image.src !== nextSource) image.src = nextSource;
  }

  async function reserveAndShow() {
    const nextSource = source();
    if (!candidate || !nextSource) return;
    const probe = new Image();
    probe.src = nextSource;
    try {
      if (typeof probe.decode === 'function') await probe.decode();
      else await new Promise((resolve, reject) => { probe.onload = resolve; probe.onerror = reject; });
      const payload = new FormData();
      payload.set('id', String(candidate.id));
      const response = await fetch(endpoint, { method: 'POST', body: payload, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' });
      const result = await response.json();
      if (!response.ok || !result.ok || result.mostrar !== true) return;
      previousFocus = document.activeElement;
      syncDeviceImage();
      image.alt = candidate.nombre || 'Publicidad';
      popup.classList.add('is-open');
      popup.setAttribute('aria-hidden', 'false');
      document.body.classList.add('public-popup-open');
      window.setTimeout(() => close.focus(), 80);
    } catch (error) {
      // Si la pieza o el endpoint fallan, el portal continúa sin interrumpir al visitante.
    }
  }

  async function initialize() {
    try {
      const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
      const result = await response.json();
      if (!response.ok || !result.ok || !result.popup) return;
      candidate = result.popup;
      syncDeviceImage();
      syncDestinations();
      timer = window.setTimeout(reserveAndShow, Math.max(0, Number(candidate.segundos) || 0) * 1000);
    } catch (error) {
      // Un popup nunca debe impedir que la portada o la noticia carguen.
    }
  }

  close.addEventListener('click', closePopup);
  popup.addEventListener('click', (event) => { if (event.target === popup) closePopup(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePopup(); });
  const deviceMedia = window.matchMedia('(max-width: 768px)');
  if (typeof deviceMedia.addEventListener === 'function') deviceMedia.addEventListener('change', syncDeviceImage);
  else if (typeof deviceMedia.addListener === 'function') deviceMedia.addListener(syncDeviceImage);
  window.addEventListener('pagehide', () => window.clearTimeout(timer), { once: true });
  initialize();
})();
