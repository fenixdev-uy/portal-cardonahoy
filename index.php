<?php
/**
 * Landing - Front.
 * Los feeds de noticias de PC y de móvil se renderizan desde la base de datos
 * a partir de las mismas dos consultas. (El slider del home sigue estático.)
 */

require_once __DIR__ . '/admin/includes/funciones.php';
require_once __DIR__ . '/admin/includes/votos.php';

$pdo = db();

// Última noticia creada = primera en verse (más reciente primero).
// Los contadores de votos viajan en la misma consulta: son columnas de
// noticias, así que mostrarlos no cuesta ninguna consulta extra.
$noticias = $pdo->query(
    'SELECT n.id, n.titulo, n.slug, n.descripcion,
            n.youtube, n.youtube_2, n.youtube_3,
            n.audio_1, n.audio_2, n.audio_3, n.created_at,
            n.me_gusta, n.no_me_gusta,
            c.nombre AS categoria_nombre,
            u.nombre AS autor_nombre
       FROM noticias n
       LEFT JOIN categorias c ON c.id = n.categoria_id
       LEFT JOIN usuarios u ON u.id = n.usuario_id
      ORDER BY n.created_at DESC, n.id DESC'
)->fetchAll();

// Todas las galerias se cargan en una sola consulta para evitar una consulta
// adicional por cada noticia.
$fotosPorNoticia = [];
foreach ($pdo->query('SELECT id, noticia_id, ruta, posicion FROM noticias_fotos ORDER BY noticia_id, posicion, id') as $foto) {
    $fotosPorNoticia[(int) $foto['noticia_id']][] = $foto;
}

// Votos ya emitidos por este visitante, en una sola consulta, para marcar los
// botones. No se crea la cookie al mirar: se emite recién al votar.
$misVotos = votos_del_visitante($pdo, visitante_id());
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Landing - Slider</title>
  <link rel="stylesheet" href="assets/css/portal.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/portal.css') ?>">
</head>
<body>
  <header class="hero" aria-label="Banner principal">
    <nav class="navbar" aria-label="Menú principal">
      <button class="hamburger" id="hamburger" aria-label="Abrir menú" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <a href="#" class="logo" aria-label="Logo - Inicio">
        <img src="imagenes/Logo2027v2.png" alt="Logo 2027" />
      </a>

      <a href="#" class="logo-right" aria-label="Radio Sur">
        <img src="imagenes/Logo2027-radiosur.png" alt="Radio Sur" />
      </a>
    </nav>

    <div class="slider" id="slider">
      <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1920&q=80');">
        <div class="slide-content">
          <span class="slide-tag">Tecnología</span>
          <h2 class="slide-title">La inteligencia artificial transforma la industria en América Latina</h2>
          <p class="slide-subtitle">Las empresas de la región aceleran la adopción de nuevas tecnologías para mejorar su productividad y ser más competitivas a nivel global.</p>
        </div>
      </div>

      <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1920&q=80');">
        <div class="slide-content">
          <span class="slide-tag">Economía</span>
          <h2 class="slide-title">Crecimiento económico abre nuevas oportunidades para los emprendedores</h2>
          <p class="slide-subtitle">Expertos destacan un panorama favorable para el desarrollo de pequeños y medianos negocios en toda la región.</p>
        </div>
      </div>

      <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1920&q=80');">
        <div class="slide-content">
          <span class="slide-tag">Deportes</span>
          <h2 class="slide-title">El deporte nacional vive una temporada histórica</h2>
          <p class="slide-subtitle">Los equipos locales protagonizan un año récord con triunfos que celebran miles de aficionados en todo el país.</p>
        </div>
      </div>
    </div>

    <div class="dots" id="dots" role="tablist" aria-label="Indicadores de imagen"></div>

    <!-- Flecha de scroll: indica que hay contenido deslizante/scroll -->
    <a href="#contenido" class="scroll-down" aria-label="Desplazarse hacia abajo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </a>
  </header>

  <div id="contenido">

  <!-- Feed de noticias estilo red social (solo móvil) -->
<?php include __DIR__ . '/partials/mobile-feed.php'; ?>

  <!-- Feed de noticias estilo artículo sticky (solo PC) -->
<?php include __DIR__ . '/partials/pc-feed.php'; ?>

  <!-- Nota completa en panel inferior, disponible solo en el feed móvil -->
<?php include __DIR__ . '/partials/nota-completa.php'; ?>

  <!-- Visor ampliado de galerías, compartido por ambos feeds -->
<?php include __DIR__ . '/partials/lightbox.php'; ?>

  </div>

  <!-- Menú desplegable a pantalla completa -->
  <div class="menu-overlay" id="menuOverlay" aria-hidden="true">
    <button class="close-btn" id="closeMenu" aria-label="Cerrar menú">
      <span></span>
      <span></span>
    </button>

    <a href="#" class="menu-logo" aria-label="Logo - Inicio">
      <img src="imagenes/Logo2027v2.png" alt="Logo 2027" />
    </a>

    <a href="#" class="menu-radio" aria-label="Escuchar Radio Sur">
      <img src="imagenes/Logo2027-radiosur.png" alt="Escuchar Radio Sur" />
    </a>

    <nav class="menu-nav" aria-label="Menú de navegación">
      <a href="#" class="menu-link">Noticias</a>
      <a href="#" class="menu-link">Videos</a>
      <a href="#" class="menu-link">Contactos</a>
    </nav>
  </div>

  <script src="assets/js/portal.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/portal.js') ?>" data-vote-url="<?= e(url_portal('votar.php')) ?>"></script>
</body>
</html>
