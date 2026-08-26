<?php
/**
 * Publicidad provisoria del portal: fuente única para los dos feeds.
 *
 * Reúne las piezas usadas por la portada PC y la experiencia móvil para no
 * duplicar rutas, textos alternativos ni secuencias entre partials.
 */
$paresPublicidad = [
    [
        ['imagen' => 'imagenes/Publicidad-facha.jpg', 'alt' => 'Publicidad de Mariano Silva Facha Pinturas'],
        ['imagen' => 'imagenes/Publicidad-intendencia.jpg', 'alt' => 'Publicidad de la Intendencia de Soriano'],
    ],
    [
        ['imagen' => 'imagenes/Publicidad-Fenix.jpg', 'alt' => 'Publicidad de Fenix'],
        ['imagen' => 'imagenes/Publicidad-Digitales.jpg', 'alt' => 'Publicidad de Digitales'],
    ],
];

// Lista plana en el mismo orden, para el feed móvil (un anuncio por bloque).
$avisosPublicidad = array_merge(...$paresPublicidad);

// Banco provisorio para las filas híbridas de noticia + publicidad en PC.
$filaPublicidadPc = [
    ['imagen' => 'imagenes/Publicidad-intendencia.jpg', 'alt' => 'Publicidad de la Intendencia de Soriano'],
    ['imagen' => 'imagenes/Publicidad-Fenix.jpg', 'alt' => 'Publicidad de Fenix'],
    ['imagen' => 'imagenes/Publicidad-facha.jpg', 'alt' => 'Publicidad de Mariano Silva Facha Pinturas'],
];
