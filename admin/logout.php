<?php
require_once __DIR__ . '/includes/funciones.php';

exigir_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Metodo no permitido.');
}
verificar_csrf();
limpiar_subidas_no_usadas_de_sesion();
cerrar_sesion();
redirigir('login.php');
