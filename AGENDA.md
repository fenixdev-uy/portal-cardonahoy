# Agenda — Portal Cardona Hoy

Última actualización: **13 de septiembre de 2026**.

Este archivo es la **única agenda aplicable a `portal-cardonahoy`**. La aplicación nace del baseline aprobado `5ec3e42`, pero no comparte configuración privada, base de datos, uploads, despliegue ni remoto con RS Medios.

## Prioridad activa

1. Confirmar manualmente en Cardona Hoy PROD la nueva experiencia Copilot y que el teclado de un teléfono real ya no cierre el chat.
2. Continuar probando en PROD la selección por popularidad, especialmente singular/plural y los períodos hoy, ayer, anteayer, esta semana y semana pasada.
3. Conservar como referencia la única conversación PROD restante —ID 20, último test aprobado— y no volver a limpiar historial sin una nueva autorización expresa.
4. Mantener pendientes las validaciones autenticadas previas de noticias, autoría, sesión única, Portada y Análisis que aún correspondan en PROD.

## Experiencia Copilot del asistente publicada en PROD — 13 de septiembre de 2026

- Correctivo móvil: el `resize` producido al abrir el teclado ya no cierra el chat cuando el launcher flotante está oculto. DEV y PROD conservaron panel, foco, texto y Enviar a `390/320px`; el cierre explícito y la regresión PC/tablet/móvil también pasaron.
- Se publicaron únicamente `admin/asistente.php`, `index.php`, `assets/css/asistente.css`, `assets/css/portal.css` y `assets/js/asistente.js`: identificador `#ID`, accesos superiores PC/móvil, Copilot 30/70, noticia lateral simultánea y halos verdes animados.
- El preflight FTPS/TLS encontró los cinco archivos exactamente en el baseline registrado, con cero conflictos. Los originales son recuperables desde `.deploy/respaldos/20260913_asistente-copilot-prod/`.
- La QA PROD detectó que el logo configurado al 130% rozaba el botón IA en 320 px. Se amplió sólo allí el desplazamiento de 12 a 24 px y se publicó exclusivamente `assets/css/portal.css` tras un segundo preflight; respaldo adicional `.deploy/respaldos/20260913_asistente-copilot-logo-prod/`.
- Hashes FTPS finales verificados y hashes HTTPS coincidentes para ambos CSS y JavaScript. Portada/login `200`, Admin anónimo `302`, endpoint GET `405`, configuraciones privadas `403` y cero temporales remotos.
- Chromium/Puppeteer PROD pasó en 1440×900, 1025×768, 390×844, 320×720 y tablet 800×900: geometría, aperturas/cierres, noticia coexistente, halos, CTA y cero overflow o errores propios. Se interceptaron IA, vistas, publicidad y analytics; no se crearon conversaciones ni métricas.
- Sin migración ni modificación de base, historial, noticias o configuración. El ajuste final de 24 px, el correctivo del teclado y esta documentación quedan incluidos en el checkpoint Git autorizado del 13 de septiembre.

## Paridad visual del asistente aplicada en DEV — 13 de septiembre de 2026

- El historial administrativo identifica ahora cada conversación como **#ID · pregunta inicial**, para facilitar diagnósticos posteriores sin copiar el chat.
- En PC, el acceso superior **Activar Modo IA** reemplaza la burbuja flotante y abre un Copilot fijo a la izquierda en división aproximada 30/70. La portada se desplaza a la derecha y una noticia puede abrirse simultáneamente en su panel lateral derecho sin cerrar el chat.
- En móvil, la burbuja queda oculta y aparece el botón compacto **IA** junto al menú. El encabezado, A−/A+ y Cerrar comparten el halo verde animado; el CTA **Explorá las noticias** del menú usa el mismo perímetro luminoso. En 320 px se separó levemente el logo para evitar solapamiento.
- Chromium/Puppeteer sobre DEV pasó en 1440×900, 1025×768, 390×844, 320×720 y tablet 800×900: aperturas/cierres, geometría, noticia simultánea, animaciones, controles y ausencia de overflow o errores propios. Las consultas se interceptaron con respuesta simulada, por lo que no se escribió historial ni se tocó la base.
- Se preservaron el motor semántico, la configuración, identidad y geometría propia de Cardona Hoy. Tablet continúa deliberadamente sin activador. Este fue el cierre DEV previo al despliegue PROD documentado arriba.

