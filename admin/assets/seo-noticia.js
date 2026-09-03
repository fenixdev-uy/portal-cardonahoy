(function () {
  const root = document.querySelector('[data-seo-root]');
  if (!root) return;

  const titleSource = document.getElementById('titulo');
  const descriptionSource = document.getElementById('descripcionInput');
  const slug = document.getElementById('slug');
  const title = document.getElementById('seo_titulo');
  const description = document.getElementById('seo_descripcion');
  const titleMode = document.getElementById('seo_titulo_personalizado');
  const descriptionMode = document.getElementById('seo_descripcion_personalizada');
  const imageSelect = document.getElementById('seo_imagen');
  const badge = document.getElementById('seoModeBadge');
  const uploadButton = document.getElementById('seoImageUpload');
  const uploadInput = document.getElementById('seoImageInput');
  const uploadStatus = document.getElementById('seoImageStatus');
  const previewImage = document.getElementById('seoPreviewImage');
  const previewImageFrame = document.getElementById('seoPreviewImageFrame');
  const publicBase = root.dataset.publicBase.replace(/\/$/, '');
  const editing = root.dataset.editing === '1';
  const processedPreviews = new Map();
  if (root.dataset.seoCurrentSource && root.dataset.seoCurrentPreview) {
    processedPreviews.set(root.dataset.seoCurrentSource, root.dataset.seoCurrentPreview);
  }
  let editorText = htmlToText(descriptionSource.value);
  let galleryItems = readGallery();
  let slugTouched = editing || (slug.value !== '' && slug.value !== normalizeSlug(titleSource.value));

  function htmlToText(html) {
    const node = document.createElement('div');
    node.innerHTML = html || '';
    return (node.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function normalizeSlug(value) {
    return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 190) || 'noticia';
  }

  function autoDescription() {
    if (editorText.length <= 160) return editorText;
    let text = editorText.slice(0, 159).trimEnd();
    const space = text.lastIndexOf(' ');
    if (space >= 104) text = text.slice(0, space);
    return text.replace(/[\s.,;:-]+$/g, '') + '…';
  }

  function isCustom(mode) { return mode.value === '1'; }

  function effectiveTitle() { return isCustom(titleMode) ? title.value.trim() : titleSource.value.trim(); }
  function effectiveDescription() { return isCustom(descriptionMode) ? description.value.trim() : autoDescription(); }
  function effectiveImage() {
    const selected = imageSelect.value;
    const route = selected || galleryItems[0]?.url || '';
    if (!route) return '';
    if (processedPreviews.has(route)) return processedPreviews.get(route);
    if (/^https?:\/\//i.test(route)) return route;
    return publicBase + '/' + route.replace(/^\/+/, '');
  }
  function effectiveUrl() { return publicBase + '/noticia/' + encodeURIComponent(normalizeSlug(slug.value || titleSource.value)); }

  function updateCounters() {
    document.querySelector('[data-seo-counter="titulo"]').textContent = title.value.length + ' caracteres';
    document.querySelector('[data-seo-counter="descripcion"]').textContent = description.value.length + ' caracteres';
  }

  function updateBadge() {
    const custom = isCustom(titleMode) || isCustom(descriptionMode) || imageSelect.value !== '';
    badge.textContent = custom ? 'Personalizado' : 'Automático';
    badge.classList.toggle('is-custom', custom);
  }

  function updatePreview() {
    if (!isCustom(titleMode)) title.value = titleSource.value.trim();
    if (!isCustom(descriptionMode)) description.value = autoDescription();
    const values = {
      title: effectiveTitle() || 'Título de la noticia',
      description: effectiveDescription() || 'La descripción aparecerá automáticamente a partir del contenido de la noticia.',
      image: effectiveImage(),
      url: effectiveUrl(),
    };
    previewImageFrame.classList.toggle('is-empty', values.image === '');
    previewImage.hidden = values.image === '';
    if (values.image === '') previewImage.removeAttribute('src');
    else previewImage.src = values.image;
    document.getElementById('seoPreviewTitle').textContent = values.title;
    document.getElementById('seoPreviewDescription').textContent = values.description;
    document.getElementById('seoPreviewUrl').textContent = values.url;
    document.getElementById('seoGoogleUrl').textContent = values.url;
    document.getElementById('seoGoogleTitle').textContent = values.title;
    document.getElementById('seoGoogleDescription').textContent = values.description;
    updateCounters();
    updateBadge();
  }

  function readGallery() {
    return Array.from(document.querySelectorAll('#galeria .gallery-item')).map((item, index) => ({
      url: item.getAttribute('data-url') || '',
      label: 'Foto ' + (index + 1) + (index === 0 ? ' — portada' : ''),
    })).filter((item) => item.url);
  }

  function syncImageOptions() {
    galleryItems = readGallery();
    const selected = imageSelect.value;
    imageSelect.replaceChildren(new Option('Automática — usar portada', ''));
    galleryItems.forEach((item) => imageSelect.add(new Option(item.label, item.url)));
    if (selected && !galleryItems.some((item) => item.url === selected)) {
      imageSelect.add(new Option('Imagen SEO subida', selected));
    }
    imageSelect.value = selected;
    updatePreview();
  }

  document.querySelectorAll('[data-seo-auto]').forEach((button) => {
    button.addEventListener('click', () => {
      const kind = button.dataset.seoAuto;
      const mode = kind === 'titulo' ? titleMode : descriptionMode;
      const input = kind === 'titulo' ? title : description;
      const customize = !isCustom(mode);
      mode.value = customize ? '1' : '0';
      input.readOnly = !customize;
      button.textContent = customize ? 'Volver a automático' : 'Personalizar';
      updatePreview();
      if (customize) input.focus();
    });
  });

  document.querySelectorAll('[data-seo-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-seo-tab]').forEach((item) => item.setAttribute('aria-selected', item === tab ? 'true' : 'false'));
      document.querySelectorAll('[data-seo-panel]').forEach((panel) => { panel.hidden = panel.dataset.seoPanel !== tab.dataset.seoTab; });
    });
  });

  titleSource.addEventListener('input', () => {
    if (!slugTouched && !editing) slug.value = normalizeSlug(titleSource.value);
    updatePreview();
  });
  slug.addEventListener('input', () => { slugTouched = true; updatePreview(); });
  slug.addEventListener('blur', () => { slug.value = normalizeSlug(slug.value || titleSource.value); updatePreview(); });
  title.addEventListener('input', updatePreview);
  description.addEventListener('input', updatePreview);
  imageSelect.addEventListener('change', updatePreview);
  document.addEventListener('noticia-editor-update', (event) => { editorText = event.detail?.texto || ''; updatePreview(); });
  document.addEventListener('noticia-galeria-update', syncImageOptions);

  uploadButton.addEventListener('click', () => uploadInput.click());
  uploadInput.addEventListener('change', async () => {
    const file = uploadInput.files?.[0];
    if (!file) return;
    uploadButton.disabled = true;
    uploadStatus.textContent = 'Subiendo ' + file.name + '…';
    const data = new FormData();
    data.append('imagen', file);
    data.append('uso', 'seo');
    data.append('csrf_token', root.dataset.csrf);
    try {
      const response = await fetch('upload-imagen.php', { method: 'POST', body: data });
      const result = await response.json();
      if (!response.ok || result.error) throw new Error(result.error || 'No se pudo subir la imagen SEO.');
      if (result.seo_preview_url) {
        const previewUrl = /^https?:\/\//i.test(result.seo_preview_url)
          ? result.seo_preview_url
          : publicBase + '/' + result.seo_preview_url.replace(/^\/+/, '');
        processedPreviews.set(result.url, previewUrl);
      }
      imageSelect.add(new Option('Imagen SEO subida', result.url, true, true));
      const pesoKb = result.seo_peso ? Math.max(1, Math.round(result.seo_peso / 1024)) : null;
      uploadStatus.textContent = 'Imagen SEO lista: 1200 × 630 px' + (pesoKb ? ' · ' + pesoKb + ' KB' : '');
      updatePreview();
    } catch (error) {
      uploadStatus.textContent = '';
      alert(error.message || 'No se pudo subir la imagen SEO.');
    } finally {
      uploadButton.disabled = false;
      uploadInput.value = '';
    }
  });

  syncImageOptions();
})();
