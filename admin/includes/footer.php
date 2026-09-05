</main>
  </div>
</div>

<script>
  (function () {
    const menuButton = document.getElementById('profileMenuButton');
    const drawer = document.getElementById('profileDrawer');
    const backdrop = document.getElementById('profileDrawerBackdrop');
    const closeButton = document.getElementById('profileDrawerClose');
    const cancelButton = document.getElementById('profileDrawerCancel');
    const form = document.getElementById('profileForm');
    if (!menuButton || !drawer || !backdrop || !closeButton || !cancelButton || !form) return;

    const nameInput = document.getElementById('profileName');
    const emailInput = document.getElementById('profileEmail');
    const photoInput = document.getElementById('profilePhoto');
    const photoPreview = document.getElementById('profilePhotoPreview');
    const photoHelp = document.getElementById('profilePhotoHelp');
    const currentPassword = document.getElementById('profileCurrentPassword');
    const newPassword = document.getElementById('profileNewPassword');
    const repeatPassword = document.getElementById('profileRepeatPassword');
    const status = document.getElementById('profileSaveStatus');
    const saveButton = document.getElementById('profileSaveButton');
    const sidebarAvatar = document.getElementById('sidebarUserAvatar');
    const sidebarName = document.getElementById('sidebarUserName');
    const defaultPhotoHelp = photoHelp.textContent;
    let savedName = nameInput.value;
    let savedEmail = emailInput.value;
    let savedPhotoNodes = Array.from(photoPreview.childNodes, node => node.cloneNode(true));
    let previewSequence = 0;

    function restorePhoto(message = defaultPhotoHelp) {
      photoPreview.classList.remove('is-loading', 'has-error', 'has-image');
      photoPreview.replaceChildren(...savedPhotoNodes.map(node => node.cloneNode(true)));
      if (photoPreview.querySelector('img')) photoPreview.classList.add('has-image');
      photoHelp.textContent = message;
    }

    function showInitial(message = defaultPhotoHelp) {
      photoPreview.classList.remove('is-loading', 'has-error', 'has-image');
      const initial = document.createElement('span');
      initial.className = 'user-photo-initial';
      initial.textContent = (nameInput.value.trim().charAt(0) || 'U').toLocaleUpperCase('es');
      photoPreview.replaceChildren(initial);
      photoHelp.textContent = message;
    }

    function resetUnsaved() {
      nameInput.value = savedName;
      emailInput.value = savedEmail;
      currentPassword.value = '';
      newPassword.value = '';
      repeatPassword.value = '';
      photoInput.value = '';
      previewSequence += 1;
      restorePhoto();
      status.textContent = '';
      status.className = 'profile-save-status';
    }

    function openDrawer() {
      document.getElementById('sidebar')?.classList.remove('open');
      document.getElementById('sidebarBackdrop')?.classList.remove('show');
      drawer.classList.add('open');
      backdrop.classList.add('show');
      drawer.setAttribute('aria-hidden', 'false');
      menuButton.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
      window.setTimeout(() => nameInput.focus(), 220);
    }

    function closeDrawer() {
      drawer.classList.remove('open');
      backdrop.classList.remove('show');
      drawer.setAttribute('aria-hidden', 'true');
      menuButton.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
      resetUnsaved();
      menuButton.focus();
    }

    photoInput.addEventListener('change', () => {
      const sequence = ++previewSequence;
      const file = photoInput.files && photoInput.files[0];
      if (!file) { restorePhoto(); return; }
      const invalidType = !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && !/\.(?:jpe?g|png|webp)$/i.test(file.name);
      if (invalidType || file.size > 3 * 1024 * 1024) {
        photoInput.value = '';
        restorePhoto(file.size > 3 * 1024 * 1024 ? 'La foto no puede superar los 3 MB.' : 'Elegí una imagen JPG, PNG o WEBP.');
        photoPreview.classList.add('has-error');
        return;
      }
      photoPreview.classList.add('is-loading');
      photoHelp.textContent = 'Preparando la vista previa…';
      const reader = new FileReader();
      reader.addEventListener('load', () => {
        if (sequence !== previewSequence || typeof reader.result !== 'string') return;
        const image = document.createElement('img');
        image.src = reader.result;
        image.alt = '';
        photoPreview.replaceChildren(image);
        photoPreview.classList.remove('is-loading', 'has-error');
        photoPreview.classList.add('has-image');
        photoHelp.textContent = file.name + ' · vista previa lista';
      });
      reader.addEventListener('error', () => {
        if (sequence === previewSequence) restorePhoto('No pudimos leer esta imagen. Elegí otra.');
      });
      reader.readAsDataURL(file);
    });

    nameInput.addEventListener('input', () => {
      if (photoPreview.querySelector('.user-photo-initial')) showInitial(photoHelp.textContent);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity() || saveButton.disabled) return;
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando perfil…';
      status.className = 'profile-save-status';
      try {
        const response = await fetch('perfil.php', { method: 'POST', body: new FormData(form) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No pudimos guardar el perfil.');

        savedName = result.perfil.nombre;
        savedEmail = result.perfil.email;
        nameInput.value = savedName;
        emailInput.value = savedEmail;
        currentPassword.value = '';
        newPassword.value = '';
        repeatPassword.value = '';
        photoInput.value = '';
        sidebarName.textContent = savedName;

        const avatar = document.createElement(result.perfil.foto_url ? 'img' : 'span');
        if (result.perfil.foto_url) {
          avatar.src = result.perfil.foto_url;
          avatar.alt = '';
        } else {
          avatar.className = 'user-photo-initial';
          avatar.textContent = result.perfil.inicial;
        }
        photoPreview.replaceChildren(avatar.cloneNode(true));
        sidebarAvatar.replaceChildren(avatar);
        photoPreview.classList.toggle('has-image', Boolean(result.perfil.foto_url));
        savedPhotoNodes = Array.from(photoPreview.childNodes, node => node.cloneNode(true));
        photoHelp.textContent = defaultPhotoHelp;
        status.textContent = result.mensaje || 'Perfil actualizado correctamente.';
        status.className = 'profile-save-status is-success';
      } catch (error) {
        status.textContent = error.message || 'No pudimos guardar el perfil.';
        status.className = 'profile-save-status is-error';
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar cambios';
      }
    });

    menuButton.addEventListener('click', openDrawer);
    closeButton.addEventListener('click', closeDrawer);
    cancelButton.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });
  })();
