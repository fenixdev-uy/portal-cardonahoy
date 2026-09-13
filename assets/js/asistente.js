(() => {
  'use strict';

  const root = document.querySelector('[data-news-assistant]');
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const storyEndpoint = root.dataset.storyEndpoint;
  const panel = root.querySelector('.news-assistant-panel');
  const panelHeader = root.querySelector('.news-assistant-header');
  const backdrop = root.querySelector('[data-assistant-backdrop]');
  const launcher = root.querySelector('[data-assistant-launcher]');
  const closeButton = root.querySelector('[data-assistant-close]');
  const navbarTriggers = Array.from(document.querySelectorAll('[data-assistant-navbar-trigger]'));
  const textDecreaseButton = root.querySelector('[data-assistant-text-decrease]');
  const textIncreaseButton = root.querySelector('[data-assistant-text-increase]');
  const textStatus = root.querySelector('[data-assistant-text-status]');
  const messages = root.querySelector('[data-assistant-messages]');
  const form = root.querySelector('[data-assistant-form]');
  const input = root.querySelector('[data-assistant-input]');
  const sendButton = root.querySelector('[data-assistant-send]');
  const contentStart = document.getElementById('contenido');
  const history = [];
  let conversationToken = '';
  let open = false;
  let waiting = false;
  let controller = null;
  let nextRequestAt = 0;
  let previousBodyOverflow = '';
  let dragPointer = null;
  let dragStartY = 0;
  let dragStartTime = 0;
  let dragOffset = 0;

  if (!endpoint || !panel || !launcher || !messages || !form || !input || !sendButton) return;

  const mobileMedia = window.matchMedia('(max-width: 768px)');
  const desktopMedia = window.matchMedia('(min-width: 1025px)');
  const reducedMotionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
  const isMobile = () => mobileMedia.matches;
  const textSizeKey = root.dataset.textSizeKey || 'portal_asistente_texto';
  const textScales = [0.9, 1, 1.1, 1.2, 1.3, 1.4];
  let textScaleIndex = 1;
  const invitationFirstDelay = 5000;
  const invitationRepeatDelay = 18000;
  const invitationVisibleTime = 3000;
  let invitationTimer = 0;
  let invitationCloseTimer = 0;

  function storedTextScaleIndex() {
    try {
      const stored = Number(window.localStorage.getItem(textSizeKey));
      const index = textScales.findIndex((scale) => Math.abs(scale - stored) < 0.001);
      return index >= 0 ? index : 1;
    } catch (error) {
      return 1;
    }
  }

  function applyTextScale(index, persist = true) {
    textScaleIndex = Math.min(textScales.length - 1, Math.max(0, index));
    const scale = textScales[textScaleIndex];
    const percentage = Math.round(scale * 100);
    root.style.setProperty('--assistant-text-scale', String(scale));
    if (textDecreaseButton) textDecreaseButton.disabled = textScaleIndex === 0;
    if (textIncreaseButton) textIncreaseButton.disabled = textScaleIndex === textScales.length - 1;
    if (textStatus) {
      textStatus.textContent = `Tamaño de texto ${percentage}%`;
    }
    if (persist) {
      try {
        window.localStorage.setItem(textSizeKey, String(scale));
      } catch (error) {
        // El control continúa funcionando aunque el navegador bloquee almacenamiento local.
      }
    }
  }

  function clearInvitation() {
    window.clearTimeout(invitationTimer);
    window.clearTimeout(invitationCloseTimer);
    invitationTimer = 0;
    invitationCloseTimer = 0;
    root.classList.remove('is-inviting');
  }

  function canShowInvitation() {
    return (desktopMedia.matches || mobileMedia.matches)
      && !reducedMotionMedia.matches
      && !open
      && document.visibilityState === 'visible'
      && root.classList.contains('is-page-engaged');
  }

  function scheduleInvitation(delay = invitationFirstDelay) {
    window.clearTimeout(invitationTimer);
    invitationTimer = 0;
    if (!canShowInvitation()) return;

    invitationTimer = window.setTimeout(() => {
      invitationTimer = 0;
      if (!canShowInvitation() || launcher.matches(':hover, :focus-visible')) {
        scheduleInvitation(invitationRepeatDelay);
        return;
      }

      root.classList.add('is-inviting');
      invitationCloseTimer = window.setTimeout(() => {
        invitationCloseTimer = 0;
        root.classList.remove('is-inviting');
        scheduleInvitation(invitationRepeatDelay);
      }, invitationVisibleTime);
    }, delay);
  }

  function setPageEngaged(pageEngaged) {
    const changed = root.classList.contains('is-page-engaged') !== pageEngaged;
    root.classList.toggle('is-page-engaged', pageEngaged);
    if (changed) {
      clearInvitation();
      if (pageEngaged) scheduleInvitation();
    }
    if (!pageEngaged && open && !desktopMedia.matches) setOpen(false, false);
  }

  function launcherTopPosition() {
    const launcherHeight = launcher.offsetHeight;
    if (!launcherHeight) return window.innerHeight;

    const positionedElement = isMobile() ? launcher : root;
    const bottom = Number.parseFloat(window.getComputedStyle(positionedElement).bottom);
    return window.innerHeight - (Number.isFinite(bottom) ? bottom : 0) - launcherHeight;
  }

  function updateLauncherVisibility() {
    const launcherHeight = launcher.offsetHeight;
    if (!contentStart) {
      setPageEngaged(launcherHeight > 0);
      return;
    }
    if (!launcherHeight) {
      setPageEngaged(false);
      return;
    }

    const contentTop = contentStart.getBoundingClientRect().top;
    const revealAt = launcherTopPosition() - 12;
    const pageEngaged = contentTop <= revealAt;
    setPageEngaged(pageEngaged);
  }

  let visibilityFrame = 0;
  function scheduleLauncherVisibility() {
    if (visibilityFrame) return;
    visibilityFrame = window.requestAnimationFrame(() => {
      visibilityFrame = 0;
      updateLauncherVisibility();
    });
  }

  function resetDragStyles() {
    panel.style.transform = '';
    panel.style.transition = '';
    if (backdrop) {
      backdrop.style.opacity = '';
      backdrop.style.transition = '';
    }
    root.classList.remove('is-dragging');
    dragOffset = 0;
  }

  function scrollToLatest() {
    window.requestAnimationFrame(() => {
      messages.scrollTop = messages.scrollHeight;
    });
  }

  function setOpen(nextOpen, manageFocus = true) {
    if (open === nextOpen) return;
    open = nextOpen;
    clearInvitation();
    resetDragStyles();
    root.classList.toggle('is-open', open);
    document.body.classList.toggle('news-assistant-desktop-open', open && desktopMedia.matches);
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    navbarTriggers.forEach((trigger) => trigger.setAttribute('aria-expanded', open ? 'true' : 'false'));
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    panel.setAttribute('aria-modal', open && isMobile() ? 'true' : 'false');
    panel.hidden = !open;
    if (open) {
      if (isMobile()) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.classList.add('news-assistant-mobile-open');
        document.body.style.overflow = 'hidden';
      }
      scrollToLatest();
      if (manageFocus) {
        window.setTimeout(() => {
          if (isMobile()) panel.focus({ preventScroll: true });
          else input.focus();
        }, 180);
      }
    } else {
      if (document.body.classList.contains('news-assistant-mobile-open')) {
        document.body.classList.remove('news-assistant-mobile-open');
        document.body.style.overflow = previousBodyOverflow;
      }
      if (manageFocus) launcher.focus();
      scheduleInvitation(invitationRepeatDelay);
    }
  }

  function updateComposer() {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 102)}px`;
    sendButton.disabled = waiting || input.value.trim().length < 3;
  }

  function createMessage(role, text) {
    const article = document.createElement('article');
    article.className = `news-assistant-message is-${role}`;

    if (role === 'assistant') {
      const avatar = document.createElement('span');
      avatar.className = 'news-assistant-message-avatar';
      avatar.setAttribute('aria-hidden', 'true');
      avatar.textContent = 'IA';
      article.appendChild(avatar);
    }

    const content = document.createElement('div');
    content.className = 'news-assistant-message-content';
    const paragraph = document.createElement('p');
    paragraph.textContent = text;
    content.appendChild(paragraph);
    article.appendChild(content);
    messages.appendChild(article);
    scrollToLatest();
    return content;
  }

  function createTyping() {
    const article = document.createElement('article');
    article.className = 'news-assistant-message is-assistant';
    article.dataset.assistantTyping = '';
    article.innerHTML = '<span class="news-assistant-message-avatar" aria-hidden="true">IA</span><span class="news-assistant-typing" aria-label="El asistente está escribiendo"><i></i><i></i><i></i></span>';
    messages.appendChild(article);
    scrollToLatest();
    return article;
  }

  function articleMeta(article) {
    const categories = Array.isArray(article.categorias) ? article.categorias.filter(Boolean) : [];
    if (categories.length) return categories.slice(0, 2).join(' · ');
    if (!article.fecha) return 'Abrir noticia';
    const date = new Date(String(article.fecha).replace(' ', 'T'));
    return Number.isNaN(date.getTime())
      ? 'Abrir noticia'
      : new Intl.DateTimeFormat('es-UY', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
  }

  function appendCards(container, articles) {
    if (!Array.isArray(articles) || !articles.length) return;
    const cards = document.createElement('div');
    cards.className = 'news-assistant-cards';

    articles.forEach((article) => {
      if (!article || typeof article.url !== 'string' || typeof article.titulo !== 'string') return;
      const link = document.createElement('a');
      link.className = 'news-assistant-card';
      link.href = article.url;
      link.dataset.storyId = String(article.id || '');
      link.setAttribute('aria-label', `Abrir noticia: ${article.titulo}`);
      link.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('portal:abrir-noticia', {
          detail: {
            storyId: link.dataset.storyId,
            trigger: link,
            fallbackUrl: link.href,
            templateEndpoint: storyEndpoint,
          },
        }));
      });

      const media = document.createElement('span');
      media.className = 'news-assistant-card-media';
      if (typeof article.imagen === 'string' && article.imagen) {
        const image = document.createElement('img');
        image.src = article.imagen;
        image.alt = '';
        image.loading = 'lazy';
        media.appendChild(image);
      }

      const copy = document.createElement('span');
      copy.className = 'news-assistant-card-copy';
      const title = document.createElement('strong');
      title.textContent = article.titulo;
      const meta = document.createElement('span');
      meta.textContent = articleMeta(article);
      copy.append(title, meta);

      const arrow = document.createElement('span');
      arrow.className = 'news-assistant-card-arrow';
      arrow.setAttribute('aria-hidden', 'true');
      arrow.textContent = '→';
      link.append(media, copy, arrow);
      cards.appendChild(link);
    });

    if (cards.childElementCount) container.appendChild(cards);
  }

  function remember(role, content) {
    history.push({ role, content });
    if (history.length > 6) history.splice(0, history.length - 6);
  }

  async function ask(rawQuestion) {
    const question = rawQuestion.trim().slice(0, 500);
    if (waiting || question.length < 3) return;

    waiting = true;
    input.value = '';
    updateComposer();
    createMessage('user', question);
    const historyForRequest = history.slice(-6);
    remember('user', question);
    const typing = createTyping();
    controller?.abort();
    controller = new AbortController();

    try {
      const remainingDelay = Math.max(0, nextRequestAt - Date.now());
      if (remainingDelay > 0) {
        await new Promise((resolve) => window.setTimeout(resolve, remainingDelay));
      }
      nextRequestAt = Date.now() + 2100;
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        signal: controller.signal,
        body: JSON.stringify({
          mensaje: question,
          historial: historyForRequest,
          conversacion: conversationToken || undefined,
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.ok !== true) {
        const fallbackMessage = response.status === 429
          ? 'Esperá unos segundos antes de volver a preguntar.'
          : 'No pudimos completar la consulta.';
        throw new Error(typeof data.error === 'string' ? data.error : fallbackMessage);
      }
      const answer = typeof data.respuesta === 'string' && data.respuesta.trim()
        ? data.respuesta.trim()
        : 'No encontré información para responder esa consulta.';
      if (typeof data.conversacion === 'string' && /^[0-9a-f]{64}$/.test(data.conversacion)) {
        conversationToken = data.conversacion;
      }
      typing.remove();
      const content = createMessage('assistant', answer);
      appendCards(content, data.noticias);
      remember('assistant', answer);
    } catch (error) {
      if (error.name === 'AbortError') return;
      typing.remove();
      const message = error instanceof Error && error.message
        ? error.message
        : 'El asistente no está disponible en este momento. Probá nuevamente.';
      createMessage('assistant', message);
    } finally {
      waiting = false;
      controller = null;
      updateComposer();
      if (!isMobile()) input.focus();
    }
  }

  launcher.addEventListener('click', () => setOpen(!open));
  navbarTriggers.forEach((trigger) => trigger.addEventListener('click', () => setOpen(true)));
  window.addEventListener('portal:abrir-asistente', () => setOpen(true));
  closeButton?.addEventListener('click', () => setOpen(false));
  backdrop?.addEventListener('click', () => setOpen(false));
  textDecreaseButton?.addEventListener('click', () => applyTextScale(textScaleIndex - 1));
  textIncreaseButton?.addEventListener('click', () => applyTextScale(textScaleIndex + 1));

  function finishDrag(event) {
    if (event.pointerId !== dragPointer) return;
    const duration = Math.max(1, performance.now() - dragStartTime);
    const velocity = dragOffset / duration;
    const shouldClose = dragOffset >= Math.min(120, panel.offsetHeight * .18)
      || (dragOffset >= 42 && velocity >= .65);
    dragPointer = null;
    if (panelHeader?.hasPointerCapture(event.pointerId)) panelHeader.releasePointerCapture(event.pointerId);
    resetDragStyles();
    if (shouldClose) setOpen(false);
  }

  panelHeader?.addEventListener('pointerdown', (event) => {
    if (!isMobile() || !open || event.pointerType === 'mouse' || event.target.closest('button')) return;
    dragPointer = event.pointerId;
    dragStartY = event.clientY;
    dragStartTime = performance.now();
    dragOffset = 0;
    root.classList.add('is-dragging');
    panelHeader.setPointerCapture(event.pointerId);
  });

  panelHeader?.addEventListener('pointermove', (event) => {
    if (event.pointerId !== dragPointer) return;
    dragOffset = Math.max(0, event.clientY - dragStartY);
    panel.style.transform = `translateY(${dragOffset}px)`;
    if (backdrop) backdrop.style.opacity = String(Math.max(0, 1 - dragOffset / (panel.offsetHeight * .75)));
  });

  panelHeader?.addEventListener('pointerup', finishDrag);
  panelHeader?.addEventListener('pointercancel', finishDrag);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    ask(input.value);
  });

  input.addEventListener('input', updateComposer);
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      if (!sendButton.disabled) form.requestSubmit();
    }
  });

  root.addEventListener('click', (event) => {
    const suggestion = event.target.closest('[data-assistant-suggestion]');
    if (suggestion) ask(suggestion.dataset.assistantSuggestion || '');
  });

  document.getElementById('hamburger')?.addEventListener('click', () => {
    if (document.getElementById('menuOverlay')?.classList.contains('open')) setOpen(false, false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && open) {
      setOpen(false);
      return;
    }
    if (event.key !== 'Tab' || !open || !isMobile() || document.getElementById('storySheet')?.classList.contains('open')) return;
    const focusable = Array.from(panel.querySelectorAll('button:not(:disabled), textarea:not(:disabled), a[href]'))
      .filter((element) => element.getClientRects().length > 0);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
      event.preventDefault();
      first.focus();
    }
  });

  const handleMobileChange = () => {
    if (open) setOpen(false, false);
    panel.setAttribute('aria-modal', 'false');
    scheduleLauncherVisibility();
  };
  if (typeof mobileMedia.addEventListener === 'function') mobileMedia.addEventListener('change', handleMobileChange);
  else if (typeof mobileMedia.addListener === 'function') mobileMedia.addListener(handleMobileChange);

  const handleInvitationContextChange = () => {
    clearInvitation();
    scheduleLauncherVisibility();
    scheduleInvitation();
  };
  const handleDesktopChange = () => {
    if (open) setOpen(false, false);
    handleInvitationContextChange();
  };
  if (typeof desktopMedia.addEventListener === 'function') desktopMedia.addEventListener('change', handleDesktopChange);
  else if (typeof desktopMedia.addListener === 'function') desktopMedia.addListener(handleDesktopChange);
  if (typeof reducedMotionMedia.addEventListener === 'function') reducedMotionMedia.addEventListener('change', handleInvitationContextChange);
  else if (typeof reducedMotionMedia.addListener === 'function') reducedMotionMedia.addListener(handleInvitationContextChange);

  document.addEventListener('visibilitychange', handleInvitationContextChange);

  window.addEventListener('storage', (event) => {
    if (event.key !== textSizeKey) return;
    applyTextScale(storedTextScaleIndex(), false);
  });

  window.addEventListener('scroll', scheduleLauncherVisibility, { passive: true });
  window.addEventListener('resize', scheduleLauncherVisibility);

  updateLauncherVisibility();
  applyTextScale(storedTextScaleIndex(), false);
  updateComposer();
})();
