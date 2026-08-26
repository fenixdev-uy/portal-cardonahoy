<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$archivo = $argv[1] ?? dirname(__DIR__) . '/servicios.local.json';
if (!is_file($archivo) || !is_readable($archivo)) {
    fwrite(STDERR, "[ERROR] No se puede leer el archivo maestro.\n");
    exit(2);
}

try {
    $config = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] El archivo no contiene JSON valido.\n");
    exit(3);
}

$errores = [];
if (($config['version'] ?? null) !== 2) $errores[] = 'version debe ser 2';
if (isset($config['database'])) $errores[] = 'la seccion legacy database no debe existir';
foreach (['project', 'deployment', 'databases', 'deepseek'] as $seccion) {
    if (!isset($config[$seccion]) || !is_array($config[$seccion])) $errores[] = "falta la seccion $seccion";
}

$entornos = ['development', 'production'];
$requeridos = ['label', 'host', 'port', 'name', 'username', 'password', 'charset'];
foreach ($entornos as $entorno) {
    $db = $config['databases'][$entorno] ?? null;
    if (!is_array($db)) {
        $errores[] = "falta databases.$entorno";
        continue;
    }
    foreach ($requeridos as $campo) {
        if (!array_key_exists($campo, $db) || (is_string($db[$campo]) && trim($db[$campo]) === '')) {
            $errores[] = "databases.$entorno.$campo esta vacio";
        }
    }
    if (isset($db['port']) && (!is_int($db['port']) || $db['port'] < 1 || $db['port'] > 65535)) {
        $errores[] = "databases.$entorno.port no es valido";
    }
}

$destino = $config['deployment']['database_environment'] ?? '';
if (!in_array($destino, $entornos, true)) {
    $errores[] = 'deployment.database_environment debe ser development o production';
}

$permisos = fileperms($archivo);
if ($permisos !== false && (($permisos & 0777) !== 0600) && str_ends_with(basename($archivo), '.local.json')) {
    $errores[] = 'el archivo privado debe tener permisos 600';
}

if ($errores !== []) {
    foreach ($errores as $error) fwrite(STDERR, "[ERROR] $error\n");
    exit(1);
}

echo "[OK] Esquema de servicios version 2.\n";
echo "[OK] Entorno de base para despliegue: $destino.\n";
foreach ($entornos as $entorno) {
    $db = $config['databases'][$entorno];
    $huella = substr(hash('sha256', implode('|', [$db['host'], $db['port'], $db['name'], $db['username']])), 0, 12);
    echo "[OK] $entorno configurado; huella $huella.\n";
}

$dev = $config['databases']['development'];
$prod = $config['databases']['production'];
$misma = $dev['host'] === $prod['host'] && $dev['port'] === $prod['port'] && $dev['name'] === $prod['name'] && $dev['username'] === $prod['username'];
if ($misma) {
    fwrite(STDERR, "[AVISO] DEV y PROD apuntan al mismo destino logico; revisarlo antes de migrar.\n");
}
