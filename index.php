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
    'SELECT n.id, n.titulo, n.descripcion, n.youtube, n.created_at,
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
  <style>
    :root {
      --dot-inactive: rgba(255, 255, 255, 0.45);
      --dot-active: #ffffff;
      --dot-size: 10px;
      --dot-active-width: 34px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      min-height: 100%;
      overflow-x: hidden;
      scroll-behavior: smooth;
    }

    body {
      min-height: 100%;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }

    /* Header a pantalla completa en PC, tablet y móvil */
    .hero {
      position: relative;
      width: 100%;
      height: 100vh;
      /* Mejor soporte en móviles (barras del navegador dinámicas) */
      height: 100svh;
      overflow: hidden;
      background: #111;
    }

    /* ===== Menú transparente (barra superior) ===== */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 20;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 24px;
      background: transparent;
      transition: background-color 0.3s ease;
    }

    .navbar.scrolled {
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }

    /* Botón hamburguesa: 3 líneas, la primera más larga */
    .hamburger {
      background: none;
      border: none;
      cursor: pointer;
      padding: 8px;
      display: flex;
      flex-direction: column;
      gap: 5px;
      filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
    }

    .hamburger span {
      display: block;
      height: 3px;
      border-radius: 3px;
      background: #fff;
      transition: transform 0.3s ease, width 0.3s ease, opacity 0.3s ease;
    }

    .hamburger span:nth-child(1) { width: 30px; }
    .hamburger span:nth-child(2) { width: 22px; }
    .hamburger span:nth-child(3) { width: 22px; }

    /* Estado abierto: se transforma en una X */
    .hamburger.active span:nth-child(1) {
      transform: translateY(8px) rotate(45deg);
    }
    .hamburger.active span:nth-child(2) {
      opacity: 0;
    }
    .hamburger.active span:nth-child(3) {
      transform: translateY(-8px) rotate(-45deg);
    }

    /* Logo centrado y transparente */
    .logo {
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      display: inline-flex;
      align-items: center;
      filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
    }

    .logo img {
      display: block;
      width: 180px;
      height: auto;
    }

    /* Logo secundario (derecha), más chico que el central */
    .logo-right {
      display: inline-flex;
      align-items: center;
      filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
    }

    .logo-right img {
      display: block;
      width: 120px;
      height: auto;
    }

    @media (max-width: 1024px) {
      .logo img {
        width: 150px;
      }
      .logo-right img {
        width: 100px;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 14px 16px;
      }
      .logo img {
        width: 115px;
      }
      .logo-right img {
        width: 80px;
      }
    }

    /* Contenedor de los slides */
    .slider {
      position: absolute;
      inset: 0;
    }

    .slide {
      position: absolute;
      inset: 0;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      opacity: 0;
      transition: opacity 1.2s ease-in-out;
      will-change: opacity;
    }

    .slide.active {
      opacity: 1;
    }

    /* Degradado para legibilidad del texto sobre la imagen */
    .slide::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.78) 0%,
        rgba(0, 0, 0, 0.35) 35%,
        rgba(0, 0, 0, 0) 65%
      );
      pointer-events: none;
    }

    /* Contenido de cada noticia (título y subtítulo) */
    .slide-content {
      position: absolute;
      left: 0;
      bottom: 0;
      padding: 0 24px 110px;
      max-width: 900px;
      z-index: 1;
    }

    .slide-tag {
      display: inline-block;
      font-size: clamp(0.75rem, 1vw, 0.9rem);
      font-weight: 700;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: #5EEAD4;
      margin-bottom: 14px;
    }

    .slide-title {
      margin: 0 0 16px;
      max-width: 800px;
      font-size: clamp(1.8rem, 4.2vw, 3.6rem);
      font-weight: 800;
      line-height: 1.12;
      color: #fff;
      text-shadow: 0 2px 14px rgba(0, 0, 0, 0.45);
    }

    .slide-subtitle {
      margin: 0;
      max-width: 620px;
      font-size: clamp(1rem, 1.6vw, 1.25rem);
      line-height: 1.5;
      color: rgba(255, 255, 255, 0.88);
      text-shadow: 0 1px 8px rgba(0, 0, 0, 0.45);
    }

    @media (max-width: 768px) {
      .slide-content {
        padding: 0 18px 76px;
      }
      .slide-tag {
        margin-bottom: 10px;
      }
      .slide-title {
        margin-bottom: 12px;
      }
    }

    /* Indicadores tipo píldora, abajo a la izquierda, dentro del slider */
    .dots {
      position: absolute;
      left: 24px;
      bottom: 24px;
      z-index: 10;
      display: flex;
      gap: 8px;
    }

    .dot {
      width: var(--dot-size);
      height: var(--dot-size);
      border: none;
      border-radius: 999px;
      background: var(--dot-inactive);
      cursor: pointer;
      padding: 0;
      transition: width 0.4s ease, background-color 0.4s ease;
    }

    .dot.active {
      width: var(--dot-active-width);
      background: var(--dot-active);
    }

    @media (max-width: 768px) {
      .dots {
        left: 18px;
        bottom: 18px;
      }
    }

    /* Flecha de scroll (indicador centrado abajo) */
    .scroll-down {
      position: absolute;
      bottom: 28px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 46px;
      height: 46px;
      color: #fff;
      border: 2px solid rgba(255, 255, 255, 0.65);
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.06);
      filter: drop-shadow(0 1px 4px rgba(0, 0, 0, 0.5));
      cursor: pointer;
      transition: border-color 0.3s ease, background-color 0.3s ease;
      animation: scroll-float 2s ease-in-out infinite;
    }

    .scroll-down:hover {
      border-color: #fff;
      background: rgba(255, 255, 255, 0.18);
    }

    .scroll-down svg {
      width: 24px;
      height: 24px;
    }

    @keyframes scroll-float {
      0%, 100% { transform: translate(-50%, 0); }
      50% { transform: translate(-50%, 8px); }
    }

    @media (max-width: 768px) {
      .scroll-down {
        bottom: 18px;
        width: 40px;
        height: 40px;
      }
      .scroll-down svg {
        width: 20px;
        height: 20px;
      }
    }

    /* ===== Menú desplegable a pantalla completa ===== */
    .menu-overlay {
      position: fixed;
      inset: 0;
      z-index: 50;
      background: rgba(0, 0, 0, 0.93); /* negro con transparencia mínima */
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 32px;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.4s ease, visibility 0.4s ease;
    }

    .menu-overlay.open {
      opacity: 1;
      visibility: visible;
    }

    /* Botón cerrar minimalista (arriba a la derecha) */
    .close-btn {
      position: absolute;
      top: 24px;
      right: 24px;
      width: 48px;
      height: 48px;
      background: none;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .close-btn span {
      position: absolute;
      width: 30px;
      height: 2px;
      background: #fff;
      border-radius: 2px;
      transition: background-color 0.3s ease;
    }

    .close-btn span:nth-child(1) { transform: rotate(45deg); }
    .close-btn span:nth-child(2) { transform: rotate(-45deg); }
    .close-btn:hover span { background: #5EEAD4; }

    /* Logo del menú (arriba centrado) */
    .menu-logo {
      position: absolute;
      top: 24px;
      left: 50%;
      transform: translateX(-50%);
      display: inline-flex;
      align-items: center;
      filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
    }

    .menu-logo img {
      display: block;
      width: 180px;
      height: auto;
    }

    @media (max-width: 1024px) {
      .menu-logo img {
        width: 150px;
      }
    }

    /* Logo "Escuchar la radio" (encima de las opciones) */
    .menu-radio {
      display: inline-flex;
      align-items: center;
      filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
      opacity: 0;
      transform: translateY(16px);
      transition: opacity 0.5s ease, transform 0.5s ease;
    }

    .menu-radio img {
      display: block;
      width: 150px;
      height: auto;
    }

    .menu-overlay.open .menu-radio {
      opacity: 1;
      transform: translateY(0);
      transition-delay: 0.05s;
    }

    .menu-overlay.open .menu-radio:hover {
      transform: translateY(0) scale(1.05);
    }

    @media (max-width: 1024px) {
      .menu-radio img {
        width: 120px;
      }
    }

    /* Enlaces del menú: textos grandes centrados */
    .menu-nav {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 28px;
    }

    .menu-link {
      font-size: clamp(2.5rem, 8vw, 5rem);
      font-weight: 700;
      color: #fff;
      text-decoration: none;
      letter-spacing: 2px;
      text-transform: uppercase;
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 0.5s ease, transform 0.5s ease, color 0.3s ease;
    }

    .menu-link:hover {
      color: #5EEAD4;
    }

    .menu-overlay.open .menu-link {
      opacity: 1;
      transform: translateY(0);
    }

    .menu-overlay.open .menu-link:nth-child(1) { transition-delay: 0.1s; }
    .menu-overlay.open .menu-link:nth-child(2) { transition-delay: 0.2s; }
    .menu-overlay.open .menu-link:nth-child(3) { transition-delay: 0.3s; }

    @media (max-width: 768px) {
      .menu-overlay {
        gap: 24px;
      }
      .close-btn {
        top: 16px;
        right: 16px;
      }
      .menu-logo {
        top: 16px;
      }
      .menu-logo img {
        width: 115px;
      }
      .menu-radio img {
        width: 100px;
      }
    }

    /* ===== Feed de noticias estilo red social (solo móvil) ===== */
    .news-feed {
      display: none;
    }

    .feed-ad-item {
      display: none;
    }

    @media (max-width: 768px) {
      .news-feed {
        display: block;
        background: #fff;
      }

      /* Foto (o galería) a pantalla completa con el título encima */
      .feed-media {
        position: relative;
        height: 100svh;
        overflow: hidden;
        background: #0f172a;
      }

      .feed-media-empty {
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
      }

      .feed-media > img,
      .feed-frame img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      /* Galería con deslizamiento nativo: sin temporizador, la mueve el dedo */
      .feed-track {
        display: flex;
        height: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
      }

      .feed-track::-webkit-scrollbar {
        display: none;
      }

      .feed-frame {
        flex: 0 0 100%;
        height: 100%;
        scroll-snap-align: center;
      }

      /* Degradado de legibilidad; no debe interceptar el deslizamiento */
      .feed-media::after {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 1;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.62), transparent 45%);
        pointer-events: none;
      }

      /* El margen inferior deja libre la franja de los controles. Sin galería
         no hay puntos ni lupa, así que el título puede bajar más. */
      .feed-media-content {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 2;
        margin: 0 20px 28px;
        pointer-events: none;
      }

      /* Con galería, la lupa ocupa de 16px a 60px del borde: el título tiene
         que arrancar por encima de los 60px o se le monta encima. */
      .feed-gallery .feed-media-content {
        margin-bottom: 68px;
      }

      .feed-media-tag {
        display: block;
        margin-bottom: 10px;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #5EEAD4;
      }

      .feed-media-title {
        margin: 0;
        font-size: 1.7rem;
        font-weight: 800;
        line-height: 1.15;
        color: #fff;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.45);
      }

      .feed-dots {
        position: absolute;
        left: 20px;
        bottom: 20px;
        z-index: 3;
        display: flex;
        gap: 6px;
      }

      .feed-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.45);
        transition: width 0.3s ease, background-color 0.3s ease;
      }

      .feed-dot.active {
        width: 22px;
        background: #fff;
      }

      .feed-gallery-expand {
        position: absolute;
        right: 18px;
        bottom: 16px;
        z-index: 3;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        padding: 0;
        color: #111;
        border: 1px solid rgba(255, 255, 255, 0.7);
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.24);
        cursor: pointer;
        backdrop-filter: blur(8px);
      }

      .feed-gallery-expand svg {
        width: 21px;
        height: 21px;
      }

      .feed-text {
        background: #fff;
        color: #1a1a1a;
        padding: 28px 20px 40px;
      }

      .feed-video {
        margin-top: 20px;
      }

      .feed-video iframe {
        width: 100%;
        aspect-ratio: 16 / 9;
        border: 0;
        border-radius: 12px;
        background: #0f172a;
      }

      /* Publicidad: un anuncio por bloque, como una tarjeta más del feed */
      .feed-ad-item {
        display: block;
        background: #fff;
        padding: 8px 20px 40px;
      }

      .feed-ad-panel {
        margin: 0;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.22);
      }

      .feed-ad-panel img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
      }

      .feed-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        font-size: 0.85rem;
        color: #555;
      }

      .feed-meta svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        color: #111;
      }

      .feed-meta .feed-author {
        font-weight: 600;
        color: #111;
      }

      .feed-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 24px;
        padding-top: 18px;
        border-top: 1px solid #eee;
      }

      /* Separación amplia a propósito: el voto no se puede deshacer, así que
         conviene reducir los toques por error entre botones vecinos. */
      .feed-vote {
        display: flex;
        align-items: center;
        gap: 22px;
      }

      .feed-share {
        display: flex;
        align-items: center;
        gap: 14px;
      }

    }

    /* ===== Botones de voto y compartir (compartidos PC + móvil) ===== */
    .vote-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      font-size: 0.85rem;
      font-weight: 600;
      color: #111;
      transition: opacity 0.2s ease, color 0.2s ease;
    }

    .vote-btn svg {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }

    .vote-btn:hover {
      opacity: 0.6;
    }

    /* El voto es definitivo: una vez emitido los dos botones quedan quietos.
       El votado se resalta; el otro se atenúa pero sigue mostrando su número. */
    .vote-btn:disabled {
      cursor: default;
    }

    .vote-btn:disabled:hover {
      opacity: 1;
    }

    .vote-btn:disabled:not(.voted) {
      opacity: 0.45;
    }

    .vote-btn.voted {
      color: #0f766e;
    }

    .vote-btn.voted svg {
      fill: rgba(15, 118, 110, 0.14);
    }

    /* tabular-nums evita que el botón salte de ancho al pasar de 9 a 10. */
    .vote-count {
      font-size: 0.78rem;
      font-weight: 600;
      color: #666;
      font-variant-numeric: tabular-nums;
    }

    .vote-count:empty {
      display: none;
    }

    .vote-btn.voted .vote-count {
      color: #0f766e;
    }

    .vote-btn.enviando {
      opacity: 0.5;
      cursor: progress;
    }

    .share-btn {
      display: inline-flex;
      align-items: center;
      color: #111;
      transition: opacity 0.2s ease;
    }

    .share-btn svg {
      width: 20px;
      height: 20px;
    }

    .share-btn:hover {
      opacity: 0.6;
    }

    /* ===== Visor ampliado de galerías (compartido PC + móvil) ===== */
    .gallery-lightbox {
      display: none;
    }

    .gallery-lightbox.open {
      position: fixed;
      inset: 0;
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: rgba(0, 0, 0, 0.97);
      overscroll-behavior: contain;
    }

    .pc-lightbox-stage {
      position: absolute;
      inset: 34px 96px 64px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .pc-lightbox-stage img {
      display: block;
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      user-select: none;
      cursor: zoom-in;
      transition: transform 0.16s ease;
      will-change: transform;
    }

    /* Durante la pinza el zoom sigue al dedo: sin transición intermedia */
    .gallery-lightbox.pinching .pc-lightbox-stage img {
      transition: none;
    }

    .pc-lightbox-close,
    .pc-lightbox-arrow {
      position: absolute;
      z-index: 2;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.28);
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      cursor: pointer;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }

    .pc-lightbox-close:hover,
    .pc-lightbox-arrow:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: scale(1.05);
    }

    .pc-lightbox-close {
      top: 24px;
      right: 26px;
      width: 48px;
      height: 48px;
    }

    .pc-lightbox-close svg {
      width: 24px;
      height: 24px;
    }

    .pc-lightbox-arrow {
      top: 50%;
      width: 54px;
      height: 54px;
      transform: translateY(-50%);
    }

    .pc-lightbox-arrow:hover {
      transform: translateY(-50%) scale(1.05);
    }

    .pc-lightbox-arrow svg {
      width: 30px;
      height: 30px;
    }

    .pc-lightbox-prev { left: 24px; }
    .pc-lightbox-next { right: 24px; }

    .pc-lightbox-counter,
    .pc-lightbox-zoom {
      position: absolute;
      bottom: 23px;
      z-index: 2;
      color: rgba(255, 255, 255, 0.82);
      font-size: 0.82rem;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .pc-lightbox-counter { left: 28px; }
    .pc-lightbox-zoom { left: 50%; transform: translateX(-50%); }

    /* En móvil se navega deslizando: sin flechas y con márgenes ajustados */
    @media (max-width: 768px) {
      .pc-lightbox-stage {
        inset: 72px 0 64px;
        touch-action: none;
      }

      .pc-lightbox-arrow {
        display: none;
      }

      .pc-lightbox-close {
        top: 16px;
        right: 16px;
        width: 44px;
        height: 44px;
      }

      .pc-lightbox-counter {
        left: 20px;
        bottom: 20px;
      }

      .pc-lightbox-zoom {
        left: auto;
        right: 20px;
        bottom: 20px;
        transform: none;
      }
    }

    /* ===== Contenido HTML enriquecido (descripción de la noticia) =====
       Base compartida por el feed móvil y el de PC. El bloque de PC más abajo
       solo reajusta tamaños y márgenes; el resto vive acá una sola vez. */
    .rich-text h1,
    .rich-text h2,
    .rich-text h3 {
      line-height: 1.25;
      font-weight: 700;
      color: #111;
      margin: 1.2em 0 0.5em;
    }

    .rich-text h1 { font-size: 1.35rem; }
    .rich-text h2 { font-size: 1.2rem; }
    .rich-text h3 { font-size: 1.05rem; }

    .rich-text p {
      margin: 0 0 16px;
      font-size: 1.05rem;
      line-height: 1.6;
      color: #333;
    }

    .rich-text ul,
    .rich-text ol {
      padding-left: 1.5rem;
      margin-bottom: 16px;
      color: #333;
    }

    .rich-text ul { list-style: disc; }
    .rich-text ol { list-style: decimal; }

    .rich-text li {
      margin-bottom: 6px;
      font-size: 1rem;
      line-height: 1.6;
    }

    .rich-text blockquote {
      border-left: 3px solid #5EEAD4;
      padding-left: 14px;
      margin-bottom: 16px;
      color: #555;
      font-style: italic;
    }

    .rich-text code {
      background: #f1f5f9;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.85em;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    .rich-text pre {
      background: #0f172a;
      color: #e2e8f0;
      padding: 14px;
      border-radius: 8px;
      overflow-x: auto;
      margin-bottom: 16px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 0.85rem;
    }

    .rich-text pre code {
      background: none;
      padding: 0;
      color: inherit;
    }

    .rich-text mark {
      background: #fef08a;
      color: inherit;
      border-radius: 2px;
      padding: 0 2px;
    }

    .rich-text sub,
    .rich-text sup {
      font-size: 0.75em;
    }

    .rich-text hr {
      border: none;
      border-top: 2px solid #eee;
      margin: 1.2em 0;
    }

    .rich-text img {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      margin-bottom: 16px;
    }

    .rich-text a {
      color: #0ea5e9;
      text-decoration: underline;
    }

    /* ===== Feed estilo artículo sticky (solo PC) ===== */
    .pc-feed {
      display: none;
    }

    .pc-ad-item {
      display: none;
    }

    @media (min-width: 769px) {
      html {
        scroll-snap-type: y mandatory;
      }

      .hero {
        scroll-snap-align: start;
      }

      .pc-feed {
        display: block;
        background: #fff;
      }

      .pc-item {
        position: relative;
        display: grid;
        grid-template-columns: 1fr 1fr;
        align-items: start;
        scroll-snap-align: start;
      }

      /* Pantalla provisoria de anuncios: una pieza por cada mitad del feed PC. */
      .pc-ad-item {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        width: 100%;
        height: 100vh;
        padding: 64px;
        align-items: center;
        overflow: hidden;
        background: #fff;
        scroll-snap-align: start;
      }

      .pc-ad-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        justify-self: center;
        width: 100%;
        max-width: calc(100vh - 128px);
        min-width: 0;
        height: auto;
        aspect-ratio: 1 / 1;
        margin: 0;
        overflow: hidden;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 0;
        box-shadow: 0 22px 40px rgba(15, 23, 42, 0.26);
      }

      .pc-ad-panel img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
      }

      .pc-media {
        position: sticky;
        top: 0;
        height: 100vh;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }

      /* Mini slider de fotos dentro de la imagen sticky */
      .pc-media.pc-slider {
        overflow: hidden;
      }

      .pc-slide {
        position: absolute;
        inset: 0;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0;
        transition: opacity 1s ease-in-out;
      }

      .pc-slide.active {
        opacity: 1;
      }

      /* Puntos indicadores del mini slider */
      .pc-dots {
        position: absolute;
        left: 20px;
        bottom: 20px;
        z-index: 2;
        display: flex;
        gap: 6px;
      }

      .pc-dot {
        width: 8px;
        height: 8px;
        border: none;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.45);
        cursor: pointer;
        padding: 0;
        transition: width 0.3s ease, background-color 0.3s ease;
      }

      .pc-dot.active {
        width: 24px;
        background: #fff;
      }

      /* Ampliación de galerías del feed PC */
      .pc-gallery-expand {
        position: absolute;
        right: 22px;
        bottom: 22px;
        z-index: 4;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        padding: 0;
        color: #111;
        border: 1px solid rgba(255, 255, 255, 0.7);
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.24);
        cursor: pointer;
        backdrop-filter: blur(8px);
        transition: transform 0.2s ease, background-color 0.2s ease;
      }

      .pc-gallery-expand:hover {
        transform: scale(1.06);
        background: #fff;
      }

      .pc-gallery-expand svg {
        width: 22px;
        height: 22px;
      }

      /* Flecha de scroll para noticias con texto largo */
      .pc-scroll-hint {
        position: absolute;
        left: 75%; /* centro de la columna derecha (grid 50/50) */
        bottom: 24px;
        transform: translateX(-50%);
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        color: #111;
        border: 2px solid rgba(0, 0, 0, 0.3);
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.85);
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
        animation: pc-hint-float 2s ease-in-out infinite;
      }

      .pc-scroll-hint.show {
        opacity: 1;
        visibility: visible;
      }

      .pc-scroll-hint svg {
        width: 22px;
        height: 22px;
      }

      @keyframes pc-hint-float {
        0%, 100% { transform: translate(-50%, 0); }
        50% { transform: translate(-50%, 6px); }
      }

      .pc-content {
        background: #fff;
        padding: 90px 70px;
        height: 100vh;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #ccc transparent;
      }

      .pc-content::-webkit-scrollbar {
        width: 6px;
      }

      .pc-content::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 999px;
      }

      .pc-content::-webkit-scrollbar-track {
        background: transparent;
      }

      .pc-tag {
        display: inline-block;
        margin-bottom: 16px;
        font-size: 0.85rem;
        font-weight: 800;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #0f766e;
      }

      .pc-title {
        margin: 0 0 28px;
        font-size: clamp(2.8rem, 5vw, 4.5rem);
        font-weight: 900;
        line-height: 1.05;
        color: #111;
      }

      .pc-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 28px;
        font-size: 0.9rem;
        color: #555;
      }

      .pc-meta svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        color: #111;
      }

      .pc-meta .pc-author {
        font-weight: 600;
        color: #111;
      }

      .pc-content p {
        margin: 0 0 18px;
        font-size: 1.08rem;
        line-height: 1.7;
        color: #333;
      }

      /* Ajustes del contenido enriquecido en PC (la base vive en .rich-text) */
      .pc-content h1 { font-size: 1.6rem; }
      .pc-content h2 { font-size: 1.35rem; }
      .pc-content h3 { font-size: 1.15rem; }

      .pc-content ul,
      .pc-content ol {
        padding-left: 1.6rem;
        margin-bottom: 18px;
      }

      .pc-content li {
        font-size: 1.05rem;
      }

      .pc-content blockquote,
      .pc-content pre,
      .pc-content img {
        margin-bottom: 18px;
      }

      /* Fondo neutro para noticias sin foto */
      .pc-media.pc-media-empty {
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
      }

      /* Video de YouTube embebido */
      .pc-video {
        margin-top: 22px;
      }

      .pc-video iframe {
        width: 100%;
        aspect-ratio: 16 / 9;
        border: 0;
        border-radius: 12px;
        background: #0f172a;
      }

      .pc-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
      }

      .pc-vote {
        display: flex;
        align-items: center;
        gap: 18px;
      }

      .pc-share {
        display: flex;
        align-items: center;
        gap: 16px;
      }

      /* Ajuste de tamaño en PC (la base vive en el bloque compartido) */
      .vote-btn {
        font-size: 0.9rem;
      }

      .vote-count {
        font-size: 0.82rem;
      }
    }
  </style>
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

  <script>
    (function () {
      const slider = document.getElementById('slider');
      const slides = Array.from(slider.querySelectorAll('.slide'));
      const dotsContainer = document.getElementById('dots');

      const INTERVAL = 5000; // ms entre imágenes
      let current = 0;
      let timer = null;

      // Genera los puntos (píldoras) dinámicamente según la cantidad de slides
      slides.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.className = 'dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Ir a la imagen ' + (i + 1));
        dotsContainer.appendChild(dot);
      });

      const dots = Array.from(dotsContainer.querySelectorAll('.dot'));

      function goTo(index) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');

        current = index;

        slides[current].classList.add('active');
        dots[current].classList.add('active');
      }

      function next() {
        goTo((current + 1) % slides.length);
      }

      function start() {
        stop();
        timer = setInterval(next, INTERVAL);
      }

      function stop() {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
      }

      // Al hacer clic en un punto, cambia la imagen y reinicia el autoplay
      dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
          goTo(i);
          start();
        });
      });

      start();
    })();

    // ===== Menú desplegable =====
    const hamburger = document.getElementById('hamburger');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenuBtn = document.getElementById('closeMenu');
    const menuLinks = Array.from(document.querySelectorAll('.menu-link'));

    function openMenu() {
      hamburger.classList.add('active');
      hamburger.setAttribute('aria-expanded', 'true');
      menuOverlay.classList.add('open');
      menuOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      hamburger.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      menuOverlay.classList.remove('open');
      menuOverlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', () => {
      menuOverlay.classList.contains('open') ? closeMenu() : openMenu();
    });

    closeMenuBtn.addEventListener('click', closeMenu);

    // Cierra el menú al hacer clic en un enlace
    menuLinks.forEach((link) => {
      link.addEventListener('click', closeMenu);
    });

    // Cierra el menú con la tecla Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && menuOverlay.classList.contains('open')) {
        closeMenu();
      }
    });

    // Fondo del menú al hacer scroll (para mantenerlo legible sobre el feed blanco)
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 10);
    });

    // Mini sliders de fotos en el feed PC (auto-rotación + pausa al pasar el mouse + puntos)
    document.querySelectorAll('.pc-slider').forEach((slider) => {
      const slides = Array.from(slider.querySelectorAll('.pc-slide'));
      const dotsContainer = slider.querySelector('.pc-dots');
      if (slides.length < 2) return;

      const dots = slides.map((_, i) => {
        const dot = document.createElement('button');
        dot.className = 'pc-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Ir a la foto ' + (i + 1));
        if (dotsContainer) dotsContainer.appendChild(dot);
        return dot;
      });

      let idx = 0;
      let timer = null;

      function show(i) {
        slides[idx].classList.remove('active');
        if (dots[idx]) dots[idx].classList.remove('active');

        idx = i;

        slides[idx].classList.add('active');
        if (dots[idx]) dots[idx].classList.add('active');
      }

      function next() {
        show((idx + 1) % slides.length);
      }

      function start() {
        stop();
        timer = setInterval(next, 3000);
      }

      function stop() {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
      }

      dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
          show(i);
          start();
        });
      });

      slider.addEventListener('mouseenter', stop);
      slider.addEventListener('mouseleave', start);

      start();
    });

    // Votos: un solo listener delegado cubre los dos feeds, así que no hace
    // falta cablear nada por noticia. El voto es definitivo: al confirmarse, los
    // dos botones de esa noticia quedan bloqueados.
    document.addEventListener('click', async (event) => {
      const boton = event.target.closest('.vote-btn[data-noticia-id]');
      if (!boton || boton.disabled || boton.classList.contains('enviando')) return;

      const grupo = boton.closest('[data-noticia-id]:not(.vote-btn)');
      const botones = grupo ? Array.from(grupo.querySelectorAll('.vote-btn')) : [boton];

      botones.forEach((b) => b.classList.add('enviando'));

      try {
        const cuerpo = new URLSearchParams({
          noticia_id: boton.dataset.noticiaId,
          valor: boton.dataset.voto,
        });
        const respuesta = await fetch('votar.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: cuerpo,
          credentials: 'same-origin',
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || 'Error al votar');

        // El servidor es la fuente de verdad, incluso si ya se había votado
        // antes desde otra pestaña: los números y el voto vienen de ahí.
        botones.forEach((b) => {
          const valor = Number(b.dataset.voto);
          const conteo = valor === 1 ? datos.me_gusta : datos.no_me_gusta;
          const marca = b.querySelector('.vote-count');
          if (marca) marca.textContent = conteo > 0 ? String(conteo) : '';
          const votado = valor === datos.mi_voto;
          b.classList.toggle('voted', votado);
          b.setAttribute('aria-pressed', votado ? 'true' : 'false');
          b.disabled = true;
        });
      } catch (error) {
        // Sin cambios optimistas que revertir: los números solo se tocan con la
        // respuesta del servidor, así que un fallo deja todo como estaba.
        console.error(error);
      } finally {
        botones.forEach((b) => b.classList.remove('enviando'));
      }
    });

    // Galerías del feed móvil: puntos sincronizados con el deslizamiento nativo.
    document.querySelectorAll('.feed-gallery').forEach((gallery) => {
      const track = gallery.querySelector('.feed-track');
      const dotsContainer = gallery.querySelector('.feed-dots');
      const frames = Array.from(gallery.querySelectorAll('.feed-frame'));
      if (!track || !dotsContainer || frames.length < 2) return;

      const dots = frames.map((_, i) => {
        const dot = document.createElement('span');
        dot.className = 'feed-dot' + (i === 0 ? ' active' : '');
        dotsContainer.appendChild(dot);
        return dot;
      });

      // El observador marca el punto de la foto que ocupa el centro del track.
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const index = frames.indexOf(entry.target);
          if (index < 0) return;
          dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
          gallery.dataset.activeIndex = String(index);
        });
      }, { root: track, threshold: 0.6 });

      frames.forEach((frame) => observer.observe(frame));
    });

    // Visor ampliado de galerías: rueda del mouse en PC, pinza y swipe en móvil.
    const galleryLightbox = document.getElementById('pcGalleryLightbox');
    const lightboxImage = document.getElementById('pcLightboxImage');
    const lightboxStage = document.getElementById('pcLightboxStage');
    const lightboxClose = document.getElementById('pcLightboxClose');
    const lightboxPrev = document.getElementById('pcLightboxPrev');
    const lightboxNext = document.getElementById('pcLightboxNext');
    const lightboxCounter = document.getElementById('pcLightboxCounter');
    const lightboxZoom = document.getElementById('pcLightboxZoom');

    if (galleryLightbox && lightboxImage && lightboxStage) {
      let galleryImages = [];
      let galleryIndex = 0;
      let zoomLevel = 1;
      let previousBodyOverflow = '';
      let lastFocusedElement = null;

      const esTactil = () => window.matchMedia('(max-width: 768px)').matches;

      function updateZoom(nextZoom, originX = 50, originY = 50) {
        zoomLevel = Math.min(4, Math.max(1, nextZoom));
        lightboxImage.style.transformOrigin = zoomLevel === 1 ? '50% 50%' : `${originX}% ${originY}%`;
        lightboxImage.style.transform = `scale(${zoomLevel})`;
        lightboxImage.style.cursor = zoomLevel > 1 ? 'zoom-out' : 'zoom-in';
        const ayuda = esTactil() ? 'Pinza para ampliar' : 'Rueda del mouse para ampliar';
        lightboxZoom.textContent = `${ayuda} · ${Math.round(zoomLevel * 100)}%`;
      }

      function showGalleryImage(index) {
        if (!galleryImages.length) return;
        galleryIndex = (index + galleryImages.length) % galleryImages.length;
        updateZoom(1);
        lightboxImage.src = galleryImages[galleryIndex];
        lightboxImage.alt = `Imagen ${galleryIndex + 1} de ${galleryImages.length}`;
        lightboxCounter.textContent = `${galleryIndex + 1} / ${galleryImages.length}`;
      }

      function openGallery(images, startIndex, trigger) {
        galleryImages = images.filter(Boolean);
        if (!galleryImages.length) return;

        lastFocusedElement = trigger;
        previousBodyOverflow = document.body.style.overflow;
        galleryLightbox.classList.add('open');
        galleryLightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        showGalleryImage(startIndex > 0 ? startIndex : 0);
        lightboxClose.focus();
      }

      function closeGallery() {
        punteros.clear();
        distanciaInicial = 0;
        galleryLightbox.classList.remove('open', 'pinching');
        galleryLightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousBodyOverflow;
        updateZoom(1);
        lightboxImage.removeAttribute('src');
        if (lastFocusedElement) lastFocusedElement.focus();
      }

      // Disparador del feed PC: mini slider con la foto activa marcada por clase.
      document.querySelectorAll('.pc-gallery-expand').forEach((button) => {
        button.addEventListener('click', () => {
          const slider = button.closest('.pc-slider');
          if (!slider) return;
          const slides = Array.from(slider.querySelectorAll('.pc-slide'));
          openGallery(
            slides.map((slide) => slide.dataset.fullSrc),
            slides.findIndex((slide) => slide.classList.contains('active')),
            button
          );
        });
      });

      // Disparador del feed móvil: carrusel con la posición en data-active-index.
      document.querySelectorAll('.feed-gallery-expand').forEach((button) => {
        button.addEventListener('click', () => {
          const gallery = button.closest('.feed-gallery');
          if (!gallery) return;
          const imagenes = Array.from(gallery.querySelectorAll('.feed-frame img'));
          openGallery(
            imagenes.map((img) => img.currentSrc || img.src),
            Number(gallery.dataset.activeIndex || 0),
            button
          );
        });
      });

      lightboxClose.addEventListener('click', closeGallery);
      lightboxPrev.addEventListener('click', () => showGalleryImage(galleryIndex - 1));
      lightboxNext.addEventListener('click', () => showGalleryImage(galleryIndex + 1));

      lightboxStage.addEventListener('wheel', (event) => {
        if (!galleryLightbox.classList.contains('open')) return;
        event.preventDefault();
        const bounds = lightboxStage.getBoundingClientRect();
        const originX = ((event.clientX - bounds.left) / bounds.width) * 100;
        const originY = ((event.clientY - bounds.top) / bounds.height) * 100;
        updateZoom(zoomLevel + (event.deltaY < 0 ? 0.25 : -0.25), originX, originY);
      }, { passive: false });

      // Gestos táctiles: pinza con dos dedos para el zoom, deslizamiento con uno
      // para cambiar de foto (o hacia abajo para cerrar) cuando no hay zoom.
      const punteros = new Map();
      let distanciaInicial = 0;
      let zoomInicial = 1;

      const distanciaEntrePunteros = () => {
        const [a, b] = Array.from(punteros.values());
        return Math.hypot(a.x - b.x, a.y - b.y);
      };

      lightboxStage.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || !galleryLightbox.classList.contains('open')) return;
        punteros.set(event.pointerId, { x: event.clientX, y: event.clientY, inicioX: event.clientX, inicioY: event.clientY });
        if (punteros.size === 2) {
          distanciaInicial = distanciaEntrePunteros();
          zoomInicial = zoomLevel;
          galleryLightbox.classList.add('pinching');
        }
      });

      lightboxStage.addEventListener('pointermove', (event) => {
        const puntero = punteros.get(event.pointerId);
        if (!puntero) return;
        puntero.x = event.clientX;
        puntero.y = event.clientY;

        if (punteros.size !== 2 || distanciaInicial <= 0) return;
        const bounds = lightboxStage.getBoundingClientRect();
        const [a, b] = Array.from(punteros.values());
        const originX = (((a.x + b.x) / 2 - bounds.left) / bounds.width) * 100;
        const originY = (((a.y + b.y) / 2 - bounds.top) / bounds.height) * 100;
        updateZoom(zoomInicial * (distanciaEntrePunteros() / distanciaInicial), originX, originY);
      });

      function finPuntero(event) {
        const puntero = punteros.get(event.pointerId);
        if (!puntero) return;
        const eraGestoSimple = punteros.size === 1;
        punteros.delete(event.pointerId);

        if (punteros.size < 2) {
          distanciaInicial = 0;
          galleryLightbox.classList.remove('pinching');
        }

        // Con zoom activo el dedo no navega: se reserva para la pinza.
        if (!eraGestoSimple || zoomLevel > 1) return;

        const desplazamientoX = puntero.x - puntero.inicioX;
        const desplazamientoY = puntero.y - puntero.inicioY;
        if (Math.abs(desplazamientoX) > 50 && Math.abs(desplazamientoX) > Math.abs(desplazamientoY)) {
          showGalleryImage(galleryIndex + (desplazamientoX < 0 ? 1 : -1));
        } else if (desplazamientoY > 90) {
          closeGallery();
        }
      }

      lightboxStage.addEventListener('pointerup', finPuntero);
      lightboxStage.addEventListener('pointercancel', finPuntero);

      galleryLightbox.addEventListener('click', (event) => {
        if (event.target === galleryLightbox) closeGallery();
      });

      document.addEventListener('keydown', (event) => {
        if (!galleryLightbox.classList.contains('open')) return;
        if (event.key === 'Escape') closeGallery();
        if (event.key === 'ArrowLeft') showGalleryImage(galleryIndex - 1);
        if (event.key === 'ArrowRight') showGalleryImage(galleryIndex + 1);
      });
    }

    // Flecha de scroll en noticias PC con texto desbordado
    document.querySelectorAll('.pc-item').forEach((item) => {
      const content = item.querySelector('.pc-content');
      const hint = item.querySelector('.pc-scroll-hint');
      if (!content || !hint) return;

      function updateHint() {
        const hasOverflow = content.scrollHeight > content.clientHeight;
        const atBottom = content.scrollTop + content.clientHeight >= content.scrollHeight - 5;
        hint.classList.toggle('show', hasOverflow && !atBottom);
      }

      content.addEventListener('scroll', updateHint);
      window.addEventListener('resize', updateHint);

      hint.addEventListener('click', () => {
        content.scrollBy({ top: content.clientHeight * 0.9, behavior: 'smooth' });
      });

      updateHint();
    });
  </script>
</body>
</html>
