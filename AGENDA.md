# Agenda — Portal de Noticias

Última actualización: **26 de agosto de 2026**.

Este archivo contiene únicamente el trabajo pendiente elegido para próximas etapas. El estado técnico, las decisiones aprobadas y las validaciones realizadas se documentan en `CONTINUIDAD.md`.

## Prioridad activa

1. **Rediseñar la experiencia de la página individual de la noticia en PC.**
   - Revisar primero la composición actual del permalink en escritorio y acordar la nueva propuesta visual.
   - Modificar únicamente la presentación de escritorio.
   - Preservar íntegramente la experiencia móvil aprobada y desplegada.
   - No desplegar la propuesta nueva hasta que el usuario la revise.

## Pendientes posteriores

1. Confirmar visualmente en el editor autenticado de PROD las cards **SEO** y **Vista Previa**; luego validar una URL real con Rich Results Test, los depuradores de Facebook/WhatsApp y Google Search Console.
2. Alimentar el slider del hero desde la base de datos; hoy conserva tres noticias estáticas de ejemplo.
3. Resolver la limpieza segura de fotos huérfanas cuando se abandona el formulario sin guardar la noticia.
4. Evaluar el formato publicitario provisorio antes de diseñar una gestión dinámica desde el panel.
5. Probar los gestos, la inercia y el rendimiento en un teléfono real, especialmente Safari iOS.
6. Incorporar un favicon para eliminar la petición 404 conocida.

## Mejoras futuras sin etapa activa

1. Evaluar la unificación de `partials/pc-feed.php` y `partials/mobile-feed.php` para evitar marcado duplicado, sin alterar las experiencias aprobadas.
2. Evaluar carga de videos al servidor, más opciones multimedia, arrastre de archivos desde el escritorio y previsualización previa a la subida.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
