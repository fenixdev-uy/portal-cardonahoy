# Agenda — Portal Cardona Hoy

Última actualización: **3 de septiembre de 2026**.

Este archivo es la **única agenda aplicable a `portal-cardonahoy`**. La aplicación nace del baseline aprobado `5ec3e42`, pero no comparte configuración privada, base de datos, uploads, despliegue ni remoto con RS Medios.

## Prioridad activa

1. Evaluar en Cardona Hoy PROD el procesamiento automático de imágenes SEO y el nuevo máximo de cinco noticias en Portada.
2. Configurar identidad, SEO, administradores y contenido definitivos del cliente sobre el despliegue inicial.

## Implementación local actual — 3 de septiembre de 2026

- Portada admite como máximo 5 noticias destacadas: el backend bloquea la sexta tanto desde la tabla como desde el formulario, muestra un mensaje claro y la consulta pública limita el slider a cinco.
- Cardona Hoy PROD tenía 9 noticias seleccionadas al momento del preflight. Se conservaron las cinco más recientes —IDs 85, 84, 83, 82 y 81— y se desmarcaron exactamente 4 antiguas, sin eliminar ni editar contenido.
- La tabla administrativa de Noticias muestra en PROD la fecha y hora exactas desde el `created_at` ya existente, con formato `DD/MM/AAAA · HH:MM hs.`; no requirió migración y conserva el orden cronológico actual.
- El despliegue incremental de cinco archivos no tuvo conflictos. El respaldo de código está en `.deploy/respaldos/2026-09-03_portada-maximo-hora/` y el respaldo completo de PROD, de 1.038.339 bytes, en `.deploy/respaldos-db/2026-09-03-portada-maximo-prod/`.
- Quedó implementado el procesamiento automático de la imagen SEO por noticia: genera derivados JPEG de `1200 × 630` y `1200 × 675`, recortados, orientados y optimizados a un máximo de 400 KB sin modificar la fuente.
- El mismo código está trasladado a Portal Base, RS Medios y Cardona Hoy. El usuario validó la carga en DEV y autorizó publicar exclusivamente en Cardona Hoy PROD para evaluarlo con noticias reales.
- El despliegue incremental de los cinco archivos funcionales quedó verificado por FTPS y HTTPS. Las versiones anteriores están en `.deploy/respaldos/2026-09-03_seo-imagen-prod/`; no hubo conflictos remotos, migración, borrados, commit ni push.
- No se modificó el estado de Mantenimiento. Al finalizar, la portada respondió HTTP 200, el login 200, el formulario sin sesión 302 y las configuraciones privadas 403.

## Cierre aprobado — 3 de septiembre de 2026

- El usuario aprobó el despliegue inicial en Mantenimiento y el resultado de las correcciones posteriores. Mantenimiento continúa activo hasta autorización expresa para abrir el Portal.
- El usuario aprobó el lote común completo, incluida la persistencia por portal de las instrucciones de **Crear con IA**, y autorizó su checkpoint local en los tres repositorios.
- DeepSeek quedó operativo en DEV mediante el runtime privado `admin/servicios.runtime.local.json`; no forma parte de Git y no debe reemplazarse por una configuración pública.
- No se hizo push, nuevo despliegue ni migración en este cierre. Al retomar, leer `CONTINUIDAD.md` y ejecutar `git status --short --branch` antes de decidir una publicación.

## Pendientes posteriores

1. Desactivar Mantenimiento únicamente cuando el usuario autorice abrir públicamente el Portal.
2. Promover el despliegue inicial a confirmado después de la validación autenticada del usuario.

## Mejoras futuras sin etapa activa

1. Mantener el baseline funcional heredado y aplicar aquí solamente las particularidades aprobadas para Cardona Hoy.
2. Trasladar mejoras comunes desde `portal-base` mediante commits pequeños y revisados.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