</script>

<script>
  (function () {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggle = document.getElementById('menuToggle');

    function openSidebar() {
      sidebar.classList.add('open');
      backdrop.classList.add('show');
    }

    function closeSidebar() {
      sidebar.classList.remove('open');
      backdrop.classList.remove('show');
    }

    if (toggle) {
      toggle.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', closeSidebar);
    }

    const groupToggles = Array.from(document.querySelectorAll('[data-nav-group-toggle]'));
    groupToggles.forEach((button) => {
      button.addEventListener('click', () => {
        const submenu = document.getElementById(button.getAttribute('aria-controls'));
        if (!submenu) return;
        const willOpen = button.getAttribute('aria-expanded') !== 'true';
        groupToggles.forEach((otherButton) => {
          if (otherButton === button) return;
          const otherSubmenu = document.getElementById(otherButton.getAttribute('aria-controls'));
          otherButton.setAttribute('aria-expanded', 'false');
          if (otherSubmenu) otherSubmenu.hidden = true;
        });
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        submenu.hidden = !willOpen;
      });
    });
  })();
</script>

<script>
  (function () {
    const menuButton = document.getElementById('settingsMenuButton');
    const drawer = document.getElementById('settingsDrawer');
    const backdrop = document.getElementById('settingsDrawerBackdrop');
    const closeButton = document.getElementById('settingsDrawerClose');
    const cancelButton = document.getElementById('settingsDrawerCancel');
    const form = document.getElementById('watermarkSettingsForm');
    if (!menuButton || !drawer || !backdrop || !closeButton || !cancelButton || !form) return;

    const fileInput = document.getElementById('watermarkFile');
    const fileName = document.getElementById('watermarkFileName');
    const opacity = document.getElementById('watermarkOpacity');
    const opacityValue = document.getElementById('watermarkOpacityValue');
    const size = document.getElementById('watermarkSize');
    const sizeValue = document.getElementById('watermarkSizeValue');
    const previewLogo = document.getElementById('watermarkPreviewLogo');
    const status = document.getElementById('watermarkSaveStatus');
    const saveButton = document.getElementById('watermarkSaveButton');
    const cardToggle = document.getElementById('watermarkCardToggle');
    const cardContent = document.getElementById('watermarkCardContent');
    if (!fileInput || !fileName || !opacity || !opacityValue || !size || !sizeValue || !previewLogo || !status || !saveButton || !cardToggle || !cardContent) return;
    let savedLogoSrc = previewLogo.src;
    let savedOpacity = opacity.value;
    let savedSize = size.value;
    let savedFileLabel = fileName.textContent;

    function updatePreview() {
      const value = Math.max(5, Math.min(100, Number(opacity.value) || 15));
      opacityValue.value = value + '%';
      opacityValue.textContent = value + '%';
      previewLogo.style.opacity = String(value / 100);
      const sizePercent = Math.max(15, Math.min(65, Number(size.value) || 36));
      sizeValue.value = sizePercent + '%';
      sizeValue.textContent = sizePercent + '%';
      previewLogo.style.width = sizePercent + '%';
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes de marca de agua' : 'Expandir ajustes de marca de agua');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    function resetUnsaved() {
      fileInput.value = '';
      fileName.textContent = savedFileLabel;
      previewLogo.src = savedLogoSrc;
      opacity.value = savedOpacity;
      size.value = savedSize;
      status.textContent = '';
      status.className = 'settings-save-status';
      updatePreview();
    }

    function openDrawer() {
      drawer.classList.add('open');
      backdrop.classList.add('show');
      drawer.setAttribute('aria-hidden', 'false');
      menuButton.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
      document.getElementById('sidebar')?.classList.remove('open');
      document.getElementById('sidebarBackdrop')?.classList.remove('show');
      window.setTimeout(() => opacity.focus(), 300);
    }

    function closeDrawer() {
      drawer.classList.remove('open');
      backdrop.classList.remove('show');
      drawer.setAttribute('aria-hidden', 'true');
      menuButton.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
      resetUnsaved();
      menuButton.focus();
    }

    opacity.addEventListener('input', updatePreview);
    size.addEventListener('input', updatePreview);
    cardToggle.addEventListener('click', () => {
      setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true');
    });
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (file.type !== 'image/png' || file.size > 2 * 1024 * 1024) {
        fileInput.value = '';
        status.textContent = file.type !== 'image/png'
          ? 'Elegí una imagen PNG.'
          : 'La marca de agua debe pesar como máximo 2 MB.';
        status.className = 'settings-save-status is-error';
        return;
      }
      fileName.textContent = file.name;
      const reader = new FileReader();
      reader.addEventListener('load', () => {
        if (typeof reader.result === 'string') previewLogo.src = reader.result;
      });
      reader.readAsDataURL(file);
      status.textContent = 'Vista previa actualizada. Guardá para aplicar el cambio.';
      status.className = 'settings-save-status';
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saveButton.disabled) return;
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando configuración…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-marca-agua.php', {
          method: 'POST',
          body: new FormData(form)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar la configuración.');
        savedLogoSrc = result.logo_url || previewLogo.src;
        savedOpacity = String(result.opacidad);
        savedSize = String(result.tamano);
        savedFileLabel = fileInput.files && fileInput.files[0]
          ? fileInput.files[0].name
          : savedFileLabel;
        previewLogo.src = savedLogoSrc;
        opacity.value = savedOpacity;
        size.value = savedSize;
        fileInput.value = '';
        fileName.textContent = savedFileLabel;
        updatePreview();
        status.textContent = result.mensaje || 'Configuración guardada.';
      } catch (error) {
        status.textContent = error.message || 'No se pudo guardar la configuración.';
        status.className = 'settings-save-status is-error';
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar configuración';
      }
    });

    menuButton.addEventListener('click', openDrawer);
    closeButton.addEventListener('click', closeDrawer);
    cancelButton.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });
    updatePreview();
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('loginLogoSettingsForm');
    const fileInput = document.getElementById('loginLogoFile');
    const fileName = document.getElementById('loginLogoFileName');
    const preview = document.getElementById('loginLogoPreview');
    const previewBox = preview?.closest('.login-logo-preview');
    const sizeInput = document.getElementById('loginLogoSize');
    const sizeValue = document.getElementById('loginLogoSizeValue');
    const status = document.getElementById('loginLogoSaveStatus');
    const saveButton = document.getElementById('loginLogoSaveButton');
    const cancelButton = document.getElementById('loginLogoCancel');
    const cardToggle = document.getElementById('loginLogoCardToggle');
    const cardContent = document.getElementById('loginLogoCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    const drawerBackdrop = document.getElementById('settingsDrawerBackdrop');
    if (!form || !fileInput || !fileName || !preview || !previewBox || !sizeInput || !sizeValue || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !drawerBackdrop) return;

    let savedLogoSrc = preview.src;
    let savedFileLabel = fileName.textContent;
    let savedSize = sizeInput.value;

    function updateSizePreview() {
      const size = Math.max(60, Math.min(140, Number(sizeInput.value) || 100));
      sizeValue.value = `${size}%`;
      sizeValue.textContent = `${size}%`;
      preview.style.transform = `scale(${size / 100})`;
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes del logo del login' : 'Expandir ajustes del logo del login');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    function resetUnsaved() {
      fileInput.value = '';
      fileName.textContent = savedFileLabel;
      preview.src = savedLogoSrc;
      sizeInput.value = savedSize;
      updateSizePreview();
      previewBox.classList.remove('is-loading');
      status.textContent = '';
      status.className = 'settings-save-status';
    }

    function showError(message) {
      previewBox.classList.remove('is-loading');
      status.textContent = message;
      status.className = 'settings-save-status is-error';
    }

    cardToggle.addEventListener('click', () => {
      setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true');
    });
    sizeInput.addEventListener('input', updateSizePreview);

    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (file.type !== 'image/png' || file.size > 2 * 1024 * 1024) {
        fileInput.value = '';
        showError(file.type !== 'image/png' ? 'Elegí una imagen PNG.' : 'El logo debe pesar como máximo 2 MB.');
        return;
      }

      previewBox.classList.add('is-loading');
      status.textContent = 'Preparando vista previa…';
      status.className = 'settings-save-status';
      const reader = new FileReader();
      reader.addEventListener('error', () => {
        fileInput.value = '';
        preview.src = savedLogoSrc;
        showError('No se pudo leer el logo. Elegí otro archivo.');
      });
      reader.addEventListener('load', () => {
        if (typeof reader.result !== 'string') {
          fileInput.value = '';
          preview.src = savedLogoSrc;
          showError('No se pudo leer el logo. Elegí otro archivo.');
          return;
        }
        const probe = new Image();
        probe.addEventListener('load', () => {
          preview.src = reader.result;
          fileName.textContent = file.name;
          previewBox.classList.remove('is-loading');
          status.textContent = 'Vista previa lista. Guardá para aplicar el cambio.';
          status.className = 'settings-save-status';
        });
        probe.addEventListener('error', () => {
          fileInput.value = '';
          preview.src = savedLogoSrc;
          showError('El logo no pudo mostrarse. Elegí otro PNG.');
        });
        probe.src = reader.result;
      });
      reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saveButton.disabled) return;
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando logo…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-logo-login.php', {
          method: 'POST',
          body: new FormData(form)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el logo.');
        savedLogoSrc = result.logo_url || preview.src;
        if (fileInput.files && fileInput.files[0]) savedFileLabel = fileInput.files[0].name;
        savedSize = String(result.tamano || sizeInput.value);
        sizeInput.value = savedSize;
        updateSizePreview();
        preview.src = savedLogoSrc;
        fileInput.value = '';
        fileName.textContent = savedFileLabel;
        status.textContent = result.mensaje || 'Logo guardado correctamente.';
      } catch (error) {
        showError(error.message || 'No se pudo guardar el logo.');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar cambios';
      }
    });

    cancelButton.addEventListener('click', () => {
      resetUnsaved();
      drawerClose.click();
    });
    drawerClose.addEventListener('click', resetUnsaved);
    drawerBackdrop.addEventListener('click', resetUnsaved);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') resetUnsaved();
    });
    updateSizePreview();
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('portalLogoSettingsForm');
    const fileInput = document.getElementById('portalLogoFile');
    const fileName = document.getElementById('portalLogoFileName');
    const preview = document.getElementById('portalLogoPreview');
    const previewBox = preview?.closest('.portal-logo-preview');
    const sizeInput = document.getElementById('portalLogoSize');
    const sizeValue = document.getElementById('portalLogoSizeValue');
    const status = document.getElementById('portalLogoSaveStatus');
    const saveButton = document.getElementById('portalLogoSaveButton');
    const cancelButton = document.getElementById('portalLogoCancel');
    const cardToggle = document.getElementById('portalLogoCardToggle');
    const cardContent = document.getElementById('portalLogoCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    const drawerBackdrop = document.getElementById('settingsDrawerBackdrop');
    if (!form || !fileInput || !fileName || !preview || !previewBox || !sizeInput || !sizeValue || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !drawerBackdrop) return;

    let savedLogoSrc = preview.src;
    let savedFileLabel = fileName.textContent;
    let savedSize = sizeInput.value;

    function updateSizePreview() {
      const size = Math.max(60, Math.min(140, Number(sizeInput.value) || 100));
      sizeValue.value = `${size}%`;
      sizeValue.textContent = `${size}%`;
      preview.style.transform = `scale(${size / 100})`;
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes del logo del portal' : 'Expandir ajustes del logo del portal');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    function resetUnsaved() {
      fileInput.value = '';
      fileName.textContent = savedFileLabel;
      preview.src = savedLogoSrc;
      sizeInput.value = savedSize;
      updateSizePreview();
      previewBox.classList.remove('is-loading');
      status.textContent = '';
      status.className = 'settings-save-status';
    }

    function showError(message) {
      previewBox.classList.remove('is-loading');
      status.textContent = message;
      status.className = 'settings-save-status is-error';
    }

    cardToggle.addEventListener('click', () => {
      setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true');
    });
    sizeInput.addEventListener('input', updateSizePreview);

    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (file.type !== 'image/png' || file.size > 2 * 1024 * 1024) {
        fileInput.value = '';
        showError(file.type !== 'image/png' ? 'Elegí una imagen PNG.' : 'El logo debe pesar como máximo 2 MB.');
        return;
      }

      previewBox.classList.add('is-loading');
      status.textContent = 'Preparando vistas previas…';
      status.className = 'settings-save-status';
      const reader = new FileReader();
      reader.addEventListener('error', () => {
        fileInput.value = '';
        preview.src = savedLogoSrc;
        showError('No se pudo leer el logo. Elegí otro archivo.');
      });
      reader.addEventListener('load', () => {
        if (typeof reader.result !== 'string') {
          fileInput.value = '';
          showError('No se pudo leer el logo. Elegí otro archivo.');
          return;
        }
        const probe = new Image();
        probe.addEventListener('load', () => {
          preview.src = reader.result;
          fileName.textContent = file.name;
          previewBox.classList.remove('is-loading');
          status.textContent = 'Vista previa lista. Guardá para aplicar el cambio.';
          status.className = 'settings-save-status';
        });
        probe.addEventListener('error', () => {
          fileInput.value = '';
          preview.src = savedLogoSrc;
          showError('El logo no pudo mostrarse. Elegí otro PNG.');
        });
        probe.src = reader.result;
      });
      reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saveButton.disabled) return;
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando logo del portal…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-logo-portal.php', {
          method: 'POST',
          body: new FormData(form)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el logo del portal.');
        savedLogoSrc = result.logo_url || preview.src;
        if (fileInput.files && fileInput.files[0]) savedFileLabel = fileInput.files[0].name;
        savedSize = String(result.tamano || sizeInput.value);
        sizeInput.value = savedSize;
        updateSizePreview();
        preview.src = savedLogoSrc;
        fileInput.value = '';
        fileName.textContent = savedFileLabel;
        status.textContent = result.mensaje || 'Logo del portal guardado.';
      } catch (error) {
        showError(error.message || 'No se pudo guardar el logo del portal.');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar cambios';
      }
    });

    cancelButton.addEventListener('click', () => {
      resetUnsaved();
      drawerClose.click();
    });
    drawerClose.addEventListener('click', resetUnsaved);
    drawerBackdrop.addEventListener('click', resetUnsaved);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') resetUnsaved();
    });
    updateSizePreview();
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('adminLogoSettingsForm');
    const fileInput = document.getElementById('adminLogoFile');
    const fileName = document.getElementById('adminLogoFileName');
    const preview = document.getElementById('adminLogoPreview');
    const fallback = document.getElementById('adminLogoFallback');
    const faviconPreview = document.getElementById('adminFaviconPreview');
    const previewBox = document.getElementById('adminLogoPreviewBox');
    const sidebarMark = document.getElementById('adminSidebarBrandMark');
    const status = document.getElementById('adminLogoSaveStatus');
    const saveButton = document.getElementById('adminLogoSaveButton');
    const cancelButton = document.getElementById('adminLogoCancel');
    const cardToggle = document.getElementById('adminLogoCardToggle');
    const cardContent = document.getElementById('adminLogoCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    const drawerBackdrop = document.getElementById('settingsDrawerBackdrop');
    if (!form || !fileInput || !fileName || !preview || !fallback || !faviconPreview || !previewBox || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !drawerBackdrop) return;

    let savedLogoSrc = preview.getAttribute('src') || '';
    let savedHasLogo = !preview.hidden;
    let savedFaviconSrc = faviconPreview.src;
    let savedFileLabel = fileName.textContent;

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes del logo del Admin y favicon' : 'Expandir ajustes del logo del Admin y favicon');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    function setLogoPreview(source, hasLogo) {
      preview.hidden = !hasLogo;
      fallback.hidden = hasLogo;
      if (hasLogo) preview.src = source;
    }

    function squarePreview(image) {
      const canvas = document.createElement('canvas');
      canvas.width = 256;
      canvas.height = 256;
      const context = canvas.getContext('2d');
      if (!context) return image.src;
      context.fillStyle = '#0f172a';
      context.fillRect(0, 0, 256, 256);
      const scale = Math.min(208 / image.naturalWidth, 208 / image.naturalHeight);
      const width = Math.max(1, Math.round(image.naturalWidth * scale));
      const height = Math.max(1, Math.round(image.naturalHeight * scale));
      context.drawImage(image, Math.round((256 - width) / 2), Math.round((256 - height) / 2), width, height);
      return canvas.toDataURL('image/png');
    }

    function resetUnsaved() {
      fileInput.value = '';
      fileName.textContent = savedFileLabel;
      setLogoPreview(savedLogoSrc, savedHasLogo);
      faviconPreview.src = savedFaviconSrc;
      previewBox.classList.remove('is-loading');
      status.textContent = '';
      status.className = 'settings-save-status';
    }

    function showError(message) {
      previewBox.classList.remove('is-loading');
      status.textContent = message;
      status.className = 'settings-save-status is-error';
    }

    cardToggle.addEventListener('click', () => {
      setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true');
    });

    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (file.type !== 'image/png' || file.size > 2 * 1024 * 1024) {
        fileInput.value = '';
        showError(file.type !== 'image/png' ? 'Elegí una imagen PNG.' : 'El logo debe pesar como máximo 2 MB.');
        return;
      }

      previewBox.classList.add('is-loading');
      status.textContent = 'Preparando vistas previas…';
      status.className = 'settings-save-status';
      const reader = new FileReader();
      reader.addEventListener('error', () => {
        fileInput.value = '';
        setLogoPreview(savedLogoSrc, savedHasLogo);
        faviconPreview.src = savedFaviconSrc;
        showError('No se pudo leer el logo. Elegí otro archivo.');
      });
      reader.addEventListener('load', () => {
        if (typeof reader.result !== 'string') {
          fileInput.value = '';
          showError('No se pudo leer el logo. Elegí otro archivo.');
          return;
        }
        const probe = new Image();
        probe.addEventListener('load', () => {
          setLogoPreview(reader.result, true);
          faviconPreview.src = squarePreview(probe);
          fileName.textContent = file.name;
          previewBox.classList.remove('is-loading');
          status.textContent = 'Vistas previas listas. Guardá para aplicar el cambio.';
          status.className = 'settings-save-status';
        });
        probe.addEventListener('error', () => {
          fileInput.value = '';
          setLogoPreview(savedLogoSrc, savedHasLogo);
          faviconPreview.src = savedFaviconSrc;
          showError('El logo no pudo mostrarse. Elegí otro PNG.');
        });
        probe.src = reader.result;
      });
      reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saveButton.disabled) return;
      if (!fileInput.files || !fileInput.files[0]) {
        showError('Elegí un logo PNG para guardar.');
        return;
      }

      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando logo del Admin y favicon…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-logo-admin.php', {
          method: 'POST',
          body: new FormData(form)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar la identidad del Admin.');
        savedLogoSrc = result.logo_url || preview.src;
        savedHasLogo = true;
        savedFaviconSrc = result.favicon_url || faviconPreview.src;
        savedFileLabel = fileInput.files[0].name;
        setLogoPreview(savedLogoSrc, true);
        faviconPreview.src = savedFaviconSrc;
        if (sidebarMark) sidebarMark.innerHTML = '<img src="' + savedLogoSrc.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '" alt="Logo del Admin">';
        fileInput.value = '';
        fileName.textContent = savedFileLabel;
        status.textContent = result.mensaje || 'Logo del Admin y favicon guardados.';
      } catch (error) {
        showError(error.message || 'No se pudo guardar la identidad del Admin.');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar identidad';
      }
    });

    cancelButton.addEventListener('click', () => {
      resetUnsaved();
      drawerClose.click();
    });
    drawerClose.addEventListener('click', resetUnsaved);
    drawerBackdrop.addEventListener('click', resetUnsaved);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') resetUnsaved();
    });
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('siteIdentitySettingsForm');
    if (!form) return;
    const input = document.getElementById('siteIdentityName');
    const status = document.getElementById('siteIdentitySaveStatus');
    const saveButton = document.getElementById('siteIdentitySaveButton');
    const cancelButton = document.getElementById('siteIdentityCancel');
    const cardToggle = document.getElementById('siteIdentityCardToggle');
    const cardContent = document.getElementById('siteIdentityCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    const drawerBackdrop = document.getElementById('settingsDrawerBackdrop');
    if (!input || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !drawerBackdrop) return;

    let savedName = input.value;

    function resetUnsaved() {
      input.value = savedName;
      status.textContent = '';
      status.className = 'settings-save-status';
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer identidad del sitio' : 'Expandir identidad del sitio');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    cardToggle.addEventListener('click', () => setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true'));
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const name = input.value.trim().replace(/\s+/g, ' ');
      if (name.length < 2 || name.length > 120) {
        status.textContent = 'Ingresá un nombre de 2 a 120 caracteres.';
        status.className = 'settings-save-status is-error';
        input.focus();
        return;
      }

      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando identidad del sitio…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-identidad-sitio.php', { method: 'POST', body: new FormData(form) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar la identidad del sitio.');
        savedName = result.nombre_sitio || name;
        input.value = savedName;
        const seoForm = document.getElementById('homeSeoSettingsForm');
        if (seoForm) {
          seoForm.dataset.defaultTitle = result.seo_titulo_automatico || seoForm.dataset.defaultTitle;
          seoForm.dataset.defaultDescription = result.seo_descripcion_automatica || seoForm.dataset.defaultDescription;
          const seoTitle = document.getElementById('homeSeoTitle');
          const seoDescription = document.getElementById('homeSeoDescription');
          if (seoTitle && document.getElementById('homeSeoTitleMode')?.value !== '1') seoTitle.value = seoForm.dataset.defaultTitle;
          if (seoDescription && document.getElementById('homeSeoDescriptionMode')?.value !== '1') seoDescription.value = seoForm.dataset.defaultDescription;
          seoTitle?.dispatchEvent(new Event('input', { bubbles: true }));
          seoDescription?.dispatchEvent(new Event('input', { bubbles: true }));
        }
        status.textContent = result.mensaje || 'Identidad del sitio guardada.';
      } catch (error) {
        status.textContent = error.message || 'No se pudo guardar la identidad del sitio.';
        status.className = 'settings-save-status is-error';
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar identidad';
      }
    });

    cancelButton.addEventListener('click', () => { resetUnsaved(); drawerClose.click(); });
    drawerClose.addEventListener('click', resetUnsaved);
    drawerBackdrop.addEventListener('click', resetUnsaved);
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('homeSeoSettingsForm');
    if (!form) return;
    const title = document.getElementById('homeSeoTitle');
    const description = document.getElementById('homeSeoDescription');
    const titleMode = document.getElementById('homeSeoTitleMode');
    const descriptionMode = document.getElementById('homeSeoDescriptionMode');
    const imageAutomatic = document.getElementById('homeSeoImageAutomatic');
    const imageInput = document.getElementById('homeSeoImage');
    const imagePreview = document.getElementById('homeSeoImagePreview');
    const socialImage = document.getElementById('homeSeoSocialImage');
    const imageAutoButton = document.getElementById('homeSeoImageAuto');
    const imageStatus = document.getElementById('homeSeoImageStatus');
    const badge = document.getElementById('homeSeoModeBadge');
    const status = document.getElementById('homeSeoSaveStatus');
    const saveButton = document.getElementById('homeSeoSaveButton');
    const cancelButton = document.getElementById('homeSeoCancel');
    const cardToggle = document.getElementById('homeSeoCardToggle');
    const cardContent = document.getElementById('homeSeoCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    const drawerBackdrop = document.getElementById('settingsDrawerBackdrop');
    if (!title || !description || !titleMode || !descriptionMode || !imageAutomatic || !imageInput || !imagePreview || !socialImage || !imageAutoButton || !imageStatus || !badge || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !drawerBackdrop) return;

    const defaults = {
      get title() { return form.dataset.defaultTitle || ''; },
      get description() { return form.dataset.defaultDescription || ''; },
      image: form.dataset.defaultImage,
    };
    let saved = readState();

    function readState() {
      return {
        title: title.value,
        description: description.value,
        titleMode: titleMode.value,
        descriptionMode: descriptionMode.value,
        image: imagePreview.src,
        imageCustom: !imageAutoButton.hidden,
        imageStatus: imageStatus.textContent,
      };
    }

    function update() {
      document.getElementById('homeSeoTitleCounter').textContent = title.value.length + ' caracteres';
      document.getElementById('homeSeoDescriptionCounter').textContent = description.value.length + ' caracteres';
      document.getElementById('homeSeoSocialTitle').textContent = title.value.trim() || defaults.title;
      document.getElementById('homeSeoSocialDescription').textContent = description.value.trim() || defaults.description;
      document.getElementById('homeSeoGoogleTitle').textContent = title.value.trim() || defaults.title;
      document.getElementById('homeSeoGoogleDescription').textContent = description.value.trim() || defaults.description;
      const custom = titleMode.value === '1' || descriptionMode.value === '1' || imageAutomatic.value !== '1' && !imageAutoButton.hidden;
      badge.textContent = custom ? 'Personalizado' : 'Automático';
      badge.classList.toggle('is-custom', custom);
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes SEO de la página principal' : 'Expandir ajustes SEO de la página principal');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    function resetUnsaved() {
      title.value = saved.title;
      description.value = saved.description;
      titleMode.value = saved.titleMode;
      descriptionMode.value = saved.descriptionMode;
      title.readOnly = saved.titleMode !== '1';
      description.readOnly = saved.descriptionMode !== '1';
      document.querySelector('[data-home-seo-auto="title"]').textContent = saved.titleMode === '1' ? 'Volver a automático' : 'Personalizar';
      document.querySelector('[data-home-seo-auto="description"]').textContent = saved.descriptionMode === '1' ? 'Volver a automático' : 'Personalizar';
      imagePreview.src = saved.image;
      socialImage.src = saved.image;
      imageAutoButton.hidden = !saved.imageCustom;
      imageStatus.textContent = saved.imageStatus;
      imageAutomatic.value = '0';
      imageInput.value = '';
      status.textContent = '';
      status.className = 'settings-save-status';
      update();
    }

    document.querySelectorAll('[data-home-seo-auto]').forEach((button) => {
      button.addEventListener('click', () => {
        const isTitle = button.dataset.homeSeoAuto === 'title';
        const mode = isTitle ? titleMode : descriptionMode;
        const input = isTitle ? title : description;
        const automaticValue = isTitle ? defaults.title : defaults.description;
        const customize = mode.value !== '1';
        mode.value = customize ? '1' : '0';
        input.readOnly = !customize;
        if (!customize) input.value = automaticValue;
        button.textContent = customize ? 'Volver a automático' : 'Personalizar';
        update();
        if (customize) input.focus();
      });
    });

    title.addEventListener('input', update);
    description.addEventListener('input', update);
    cardToggle.addEventListener('click', () => setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true'));

    imageInput.addEventListener('change', () => {
      const file = imageInput.files && imageInput.files[0];
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        imageInput.value = '';
        status.textContent = !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ? 'Elegí una imagen JPG, PNG o WEBP.' : 'La imagen debe pesar como máximo 5 MB.';
        status.className = 'settings-save-status is-error';
        return;
      }
      const reader = new FileReader();
      imageStatus.textContent = 'Preparando vista previa…';
      reader.addEventListener('load', () => {
        if (typeof reader.result !== 'string') return;
        const probe = new Image();
        probe.addEventListener('load', () => {
          imagePreview.src = reader.result;
          socialImage.src = reader.result;
          imageAutomatic.value = '0';
          imageAutoButton.hidden = false;
          imageStatus.textContent = file.name;
          status.textContent = 'Vista previa lista. Guardá para aplicar el cambio.';
          status.className = 'settings-save-status';
          update();
        });
        probe.addEventListener('error', () => {
          imageInput.value = '';
          imageStatus.textContent = saved.imageStatus;
          status.textContent = 'La imagen no pudo mostrarse. Elegí otro archivo.';
          status.className = 'settings-save-status is-error';
        });
        probe.src = reader.result;
      });
      reader.addEventListener('error', () => {
        imageInput.value = '';
        imageStatus.textContent = saved.imageStatus;
        status.textContent = 'No se pudo leer la imagen.';
        status.className = 'settings-save-status is-error';
      });
      reader.readAsDataURL(file);
    });

    imageAutoButton.addEventListener('click', () => {
      imageInput.value = '';
      imageAutomatic.value = '1';
      imagePreview.src = defaults.image;
      socialImage.src = defaults.image;
      imageAutoButton.hidden = true;
      imageStatus.textContent = 'Imagen automática del portal';
      update();
    });

    document.querySelectorAll('[data-home-seo-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('[data-home-seo-tab]').forEach((item) => item.setAttribute('aria-selected', item === tab ? 'true' : 'false'));
        document.querySelectorAll('[data-home-seo-panel]').forEach((panel) => { panel.hidden = panel.dataset.homeSeoPanel !== tab.dataset.homeSeoTab; });
      });
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saveButton.disabled) return;
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando SEO de la página principal…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-seo-portada.php', { method: 'POST', body: new FormData(form) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el SEO de la página principal.');
        title.value = result.titulo || title.value;
        description.value = result.descripcion || description.value;
        imagePreview.src = result.imagen_url || imagePreview.src;
        socialImage.src = imagePreview.src;
        imageAutoButton.hidden = !result.imagen_personalizada;
        imageStatus.textContent = result.imagen_personalizada ? 'Imagen personalizada' : 'Imagen automática del portal';
        imageInput.value = '';
        imageAutomatic.value = '0';
        saved = readState();
        update();
        status.textContent = result.mensaje || 'SEO de la página principal guardado.';
      } catch (error) {
        status.textContent = error.message || 'No se pudo guardar el SEO de la página principal.';
        status.className = 'settings-save-status is-error';
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar SEO';
      }
    });

    cancelButton.addEventListener('click', () => { resetUnsaved(); drawerClose.click(); });
    drawerClose.addEventListener('click', resetUnsaved);
    drawerBackdrop.addEventListener('click', resetUnsaved);
    update();
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('headerCodeSettingsForm');
    if (!form) return;
    const editor = document.getElementById('headerCodeEditor');
    const active = document.getElementById('headerCodeActive');
    const activeLabel = document.getElementById('headerCodeActiveLabel');
    const state = document.getElementById('headerCodeState');
    const counter = document.getElementById('headerCodeCounter');
    const status = document.getElementById('headerCodeSaveStatus');
    const saveButton = document.getElementById('headerCodeSaveButton');
    const cancelButton = document.getElementById('headerCodeCancel');
    const cardToggle = document.getElementById('headerCodeCardToggle');
    const cardContent = document.getElementById('headerCodeCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    const drawerBackdrop = document.getElementById('settingsDrawerBackdrop');
    if (!editor || !active || !activeLabel || !state || !counter || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !drawerBackdrop) return;

    let savedCode = editor.value;
    let savedActive = active.checked;

    function updateState() {
      counter.textContent = editor.value.length + ' caracteres';
      activeLabel.textContent = active.checked ? 'Activado' : 'Desactivado';
      state.textContent = active.checked && editor.value.trim() ? 'Activo' : 'Inactivo';
      state.classList.toggle('is-active', active.checked && editor.value.trim() !== '');
    }

    function resetUnsaved() {
      editor.value = savedCode;
      active.checked = savedActive;
      status.textContent = '';
      status.className = 'settings-save-status';
      updateState();
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes del Código del Header' : 'Expandir ajustes del Código del Header');
      cardContent.hidden = !expanded;
      form.classList.toggle('is-collapsed', !expanded);
    }

    editor.addEventListener('input', updateState);
    active.addEventListener('change', updateState);
    editor.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      event.preventDefault();
      const start = editor.selectionStart;
      const end = editor.selectionEnd;
      editor.setRangeText('  ', start, end, 'end');
      updateState();
    });
    cardToggle.addEventListener('click', () => setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true'));

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saveButton.disabled) return;
      if (active.checked && editor.value.trim() === '') {
        status.textContent = 'Pegá un código antes de activarlo.';
        status.className = 'settings-save-status is-error';
        editor.focus();
        return;
      }
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando Código del Header…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-codigo-header.php', { method: 'POST', body: new FormData(form) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el Código del Header.');
        savedCode = editor.value.trim();
        editor.value = savedCode;
        savedActive = Boolean(result.activo);
        active.checked = savedActive;
        updateState();
        status.textContent = result.mensaje || 'Código del Header guardado.';
      } catch (error) {
        status.textContent = error.message || 'No se pudo guardar el Código del Header.';
        status.className = 'settings-save-status is-error';
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar código';
      }
    });

    cancelButton.addEventListener('click', () => { resetUnsaved(); drawerClose.click(); });
    drawerClose.addEventListener('click', resetUnsaved);
    drawerBackdrop.addEventListener('click', resetUnsaved);
    updateState();
  })();
