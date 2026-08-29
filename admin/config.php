<?php
/**
 * Cargador de configuracion privada.
 *
 * config.local.php contiene las credenciales reales, queda fuera del control de
 * versiones y Apache tiene prohibido entregarlo como archivo publico.
 */

$configInstancia = __DIR__ . '/config.instance.php';
if (!is_file($configInstancia)) {
    http_response_code(500);
    exit('Falta la identidad de la instalacion del portal.');
}

require_once $configInstancia;

if (!defined('PORTAL_INSTANCE_ID')
    || !preg_match('/^[a-z][a-z0-9_]{1,31}$/', (string) PORTAL_INSTANCE_ID)) {
    http_response_code(500);
    exit('La identidad de la instalacion del portal no es valida.');
}

function portal_cookie_name(string $scope): string
{
    $scope = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $scope) ?? '');
    return 'portal_' . trim($scope, '_') . '_' . PORTAL_INSTANCE_ID;
}

function portal_cookie_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $adminPos = strpos($script, '/admin/');
    $base = $adminPos !== false ? substr($script, 0, $adminPos) : dirname($script);
    $base = rtrim(str_replace('\\', '/', $base), '/');
    return $base === '' || $base === '.' ? '/' : $base . '/';
}

$configLocal = __DIR__ . '/config.local.php';
if (!is_file($configLocal)) {
    http_response_code(500);
    exit('Falta la configuracion privada del portal.');
}

require_once $configLocal;
