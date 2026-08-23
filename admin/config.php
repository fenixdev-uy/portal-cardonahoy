<?php
/**
 * Cargador de configuracion privada.
 *
 * config.local.php contiene las credenciales reales, queda fuera del control de
 * versiones y Apache tiene prohibido entregarlo como archivo publico.
 */

$configLocal = __DIR__ . '/config.local.php';
if (!is_file($configLocal)) {
    http_response_code(500);
    exit('Falta la configuracion privada del portal.');
}

require_once $configLocal;