## Cierre Git autorizado — 12 de septiembre de 2026

- La evolución conversacional, popularidad por vistas, acceso desde el menú, controles de tamaño y paleta neutral aprobados quedan incluidos en un checkpoint propio de Cardona Hoy y en su push a `origin/main`.
- Este cierre Git documenta también las publicaciones PROD selectivas ya verificadas; no ejecuta un nuevo despliegue, migración ni cambio de datos.

## Paleta del acceso IA dentro del menú publicada en PROD — 12 de septiembre de 2026

- Se corrigió la pieza omitida del traslado visual: el CTA **Explorá las noticias** del menú ahora usa degradado negro/grafito, icono gris claro y destellos blancos, sin azul ni turquesa.
- Se publicó únicamente `assets/css/portal.css` después de validar DEV. Preflight sin conflictos, respaldo, reemplazo atómico y hash final verificado por FTPS/HTTPS; QA PROD pasó en PC y móvil. Sin base de datos, historial, commit ni push.

## Nueva paleta visual del asistente publicada en PROD — 12 de septiembre de 2026

- Se trasladó desde la variante aprobada en RS Medios únicamente la paleta: launcher y encabezado negro/grafito, conversación blanca con trama de puntos gris, mensajes claros y compositor gris con campo blanco.
- No se copió el desplazamiento vertical móvil de RS Medios porque depende de su navegación fija Noticias/Radio/Canal TV. Cardona Hoy conserva su geometría propia.
- Se publicó únicamente `assets/css/asistente.css` con preflight sin conflictos, respaldo, reemplazo atómico y hash final verificado por FTPS y HTTPS. QA PROD pasó en PC y móvil. Sin base de datos, historial, commit ni push.

## Acceso al asistente desde el menú publicado en PROD — 12 de septiembre de 2026

- Mientras el asistente público está activo, el menú de la portada oculta provisoriamente su buscador y muestra una invitación con icono IA, estrellas y el texto **“Explorá las noticias con nuestro asistente de IA”**. Su paleta azul/turquesa original fue reemplazada posteriormente por negro/grafito. Si el asistente se desactiva, vuelve automáticamente el buscador anterior.
- Al pulsarla se cierra el menú y se abre el panel existente, sin duplicar chat ni endpoint. El foco termina en el campo de escritura PC y en el diálogo móvil.
- QA Chromium DEV pasó en `1440×900` y `390×844`: una invitación, cero buscadores visibles, texto completo, medidas `680×86` y `358×78`, cero overflow y apertura correcta. La única consola fue Google Fonts bloqueada por la CSP preexistente.
- Se publicaron únicamente los cuatro archivos de la mejora, con cero conflictos, respaldo `.deploy/respaldos/20260912_193631-menu-asistente-prod/`, reemplazo atómico, hashes verificados y cero temporales.
- QA PROD repitió correctamente escritorio y móvil; portada/assets `200`, privado `403`. No hubo base de datos, historial, Mantenimiento, commit ni push.

## Historial de pruebas limpiado en PROD — 12 de septiembre de 2026

- Se identificaron 20 conversaciones/88 mensajes y se conservó únicamente la conversación más reciente, ID 20, **“¿Cuál es la noticia más importante de esta semana?”**, con sus 10 mensajes.
- Un respaldo privado completo precedió a la escritura. La transacción eliminó 19 conversaciones y 78 mensajes; el postflight confirmó 1 conversación/10 mensajes.
- Noticias, vistas acumuladas y Mantenimiento permanecieron exactamente en 149, 11.241 y `0`. El ejecutor temporal fue retirado y responde `404`.

## Popularidad del asistente publicada en PROD — 12 de septiembre de 2026