</script>

<script>
  (function () {
    const quickForm = document.getElementById('maintenanceQuickForm');
    const quickToggle = document.getElementById('maintenanceQuickToggle');
    const quickState = document.getElementById('maintenanceQuickState');
    const settingsForm = document.getElementById('maintenanceSettingsForm');
    const active = document.getElementById('maintenanceActive');
    const activeLabel = document.getElementById('maintenanceActiveLabel');
    const cardState = document.getElementById('maintenanceCardState');
    let persistedState = Boolean(quickToggle ? quickToggle.checked : (active && active.checked));

    function paintState(isActive) {
      if (quickToggle) quickToggle.checked = isActive;
      if (quickState) {
        quickState.textContent = isActive ? 'Activo' : 'Inactivo';
        quickState.classList.toggle('is-active', isActive);
      }
      if (active) active.checked = isActive;
      if (activeLabel) activeLabel.textContent = isActive ? 'Activado' : 'Desactivado';
      if (cardState) {
        cardState.textContent = isActive ? 'Activo' : 'Inactivo';
        cardState.classList.toggle('is-active', isActive);
      }
    }

    if (quickForm && quickToggle && quickState) {
      paintState(persistedState);
      quickToggle.addEventListener('change', async () => {
        const requestedState = quickToggle.checked;
        quickToggle.disabled = true;
        quickState.textContent = 'Guardando…';
        const data = new FormData(quickForm);
        data.set('activo', requestedState ? '1' : '0');
        try {
          const response = await fetch('configuracion-mantenimiento.php', { method: 'POST', body: data });
          const result = await response.json().catch(() => ({}));
          if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo cambiar el estado.');
          persistedState = Boolean(result.activo);
          paintState(persistedState);
          document.dispatchEvent(new CustomEvent('maintenance:state-saved', { detail: { active: persistedState } }));
        } catch (error) {
          paintState(persistedState);
          quickState.textContent = 'Error';
          quickState.title = error.message || 'No se pudo cambiar el estado.';
        } finally {
          quickToggle.disabled = false;
        }
      });
    }

    if (!settingsForm) return;
    const fileInput = document.getElementById('maintenanceLogoFile');
    const fileName = document.getElementById('maintenanceLogoFileName');
    const preview = document.getElementById('maintenanceAdminPreview');
    const previewLogo = document.getElementById('maintenanceLogoPreview');
    const size = document.getElementById('maintenanceLogoSize');
    const sizeValue = document.getElementById('maintenanceLogoSizeValue');
    const message = document.getElementById('maintenanceMessage');
    const messagePreview = document.getElementById('maintenanceMessagePreview');
    const messageCounter = document.getElementById('maintenanceMessageCounter');
    const showLogin = document.getElementById('maintenanceShowLogin');
    const previewUser = document.getElementById('maintenancePreviewUser');
    const status = document.getElementById('maintenanceSaveStatus');
    const saveButton = document.getElementById('maintenanceSaveButton');
    const cancelButton = document.getElementById('maintenanceCancel');
    const cardToggle = document.getElementById('maintenanceCardToggle');
    const cardContent = document.getElementById('maintenanceCardContent');
    const drawerClose = document.getElementById('settingsDrawerClose');
    if (!fileInput || !fileName || !preview || !previewLogo || !size || !sizeValue || !message || !messagePreview || !messageCounter || !showLogin || !previewUser || !status || !saveButton || !cancelButton || !cardToggle || !cardContent || !drawerClose || !active) return;

    let saved = {
      active: active.checked,
      logo: previewLogo.src,
      size: size.value,
      message: message.value,
      showLogin: showLogin.checked,
      fileLabel: fileName.textContent
    };
    document.addEventListener('maintenance:state-saved', (event) => {
      persistedState = Boolean(event.detail && event.detail.active);
      saved.active = persistedState;
    });
    let objectUrl = '';

    function updatePreview() {
      const sizePercent = Math.max(25, Math.min(80, Number(size.value) || 58));
      sizeValue.value = sizePercent + '%';
      sizeValue.textContent = sizePercent + '%';
      preview.style.setProperty('--maintenance-preview-logo-width', sizePercent + '%');
      messagePreview.textContent = message.value.trim() || 'En mantenimiento, ¡volvemos pronto!';
      messageCounter.textContent = message.value.length + '/160';
      previewUser.classList.toggle('is-hidden', !showLogin.checked);
      paintState(active.checked);
    }

    function resetUnsaved() {
      active.checked = saved.active;
      size.value = saved.size;
      message.value = saved.message;
      showLogin.checked = saved.showLogin;
      fileInput.value = '';
      fileName.textContent = saved.fileLabel;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = '';
      previewLogo.src = saved.logo;
      status.textContent = '';
      status.className = 'settings-save-status';
      updatePreview();
    }

    function setCardExpanded(expanded) {
      cardToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      cardToggle.setAttribute('aria-label', expanded ? 'Contraer ajustes de mantenimiento' : 'Expandir ajustes de mantenimiento');
      cardContent.hidden = !expanded;
      settingsForm.classList.toggle('is-collapsed', !expanded);
    }

    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) { resetUnsaved(); return; }
      const valid = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || /\.(?:jpe?g|png|webp)$/i.test(file.name);
      if (!valid || file.size > 3 * 1024 * 1024) {
        fileInput.value = '';
        fileName.textContent = file.size > 3 * 1024 * 1024 ? 'La imagen supera los 3 MB.' : 'Elegí una imagen JPG, PNG o WEBP.';
        return;
      }
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      previewLogo.src = objectUrl;
      fileName.textContent = file.name + ' · vista previa lista';
    });
    size.addEventListener('input', updatePreview);
    message.addEventListener('input', updatePreview);
    active.addEventListener('change', updatePreview);
    showLogin.addEventListener('change', updatePreview);
    cardToggle.addEventListener('click', () => setCardExpanded(cardToggle.getAttribute('aria-expanded') !== 'true'));

    settingsForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!settingsForm.reportValidity() || saveButton.disabled) return;
      saveButton.disabled = true;
      saveButton.textContent = 'Guardando…';
      status.textContent = 'Guardando modo mantenimiento…';
      status.className = 'settings-save-status';
      try {
        const response = await fetch('configuracion-mantenimiento.php', { method: 'POST', body: new FormData(settingsForm) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el modo mantenimiento.');
        active.checked = Boolean(result.activo);
        persistedState = active.checked;
        size.value = String(result.logo_tamano);
        message.value = result.mensaje_publico;
        showLogin.checked = Boolean(result.mostrar_login);
        previewLogo.src = result.logo_url;
        fileInput.value = '';
        fileName.textContent = 'JPG, PNG o WEBP. Máximo 3 MB.';
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = '';
        saved = {
          active: active.checked,
          logo: previewLogo.src,
          size: size.value,
          message: message.value,
          showLogin: showLogin.checked,
          fileLabel: fileName.textContent
        };
        updatePreview();
        status.textContent = result.mensaje || 'Configuración de mantenimiento guardada.';
      } catch (error) {
        status.textContent = error.message || 'No se pudo guardar el modo mantenimiento.';
        status.className = 'settings-save-status is-error';
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar mantenimiento';
      }
    });

    cancelButton.addEventListener('click', () => { resetUnsaved(); drawerClose.click(); });
    drawerClose.addEventListener('click', resetUnsaved);
    updatePreview();
  })();
</script>
</body>
</html>
