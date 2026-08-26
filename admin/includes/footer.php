</main>
  </div>
</div>

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
    const previewLogo = document.getElementById('watermarkPreviewLogo');
    const status = document.getElementById('watermarkSaveStatus');
    const saveButton = document.getElementById('watermarkSaveButton');
    let savedLogoSrc = previewLogo.src;
    let savedOpacity = opacity.value;
    let savedFileLabel = fileName.textContent;

    function updatePreview() {
      const value = Math.max(5, Math.min(100, Number(opacity.value) || 15));
      opacityValue.value = value + '%';
      opacityValue.textContent = value + '%';
      previewLogo.style.opacity = String(value / 100);
    }

    function resetUnsaved() {
      fileInput.value = '';
      fileName.textContent = savedFileLabel;
      previewLogo.src = savedLogoSrc;
      opacity.value = savedOpacity;
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
        savedFileLabel = fileInput.files && fileInput.files[0]
          ? fileInput.files[0].name
          : savedFileLabel;
        previewLogo.src = savedLogoSrc;
        opacity.value = savedOpacity;
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
</body>
</html>