- La importancia solicitada por el visitante se interpreta como interés expresado por las lecturas. El servidor filtra únicamente noticias Publicadas dentro del período y ordena por `vistas DESC`, `publicada_at DESC`, `id DESC`; DeepSeek resume el resultado pero no decide ni modifica la clasificación.
- La selección admite una noticia o hasta cinco según la formulación, tolera errores ortográficos y reconoce hoy, ayer, anteayer, esta semana y semana pasada con límites calculados en `America/Montevideo`.
- Las vistas se usan exclusivamente en backend. No aparecen en el prompt como cifra, la respuesta JSON, las tarjetas ni el historial público.
- QA HTTPS DEV validó singular ID 11, plural `[11,8,7,6,5]`, el error `improtante` y una semana sin publicaciones. Las cuatro conversaciones y ocho mensajes creados se eliminaron transaccionalmente; DEV volvió a 25 conversaciones/92 mensajes.
- Por autorización expresa se publicó únicamente `asistente/consultar.php`: cero conflictos, respaldo recuperable, reemplazo atómico y hash local/FTPS `ab470097c54b9bf49b1127650f7fe1852bce3fd1728195a9e240dfe1870e2d57`. Portada `200`, endpoint GET `405`, runtime privado `403` y cero temporales.
- No hubo migración, modificación de noticias, vistas, historial o Mantenimiento, commit ni push. La consulta funcional final queda para la validación manual del usuario en el chat PROD.

## Asistente público publicado en PROD — 12 de septiembre de 2026

- Se publicó incrementalmente el módulo completo del asistente público en Cardona Hoy PROD, incluido el ajuste posterior que amplía la interpretación mediante DeepSeek sin ceder al modelo el orden cronológico, las fechas ni la construcción de enlaces.
- La búsqueda estricta conserva precisión; si no encuentra resultados, una segunda recuperación reúne hasta 15 candidatas de cualquier fecha por coincidencia parcial y sólo como último recurso ofrece las 15 recientes. Esto permitió encontrar la noticia antigua de Indulacsa con una consulta cotidiana sobre “la fábrica de leche que venden”.
- La base PROD verificada con huella `b83ead0fb903` fue respaldada y migrada dos veces de forma idempotente: 20 tablas finales, 2 tablas de historial, permiso `asistente.ver`, asignación al Administrador, 4 ajustes públicos y Mantenimiento conservado en `0`.
- Los 15 archivos remotos coinciden con los hashes locales; el runtime privado responde `403`, el instalador `403`, el ejecutor efímero fue eliminado y responde `404`, y no quedaron temporales FTPS.
- QA real pasó en `1440×900` y `390×844`, sin overflow ni errores propios del asistente. La advertencia existente de Google Fonts bloqueada por CSP es ajena a este módulo.
- PROD conserva 5 conversaciones y 10 mensajes creados por el smoke QA. No se borrarán sin autorización expresa.

## Asistente público aprobado en DEV — 12 de septiembre de 2026

