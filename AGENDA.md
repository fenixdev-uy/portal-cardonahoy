# Agenda — Portal de Noticias

Última actualización: **26 de agosto de 2026**.

Este archivo contiene únicamente el trabajo pendiente elegido para próximas etapas. El estado técnico, las decisiones aprobadas y las validaciones realizadas se documentan en `CONTINUIDAD.md`.

## Prioridad activa

1. **Continuar dando forma a la nueva portada de noticias en PC.**
   - Estado: **primera versión implementada y validada en DEV; pendiente de revisión visual del usuario**.
   - Se conserva sin cambios el header con slider y, debajo, todas las noticias aparecen como tarjetas editoriales: tres columnas desde `1100px` y dos entre `769px` y `1099px`.
   - Cada pareja completa de noticias forma una fila híbrida con un anuncio. Tanto el anunciante como su ubicación —izquierda, centro o derecha— varían de forma controlada entre filas; si el total es impar, la última noticia queda sola.
   - El pie de cada anuncio muestra «Publicidad» y cuatro accesos negros: Facebook, Instagram, WhatsApp y sitio web. Las URLs actuales son de demostración y ya están modeladas por anunciante para conectarlas posteriormente a la base.
   - El clic normal abre la noticia completa en un drawer recto desde la derecha al `40%`: galería con autoplay/fullscreen, contenido, medios, anuncios, votos y compartir. Los clics modificados conservan el permalink para abrirlo en otra pestaña.
   - Debajo del primer anuncio, `A−` y `A+` permiten ajustar el cuerpo editorial entre 90% y 140% tanto en PC como en móvil.
   - Ajustar solamente la grilla y la anatomía de sus tarjetas según la próxima devolución del usuario.
   - Preservar íntegramente la experiencia móvil aprobada hasta `768px`.
   - No desplegar esta propuesta nueva hasta que el usuario la apruebe expresamente.

## Pendientes posteriores

1. Conectar las cards publicitarias de la portada con los registros vigentes de **Publicidad → Anuncios**, respetando la distribución PC aprobada y sin publicar anuncios cuya fecha de vencimiento haya comenzado.
2. Implementar la gestión real de **Publicidad → Popups**; su pantalla continúa como placeholder.
3. Rediseñar la experiencia de la página individual de la noticia en PC después de cerrar la portada; la versión móvil sigue siendo el baseline aprobado.
4. Confirmar visualmente en el editor autenticado de PROD las cards **SEO** y **Vista Previa**; luego validar una URL real con Rich Results Test, los depuradores de Facebook/WhatsApp y Google Search Console.
5. Alimentar el slider del hero desde la base de datos; hoy conserva tres noticias estáticas de ejemplo.
6. Resolver la limpieza segura de fotos huérfanas cuando se abandona el formulario sin guardar la noticia.
7. Probar los gestos, la inercia y el rendimiento en un teléfono real, especialmente Safari iOS.
8. Incorporar un favicon para eliminar la petición 404 conocida.

## Mejoras futuras sin etapa activa

1. Evaluar la unificación de `partials/pc-feed.php` y `partials/mobile-feed.php` para evitar marcado duplicado, sin alterar las experiencias aprobadas.
2. Evaluar carga de videos al servidor, más opciones multimedia, arrastre de archivos desde el escritorio y previsualización previa a la subida.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
