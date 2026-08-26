<?php
require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('publicidad.gestionar');

$titulo = 'Popups';
$active = 'publicidad-popups';
require __DIR__ . '/includes/header.php';
?>
<div class="users-page-heading">
  <h1>Popups</h1>
  <p>Este espacio contendrá la configuración de avisos emergentes y sus condiciones de visualización.</p>
</div>

<section class="users-panel">
  <div class="empty">La administración de popups se incorporará en una próxima etapa.</div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