- Se trasladó desde Portal Base el asistente público de búsqueda de noticias sin combinarlo con **Crear noticia con IA** ni reemplazar variantes propias de Cardona Hoy.
- La integración incluye burbuja y panel responsive, recuperación de noticias publicadas, apertura de la nota, configuración de disponibilidad y textos, historial administrativo, permiso independiente y migración reproducible.
- El runtime DeepSeek se derivó de la configuración privada propia de Cardona Hoy, permanece ignorado por Git, conserva modo `600` y no fue publicado.
- QA local con Chromium/Puppeteer pasó en `1440×900`, `390×844` y tablet `900×900`: interfaz y apertura de noticias correctas, sin overflow ni errores; en tablet continúa deliberadamente oculto.
- Después de detectar que el menú administrativo no aparecía, se respaldó y migró exclusivamente DEV. Las 2 tablas, el permiso `asistente.ver` y su asignación al Administrador quedaron verificados en dos ejecuciones idempotentes; las 4 claves configurables ya existentes se preservaron.
- El usuario validó con una sesión real la configuración, activación, menú, respuestas DeepSeek, apertura y cards, historial, categorías, fechas, consultas encadenadas, errores ortográficos, repeticiones y ausencia de resultados irrelevantes.
- La consulta general **¿Qué noticias recientes hay?** quedó corregida para seleccionar en servidor las cinco publicaciones más nuevas por `publicada_at DESC, id DESC`; no delega el orden a DeepSeek ni hereda términos del turno anterior. QA real, incluido un contexto previo de Deportes, devolvió Indulacsa primero y las conversaciones temporales fueron retiradas.
- Las búsquedas de categoría contemplan todas las categorías asignadas, los seguimientos reutilizan el contexto inmediato, las fechas relativas se filtran en servidor y las consultas nuevas con varios términos exigen coincidencias significativas para no arrastrar noticias por una palabra genérica.
- Se corrigieron dos fallas observadas bajo Apache: términos numéricos convertidos a enteros y consultas consecutivas dentro del límite del servidor. El endpoint devuelve además un error JSON controlado ante una falla fatal, sin exponer detalles técnicos.
- Una reproducción con el catálogo real de PROD confirmó que la frase sobre la oferta educativa de UTU quedaba bloqueada antes de llegar a DeepSeek: `educativa` activaba la categoría Educación y la búsqueda exigía simultáneamente todos los términos conversacionales restantes. La consulta sobre una muchacha desaparecida podía acertar o fallar según entrara la fuente correcta en el lote léxico de 15 candidatas.
- DEV incorpora ahora un selector semántico previo: cuando falla la coincidencia estricta, DeepSeek recibe como catálogo únicamente ID, título, fecha y categorías de hasta 250 noticias publicadas, elige como máximo 8 IDs y el servidor los valida antes de cargar los textos completos. Si el proveedor falla, se conserva la búsqueda léxica relajada.
- QA DEV: “qué complejo lechero pusieron a la venta” e “indulasca” recuperaron correctamente ID 11 mediante el selector global; “ovnis en Cardona” devolvió cero cards. Las frases de Silvana y UTU devolvieron vacío porque esas noticias no existen entre las 8 publicaciones de DEV. PHP y `git diff --check` correctos.
- Con autorización posterior se publicó únicamente `asistente/consultar.php` en PROD: preflight sin conflictos, respaldo recuperable, reemplazo atómico y hash remoto `3cdc6812d0e44441701049be54fa608f2a899145a7563f5a9e042fc066265be6`. Portada `200`, endpoint GET `405`, runtime privado `403` y cero temporales FTPS.
- QA semántico PROD autorizado: “había una muchacha desaparecida” recuperó las publicaciones 94 y 92 sobre Silvana; la consulta conversacional sobre la nueva oferta educativa de UTU recuperó 153 y 107; OVNIs devolvió cero cards; y `utlima` conservó correctamente la publicación más reciente, ID 158.
- El campo de escritura móvil ya no muestra la barra vertical interna junto al botón de envío. Se publicó únicamente `assets/css/asistente.css`, con respaldo, reemplazo atómico y hash HTTP/FTPS `9034ec3ebfc9e831679658c8c52faddbbe3772a69298cf6a860ad7cfcf8c9044`; el desplazamiento de textos largos permanece funcional.
- El panel PC del asistente es aproximadamente 10% más ancho y la barra superior incorpora controles accesibles **A− / A+** antes de Cerrar, tanto en PC como en móvil. El visitante puede elegir entre 90% y 140%; la preferencia se guarda en `localStorage` con una clave exclusiva de Cardona Hoy y se restaura al recargar.
- QA local y PROD con Chromium/Puppeteer validó el cambio en `1440×900`, `390×844` y `320×568`: persistencia, límites deshabilitados, orden y etiquetas accesibles, panel PC de 461px, encabezado móvil completo y cero overflow. Se publicaron únicamente `partials/asistente.php`, `assets/js/asistente.js` y `assets/css/asistente.css`, con respaldos, reemplazo atómico y hashes verificados; no hubo base de datos, commit ni push.
- Una nueva corrección en DEV evita tratar **relevantes/importantes/destacadas/principales** como temas literales. Las consultas amplias por hoy, ayer o anteayer recuperan hasta 15 publicaciones del rango exacto calculado en `America/Montevideo` y recién entonces DeepSeek selecciona y resume las de mayor interés. El prompt recibe fecha y hora actuales más el rango ya verificado; la base conserva el control real del filtro.
- QA HTTP DEV: la frase exacta “cuales son las noticias mas relevantes de ayer” pasó de “sobre este tema” a una consulta temporal general correcta —DEV no posee publicaciones de ese día—; “cuales son las noticias mas relevantes” usó DeepSeek en modo `relevantes`, evaluó las 8 publicaciones y devolvió fuentes pertinentes. Las 3 conversaciones/6 mensajes automáticos se eliminaron transaccionalmente: el historial volvió de 28/98 a 25/92.
- Con autorización posterior se publicó únicamente `asistente/consultar.php` en PROD. La consulta exacta recuperó las 8 publicaciones del 11 de septiembre, respondió con IA en modo `relevantes_temporales` y construyó cards verificadas para IDs 151, 153, 154 y 152, sin incluir otro día. Respaldo, hash y temporales correctos; la prueba agregó 1 conversación/2 mensajes a PROD, que se conservan.
- Al cerrar la validación se eliminaron exclusivamente de la base DEV las 14 conversaciones y 104 mensajes de prueba; ambas tablas del historial quedaron en cero. No se modificaron noticias ni configuración y PROD no fue tocado.

