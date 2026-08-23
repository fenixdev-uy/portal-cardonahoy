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
  })();
</script>
</body>
</html>