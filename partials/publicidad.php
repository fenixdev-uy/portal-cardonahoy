<?php
/**
 * Publicidad provisoria del portal: fuente única para los dos feeds.
 *
 * PC muestra un par de anuncios por pantalla; móvil muestra un anuncio por
 * bloque para no encadenar dos pantallas de publicidad seguidas. Al ser el
 * mismo origen, la secuencia y las piezas no se duplican entre versiones.
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
