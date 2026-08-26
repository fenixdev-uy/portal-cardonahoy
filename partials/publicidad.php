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

// Banco provisorio para las filas híbridas de dos noticias + un aviso en PC.
$filaPublicidadPc = [
    [
        'nombre' => 'Intendencia de Soriano',
        'imagen' => 'imagenes/Publicidad-intendencia.jpg',
        'alt' => 'Publicidad de la Intendencia de Soriano',
        'facebook_url' => 'https://www.facebook.com/',
        'instagram_url' => 'https://www.instagram.com/',
        'whatsapp_url' => 'https://www.whatsapp.com/',
        'sitio_web_url' => 'https://www.soriano.gub.uy/',
    ],
    [
        'nombre' => 'Fenix',
        'imagen' => 'imagenes/Publicidad-Fenix.jpg',
        'alt' => 'Publicidad de Fenix',
        'facebook_url' => 'https://www.facebook.com/',
        'instagram_url' => 'https://www.instagram.com/',
        'whatsapp_url' => 'https://wa.me/59898375424',
        'sitio_web_url' => 'https://fenixlab.uno/',
    ],
    [
        'nombre' => 'Mariano Silva Facha Pinturas',
        'imagen' => 'imagenes/Publicidad-facha.jpg',
        'alt' => 'Publicidad de Mariano Silva Facha Pinturas',
        'facebook_url' => 'https://www.facebook.com/',
        'instagram_url' => 'https://www.instagram.com/',
        'whatsapp_url' => 'https://wa.me/59891369668',
        'sitio_web_url' => 'https://example.com/',
    ],
];
