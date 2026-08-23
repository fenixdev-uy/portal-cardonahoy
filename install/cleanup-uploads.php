<?php
/** Limpieza manual de imagenes huerfanas. Solo CLI. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../admin/includes/funciones.php';
$horas = max(1, (int) ($argv[1] ?? 48));
$eliminadas = limpiar_imagenes_huerfanas_antiguas($horas, 10000);
echo "Imagenes huerfanas eliminadas: {$eliminadas}\n";

