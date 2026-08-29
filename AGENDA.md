# Agenda — Portal de Noticias

Última actualización: **28 de agosto de 2026**.

Este archivo es la **única agenda aplicable a este proyecto** y contiene únicamente trabajo del Portal de Noticias. No consultar ni mezclar agendas externas o pertenecientes a otros proyectos. El estado técnico, las decisiones aprobadas y las validaciones realizadas se documentan en `CONTINUIDAD.md`.

## Prioridad activa

1. **Punto de pausa general aprobado — panel, publicidad y análisis:** conservar sin rediseñar la etapa completa validada el 28 de agosto: Popups administrativos y públicos; métricas de votos, vistas y compartidos; Perfil propio; tablas móviles compactas de Noticias y Anuncios; encabezados móviles con buscador y botón `+`; y las pantallas de Análisis → Vistas y Análisis → Publicaciones con filtros y actualización automática. Al retomar, esperar la próxima indicación concreta del usuario. Todo este bloque posterior al último despliegue permanece acumulado en DEV, sin commit ni publicación en PROD.
2. Revisar el resultado de la prueba manual de Google Analytics en PROD con `https://digitales.uy/subir/testgo.html`: confirmar en **Páginas en tiempo real** que aparezca `/subir/testgo.html` y comparar su latencia con las vistas virtuales `/subir/noticia/...`. La CSP de PROD ya permite Google Analytics/GTM. No generar tráfico automatizado real ni cambiar la navegación lateral mientras la captura de red continúe enviando correctamente título y URL de noticia.
3. Crear la página de **Radio** adaptando y mejorando el código que entregará el usuario, integrada visualmente al portal y sin rehacer de cero el reproductor existente.
4. Crear la página de **Canal de TV** adaptando y mejorando el código que entregará el usuario, incluyendo su reproductor e integración visual con el portal.

## Pendientes posteriores

1. Rediseñar la experiencia de la página individual de la noticia en PC después de cerrar la portada; la versión móvil sigue siendo el baseline aprobado.
2. Confirmar visualmente en el editor autenticado de PROD las cards **SEO** y **Vista Previa**; luego validar una URL real con Rich Results Test, los depuradores de Facebook/WhatsApp y Google Search Console.
3. Resolver la limpieza segura de fotos huérfanas cuando se abandona el formulario sin guardar la noticia.
4. Probar los gestos, la inercia y el rendimiento en un teléfono real, especialmente Safari iOS.

## Mejoras futuras sin etapa activa

1. Evaluar la unificación de `partials/pc-feed.php` y `partials/mobile-feed.php` para evitar marcado duplicado, sin alterar las experiencias aprobadas.
2. Evaluar carga de videos al servidor, más opciones multimedia, arrastre de archivos desde el escritorio y previsualización previa a la subida.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
