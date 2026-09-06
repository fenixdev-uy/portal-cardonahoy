# Agenda — Portal Cardona Hoy

Última actualización: **6 de septiembre de 2026**.

Este archivo es la **única agenda aplicable a `portal-cardonahoy`**. La aplicación nace del baseline aprobado `5ec3e42`, pero no comparte configuración privada, base de datos, uploads, despliegue ni remoto con RS Medios.

## Prioridad activa

1. Validar con una sesión editorial real de Cardona Hoy PROD el nuevo **Título ideal**, los destellos y una generación DeepSeek completa desde URL y desde contenido pegado.
2. Validar con dos navegadores o dispositivos reales que el segundo login cierre la primera sesión del mismo usuario y conserve la nueva.
3. Validar con una sesión administradora de Cardona Hoy PROD la selección de hasta diez noticias en Portada.
4. Validar con una sesión administradora de Cardona Hoy PROD las tasas mensuales y la copia PNG ya publicadas.
5. Configurar SEO, administradores y contenido definitivos del cliente sobre el despliegue inicial.

## Cierre Git actual — 6 de septiembre de 2026

- La experiencia de lectura y las acciones de noticia quedaron mejoradas en portada, nota completa móvil y página individual: acceso **Ver nota completa** desde el slider, votos junto a los controles de tamaño de texto y acciones de Facebook/WhatsApp más claras.
- El lote común está consolidado y publicado en `origin/main` mediante `a8ab659` (`feat: mejorar lectura y acciones de noticias`).
- Antes de esta actualización documental, la rama `main` estaba limpia y sincronizada con `origin/main`. El checkpoint Git no permite afirmar por sí solo que este lote del 6 de septiembre esté en Cardona Hoy PROD; esa correspondencia debe verificarse antes de considerarlo desplegado.

## Implementación aprobada — 5 de septiembre de 2026

- La nueva card **Identidad del sitio** administra `nombre_sitio`; Cardona Hoy DEV quedó en `CardonaHoy`. Portada, noticia, Open Graph, `WebSite` y `NewsArticle.publisher` consumen esa fuente única, eliminando la identidad heredada de Radio Sur.
- La migración DEV fue respaldada, aplicada dos veces de forma idempotente y validada en escritorio/móvil. Cardona Hoy PROD recibió después la misma identidad mediante una migración respaldada y siete archivos publicados con hashes coincidentes.
- **Crear noticia con IA** incorpora arriba el selector **Contenido**, con los modos **Extraer de URL** y **Pegar contenido**. Cada modo conserva temporalmente su propio valor al alternar y mantiene las indicaciones editoriales como entrada adicional.
- Cada generación propone además un **Título ideal** basado exclusivamente en la noticia. Se muestra completo en una caja editable y sólo reemplaza el título principal cuando el periodista pulsa **Usar este título**; agregar el cuerpo al editor sigue siendo una decisión independiente.
- El encabezado del asistente se presenta como **Crear noticia con IA ✨** y los botones **Crear noticia**, **Crear otra versión** y **Agregar al editor** comparten destellos vectoriales dorados, visibles de forma consistente aunque el dispositivo no tenga fuente de emojis.
- La extracción remota admite páginas públicas HTTP/HTTPS, sigue hasta tres redirecciones validadas, limita tiempo y respuesta, acepta solamente HTML/texto, elimina navegación/scripts/publicidad estructural y entrega mensajes claros cuando un sitio bloquea o no expone suficiente contenido.
- La conexión fija el DNS validado y bloquea redes privadas, locales, reservadas, metadata, CGNAT, IPv4 encapsulada en IPv6 y protocolos no web. QA de extracción pública y navegador pasó en DEV; no se invocó DeepSeek con una noticia real.
- Cada usuario admite una sola sesión activa. Un nuevo login reemplaza el token anterior y la otra máquina vuelve al acceso con un aviso explícito.
- Se mantienen los cierres existentes tras 2 horas de inactividad o 12 horas totales. El logout condicionado de una sesión antigua no puede cerrar la sesión nueva.
- Cardona Hoy PROD recibió ambos cambios el 5 de septiembre: respaldo completo de base, migración verificada sin alterar filas, cinco reemplazos y un alta con hashes remotos coincidentes. El ejecutor temporal fue eliminado y respondió HTTP 404.
- Cardona Hoy PROD recibió después **Título ideal** y los destellos IA mediante tres reemplazos atómicos sin conflictos, migración, cambios de datos ni borrados. El respaldo anterior quedó en `.deploy/respaldos/2026-09-05_123904-titulo-ideal-estrellas-prod/`; FTPS y HTTP confirmaron tamaños y SHA-256.
- Cardona Hoy PROD publica ahora `CardonaHoy` desde la fuente única: Open Graph, `WebSite` y `NewsArticle.publisher` quedaron verificados en portada y una noticia real, sin apariciones de `Radio Sur`. Se conservaron Mantenimiento desactivado y todos los demás datos.
- El código de identidad, sesión única, extracción segura y mejoras editoriales quedó consolidado y publicado en `origin/main` dentro de `5d7f1e1` (`feat: consolidar identidad y mejoras editoriales`).

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

- El usuario aprobó el despliegue inicial en Mantenimiento y el resultado de las correcciones posteriores. Este punto conserva el estado histórico del 3 de septiembre; desde el cierre del 5 de septiembre, Cardona Hoy PROD tiene Mantenimiento desactivado.
- El usuario aprobó el lote común completo, incluida la persistencia por portal de las instrucciones de **Crear con IA**, y autorizó su checkpoint local en los tres repositorios.
- DeepSeek quedó operativo en DEV mediante el runtime privado `admin/servicios.runtime.local.json`; no forma parte de Git y no debe reemplazarse por una configuración pública.
- El checkpoint funcional aprobado `4c3f01c` fue publicado en `origin/main`. Al retomar, leer `CONTINUIDAD.md` y ejecutar `git status --short --branch` antes de decidir una publicación nueva.

## Pendientes posteriores

1. Promover el despliegue inicial a confirmado después de completar las validaciones autenticadas pendientes con usuarios reales.
2. Verificar si el lote común del checkpoint `a8ab659` coincide con Cardona Hoy PROD antes de registrar cualquier despliegue adicional.

## Mejoras futuras sin etapa activa

1. Mantener el baseline funcional heredado y aplicar aquí solamente las particularidades aprobadas para Cardona Hoy.
2. Trasladar mejoras comunes desde `portal-base` mediante commits pequeños y revisados.

## Regla de trabajo

- Comenzar por la prioridad activa, salvo indicación expresa del usuario.
- Mantener cambios pequeños, verificables y separados en commits descriptivos.
- Al completar un punto, retirarlo de esta agenda y registrar el cierre técnico en `CONTINUIDAD.md`.
- No desplegar, migrar bases de datos, eliminar archivos ni modificar datos persistentes por inferencia.
