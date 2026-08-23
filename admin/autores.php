<?php
// Compatibilidad con marcadores antiguos del panel.
require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('usuarios.gestionar');
redirigir('usuarios.php');

