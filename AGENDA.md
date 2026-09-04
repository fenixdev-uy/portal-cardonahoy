# Agenda — Portal Cardona Hoy

Última actualización: **4 de septiembre de 2026**.

Este archivo es la **única agenda aplicable a `portal-cardonahoy`**. La aplicación nace del baseline aprobado `5ec3e42`, pero no comparte configuración privada, base de datos, uploads, despliegue ni remoto con RS Medios.

## Prioridad activa

1. Validar con una sesión administradora de Cardona Hoy PROD la selección de hasta diez noticias en Portada.
2. Validar con una sesión administradora de Cardona Hoy PROD las tasas mensuales y la copia PNG ya publicadas.
3. Configurar identidad, SEO, administradores y contenido definitivos del cliente sobre el despliegue inicial.

## Implementación local actual — 4 de septiembre de 2026

- Portada admite ahora como máximo 10 noticias destacadas en DEV: el panel acepta la décima, rechaza la undécima y la consulta pública limita el slider a diez.
- El cupo se mantiene centralizado en `PORTADA_NOTICIAS_LIMITE`; el texto del formulario y el fallback del panel consumen la misma constante para evitar valores desincronizados.
- La validación transaccional 10/11 fue reversible y la portada pasó QA renderizada en escritorio `1440×900` y móvil `390×844`, incluidos diez indicadores sin overflow ni errores de consola.
- Cardona Hoy PROD recibió los tres archivos funcionales mediante despliegue incremental verificado; no requirió migración ni modificación de datos.
- **Análisis → Vistas** distribuye ahora su visual principal en 70/30 y presenta a la derecha la tasa mensual `compartidos ÷ vistas × 100` desde enero hasta el mes corriente del año actual, con referencia histórica mensual del 5%. Esta serie anual tiene consultas propias y no cambia con Desde/Hasta; en móvil se apila debajo del gráfico.
- Junto a **Ver tabla** se agregó un botón de cámara que genera un PNG nítido de `3200×1960` con el período filtrado, los tres indicadores, el modo Área/Columnas, las series visibles, el gráfico principal y los KPI mensuales independientes. Intenta copiarlo al portapapeles para pegarlo directamente en WhatsApp y, si el navegador bloquea esa API, descarga el mismo PNG automáticamente.
- Cardona Hoy PROD recibió los cuatro archivos del bloque de análisis mediante despliegue incremental con respaldo, reemplazo temporal y verificación SHA-256. No requirió migración ni modificación de datos.
- El bloque funcional completo quedó consolidado en `cc7737e` (`feat: ampliar portada y análisis de vistas`) para su publicación en el GitHub exclusivo de Cardona Hoy.

## Implementación local actual — 3 de septiembre de 2026

- Las vistas públicas de una noticia muestran debajo de votos y Compartir la herramienta **Copiar link de noticia** únicamente a sesiones cuyo rol posee `noticias.editar`; copia la URL canónica y confirma **Link copiado**. Cardona Hoy PROD ya recibió la mejora y queda pendiente la validación autenticada del usuario.
- La mejora quedó consolidada en el checkpoint funcional `837ee57`; no requirió migración ni modificación de datos.
- El límite anterior de Portada era de 5 noticias destacadas; este registro conserva el estado que fue publicado en PROD el 3 de septiembre.
- Cardona Hoy PROD tenía 9 noticias seleccionadas al momento del preflight. Se conservaron las cinco más recientes —IDs 85, 84, 83, 82 y 81— y se desmarcaron exactamente 4 antiguas, sin eliminar ni editar contenido.
- La tabla administrativa de Noticias muestra en PROD la fecha y hora exactas desde el `created_at` ya existente, con formato `DD/MM/AAAA · HH:MM hs.`; no requirió migración y conserva el orden cronológico actual.
- El despliegue incremental de cinco archivos no tuvo conflictos. El respaldo de código está en `.deploy/respaldos/2026-09-03_portada-maximo-hora/` y el respaldo completo de PROD, de 1.038.339 bytes, en `.deploy/respaldos-db/2026-09-03-portada-maximo-prod/`.
- Quedó implementado el procesamiento automático de la imagen SEO por noticia: genera derivados JPEG de `1200 × 630` y `1200 × 675`, recortados, orientados y optimizados a un máximo de 400 KB sin modificar la fuente.
- El mismo código está trasladado a Portal Base, RS Medios y Cardona Hoy. El usuario validó la carga en DEV y autorizó publicar exclusivamente en Cardona Hoy PROD para evaluarlo con noticias reales.
- El despliegue incremental de los cinco archivos funcionales quedó verificado por FTPS y HTTPS. Las versiones anteriores están en `.deploy/respaldos/2026-09-03_seo-imagen-prod/`; no hubo conflictos remotos, migración ni borrados.
- No se modificó el estado de Mantenimiento. Al finalizar, la portada respondió HTTP 200, el login 200, el formulario sin sesión 302 y las configuraciones privadas 403.

## Cierre aprobado — 3 de septiembre de 2026

- El usuario aprobó el despliegue inicial en Mantenimiento y el resultado de las correcciones posteriores. Mantenimiento continúa activo hasta autorización expresa para abrir el Portal.
- El usuario aprobó el lote común completo, incluida la persistencia por portal de las instrucciones de **Crear con IA**, y autorizó su checkpoint local en los tres repositorios.
- DeepSeek quedó operativo en DEV mediante el runtime privado `admin/servicios.runtime.local.json`; no forma parte de Git y no debe reemplazarse por una configuración pública.
- El checkpoint funcional aprobado `4c3f01c` fue publicado en `origin/main`. Al retomar, leer `CONTINUIDAD.md` y ejecutar `git status --short --branch` antes de decidir una publicación nueva.

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