## Cierre Git aprobado — 11 de septiembre de 2026

- El usuario aprobó el árbol actual y autorizó su commit y push independiente.
- El checkpoint reúne el flujo Borrador/Publicada y las mejoras editoriales comunes, preservando la identidad y las variantes propias de Cardona Hoy.
- Mañana se debe comparar el estado real de PROD, respaldar y publicar solamente lo pendiente. El push de hoy no constituye despliegue ni autorización para modificar la base PROD.

## Clics y enlaces de anuncios — publicado el 9 de septiembre de 2026

- Cardona Hoy recibió la corrección aprobada en RS Medios para que `anuncios.clics` aumente únicamente mediante un POST originado por un clic humano confiable sobre Facebook, Instagram, WhatsApp o Sitio web. GET, HEAD, cargas, rastreadores y activaciones programáticas no cuentan.
- Las cards usan directamente la URL externa vigente y sincronizan sin recarga los destinos modificados en Administración; un enlace vacío queda deshabilitado y uno válido vuelve a habilitarse.
- Se publicaron solamente cinco archivos funcionales con preflight sin conflictos, respaldo, reemplazo atómico y verificación SHA-256. No hubo migración, borrado, reinicio de contadores ni cambios en Popup.
- DEV y PROD pasaron PHP/JavaScript, HTTP y QA headless en escritorio y móvil. El usuario confirmó después en Cardona Hoy PROD que un clic real sobre un anuncio aumentó correctamente el contador una sola vez.
- Por autorización posterior se respaldó y reinició exclusivamente `anuncios.clics` en Cardona Hoy PROD: 18 anuncios pasaron de 582 clics acumulados a 0. Popup, sus impresiones y Mantenimiento no cambiaron.

## Cierre Git actual — 6 de septiembre de 2026

- La experiencia de lectura y las acciones de noticia quedaron mejoradas en portada, nota completa móvil y página individual: acceso **Ver nota completa** desde el slider, votos junto a los controles de tamaño de texto y acciones de Facebook/WhatsApp más claras.
- El lote común está consolidado y publicado en `origin/main` mediante `a8ab659` (`feat: mejorar lectura y acciones de noticias`).
- Antes de esta actualización documental, la rama `main` estaba limpia y sincronizada con `origin/main`. El checkpoint Git no permite afirmar por sí solo que este lote del 6 de septiembre esté en Cardona Hoy PROD; esa correspondencia debe verificarse antes de considerarlo desplegado.

## Páginas Home y firmas activas — publicado el 8 de septiembre de 2026

- El panel incorpora **Páginas → Home** debajo de Noticias, con vista previa segura de la portada y edición de título, descripción e imagen SEO. La misma card continúa disponible en Configuración sin duplicar su lógica.
- El acceso depende del permiso independiente `paginas.gestionar`, configurable por rol y asignado inicialmente al Administrador.
- El selector **Firma de la noticia** muestra únicamente usuarios activos y el backend rechaza una firma inactiva enviada manualmente.
- El checkpoint funcional `c16025f` quedó publicado en `origin/main` y desplegado en Cardona Hoy PROD con respaldo de código, respaldo completo de base, migración idempotente y verificación SHA-256.
- No se agregaron páginas Radio ni Televisión, no se borraron archivos ni contenidos y Mantenimiento permaneció sin cambios. Queda pendiente solamente la validación visual autenticada con un usuario real.

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
