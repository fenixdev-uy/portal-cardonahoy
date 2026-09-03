# Continuidad — Portal de Noticias

## Copiar link editorial desde la noticia — 3 de septiembre de 2026

- `partials/acciones-noticia.php` incorpora debajo de la fila de votos y Compartir una herramienta compacta con icono y texto **Copiar link de noticia**. Se reutiliza en la página canónica y en la nota completa abierta desde la portada.
- El servidor solo incluye el control cuando existe una sesión válida y su rol posee `noticias.editar`; para visitantes y roles sin esa capacidad no queda marcado oculto en el HTML.
- La copia usa la misma URL canónica generada por `url_noticia()` que Facebook y WhatsApp, ofrece fallback para navegadores sin la API moderna del portapapeles y confirma **Link copiado** durante 1,8 segundos.
- Implementación funcional idéntica en Portal Base, RS Medios y Cardona Hoy. Pasaron PHP, JavaScript, whitespace, render autorizado/anónimo y Puppeteer en `1440×900` y `390×844`, sin overflow ni errores de consola.
- Por autorización expresa, Cardona Hoy PROD recibió únicamente `partials/acciones-noticia.php`, `assets/css/noticia.css`, `assets/css/portal.css`, `assets/js/noticia.js` y `assets/js/portal.js`. El preflight FTPS verificó TLS, destino y cero conflictos; las versiones anteriores quedaron en `.deploy/respaldos/2026-09-03_203354/` y cada descarga posterior coincidió en SHA-256 con el archivo local.
- Smoke test PROD: portada, login y una noticia real respondieron HTTP 200; los dos JavaScript publicados contienen el controlador de copia, y tanto portada como noticia omiten correctamente el control para visitantes anónimos. `servicios.local.json` y `admin/config.local.php` continúan en HTTP 403. No hubo migración, modificación de datos ni borrados.
- Checkpoint funcional `837ee57` (`feat: agregar copia de enlace para editores`), publicado en `origin/main`. La documentación del despliegue se consolidó en un commit posterior; la prueba con una sesión editorial real queda a cargo del usuario.

## Checkpoint GitHub aprobado — 3 de septiembre de 2026

- El lote funcional de imágenes SEO, fecha y hora administrativa y máximo de cinco noticias en Portada quedó consolidado en `4c3f01c` (`feat: optimizar SEO y limitar noticias de portada`) y publicado en `origin/main`.
- Cardona Hoy PROD recibió los despliegues y el ajuste de datos documentados debajo; Portal Base y RS Medios conservan sus destinos separados.

## Máximo de cinco noticias en Portada — 3 de septiembre de 2026

- `PORTADA_NOTICIAS_LIMITE=5` centraliza la regla. El switch AJAX de `admin/index.php` y el guardado de `admin/noticia-form.php` validan el cupo dentro de la transacción; una noticia que ya estaba seleccionada puede seguir editándose.
- Intentar agregar una sexta devuelve HTTP 422, revierte el switch y muestra: `La portada admite un máximo de 5 noticias destacadas. Desmarcá una antes de agregar otra.` Si existen más de cinco selecciones históricas, el panel informa el total y cuántas deben desmarcarse.
- La consulta pública de `index.php` aplica `LIMIT 5`, por lo que el slider nunca renderiza más de cinco aun antes de sanear selecciones históricas. No se desmarca ni elimina ninguna noticia automáticamente.
- Prueba transaccional DEV: cinco selecciones aceptadas, sexta rechazada, caso histórico de seis limitado a cinco públicamente y rollback con estado exacto. Puppeteer pasó en `1440×950` y `390×844`, sin overflow ni errores de consola. El código permanece idéntico en los tres portales.
- Cardona Hoy PROD fue actualizado por autorización expresa. El preflight autenticado confirmó 18 tablas, 74 noticias, 9 marcadas para Portada y las 9 con foto. Se conservaron por `created_at DESC, id DESC` las IDs 85, 84, 83, 82 y 81; una transacción desmarcó las otras 4 y el postflight confirmó exactamente 5 seleccionadas.
- Antes de escribir se generó el respaldo PROD `.deploy/respaldos-db/2026-09-03-portada-maximo-prod/cardonahoy-production-before-portada.sql`: 1.038.339 bytes, 18 tablas, 74 filas de noticias y SHA-256 `4677fac6be575199349c3c19fea8b6d9228fd8113ef5893532bc2b6b756fdff2`. El ejecutor autenticado y su token fueron retirados; su URL final respondió HTTP 404.
- Se publicaron con preflight, respaldo, temporal más renombrado y descarga SHA-256 los cinco archivos `admin/assets/admin.css`, `admin/includes/funciones.php`, `admin/index.php`, `admin/noticia-form.php` e `index.php`. HTTP final: portada 200 con exactamente 5 slides, login 200, formulario sin sesión 302 y configuraciones privadas 403. No hubo borrados de noticias, cambios de contenido ni migración de esquema; el checkpoint GitHub se completó después de la aprobación del usuario.

## Fecha y hora en la tabla de Noticias — 3 de septiembre de 2026

- `noticias.created_at` ya conserva automáticamente el momento en que se crea y publica cada noticia; no se agregó ninguna columna ni migración.
- `admin/index.php` muestra ahora `DD/MM/AAAA · HH:MM hs.` dentro de un elemento `<time>` semántico. La ordenación existente continúa usando el timestamp completo y editar una noticia no modifica su fecha de creación.
- El cambio común quedó aplicado en Portal Base, RS Medios y Cardona Hoy. PHP, whitespace y QA renderizada con Puppeteer pasaron en `1440×950` y `390×844`: fecha y hora visibles, orden ascendente funcional, sin overflow ni errores de consola. Cardona Hoy PROD ya recibió este archivo junto con el límite de Portada; Base y RS Medios permanecen locales.

## Procesamiento automático de imágenes SEO — 3 de septiembre de 2026

- La fuente continúa guardándose con la semántica existente: `seo_imagen` vacía usa automáticamente la portada y un valor explícito representa una foto elegida o subida para SEO. No se agregó ninguna columna ni migración.
- Al guardar una noticia se generan dos copias independientes dentro de `uploads/noticias/`: JPEG progresivo de `1200 × 630` para Open Graph, WhatsApp, Facebook y Twitter, y JPEG de `1200 × 675` para Google Discover y el arreglo `NewsArticle.image`.
- El procesador valida el contenido real JPG/PNG/WEBP, corrige orientación EXIF aun cuando la extensión PHP `exif` no está disponible, recorta al centro con proporción `cover`, escala, elimina metadatos y reduce calidad gradualmente hasta un máximo de 400 KB.
- Nunca modifica la fotografía fuente por generar SEO. Las variantes usan nombres deterministas, se regeneran cuando cambia la fuente y se eliminan junto con ella o cuando deja de ser la fuente SEO efectiva.
- La subida exclusiva desde la card SEO solicita el procesamiento en el mismo endpoint autenticado y muestra `Imagen SEO lista: 1200 × 630 px · N KB`; la vista previa utiliza la copia resultante. Un fallo elimina la subida incompleta y devuelve un error controlado.
- La página pública informa ancho, alto y MIME de `og:image`, usa la variante social también en Twitter, publica ambas variantes en `NewsArticle` y habilita `max-image-preview:large`. Si una noticia histórica todavía no tiene derivados, conserva el fallback seguro a su imagen original.
- QA de backend: JPG horizontal, PNG vertical, WEBP pequeño, imagen de alto detalle y JPEG con Orientation 6; todos produjeron las medidas exactas, MIME JPEG y peso menor o igual a 400 KB. La prueba se repitió en los tres checkouts.
- QA pública reversible en Portal Base DEV: una noticia existente sirvió la variante social con HTTP 200, `image/jpeg`, 1200×630 y 96.389 bytes; la variante Discover respondió HTTP 200, 1200×675 y 102.238 bytes. Open Graph, Twitter y JSON-LD apuntaron a las copias correctas. Los dos archivos temporales fueron retirados y la base no se modificó.
- QA visual con Puppeteer —Browser plugin no disponible— pasó en `1440×950` y `390×844`: imagen visible, estado con medidas/peso, proporción exacta, sin overflow y sin errores de consola. No se creó una cuenta administrativa temporal; la interacción autenticada real queda para validación manual del usuario.
- La implementación está idéntica en Portal Base, RS Medios y Cardona Hoy. El usuario confirmó la carga en DEV y autorizó desplegarla solamente en Cardona Hoy PROD.
- El despliegue incremental de `admin/assets/seo-noticia.js`, `admin/includes/funciones.php`, `admin/noticia-form.php`, `admin/upload-imagen.php` y `noticia.php` terminó sin conflictos: se comparó cada remoto con `.deploy/estado.json`, se respaldó la versión anterior en `.deploy/respaldos/2026-09-03_seo-imagen-prod/`, se usó temporal más renombrado y la descarga final coincidió en SHA-256 para los cinco archivos.
- QA posterior: portada HTTP 200, login 200, formulario administrativo sin sesión 302, runtimes privados 403 y JavaScript servido por HTTPS con hash `ac7675d527d835abca122eeb2a568805defa75d90961b84daf6bff1b6f99cc76`. No hubo migración, cambios de datos, borrados, commit ni push, y no se alteró el estado de Mantenimiento. La prueba autenticada con imágenes reales queda a cargo del usuario.

> **Identidad de este checkout — 30 de agosto de 2026:** esta copia es `portal-cardonahoy`, ubicada en `/home/fenixdev/public_html/proyectos.fenixdev.uno/09portal-noticias/portal-cardonahoy`. Nació del commit aprobado `5ec3e42`, tiene su remoto GitHub exclusivo y su PROD inicial en `https://cardonahoy.com/`. Las referencias operativas a RS Medios que siguen debajo documentan el origen funcional y no autorizan usar sus destinos o credenciales en esta copia.

## Cierre aprobado — 3 de septiembre de 2026

- El usuario aprobó el lote común completo y autorizó consolidarlo mediante un commit local en cada uno de los tres repositorios.
- **Crear con IA** conserva las instrucciones en `localStorage`, con una clave separada mediante `PORTAL_INSTANCE_ID`; vaciar el campo elimina el valor y un bloqueo del almacenamiento no interrumpe el editor.
- El cierre incluye además el acceso superior al Admin solo para usuarios autenticados, la etiqueta **Compartir** con ajuste responsive, la protección HTTP de archivos SQL, la carga correcta de Mantenimiento en los tres endpoints públicos y el placeholder **Sin imagen** de la vista previa SEO.
- Los once archivos funcionales coinciden byte a byte entre `portal-base`, `portal-rsmedios` y `portal-cardonahoy`. Pasaron `php -l`, `node --check` y `git diff --check` en los tres checkouts.
- No se hizo push, nuevo despliegue ni migración. Según confirmación del usuario, la persistencia de instrucciones ya está en Cardona Hoy PROD; Portal Base y RS Medios la conservan localmente.

## Cierre documentado — 30 de agosto de 2026

- El usuario aprobó el despliegue inicial de Cardona Hoy en Mantenimiento y el resultado de las correcciones posteriores. Mantenimiento continúa activo y no debe desactivarse sin autorización expresa.
- Se cerró la sesión conservando todos los cambios locales existentes. El placeholder SEO y las correcciones comunes permanecen sin commit, push ni nuevo despliegue; no hubo nuevas migraciones de base de datos.
- DeepSeek quedó operativo en **Cardona Hoy DEV**: `servicios.local.json` conserva el dato maestro privado y se generó `admin/servicios.runtime.local.json` con únicamente la sección `deepseek`, modo `600` e ignorado por Git. La comprobación oficial `GET /models` respondió HTTP `200`, autenticación correcta y modelo configurado disponible; la ruta pública del runtime respondió HTTP `403`.
- Ninguna clave fue impresa ni incorporada a PHP, documentación o archivos versionados. El runtime DEV no debe confundirse con la configuración PROD ni copiarse a otros portales.
- Al retomar: leer primero `AGENDA.md`, este archivo y `git status --short --branch`; elegir explícitamente el portal y no desplegar ni crear un checkpoint por inferencia.

## Vista previa SEO sin imagen — 30 de agosto de 2026

- En **Nueva noticia**, la vista previa social ya no usa `imagenes/Logo2027v3.png` cuando todavía no hay portada ni imagen SEO seleccionada. Muestra un bloque gris neutro con trama diagonal sutil y el texto **Sin imagen**.
- El alcance es únicamente la vista previa del editor: no se modificó el fallback de metadatos SEO del portal público.
- La implementación quedó idéntica en `portal-base`, `portal-rsmedios` y `portal-cardonahoy`. PHP, JavaScript y whitespace pasaron validación; el QA renderizado se ejecutó en Cardona Hoy DEV en `1440×950` y `390×844`, sin errores de consola, requests fallidos ni overflow.
- La prueba no guardó ninguna noticia y la cuenta DEV temporal fue eliminada. Cambio local: no se desplegó, no se migró base de datos, no se creó commit y no se hizo push.

## Despliegue inicial PROD en mantenimiento — 30 de agosto de 2026

- El usuario autorizó expresamente publicar la aplicación completa, sus uploads y todos los datos de prueba de DEV en PROD, manteniendo el sitio cerrado al público.
- El manifiesto privado `servicios.local.json` quedó en versión 2, modo `600`, ignorado por Git y con `deployment.database_environment=production`. FTPS usa el hostname canónico certificado `vps-4962765-x.dattaweb.com:21`; `ftp.cardonahoy.com` fue descartado porque no coincide con el SAN/CN del certificado.
- Antes de la importación se verificó la base PROD real mediante un runner autenticado y efímero: huella `95fbe89f9bdc`, cero tablas y cero filas. Su respaldo previo privado tiene 133 bytes y SHA-256 `f2eb2bacfa2c840492ce0f145f709675fa6f2538bdcabe9d3656cbc2634a9e88`; no se aceptó ningún respaldo de 0 bytes.
- El volcado consistente de DEV tiene 42.006 bytes, 18 tablas y SHA-256 `99aa403f0d7210aba3999ce2e7adf7f059165c77e1b3436176d1ae793a0de2ea`. PROD terminó con las mismas 18 tablas, 126 filas y conteos exactos por tabla.
- `mantenimiento_activo=1`. Solamente un usuario activo —rol `admin`— posee `mantenimiento.gestionar`; la cuenta activa de Editor no puede atravesar el bloqueo. El usuario debe confirmar todavía su ingreso real con contraseña en PROD.
- La primera comprobación previa a importar detectó que Apache entregaba el SQL temporal con HTTP `200`; la importación se abortó antes de tocar la base y el archivo fue eliminado inmediatamente. Se corrigió `.htaccess` para bloquear globalmente `*.sql`, un probe inocuo confirmó HTTP `403`, y recién entonces se repitió la transferencia, importación y eliminación verificadas. Esta protección quedó también en el archivo versionado local.
- Se publicaron 144 rutas por FTPS explícito; 142 fueron transferidas en la tanda principal y verificadas una por una mediante descarga y SHA-256. El manifiesto incluye 29 rutas de uploads por 16.488.666 bytes. Se preservaron `old/` y `prueba-conexion-codex-20260830-024956.txt`.
- La configuración PROD contiene únicamente el runtime de base y el runtime mínimo de DeepSeek; el manifiesto maestro y las credenciales FTPS no se publicaron. `admin/config.local.php`, `admin/servicios.runtime.local.json`, `servicios.local.json` e instaladores responden `403`; `tools/validar-servicios.php` responde `404` porque `tools/` no fue publicado.
- El runner, SQL, probe, temporales y respaldos remotos del `.htaccess` fueron eliminados. El runner final responde `404`; el `.htaccess` original de cPanel y los respaldos de base permanecen solamente en `.deploy/` con modo `600`.
- QA PROD con Chromium/Puppeteer —Browser plugin ausente y Playwright no instalado— pasó en `1440×900` y `390×844`: portada `503`, pantalla de mantenimiento y logo correctos, login `200`, interacción Mantenimiento → Login, recursos cargados y cero overflow. El único error de consola fue el `503` deliberado del documento principal.
- El smoke test ampliado detectó que `noticia-compartir.php`, `noticia-vista.php` y `votar.php` llamaban `exigir_portal_disponible()` sin cargar `admin/includes/funciones.php`, por lo que devolvían `500`. Se agregó únicamente ese `require_once`, se respaldaron y reemplazaron las tres rutas con control de hash, y la repetición final dejó los 11 endpoints públicos dinámicos en `503`, portada `503` y login `200`.
- Estado operativo: `.deploy/estado.json` está en `verified_pending_user_login_confirmation`. No se creó commit, no se hizo push y Mantenimiento no debe desactivarse por inferencia.

## Cierre de etapa aprobado — 29 de agosto de 2026

- El usuario aprobó integralmente el traslado de Modo Mantenimiento y los tamaños configurables de logos.
- Checkpoint de este checkout: `91c4863` (`feat: agregar mantenimiento y tamanos de logos`), rama `main` sincronizada con `origin/main` al iniciar el cierre.
- Se pausa el trabajo sin despliegue ni migración PROD. La eventual habilitación de Browser queda diferida y no bloquea futuras pruebas con Puppeteer.
- Al retomar, leer `AGENDA.md`, este archivo y `git status --short --branch` antes de realizar cambios.

## Modo mantenimiento y tamaños de logos — 29 de agosto de 2026

- Se trasladó desde `portal-base` el bloque aprobado de **Mantenimiento**, incluido su icono de llave de reparación, switch rápido, card de configuración, pantalla pública `503`, bloqueo de endpoints y permiso independiente `mantenimiento.gestionar` administrable desde Roles.
- Los usuarios cuyo rol posee ese permiso pueden ingresar y revisar el Portal durante el bloqueo; los demás reciben acceso denegado. La URL directa de login permanece disponible aunque se oculte su icono público.
- Las cards **Logo del Login** y **Logo del Portal** incorporan sliders independientes de `60%` a `140%`, vista previa en vivo y guardado sin necesidad de subir otro PNG. El Portal comparte su porcentaje entre encabezado y menú fullscreen, conservando medidas responsive propias para PC, tablet y móvil.
- Migraciones idempotentes: `install/mantenimiento-v1.php` y `install/configuracion-v1.php`. Ambas se ejecutaron dos veces únicamente en DEV (`fenixdev_noticias-cardonahoy`) después de verificar la huella documentada `93774fb37b9b` y el servidor `vps-6107919-x.dattaweb.com`.
- Respaldo privado previo: `.deploy/respaldos-db/2026-08-29/dev-before-mantenimiento-logos.sql`, 41.281 bytes, SHA-256 `2297008498658f31b26fe1744a766ef8f3cedf3e618d9c19c8255dacdd43e950` y modo `600`.
- La verificación dejó `mantenimiento_activo=0`, ambos tamaños de logo en `100`, seis valores efectivos de configuración, un permiso y asignación inicial únicamente al rol `admin`.
- QA local por Puppeteer —Browser y Playwright no disponibles— pasó en `1440×900` y `390×844`: sliders y previews al `140%`, login, encabezado y menú, pantalla pública `503`, acceso autorizado, denegación `403`, logos propios cargados y cero overflow. Los únicos errores de consola correspondieron a los estados HTTP deliberados.
- El usuario aprobó integralmente el resultado. El bloque quedó cerrado en `91c4863` y sincronizado con el remoto exclusivo de Cardona Hoy; no hubo migración ni despliegue PROD.

## Aislamiento de sesiones por instalación — 29 de agosto de 2026

- La identidad pública versionada vive en `admin/config.instance.php` y para este checkout es `cardonahoy`.
- La cookie del panel es `portal_noticias_admin_cardonahoy` y limita su ruta al portal; ya no comparte sesión ni cierre de sesión con los otros portales del dominio.
- La cookie anónima de votos y popups es `portal_visitante_cardonahoy`, evitando cruces de visitantes entre instalaciones.
- La validación HTTPS real respondió `200` y emitió la nueva cookie con `Secure`, `HttpOnly`, `SameSite=Lax` y la ruta exclusiva de este checkout.

## Alta DEV autónoma — 29 de agosto de 2026

- `admin/config.local.php` existe con modo `600`, está ignorado por Git y el acceso HTTP devuelve `403`.
- La conexión DEV tiene huella `93774fb37b9`, distinta de RS Medios y Portal Base, sobre el servidor verificado `a45af3c91c16`.
- Antes de importar se confirmó que el destino tenía cero tablas y cero filas. Su respaldo privado previo está en `.deploy/respaldos-db/2026-08-29-clone/cardonahoy-dev-before-clone.sql`: 1.069 bytes, SHA-256 `f0903d651035419b47cfde5afd53d6911fb01f362cd6d1aacf22dc74061567bf`.
- Se importó el mismo respaldo DEV de RS Medios de 38.588 bytes y SHA-256 `309f28e75ab29f3a63fa098ae53ba936f0bce2625e1fb3e2c7fd923b98d6a627`.
- La verificación posterior coincide exactamente con el origen: 18 tablas, 111 filas, esquema `1e0d06672259177ab3ac594a10fbde30648a528fab207d501a3fac135c3372ac` y datos `56fa78cf24882ab3baa2b183b74e530d64d21f4e27f209e5d1e2589e9a2d8819`.
- Los 29 archivos persistentes de `uploads/` suman 16.520.582 bytes y tienen huella conjunta `0b5a910f5a64ac8f2388df824904ad172808372ef24fb057adba97cea5985360`, idéntica a RS Medios.
- Portada y login respondieron HTTP `200`. Esta comprobación no reemplaza la validación visual autenticada en navegador.
- GitHub privado: `git@github.com:fenixdev-uy/portal-cardonahoy.git`. `main` sigue `origin/main` y el primer push incluyó las etiquetas `cardonahoy-initial-2026-08-29` y `cardonahoy-dev-clone-2026-08-29`.
- La Deploy Key ED25519 es exclusiva de este repositorio, tiene escritura habilitada y huella `SHA256:qirI8yvr0xwmEzitzDObDPeTCa/qxA2GxEL5ZMdsBqg`; la clave privada permanece fuera de `public_html` con modo `600`.
- No se creó `servicios.local.json` ni configuración o despliegue PROD.

Fecha del punto de continuidad: **28 de agosto de 2026**.

## Carpeta oficial

El proyecto oficial y único sobre el que se debe continuar es:

`/home/fenixdev/public_html/proyectos.fenixdev.uno/09portal-noticias`

No mezclar cambios con las demás carpetas que pertenecen al repositorio Git superior.

## Control de versiones

Este proyecto tiene **su propio repositorio git**, independiente del repo grande de `/home/fenixdev/public_html` (que mezcla otros proyectos de clientes sin relación). Se creó el 23 de agosto de 2026 y trabaja sobre la rama `main`.

- Remoto privado `origin`: `git@github.com:fenixdev-uy/portal-rsmedios.git`, configurado y verificado el 26 de agosto de 2026 mediante una Deploy Key ED25519 exclusiva para este repositorio.
- La rama local `main` sigue `origin/main`. El checkpoint aprobado `27ca624` y la etiqueta `prod-2026-08-26` fueron comprobados en GitHub.
- La clave privada permanece únicamente en el servidor; GitHub recibió solo la clave pública. El repositorio remoto respalda código e historial, no bases de datos, secretos ni uploads persistentes.

- El primer commit (`440a41b`) incluye todo el estado aprobado hasta esa fecha: feeds, votos, panel de votaciones, administración completa.
- El `.gitignore` propio del proyecto ya excluye `admin/config.local.php` (credenciales de la base de datos), `/error_log` y las imágenes subidas en `/uploads/noticias/` (contenido de usuarios, no código fuente). **Las fotos de las noticias no viajan con el repo** — si se clona en otra máquina, `uploads/noticias/` aparece vacío salvo el `.htaccess`.
- Antes de cualquier commit, revisar `git status --short` y `git diff --cached --name-only` para confirmar que no se cuela nada de `config.local.php` ni de logs.
- Después de cada commit aprobado, subir `main` a `origin`. Las etiquetas se publican explícitamente cuando corresponda.

## Estándar de despliegue directo

Definido el **25 de agosto de 2026** como procedimiento reutilizable para este y futuros proyectos. La especificación completa está en `ESTANDAR_DESPLIEGUE_FTPS.md` y la estructura versionable sin valores reales está en `servicios.example.json`.

- Primera publicación completa con lista previa y exclusiones; nunca usar borrado espejo.
- Publicaciones posteriores mediante manifiesto local de rutas, tamaños y SHA-256 para transferir solamente archivos agregados o modificados.
- Git conserva historial/reversión y el manifiesto registra los bytes realmente desplegados, incluso si todavía existen cambios sin commit.
- Antes de reemplazar un archivo, comparar la copia remota con el hash del último despliegue para detectar ediciones hechas fuera del flujo.
- Respaldar cada archivo remoto reemplazado, transferir mediante archivo temporal + renombrado cuando el hosting lo permita y verificar nuevamente la copia remota.
- Eliminaciones y migraciones de base de datos siempre separadas y con autorización explícita.
- `servicios.local.json` es el archivo maestro privado versión 2 con `project`, `deployment`, `databases.development`, `databases.production` y `deepseek`. `deployment.database_environment` vincula el despliegue con `production`. Permanece con permisos `600`, está ignorado por Git, bloqueado por `.htaccess` y excluido de toda publicación. La aplicación nunca recibe la sección `deployment` ni el mapa completo de bases.
- La cuenta FTPS probada queda confinada por el hosting a su carpeta asignada; dentro de la sesión su ruta efectiva es `/`.
- Lectura, escritura, despliegue inicial completo y primera actualización incremental verificados el 25 de agosto de 2026.
- El problema de certificado quedó resuelto: todas las cuentas de este VPS deben usar `vps-4962765-x.dattaweb.com`, que coincide con el certificado global del servicio FTP. ProFTPD ahora entrega la cadena completa mediante `TLSCertificateChainFile /var/cpanel/ssl/ftp/ftpd-ca.pem`; la validación externa devuelve `Verify return code: 0 (ok)` y la cuenta confinada autentica con `curl --ssl-reqd` sin `--insecure`.
- La corrección es global para este servidor cPanel/WHM, no por cuenta. Otros proyectos alojados en el mismo VPS reutilizan hostname y certificado, pero conservan credenciales y rutas propias. Otro VPS requiere su propia validación.
- Respaldo de la configuración anterior del servidor: `/root/proftpd.conf.before-chain-20260825`. Como cPanel puede regenerar `/etc/proftpd.conf`, cada despliegue debe validar TLS antes de autenticarse y detenerse si la directiva o la cadena desaparecen.
- El estándar incluye el alta de futuros proyectos: revisar primero los datos disponibles y preguntar solamente lo que falte o pueda cambiar el destino, los datos persistentes, la base, los permisos o la recuperación. No repetir preguntas ya resueltas en `servicios.local.json`, la documentación o el manifiesto.

### Configuración maestra de servicios

El **25 de agosto de 2026** se reemplazó el formato libre `subir.txt` por `servicios.local.json`, aprobado como estándar para reunir en forma estructurada los datos operativos del proyecto.

- El 25 de agosto de 2026 se evolucionó a `version: 2`: `database` fue reemplazada por `databases.development` y `databases.production`, cada una con etiqueta y credenciales independientes. `deployment.database_environment` vale `production`.
- DEV fue obtenido de `admin/config.local.php` sin imprimir valores; PROD conserva la antigua sección `database`. El archivo v1 quedó respaldado con permisos `600` en `.deploy/respaldos-configuracion/2026-08-25/servicios.local.before-databases-v2.json`.
- `php tools/validar-servicios.php` comprueba el contrato sin mostrar secretos: versión, ausencia de la sección legacy, ambos entornos, campos, selector, permisos y huellas no reversibles. `tools/.htaccess` bloquea acceso web y `tools/` queda fuera de despliegues.
- Los valores FTPS y de base existentes fueron migrados sin imprimirlos. El original quedó como respaldo privado con permisos `600` en `.deploy/respaldos-configuracion/2026-08-25/subir.txt`.
- `servicios.local.json` tiene permisos `600`, está ignorado por Git, bloqueado por `.htaccess` y excluido de FTPS. La plantilla segura es `servicios.example.json`.
- La conexión FTPS se probó realmente leyendo el nuevo JSON: autenticación correcta y TLS verificado, sin `--insecure`.
- `deepseek.base_url` queda en `https://api.deepseek.com`, el modelo inicial de prueba en `deepseek-v4-flash` y el timeout en 45 segundos. El usuario incorporó `deepseek.api_key` el 25 de agosto de 2026; se validó sin imprimirla mediante `GET /models`: HTTP 200, autenticación correcta y modelo configurado disponible.
- El consumo de DeepSeek quedó implementado localmente mediante `admin/mejorar-noticia.php`. La aplicación lee `admin/servicios.runtime.local.json`, derivado con solamente la sección `deepseek`; nunca recibe ni publica la sección `deployment` del archivo maestro.
- No registrar el JSON completo en consola, capturas, errores ni informes. Las validaciones deben mostrar únicamente nombres de secciones, tipos o valores redactados.
- Regla surgida de la incidencia real de Configuración: un respaldo o una migración sobre DEV nunca valida PROD. Toda operación de base debe nombrar el entorno, comparar su huella con el runtime correspondiente, respaldar ese destino y verificar allí los invariantes.

### Primer despliegue confirmado — baseline del flujo incremental

Realizado el **25 de agosto de 2026** hacia la carpeta FTPS confinada cuya URL pública es `https://digitales.uy/subir/`.

- Se desplegaron 53 archivos de aplicación, 10 archivos persistentes de uploads y una configuración privada generada para destino. No viajaron Git, documentación, credenciales FTPS, logs, diseño de referencia ni el esquema histórico `install/schema-v2-legacy.sql`.
- Se exportó la base actual mediante `mysqldump` consistente y se importó únicamente después de comprobar que la base destino tenía cero tablas. Resultado: 10 tablas importadas. El front remoto renderizó las 5 noticias esperadas.
- Los 67 archivos de la transferencia inicial —incluidos tres artefactos temporales de importación— se descargaron nuevamente y coincidieron en SHA-256, sin diferencias. Después se eliminaron del servidor el importador, el token y el volcado SQL; el resultado permanente es de 64 archivos del proyecto.
- Se conservaron sin cambios `test.txt` y `prueba-codex-2026-08-25.txt`, creados durante las pruebas previas.
- HTTP: front 200, login 200, configuración privada 404, instaladores 404, audio 200 y hash idéntico al origen. El importador temporal devuelve 404 después de su eliminación.
- QA remota en Chromium/Puppeteer porque el plugin Browser y Playwright no estaban disponibles: escritorio `1440×900` y móvil `390×844`, sin overflow horizontal; 5 noticias en ambos feeds; «Ver nota completa» abrió la hoja a 717 px con galería y votos. No se emitieron votos ni se modificaron datos durante esta QA.
- El único error de consola es el beacon de métricas que Cloudflare inyecta y que la CSP del portal bloquea; no corresponde al código de la aplicación. La única imagen con `naturalWidth = 0` es el placeholder vacío y oculto del lightbox antes de abrirlo, no un medio faltante.
- La primera ejecución reveló accesos internos `.agents`, `.codex` y enlaces `.ea-php-cli.cache` heredados del entorno. Los enlaces fueron rechazados por FTPS y las dos carpetas vacías creadas se retiraron inmediatamente. Se incorporaron como exclusiones obligatorias al estándar.
- La publicación inicial usó temporalmente la excepción TLS autorizada. El 25 de agosto se corrigieron hostname y cadena en ProFTPD, se actualizó primero `subir.txt` y luego se migró ese dato a `servicios.local.json`; `.deploy/estado.json` quedó con `tls_certificate_verified: true`. Las publicaciones siguientes no deben usar `--insecure`.
- El usuario revisó el resultado y lo confirmó como **«impresionante»**. El manifiesto provisional fue promovido a `.deploy/estado.json`: esta versión de 64 archivos es el baseline oficial para calcular los próximos despliegues incrementales.
- Este flujo queda adoptado como estándar de trabajo para el Portal de Noticias. Su réplica en otros proyectos se evaluará posteriormente y deberá comenzar por el alta documentada en `ESTANDAR_DESPLIEGUE_FTPS.md`, sin copiar credenciales ni supuestos propios de este portal.

### Primera actualización incremental confirmada

Realizada el **25 de agosto de 2026** para incorporar el botón visual deshabilitado **«Mejorar con IA»** en la barra de TipTap.

- El manifiesto detectó exactamente dos archivos modificados respecto del baseline: `admin/noticia-form.php` y `admin/assets/admin.css`. `admin/config.local.php` presentaba una diferencia local, pero fue excluido por ser configuración privada.
- Antes de reemplazar, se descargaron las dos versiones remotas y sus hashes coincidieron con `.deploy/estado.json`; no existían ediciones externas.
- Las copias anteriores quedaron en `.deploy/respaldos/2026-08-25_202803/`. Cada archivo nuevo se subió con nombre temporal, se renombró y se descargó nuevamente; ambos SHA-256 coincidieron.
- Cloudflare continuó sirviendo la URL sin versión de `admin.css` desde caché (`cf-cache-status: HIT`, `max-age=14400`). Se agregó versionado automático con `filemtime()` en `admin/includes/header.php`, se aplicó el mismo control de conflicto/respaldo/hash y la URL versionada entregó inmediatamente el CSS nuevo.
- Resultado HTTP: portada 200, CSS versionado 200 y formulario protegido redirigiendo correctamente al login. No hubo base de datos, migraciones, borrados ni cambios en uploads.
- El usuario validó producción y calificó la actualización como **«impecable»**. `.deploy/estado.json` conserva los tres hashes confirmados y es el nuevo baseline incremental.

### Actualización integral — IA, Configuración, móvil y login

Realizada el **25 de agosto de 2026** después de la autorización expresa de actualizar todo el proyecto.

- Se publicaron 18 archivos: 12 reemplazos sin conflictos y 6 altas. Incluyen el asistente completo **Crear con IA**, el drawer **Configuración → Marca de Agua**, los últimos ajustes de la nota móvil y el login con `Logo2027v3.png`, título **Panel de Gestión**, logo ampliado y menor separación.
- Antes de escribir, los 12 archivos remotos existentes coincidieron con el baseline. Sus copias quedaron en `.deploy/respaldos/2026-08-25_configuracion-ia-login/`.
- La primera comprobación de base alcanzó la conexión privada local, cuya configuración no coincide con `admin/config.local.php` de producción. Esto se detectó cuando el usuario informó que el menú no aparecía. Sin exponer credenciales, se compararon los hashes de ambas configuraciones y se confirmó la diferencia.
- Se ejecutó entonces el procedimiento correcto sobre la base real: un runner temporal protegido por token generó `production-database-before-configuracion.sql`, respaldo completo de las 10 tablas previas, aplicó la migración idempotente y verificó tabla, permiso `configuracion.gestionar`, asignación al rol `admin` y dos valores iniciales. Runner y token se eliminaron inmediatamente y ambos devolvieron 404.
- La configuración mínima de DeepSeek se transfirió en un paso privado separado y quedó con permisos `600`. `servicios.local.json`, credenciales FTPS, documentación y contenidos persistentes de usuarios no fueron publicados.
- Todos los archivos se subieron con nombre temporal, se renombraron y se descargaron nuevamente: los 18 SHA-256 coinciden. No quedaron archivos temporales ni se eliminó ningún archivo remoto preexistente.
- Validación HTTPS: portada, login, logo y CSS versionado respondieron 200; el título, `Logo2027v3.png` y el nuevo tamaño aparecen en los bytes servidos. El runtime privado y el instalador respondieron 404.

## Estado aprobado

El usuario aprobó expresamente el estado visual y funcional descrito a continuación. Al retomar, conservarlo y realizar cambios pequeños únicamente cuando sean solicitados.

### Gestión de usuarios

- La pantalla principal muestra **Gestión de Usuarios**, subtítulo y tabla; el formulario ya no aparece arriba de la tabla.
- El encabezado de la tabla incluye **Roles y permisos** y **Nuevo usuario**.
- Crear o editar abre un panel lateral derecho.
- Editar conserva nombre, correo, rol, contraseña opcional, firma/descripción y estado.
- Durante la edición aparece **Editar roles**, con acceso a `roles.php` cuando el usuario actual tiene permiso.
- Las acciones de la tabla son iconos modernos para editar y activar/desactivar.
- Los errores del servidor conservan los datos escritos y vuelven a abrir el panel.
- Archivos: `admin/usuarios.php` y `admin/assets/admin.css`.

### Feed móvil resumido desde base de datos — aprobado

El usuario aprobó el feed original el 23 de agosto y el flujo resumido actual como perfecto el **24 de agosto de 2026**. Conservarlo.

- El feed móvil **ya no es estático**: se renderiza desde la base de datos en `partials/mobile-feed.php`. Se eliminaron de `index.php` las tres noticias de ejemplo con imágenes de Unsplash.
- No agrega consultas: consume las mismas `$noticias` y `$fotosPorNoticia` que ya prepara `index.php` para el feed PC.
- Estructura por noticia: **una sola portada** a `100svh` con degradado inferior, categoría y título encima; debajo aparecen fecha, autor, un resumen en texto plano de hasta **280 caracteres** y el botón claro **«Ver nota completa»**.
- Aunque la noticia tenga varias fotos, el feed muestra solamente la portada (`posicion = 0`). La galería, los audios, videos y votos quedan dentro de la vista completa; esto reduce mucho la altura de lectura del feed.
- **Sin `scroll-snap` en móvil**: el feed se lee corrido. Fue una decisión deliberada, porque el encaje pelea con las noticias de texto largo. El `scroll-snap` sigue activo solo en PC.
- Las fotos son `<img loading="lazy" decoding="async">` con `object-fit: cover`, no `background-image`. El cambio fue necesario para que el lazy loading funcione de verdad: con imágenes de fondo el navegador no lo aplica.
- **No hay carrusel ni rotación automática en el feed móvil**. La rotación existe solo dentro de la vista completa, donde sirve para comunicar que hay más fotos.
- Noticias sin fotos: fondo degradado neutro `linear-gradient(135deg, #0f172a, #334155)`, igual criterio que `pc-media-empty`.
- Archivos: `partials/mobile-feed.php` e `index.php`.

### Panel: Votaciones — ranking de noticias más votadas

Nueva pantalla del panel, `admin/votaciones.php`, enlazada en el menú lateral entre Categorías y Usuarios. Aprobada.

- **Datos.** Lee `noticias` filtrando `me_gusta > 0 OR no_me_gusta > 0`, ordenado por total descendente. El gráfico muestra el top 8 (`VOTACIONES_TOPE`); si hay más, se avisa cuántas quedan fuera y la tabla las lista todas. No hay un límite silencioso: lo que no entra en el gráfico se explicita.
- **Dos lecturas del mismo dato**, a elección del usuario con dos botones tipo segmented control: área degradada y columnas. Un solo eje vertical en los dos casos, porque las dos series (me gusta / no me gusta) comparten unidad.
- **Colores.** Azul `#2a78d6` y rojo `#e34948`, el par divergente de la paleta de referencia del skill de dataviz — se leen como opuestos, igual que las dos opciones de voto. Validado con `validate_palette.js` sobre la superficie real del panel (`#ffffff`, no la de referencia): CVD ΔE 21.6, visión normal ΔE 32.3, muy por encima de los pisos de 8 y 15. Si el panel alguna vez suma modo oscuro, revalidar contra esa superficie antes de reusar estos hex.
- **Marcas.** Columnas de máximo 24px con extremo redondeado de 4px y base recta; líneas de 2px; marcadores de 10px de diámetro con anillo de 2px en color de superficie; área en degradado de opacidad decreciente, nunca un bloque saturado.
- **Un solo dato etiquetado**: el de la noticia líder. El resto se lee por eje, leyenda y tooltip — nunca un número clavado en cada punto.
- **Leyenda siempre presente** al ser dos series; la identidad no depende de memorizar el color.
- **Interacción.** Tooltip por hover con crosshair en modo área; se ancla arriba de la marca más alta de la noticia y se voltea hacia abajo si no entra, sin tapar nunca las etiquetas del eje vertical ni salirse del lienzo — verificado por medición, no a ojo.
- **Gemelo en tabla** (`#vizTabla`), oculto por defecto, con las mismas noticias en el mismo orden. Ningún valor queda solo detrás del tooltip.
- **Ancho.** El SVG mide `lienzo.clientWidth` **descontando el padding** antes de fijar el `viewBox`; si no se descuenta, el dibujo entero sale escalado y las medidas fijas (la columna de 24px) dejan de ser exactas. Se redibuja con `ResizeObserver` al cambiar el ancho del panel.
- **Estado vacío**: sin votos se muestra un mensaje en vez de un gráfico en blanco; ni el SVG ni `votaciones.js` se cargan en ese caso.
- **Trama 45°/135° de respaldo**, solo bajo `forced-colors`, nunca decorativa por defecto.
- Archivos: `admin/votaciones.php`, `admin/assets/votaciones.js`, estilos `.viz-*` en `admin/assets/admin.css`, entrada de menú en `admin/includes/header.php` (clave `votaciones`).

### Votos: me gusta / no me gusta

**Regla de negocio aprobada, y es la decisión central de esta etapa: un voto por visitante y por noticia, definitivo. No se deshace ni se cambia.** Por eso los contadores de `noticias` solo se incrementan y nunca pueden quedar por debajo de cero.

Se evaluó explícitamente la alternativa (contadores que espejan la tabla y pueden bajar 1 cuando el propio votante se retracta) y se descartó. No volver a introducir el deshacer sin decisión expresa: los votos ya emitidos no distinguen si fueron definitivos, así que habilitarlo después arranca con contadores que no coinciden con la tabla.

- **Esquema.** `noticias.me_gusta` y `noticias.no_me_gusta`, `INT UNSIGNED NOT NULL DEFAULT 0`. `UNSIGNED` es seguro justamente porque nunca se resta.
- **Verdad y caché.** La verdad vive en `noticias_votos`, con clave primaria compuesta `(noticia_id, visitante)`: es esa clave la que impide el segundo voto. Las dos columnas son un caché de lectura para que el feed no tenga que agrupar en cada carga.
- **Visitante.** Cookie `portal_visitante` con un UUID v4, `httpOnly`, `SameSite=Lax`, `Secure` cuando hay HTTPS, un año de vida. Se descartó identificar por IP más user-agent: los celulares salen por NAT del operador y media ciudad compartiría el voto.
- **La cookie se emite al votar, no al mirar.** `index.php` llama a `visitante_id()` sin crear; solo `votar.php` la crea. Así una visita que no vota no recibe cookie.
- **Endpoint.** `votar.php`, en la raíz y sin login, a diferencia de los de `admin/`. Solo POST; responde 405, 400, 404, 429 o 200 con `{me_gusta, no_me_gusta, mi_voto, nuevo}`. Es idempotente: si el visitante ya había votado, devuelve el estado actual sin tocar nada y con `nuevo: false`.
- **Límite por IP.** Tabla `votos_limite`, 60 votos por hora, guardando solo el hash de la IP. Es la protección real: la cookie no sirve contra peticiones directas con `curl`.
- **Concurrencia.** El registro corre en una transacción con `SELECT ... FOR UPDATE` sobre la noticia, para que dos votos simultáneos no se pisen.
- **`updated_at` se preserva** en el `UPDATE` del contador (`updated_at = updated_at`). Votar no debe contar como una edición de la noticia. La migración hace lo mismo.
- **Interfaz.** Número chiquito al lado de cada opción, con `font-variant-numeric: tabular-nums` para que el botón no salte de ancho al pasar de 9 a 10. **El cero no se muestra**: se renderiza vacío y `.vote-count:empty` lo oculta. Al votar, los dos botones de esa noticia quedan `disabled`; el votado se resalta en teal `#0f766e` y el otro se atenúa pero conserva su número.
- **En móvil el `gap` de `.feed-vote` es `22px`, más amplio a propósito**, para reducir los toques por error. Es la mitigación elegida en lugar de permitir deshacer. No bajarlo.
- **Panel.** Los contadores se ven en la columna «Votos» del listado (`admin/index.php`) y viajan en el JSON de `admin/noticia-detalle.php`.
- Archivos: `admin/includes/votos.php`, `votar.php`, `partials/acciones-noticia.php`, `install/votos-v1.php`, `install/schema.sql`, `index.php`, `admin/index.php`, `admin/noticia-detalle.php`, `admin/assets/admin.css`.

### Acciones de noticia compartidas

- El bloque de voto y compartir vive una sola vez, en `partials/acciones-noticia.php`, usado por los dos feeds. Antes estaba duplicado literal en `pc-feed.php` y `mobile-feed.php`.
- Recibe `$n`, `$misVotos` y `$prefijo` (`'pc'` o `'feed'`), que define las clases del contenedor.
- Los estilos de `.vote-btn` y `.share-btn` también se unificaron en un bloque global; el bloque de PC solo reajusta el tamaño de letra.

### Contenido enriquecido compartido (`.rich-text`)

- Las reglas de estilo del HTML de la descripción (`h1`–`h3`, listas, `blockquote`, `code`, `pre`, `mark`, `hr`, `img`, `a`, `sub`, `sup`) viven **una sola vez**, en una clase global `.rich-text` dentro de `index.php`, fuera de cualquier media query.
- Antes estaban únicamente dentro de `@media (min-width: 769px)`, por lo que en móvil una noticia con subtítulos o listas se veía sin formato. Ese era un defecto real, no una decisión de diseño.
- El bloque de PC ahora solo reajusta tamaños y márgenes (`.pc-content h1/h2/h3`, `li`, `ul`, `ol`, `blockquote`, `pre`, `img`). No volver a duplicar el conjunto completo ahí.
- La clase se aplica al contenido completo de PC y a la hoja móvil. El resumen del feed es texto plano y no usa `.rich-text`.
- Al agregar una etiqueta nueva permitida en `sanitizar_html()`, darle estilo en `.rich-text` y no en el bloque de PC.

### Publicidad provisoria, PC y móvil

- Las piezas y su orden viven en un único archivo, `partials/publicidad.php`, del que se alimentan los dos feeds. No duplicar rutas de anuncios en ningún otro lugar.
  - `$paresPublicidad`: las parejas que consume PC.
  - `$avisosPublicidad`: la misma lista aplanada, que consume móvil.
- Piezas actuales, en este orden:
  1. `imagenes/Publicidad-facha.jpg` + `imagenes/Publicidad-intendencia.jpg`.
  2. `imagenes/Publicidad-Fenix.jpg` + `imagenes/Publicidad-Digitales.jpg`.
- **PC**: después de cada noticia aparece una pantalla completa con dos anuncios cuadrados. Secuencia: noticia 1 usa la pareja 1, noticia 2 la pareja 2, noticia 3 vuelve a la pareja 1, etc. Diseño aprobado: fondo blanco, `64px` de margen exterior, `40px` entre piezas, formato `1:1`, bordes rectos y sombra inferior `0 22px 40px rgba(15, 23, 42, 0.26)`.
- **Móvil**: después de cada noticia aparece **un solo** anuncio a ancho completo, formato `1:1`, sombra `0 14px 28px rgba(15, 23, 42, 0.22)`. Se lee como una tarjeta más del feed. La decisión aprobada fue un aviso por bloque, no la pareja apilada, para no encadenar dos pantallas de publicidad seguidas. Secuencia: `facha`, `intendencia`, `Fenix`, `Digitales`, y vuelve a empezar.
- Sigue siendo una prueba provisoria y debe poder quitarse sin afectar las noticias.
- Archivos: `partials/publicidad.php`, `partials/pc-feed.php`, `partials/mobile-feed.php` e `index.php`.

### Vista completa móvil y flujo publicitario — aprobados en esta etapa

Implementada y ajustada el **24 de agosto de 2026**. Después de los cambios de resumen, galería, fullscreen y zoom, el usuario calificó el resultado como **«perfecto»** e **«impecable»** y cerró la etapa. Preservar este comportamiento. La publicidad continúa siendo provisoria y reversible; esta aprobación no autoriza convertirla en gestión dinámica.

- Es **solo para móvil**. En `partials/mobile-feed.php`, después del resumen aparece el botón transparente de borde fino **«Ver nota completa»**. El feed PC no tiene ese botón y `.story-sheet` permanece `display: none` fuera del breakpoint móvil.
- El botón abre desde abajo una hoja de `85svh`, con animación suave, bordes superiores de `28px` y bloqueo del scroll del fondo.
- Encabezado fijo de `72px`: barrita central de `40×4px`, icono SVG de diario, título **«RADIO SUR - NOTICIAS»** y cierre circular de `42×42px`.
- El contenido interno comienza con la **portada o galería** inmediatamente debajo del encabezado blanco. Después aparecen categoría, título, fecha y autor; a continuación se muestra **el primer anuncio cuadrado**, con radio de `18px`, y luego siguen la descripción completa, audios, videos y botones de voto. **El segundo anuncio** de la pareja permanece al final de la nota.
- Las dos piezas salen de la pareja que ya corresponde a esa noticia en `$paresPublicidad`; `partials/publicidad.php` sigue siendo la fuente única. Esto es un nuevo flujo de **exposición** publicitaria, no una administración dinámica ni un nuevo esquema de base de datos.
- Los anuncios que ya aparecían como bloques independientes después de cada noticia **no fueron eliminados**. La hoja se agregó como plus reversible mientras se evalúa el modelo publicitario.
- El contenido de cada noticia se guarda en un `<template>` inerte y solo se clona al abrir; así las imágenes, audios y videos propios de esta vista no se activan todos al cargar la página.
- Cierre disponible mediante cruz, fondo exterior, `Escape` y gesto táctil sobre el encabezado. La hoja acompaña el dedo hacia abajo y el fondo se aclara; un arrastre corto vuelve suavemente al `85%`, mientras que uno que supera `120px` —o un gesto rápido de al menos `42px`— completa el cierre.
- Al votar desde la hoja, se sincronizan los botones de esa misma noticia presentes en las demás instancias visibles del documento.
- Si el visor fullscreen está abierto, `Escape` cierra primero solamente el visor; la hoja permanece abierta, conserva su scroll y recupera el foco en el disparador.
- Archivos: `partials/boton-nota-completa.php`, `partials/nota-completa.php`, `partials/mobile-feed.php`, `partials/acciones-noticia.php` e `index.php`.

La prueba en un teléfono real sigue recomendada para evaluar la sensibilidad e inercia de los gestos, especialmente en Safari iOS, pero el diseño y comportamiento actual quedaron aprobados.

### Ampliación de galerías, PC y móvil

- El visor es **uno solo y compartido**, en `partials/lightbox.php`, incluido una única vez desde `index.php`. Antes vivía dentro de `partials/pc-feed.php`; no volver a duplicarlo.
- En la **vista completa móvil**, la galería se desliza horizontalmente, muestra puntos sincronizados y avanza automáticamente cada **3,2 segundos**. Se pausa durante la interacción y mientras el fullscreen está abierto; luego continúa. Respeta `prefers-reduced-motion`.
- La galería interna mide un **30% más de alto** que el antiguo `4:3`: a 390px de viewport, marco real `388×378,3px`. Cada imagen cubre exactamente el marco con `object-fit: cover`, sin margen, borde redondeado ni franja azul; puntos y lupa quedan superpuestos.
- En PC, la lupa continúa apareciendo cuando la noticia tiene más de una foto. En la hoja móvil aparece abajo a la derecha siempre que exista al menos una portada, incluso si es la única imagen, para hacer evidente que puede ampliarse. Tocar directamente la foto también abre el fullscreen en la imagen activa.
- La lupa abre un visor negro a pantalla completa con la foto que estaba activa.
- **PC**: navegación circular mediante botones anterior/siguiente o teclas izquierda/derecha; zoom con la rueda del mouse entre 100% y 400%, orientado al punto del cursor.
- **Móvil**: sin flechas. Al 100%, se navega deslizando en horizontal (umbral de 50px) y se cierra deslizando hacia abajo (umbral de 90px). Dos dedos amplían entre 100% y 400%; con zoom activo, **un dedo desplaza la imagen** en ambos ejes y los límites evitan perderla fuera del visor.
- Cierre en ambos: cruz superior derecha, tecla `Escape` o clic en el fondo exterior.
- Cada cambio de foto reinicia el zoom al 100%.
- El texto de ayuda cambia según estado/dispositivo: «Rueda del mouse para ampliar», «Pinza para ampliar» o «Arrastrá con un dedo».
- Archivos: `partials/lightbox.php`, `partials/pc-feed.php`, `partials/mobile-feed.php` e `index.php`.

### Marca de agua automática

- Todas las imágenes **nuevas** subidas por `admin/upload-imagen.php` pasan por `subir_imagen()` y reciben la marca antes de publicarse.
- El menú lateral del panel incorpora **Configuración**, inmediatamente encima de los datos del usuario conectado. Solo aparece con el permiso `configuracion.gestionar` y abre un drawer derecho responsive.
- La primera card, **Marca de Agua**, permite subir un PNG de hasta 2 MB, ajustar su intensidad entre 5% y 100% y ver el resultado al instante sobre una foto de demostración. Al editar el slider se muestra el porcentaje exacto.
- El endpoint `admin/configuracion-marca-agua.php` exige POST, sesión, permiso y CSRF; valida tipo, tamaño y dimensiones, normaliza el PNG con GD y lo guarda con nombre único en `uploads/configuracion/`.
- La configuración activa vive en la tabla genérica `configuracion`, claves `marca_agua_ruta` y `marca_agua_opacidad`. La migración CLI idempotente `install/configuracion-v1.php` crea la tabla, agrega el permiso y lo asigna al rol administrador.
- Configuración inicial y fallback seguro: `imagenes/Logo2027v2.png` (PNG transparente de 250×100) al 15%. Si la migración todavía no fue ejecutada o un archivo configurado desaparece, la subida de noticias continúa usando ese valor.
- Configuración aprobada: marca centrada y ancho equivalente al 36% de la foto; la intensidad queda definida por el panel.
- Compatible con JPG, PNG y WEBP mediante GD.
- Salida: JPEG calidad 90, PNG compresión 6 y WEBP calidad 90.
- La escritura usa un archivo temporal y reemplazo final; ante un error se elimina el archivo incompleto.
- Guardar una configuración nueva **no modifica imágenes existentes**: se utiliza solamente en las fotos subidas a partir de ese momento.
- Archivos principales: `admin/includes/funciones.php`, `admin/configuracion-marca-agua.php`, `admin/includes/header.php`, `admin/includes/footer.php`, `admin/assets/admin.css` e `install/configuracion-v1.php`.

## SEO por noticia — desplegado en PROD

Conversado el **24 de agosto de 2026**, implementado en DEV y desplegado en **PROD** el **25 de agosto de 2026**. Antes de migrar producción se verificó la huella `866bb80a5356` del runtime y se guardó el respaldo `.deploy/respaldos-db/2026-08-25/prod-before-seo-v1.sql` (23.221 bytes, SHA-256 `67cc015401c4a977e45c4c1301ea6d17d8b9b11106e4e8062fc37b0403455b8c`).

Se agregaron `noticias.slug`, `seo_titulo`, `seo_descripcion`, `seo_imagen` y la tabla `noticias_slugs_historial`. Las 7 noticias de DEV y las 6 noticias existentes en PROD recibieron slugs únicos; los tres overrides quedaron `NULL`, por lo que continúan funcionando en modo Automático sin reguardarlas. La segunda ejecución de la migración no produjo cambios en ninguno de los dos entornos.

La propuesta queda **aprobada tal como está documentada**: agregar al final de los formularios de alta y edición una card contraíble **SEO**. Por defecto no exigirá trabajo adicional al redactor y tomará los datos editoriales de la noticia; cada valor podrá personalizarse y luego volver a «Automático».

### Condición previa: una URL pública estable por noticia

- La página pública individual `noticia.php`, expuesta mediante `/noticia/{slug}`, se renderiza en servidor y es accesible sin login.
- El `slug` se genera automáticamente desde el título, debe ser único y queda estable después de publicar. La card mostrará la URL completa y permitirá editar solamente el `slug`, nunca dominio ni protocolo.
- Si se modifica un slug ya publicado, conservar el anterior y responder con redirección **301** hacia la URL nueva para no romper enlaces, historial ni posicionamiento.
- Esa URL es la canónica de la noticia y alimenta los botones funcionales de Facebook y WhatsApp.

### Comportamiento de la card SEO

- Estado inicial: badge **Automático** y resumen de los valores efectivos. Puede abrirse para personalizar.
- **Título SEO:** usa `noticias.titulo` cuando no existe override. Campo opcional, contador orientativo y acción «Volver a automático»; no imponer un corte destructivo porque Google puede reescribir o truncar el título según contexto.
- **Descripción SEO:** usa una versión en texto plano, limpia y resumida de `descripcion`. Campo opcional con contador orientativo (aproximadamente 150–160 caracteres), sin lista de palabras clave ni `meta keywords`.
- **Imagen SEO/social:** usa la portada de la galería. Se podrá elegir otra foto existente o subir una pieza dedicada, mostrando una previsualización horizontal; primera referencia recomendada para compartir: **1200 × 630 px**. Más adelante se pueden generar variantes 16:9, 4:3 y 1:1 para datos estructurados sin pedir tres cargas al usuario.
- **URL:** muestra la URL final y permite editar el slug con validación, disponibilidad y advertencia si la noticia ya estaba publicada.
- **Vista previa social en vivo:** vive en la card separada **Vista Previa** acordada al iniciar la implementación. Se actualiza inmediatamente mientras cambia título, descripción, imagen o slug y alterna entre **Al compartir** y **En Google**.
- Los overrides se guardarán como `NULL` mientras estén en automático. No copiar los valores editoriales a columnas SEO: así un cambio de título, descripción o portada se refleja solo mientras no exista una personalización expresa.
- Las noticias existentes deben funcionar inmediatamente con los fallbacks automáticos, sin obligar a abrirlas y guardarlas una por una.

### Vista previa para compartir

- La preview será una card social horizontal con proporción aproximada **1.91:1** para la imagen y, debajo o a su lado según el ancho disponible: nombre del sitio/dominio, título efectivo, descripción efectiva y URL pública.
- Debe usar exactamente la misma resolución de fallbacks que los metadatos reales: imagen SEO personalizada → portada de la noticia → imagen social predeterminada del portal; título/descripción personalizados → valores automáticos de la noticia.
- La miniatura activa, el título, la descripción y la URL se actualizan en vivo. Si se pulsa «Volver a automático», la preview vuelve inmediatamente al dato editorial correspondiente.
- En escritorio puede mostrarse completa dentro de la card SEO; en móvil debe conservar el formato sin desbordar y apilar imagen/texto cuando sea necesario.
- Mostrará avisos suaves, no bloqueantes, si falta una imagen utilizable, si el texto probablemente se truncará o si el slug todavía no es válido/disponible.
- Es una representación orientativa: Facebook, WhatsApp, X y otras plataformas pueden recortar imágenes o truncar textos de forma diferente y además conservan caché. La interfaz no debe prometer una coincidencia píxel por píxel.
- Como complemento útil, la card podrá alternar entre **Vista en Google** y **Vista al compartir** mediante dos pestañas compactas, reutilizando siempre los mismos valores efectivos y sin duplicar campos.

### Salida pública implementada

- HTML básico por noticia: `<title>`, `<meta name="description">` y `<link rel="canonical">`.
- Open Graph: `og:type=article`, `og:title`, `og:description`, `og:image`, `og:image:alt`, `og:url`, `og:site_name`, fecha, autor y categoría cuando corresponda.
- Tarjeta social grande: `twitter:card=summary_large_image` reutilizando título, descripción e imagen efectivos.
- JSON-LD `NewsArticle` generado en servidor con título, imágenes, fecha de publicación/modificación, autor, categoría, URL canónica y organización editora. Escapar HTML y construir JSON-LD con `json_encode`, nunca concatenando JSON manualmente.
- `sitemap.xml` dinámico con URLs absolutas y `lastmod` real, referencia desde `robots.txt` y alta posterior en Google Search Console.
- Validación final con Rich Results Test, inspección de URL, depuradores sociales y pruebas de redirecciones/canonicals.

### Implementación realizada

1. Crear permalink, slug único e historial de redirects 301.
2. Agregar migración idempotente y fallbacks SEO sin cambiar el resultado de las noticias existentes.
3. Construir la card SEO al final del formulario, compartida por alta y edición, con controles de «Automático» y previews en vivo de Google y de la publicación compartida.
4. Renderizar metadatos, Open Graph, tarjeta social y `NewsArticle` en la página individual.
5. Generar sitemap/robots, conectar Compartir y validar en herramientas externas.

La imagen SEO dedicada reutiliza `admin/upload-imagen.php`: aplica la misma validación segura, límite de 5 MB y marca de agua configurada que las imágenes editoriales.

### Validación SEO realizada en DEV

- Respaldo previo SHA-256 verificado; migración idempotente, 7 slugs no vacíos, cero duplicados e índice único activo.
- Flujo autenticado real con noticia temporal: automático → personalización → guardado → cambio de slug → 301. La noticia y su historial temporal se eliminaron al finalizar y el total volvió a 7.
- Metatags básicos, canonical, Open Graph, Twitter Card y JSON-LD `NewsArticle` coinciden con los valores efectivos.
- `sitemap.php` produjo XML válido con 8 URLs (home + 7 noticias); `robots.php` referencia el sitemap y el 404 público envía `X-Robots-Tag: noindex`.
- Chrome/Puppeteer: escritorio `1440×1000` y móvil `390×844`, sin overflow ni errores de consola. El plugin Browser no estaba disponible.

### Despliegue y validación SEO en PROD

- Plan incremental: 10 archivos reemplazados y respaldados en `.deploy/respaldos/2026-08-25_seo-prod/`, 5 archivos nuevos, cero eliminaciones y 15 hashes remotos iguales a los locales.
- Migración PROD idempotente: 6 noticias, cero slugs vacíos o duplicados, índice único y tabla histórica activos, cero overrides SEO creados por la migración.
- HTTPS: portada, noticia limpia, `robots.txt` y `sitemap.xml` responden 200; slug inexistente responde 404; migración y configuración privada responden 404.
- La salida real contiene descripción, canonical, Open Graph, Twitter Card y JSON-LD `NewsArticle`; sitemap lista la portada y las 6 noticias y robots referencia su URL absoluta.
- Puppeteer sobre PROD en `1440×1000` y `390×844`: identidad, contenido, imágenes, ausencia de overlay y overflow, consola, compartir y vuelta a la portada correctos. Capturas temporales en `/tmp/pntest/capturas/seo-prod-noticia-desktop.png` y `seo-prod-noticia-mobile.png`.
- Queda pendiente la confirmación visual del usuario dentro del editor autenticado y, como tareas externas, Rich Results Test, depuradores sociales y alta en Google Search Console.

### Rediseño móvil de la página individual — desplegado en PROD

Solicitado, aprobado y desplegado en **PROD el 26 de agosto de 2026** después de comprobar que la primera página individual, aunque correcta para SEO, no conservaba la experiencia visual del portal. El rediseño vive en `noticia.php`; el ajuste visual de las acciones del slide-up vive en `index.php`. No hubo cambios de base ni de metatags.

- Encabezado móvil equivalente al portal: hamburguesa funcional, `Logo2027v2.png` centrado y `Logo2027-radiosur.png` a la derecha; el menú de pantalla completa conserva Noticias, Videos y Contactos.
- Hero a `100svh` con categoría y título sobre la foto, degradado de lectura y visor ampliado mediante lupa incluso cuando existe una sola imagen. El visor reutiliza el zoom aprobado de `100%` a `400%`, porcentaje visible, pinza táctil, arrastre ampliado y reinicio al cambiar/cerrar. Con varias fotos agrega desplazamiento horizontal, puntos y rotación cada 4 segundos.
- Debajo aparecen fecha, autor, descripción enriquecida, audios, videos y acciones. Votos quedan a la izquierda; Facebook y WhatsApp, alineados a la derecha y separados por una línea vertical.
- El pie reemplaza la firma pasiva por el enlace claro **«← Ver más noticias»**, que regresa a la portada.
- La barra de acciones del slide-up **Nota completa** adopta el mismo estilo aprobado: votos a la izquierda y Facebook/WhatsApp negros a la derecha, alineados y separados por una línea vertical.
- La rotación automática de la galería individual mueve solo el carrusel horizontal y ya no altera el scroll vertical de la página. El cuerpo de la noticia conserva visualmente el HTML enriquecido permitido por el editor, incluidas citas con línea azul, negritas, cursivas, listas, títulos, enlaces, código, resaltado y separadores.
- QA local con Puppeteer: `390×844` y `360×800` sin overflow; hero de altura exacta, logo centrado, una foto con lupa y visor `1 / 1`, galería de 3 fotos con cambio mediante punto y visor `2 / 3`, menú abierto/cerrado y acciones correctamente separadas. Gestos multitáctiles reales validaron `100%` → `400%`, arrastre con un dedo y reinicio al cambiar/cerrar, tanto con una foto como con varias. Smoke test `1440×900` también sin overflow. Canonical, Open Graph, Twitter Card y `NewsArticle` permanecen intactos.
- Evidencia temporal: `/tmp/pntest/capturas/seo-noticia-mobile-hero.png`, `seo-noticia-mobile-galeria.png`, `seo-noticia-mobile-menu.png` y `seo-noticia-mobile-acciones.png`.

#### Despliegue incremental y validación en PROD

- Delta exacto: `index.php` y `noticia.php`, ambos modificados; cero altas, cero eliminaciones y ninguna migración de base.
- Antes de escribir, los dos archivos remotos coincidieron con el manifiesto previo y quedaron respaldados en `.deploy/respaldos/2026-08-26_mobile-noticia-prod/`.
- FTPS explícito con hostname canónico, TLS validado externamente y sin `--insecure`; transferencia temporal, hash previo y renombrado atómico. Los hashes finales descargados desde PROD coinciden con los locales y no quedaron temporales.
- HTTPS: portada y permalink real respondieron 200. Puppeteer en `390×844` y `360×800` verificó la galería de foto 1 a 3 con variación vertical exacta de `0px`, cita azul, negrita, títulos, ausencia de overflow y la barra de votos/redes alineada. No se emitieron votos ni se modificaron datos.
- El único aviso descartado es el beacon de Cloudflare bloqueado por la CSP, ya conocido y ajeno al código del portal. Capturas: `/tmp/pntest/capturas/prod-noticia-formato-sin-salto.png` y `prod-story-sheet-acciones-unificadas.png`.

## Mejora del texto con IA en TipTap — flujo completo implementado localmente

Conversado y aprobado el **24 de agosto de 2026**. La primera etapa visual se desplegó el **25 de agosto de 2026**. Ese mismo día se completó el flujo funcional y se publicó en producción: el botón ahora se llama **«Crear con IA»** y abre inmediatamente un drawer editorial desde la derecha. El periodista carga o corrige la información base, puede añadir indicaciones opcionales, genera una noticia, solicita otra versión si no le convence y solo la incorpora a TipTap mediante **«Agregar al editor»**. **«Cancelar»** no modifica el editor y TipTap permite deshacer una aplicación.

El endpoint `admin/mejorar-noticia.php` exige sesión, permiso de crear o editar noticias, POST y CSRF. Sanitiza entrada y salida, limita la fuente a 50.000 caracteres y las indicaciones a 2.000, aplica espera mínima de 4 segundos y un máximo de 20 solicitudes por hora y usuario. Usa Chat Completions sin razonamiento y restringe los hechos al material pegado. Para evitar resultados basados en páginas inaccesibles, fragmentos de buscador o fuentes incompletas, rechaza URLs tanto en la información base como en las indicaciones y explica que debe pegarse el contenido relevante. El prompt no permite inventar datos ni exponer explicaciones sobre el proceso. Redacta un mínimo de dos párrafos cuando la fuente lo permita, con extensión proporcional y formato periodístico moderado. El servidor compara grupos de palabras contra la fuente y contra la versión anterior: desde 84 % de similitud solicita automáticamente otra redacción y finalmente rechaza una propuesta demasiado parecida. En PC, el drawer recto ocupa aproximadamente 50 % del ancho, sin borde claro ni esquinas redondeadas; información e indicaciones quedan arriba, la noticia generada abajo y las acciones al pie.

El asistente se integra directamente en la barra de TipTap mediante el botón con destellos **«Crear con IA»**. Cada vez que se abre, si TipTap contiene texto —incluido el contenido cargado al editar una noticia— ese texto actual reemplaza la información base del drawer, evitando reutilizar una fuente anterior. Si TipTap está vacío no sobrescribe el campo y permite empezar cargando información manualmente.

### Flujo de edición previsto

- Al pulsarlo, tomará el HTML completo del editor y enviará la solicitud a un endpoint PHP interno mediante `POST`.
- La llamada al proveedor de IA se hará exclusivamente desde el servidor. La clave de API nunca debe aparecer en JavaScript, en el HTML ni en el repositorio.
- El botón mostrará el estado **«Mejorando…»** y quedará temporalmente deshabilitado para impedir solicitudes duplicadas.
- Antes de reemplazar el contenido se mostrará una vista previa clara del texto propuesto, con acciones **Aplicar cambios** y **Cancelar**. Es conveniente presentar original y propuesta de forma comparable.
- **Aplicar cambios** actualizará todo el contenido mediante la API de TipTap y conservará la posibilidad de deshacer desde el historial normal del editor. **Cancelar** no modificará nada.
- El resultado no se guardará automáticamente en la noticia: seguirá siendo necesario pulsar el botón general **Guardar** del formulario.

### Reglas editoriales y seguridad

- El pedido base debe corregir ortografía y gramática, mejorar claridad y redacción periodística, ordenar párrafos y agregar subtítulos o listas únicamente cuando ayuden a la lectura.
- La IA no podrá inventar nombres, fechas, cifras, citas ni ningún otro dato. Debe conservar el sentido, los hechos y el tono de la noticia original.
- La respuesta admitirá solamente el subconjunto de HTML soportado por TipTap y por `sanitizar_html()`. El servidor debe sanear igualmente el resultado antes de devolverlo o aplicarlo.
- El endpoint requerirá sesión válida, permiso para editar noticias, token CSRF, límites de tamaño y control de frecuencia. Los errores, timeouts o respuestas vacías deben dejar intacto el contenido original y mostrar un mensaje comprensible.
- Si la descripción está vacía, el botón no enviará ninguna solicitud y explicará que primero hay que escribir contenido.
- El proveedor, modelo, costos y límites de uso se elegirán al iniciar esta etapa. Su configuración privada debe quedar fuera del repositorio, siguiendo el mismo criterio que `admin/config.local.php`.

### Implementación sugerida por etapas

1. Construir el botón, estados y vista previa utilizando una respuesta simulada, sin consumir ninguna API.
2. Crear el endpoint autenticado, el prompt editorial y la validación/sanitización de la respuesta.
3. Conectar el proveedor elegido mediante configuración privada y establecer límites de uso.
4. Probar noticias cortas, extensas y con formato enriquecido, además de errores de red, cancelación, aplicación y deshacer.

## Próxima mejora priorizada

1. Continuar ajustando la **nueva grilla de la portada en PC** a partir de la primera versión solicitada el 26 de agosto. Debe conservar el header/slider y mostrar todas las noticias en tarjetas; la próxima intervención se limita a los detalles visuales que indique el usuario.
2. Después de cerrar la portada, diseñar la experiencia de la **página individual de la noticia en PC**. La presentación móvil sigue totalmente aprobada y no debe modificarse.
3. Más adelante, confirmar visualmente en el editor autenticado de PROD las cards **SEO** y **Vista Previa**. Después corresponderá validar la URL real con Rich Results Test, depuradores sociales y Google Search Console.

## Otros próximos pasos acordados

1. **Completado el 26 de agosto:** el slider del hero sale de la base y se administra con `noticias.portada`; ver «Slider administrable desde Noticias» al final de este documento.
2. Los botones de **compartir** ya están desplegados con Facebook y WhatsApp usando el permalink canónico; queda revisar la caché de cada plataforma con sus depuradores.
3. Evaluar más adelante la gestión dinámica de anuncios. El formato actual continúa provisorio aunque el flujo visual de la nota haya sido aprobado.
4. El sitio no tiene `favicon.ico` y el navegador lo pide en cada carga, devolviendo 404. Hay un `isotipo.png` en el directorio padre que podría servir.

El punto que antes figuraba como «implementar la ampliación y el zoom táctil de galerías cuando se trabaje en el feed móvil» quedó **hecho**; ver la sección de galerías.

## Validaciones realizadas en este punto

### Del ordenamiento estructural del front (26 de agosto de 2026)

- `index.php` pasó de 2.790 a 142 líneas al mover sin reescritura sus 1.915 líneas de CSS a `assets/css/portal.css` y sus 736 líneas de interacción a `assets/js/portal.js`.
- `noticia.php` pasó de 262 a 137 líneas: sus estilos viven en `assets/css/noticia.css` y sus interacciones en `assets/js/noticia.js`. Los condicionales PHP del script fueron reemplazados por inicialización defensiva desde el DOM, conservando el estado 404 sin galería ni votos.
- Los cuatro assets se cargan con versión automática por `filemtime()`. La URL del endpoint de votos permanece resuelta por PHP y viaja al JavaScript mediante `data-vote-url`; no se expusieron configuraciones privadas.
- Validación estática: `php -l` correcto, `node --check` correcto y `git diff --check` correcto. Los bloques CSS extraídos coincidieron en SHA-256 con sus fuentes; el JavaScript de la portada también coincidió después de sustituir únicamente la URL dinámica.
- Chromium/Puppeteer local con datos DEV: portada a `1440×900` y `390×844`, siete noticias, cero overflow, visor PC y hoja completa móvil operativos. Página individual válida y 404 en ambos tamaños, sin errores JavaScript; visor de tres fotos verificó teclado `1 / 3` → `2 / 3` y cierre con `Escape`.
- El único error de consola ajeno al refactor continúa siendo la petición conocida de `favicon.ico` inexistente. Browser plugin y Playwright no estaban disponibles; se reutilizó el entorno Puppeteer existente. No se emitieron votos ni se modificaron datos.

### De Configuración y marca de agua editable (25 de agosto de 2026)

- La migración `install/configuracion-v1.php` se ejecutó y verificó en la base de producción: tabla, permiso, asignación al administrador y valores iniciales correctos.
- `php -l` correcto en funciones, encabezado, pie, endpoint y migración; `git diff --check` correcto.
- Prueba de escritorio con sesión real en Chrome/Puppeteer a `1440×1000`: posición del botón, apertura/cierre, drawer derecho, encabezado degradado, card, preview, slider y estados accesibles correctos.
- El slider se movió al 42% y la vista previa reflejó una opacidad calculada de `0.42`; el guardado real respondió correctamente y luego se restauró el 15%.
- Se hizo una carga PNG real por HTTP al 31%, se verificó en la configuración activa y luego se restauró `imagenes/Logo2027v2.png` al 15%; el archivo exclusivo de prueba fue eliminado de forma controlada.
- Prueba responsive a `390×844`: drawer de ancho completo, sin desborde horizontal, con la card y el preview visibles; al abrirlo se cierra el menú móvil. Sin errores de JavaScript ni pantallas superpuestas.
- Las pruebas de navegador fueron en Chrome headless; queda pendiente la validación táctil en un teléfono real/Safari iOS.

### Del feed resumido, galería y zoom móvil (24 de agosto de 2026)

- Feed resumido: una portada, fecha, autor, extracto de 280 caracteres y botón; medios y votos reservados a la nota completa. Chrome/Puppeteer en `390×844` y control PC `1440×900`: **17/17**.
- Galería de la hoja: autoplay, puntos, lupa, apertura tocando la foto, prioridad correcta de `Escape`, conservación de scroll/foco y reanudación del autoplay: **19/19**.
- Zoom/pan: pinza emulada hasta 356%, arrastre con un dedo en X/Y sin cambiar de foto, límites del visor, reinicio a 100% y regresión de rueda PC: **14/14**.
- Cobertura/altura: las tres imágenes reales midieron igual que sus marcos (`388×378,3px`), aumento exacto de 30%, sin franja azul y con puntos/lupa dentro: **9/9**.
- `php -l` de los PHP modificados y `git diff --check` correctos. Las pruebas fueron de lectura; no emitieron votos ni modificaron datos.

### De la vista completa móvil — validación inicial (24 de agosto de 2026)

- `php -l` correcto en `index.php`, `partials/nota-completa.php`, `partials/mobile-feed.php`, `partials/pc-feed.php` y `partials/acciones-noticia.php`; `git diff --check` correcto.
- Puppeteer/Chrome headless contra datos reales: **23/23 controles correctos** en móvil `390×844`, más verificación PC `1440×900`.
- Geometría medida: panel `717/844px` (`85%`), encabezado `72px`, cierre `42×42px`, barrita `40×4px`, margen superior del primer anuncio `26px` y radio `18px`.
- Se verificó que PC no renderiza el disparador y que la hoja está oculta fuera de móvil.
- Gesto táctil emulado: arrastre corto de `70px` siguió al dedo y volvió exactamente a su posición; arrastre largo de `150px` aclaró el fondo y cerró completamente. La cruz continuó funcionando después del gesto.
- Se verificaron los dos anuncios, la galería, el contenido editorial, los dos votos, el scroll interno, el encabezado fijo y la restauración del foco, sin errores relevantes de JavaScript o red.
- Esta validación **no emitió votos ni modificó datos**. Chrome headless emula el dedo; la sensibilidad en hardware real sigue pendiente aunque el diseño actual ya fue aprobado.

### De los votos (23 de agosto de 2026)

- `php -l` correcto en `index.php`, `votar.php`, `admin/includes/votos.php`, `install/votos-v1.php`, `partials/acciones-noticia.php`, `admin/index.php` y `admin/noticia-detalle.php`.
- `node --check` del JavaScript y llaves del CSS balanceadas (219/219).
- `install/votos-v1.php` corrido **realmente** contra la base: columnas agregadas, tablas creadas, y verificado que es idempotente.
- Flujo real por HTTP contra la base, con cookies: primer voto devuelve `nuevo: true`; el segundo voto del mismo visitante devuelve `nuevo: false` sin alterar el contador; el intento de **cambiar** a la opción opuesta conserva el voto original y no mueve ningún número; otro visitante sin cookie sí cuenta.
- Códigos de error verificados: 405 en GET, 400 con `valor` inválido, 404 con noticia inexistente, 429 al pasar el techo por IP (cortó según lo esperado).
- Invariantes verificadas sobre la base después de 57 votos: **cero contadores desincronizados** respecto de `noticias_votos` y **cero valores negativos**.
- Render verificado: sin votos, los 24 `vote-count` salen vacíos; después de votar dos noticias, aparecen 4 botones `voted` y 8 `disabled`, o sea las dos noticias por dos botones por dos feeds, y el único número mostrado es `1`.
- El HTML del render con votos pasa el parser de etiquetas sin huérfanas ni sin cerrar.
- `git diff --check`: correcto.
- **Los 57 votos de prueba se borraron de la base y los contadores volvieron a cero.** La base quedó limpia.

### Del feed móvil (23 de agosto de 2026)

- `php -l` correcto en `index.php`, `partials/pc-feed.php`, `partials/mobile-feed.php`, `partials/publicidad.php` y `partials/lightbox.php`.
- `node --check` sobre el JavaScript extraído de `index.php`: correcto.
- Llaves del CSS balanceadas: 214 de apertura y 214 de cierre.
- Render PHP simulado con cinco noticias (una con tres fotos, una con una sola, tres sin fotos): una galería con tres frames, una foto única, tres fondos neutros, cinco bloques de publicidad. Secuencia de avisos obtenida: `facha`, `intendencia`, `Fenix`, `Digitales`, `facha`.
- El feed PC quedó verificado como intacto en el mismo render: diez paneles, o sea cinco parejas, con la misma secuencia que antes, y `pcGalleryLightbox` ya no aparece dentro de `partials/pc-feed.php`.
- Render real contra la base de datos mediante `php -S`: HTTP 200 con seis noticias, cuatro con galería, una sin fotos, seis bloques de publicidad y el visor generado una sola vez.
- El HTML producido por ese render real pasa un parser de etiquetas sin huérfanas ni sin cerrar.
- `git diff --check` sobre los archivos modificados: correcto.
- **El usuario probó el feed móvil en su entorno y lo aprobó como impecable.**

### Del punto de pausa anterior

- `php -l admin/usuarios.php`: correcto.
- `php -l admin/includes/funciones.php`: correcto.
- Render PHP simulado: la lupa aparece una vez con múltiples fotos, no aparece con una sola y el visor global se genera una sola vez.
- Alternancia simulada con tres noticias: pareja 1, pareja 2, pareja 1.
- Marca de agua probada realmente sobre JPG, PNG y WEBP; dimensiones y formato resultantes correctos, con inspección visual de la salida JPG.

## Validación del gráfico de Votaciones (23 de agosto de 2026)

- Se corrió `validate_palette.js` (del skill de dataviz) contra la superficie real del panel, no la de referencia — ver la sección de Votaciones arriba.
- Suite dedicada `~/tools/pruebas-navegador/prueba-votaciones.js`, 24 comprobaciones con clic e inicio de sesión real: el menú muestra la entrada y se marca activa, el SVG llena el ancho del panel y su `viewBox` coincide exactamente con el render (0 desfase), los dos modos dibujan lo esperado, las columnas no superan 24px, el tooltip trae título + los dos valores + total, la tabla lista las noticias en el orden correcto, y el redibujado ocurre al angostar la ventana.
- `prueba-tooltip.js` verificó por medición, en los dos modos y sobre las 6 noticias, que el tooltip nunca se sale del lienzo ni tapa las etiquetas del eje — encontró y confirmó la corrección de un defecto real (ver abajo).
- `prueba-vacio.js` confirmó el estado sin votos: aparece el mensaje, no se dibuja el SVG y no se carga `votaciones.js`.
- **Para probar con login se creó un usuario administrador temporal** (`prueba-viz-temporal@localhost.invalid`) y votos de prueba sobre las 6 noticias reales. Los dos se borraron al terminar; se verificó que el conteo de usuarios volvió a 5 y los contadores de voto a cero.
- **Defecto real encontrado y corregido**: el ancho del SVG se tomaba de `lienzo.clientWidth` sin descontar el padding, así que el `viewBox` no coincidía con el ancho renderizado y todo el dibujo salía escalado ~2.5%. Con eso las columnas de 24px rendían 23.4px. Se corrigió restando el padding antes de fijar el `viewBox`.
- **Segundo defecto encontrado y corregido**: el tooltip se anclaba al tope fijo del trazado, así que en la noticia líder (la marca más alta) se dibujaba pegado al eje superior en vez de sobre su propia marca. Se agregó anclaje a la marca de la noticia resaltada, volteo hacia abajo cuando no entra arriba, y límites explícitos al área de trazado para que nunca tape las etiquetas del eje vertical.

## Entorno de pruebas de navegador

**Ya existe. No hace falta volver a instalarlo, y no volver a anotar «no hay navegador» como límite.**

- Ubicación: `~/tools/pruebas-navegador`. Está **fuera de `public_html` a propósito**: `node_modules` no debe quedar accesible por HTTP.
- Puppeteer 20.9.0 con su propio Chrome for Testing 115 en `~/.cache/puppeteer`. Se instaló sin root, con `npm`. Ocupa unos 370 MB entre las dos carpetas.
- Se eligió la rama 20 de Puppeteer porque el Node del servidor es v16.20.2 y la 21 en adelante exige Node 18.
- Las 19 librerías compartidas que Chromium necesita ya estaban en el sistema, que es el bloqueo habitual en hostings compartidos. No hubo que instalar nada del sistema.
- `~/tools/pruebas-navegador/correr.sh` y `prueba-feed.js` corresponden al feed anterior: todavía buscan carrusel y votos dentro del feed. **No usarlos como suite vigente sin actualizarlos** al resumen + hoja; además el wrapper pone los votos en cero antes y después.
- Las verificaciones actuales de resumen, galería, pan y altura se ejecutaron con scripts dedicados temporales en `/tmp/pntest/`; no forman parte del repositorio y `/tmp` se limpia.
- Archivos persistentes del entorno: `prueba-feed.js` (baseline histórico), `medir.js` y `correr.sh`.
- Capturas en `/tmp/pntest/capturas`. Son temporales: `/tmp` se limpia.
- Al escribir pruebas de votos, que las aserciones sean **relativas** al conteo previo, no a un número fijo. Cada corrida usa un perfil nuevo de navegador, o sea un visitante nuevo, así que el contador sube. Ese error ya se cometió una vez.

## Límites de validación

- **Ya hay navegador en el entorno** (ver la sección anterior). La suite vigente de esta etapa fue la batería temporal documentada arriba (17/17, 19/19, 14/14 y 9/9); la suite persistente de 29 controles queda como baseline histórico y necesita adaptación.
- Lo que **sigue sin verificarse** es el comportamiento en hardware táctil real: Chrome headless emula los eventos de puntero, no un dedo. Los gestos finos, la inercia del deslizamiento nativo y el rendimiento en un teléfono de gama baja necesitan una prueba manual.
- Tampoco se probó en Safari de iOS, que es el motor con más diferencias en `100svh`, `scroll-snap` y `touch-action`.
- Quien confirmó visualmente el resultado en un dispositivo real fue el usuario, que aprobó el feed móvil, los anuncios y la marca de agua como impecables.
- La base de datos **sí** respondió en este punto: el render por `php -S` se hizo contra los datos reales. En el punto anterior no había respondido desde la terminal.
- No se ejecutó un flujo completo autenticado del panel desde CLI, así que la columna «Votos» del listado y el JSON del detalle se validaron por sintaxis y por consulta, no viéndolos en el panel.
- Los votos se probaron de punta a punta, incluido el clic real en el navegador: el `fetch` a `votar.php`, el repintado del número, el resaltado del botón y el bloqueo de los dos botones.
- **Efecto colateral ya ocurrido en la base:** la primera versión de `install/votos-v1.php` reconciliaba los contadores sin preservar `updated_at`, así que las seis noticias existentes tienen hoy un `updated_at` posterior a su `created_at` real. El script quedó corregido, pero el dato ya cambió. No se muestra en ninguna pantalla, así que el impacto es nulo.

## Mapa de archivos del front

Después de esta etapa el front quedó repartido así. Conviene conocerlo antes de tocar nada.

| Archivo | Responsabilidad |
| --- | --- |
| `index.php` | Consultas a la base, estructura del hero, `include` de los partials y configuración mínima de la URL de votos. |
| `assets/css/portal.css` | Todo el CSS compartido de la portada para PC y móvil, extraído sin modificar reglas y cargado con versión `filemtime()`. |
| `assets/js/portal.js` | Interacciones compartidas de la portada: hero, menú, hoja completa móvil, visor y votos. |
| `assets/css/noticia.css` | Estilos de la página pública individual, incluida su adaptación PC/móvil y el visor propio. |
| `assets/js/noticia.js` | Menú, carrusel, visor con zoom/gestos/teclado y votos de la página pública individual; se inicializa según los elementos presentes. |
| `partials/mobile-feed.php` | Marcado del feed móvil. |
| `partials/pc-feed.php` | Grilla resumida de tarjetas para la portada PC. |
| `partials/publicidad.php` | Piezas de publicidad y su orden. Fuente única. |
| `partials/boton-nota-completa.php` | Botón móvil «Ver nota completa». |
| `partials/nota-completa.php` | Hoja móvil aprobada, contenido completo, galería y doble exposición publicitaria provisoria. |
| `partials/lightbox.php` | Marcado del visor ampliado. Compartido. |
| `partials/acciones-noticia.php` | Marcado de voto y compartir. Compartido. |
| `votar.php` | Endpoint público de votos. Raíz, sin login. |
| `admin/includes/votos.php` | Cookie de visitante, límite por IP y registro del voto. |
| `admin/votaciones.php` | Pantalla del panel: ranking de noticias más votadas. |
| `admin/assets/votaciones.js` | Dibuja el gráfico (SVG a mano, sin librería). |

Se mantienen **dos partials de feed** porque móvil y PC son experiencias deliberadamente independientes. La contrapartida asumida es que cada dispositivo descarga el marcado del otro oculto por CSS, con contenido duplicado para lectores de pantalla y SEO. Unificarlos requiere una etapa propia y un pedido explícito.

## Regla al retomar

Leer primero `AGENDA.md`, este archivo y `README.md`, confirmar la carpeta oficial y revisar el estado de los archivos sin descartar ni sobrescribir cambios existentes. `AGENDA.md` contiene solo el trabajo pendiente elegido; este archivo conserva el detalle técnico y las decisiones aprobadas. El feed resumido, la hoja completa, la galería alta con autoplay, el visor con zoom/arrastre, **Crear con IA**, el drawer de **Configuración → Marca de Agua** y el login renovado están desplegados y fueron aprobados por el usuario como excelentes: preservarlos.

El estándar operativo también quedó aprobado: `servicios.local.json` versión 2 separa DEV y PROD, el validador comprobó que cada bloque coincide con su runtime real, y toda migración futura debe declarar el entorno, respaldarlo y verificarlo por separado. La incidencia de la primera migración de Configuración quedó corregida en la base real de producción; el usuario confirmó que el menú apareció inmediatamente y lo calificó como impecable.

Punto de cierre del **26 de agosto de 2026**: todo el trabajo aprobado quedó consolidado en el commit **`feat: consolidar portal aprobado en produccion`** y marcado con la etiqueta local **`prod-2026-08-26`**. SEO/permalinks, el rediseño móvil de la noticia individual, las correcciones de galería/formato enriquecido y la barra unificada de acciones fueron respaldados, desplegados y validados en PROD. El usuario revisó el resultado y cerró la etapa con **«Genial»** y **«totalmente aprobado todo»**; `.deploy/estado.json` quedó confirmado.

Al retomar, la única prioridad nueva acordada es diseñar la experiencia de la página individual en **PC**, que todavía no convence. Preservar como baseline cerrado todo el comportamiento móvil actual y no desplegar una nueva propuesta de escritorio hasta que el usuario la revise. No descartar, resetear ni volver a desplegar por inferencia; hero, anuncios dinámicos y unificación de partials requieren pedido explícito. Desde este checkpoint, cada mejora aprobada debe quedar en un commit pequeño y descriptivo.

### Pausa posterior al ordenamiento estructural — 26 de agosto de 2026

El usuario aprobó el refactor como **«perfecto, impecable»** y pidió dejar todo pronto para regresar más tarde y comenzar la vista de PC. El cierre técnico está en `83270c6 refactor: extraer interacciones de la noticia`, precedido por tres commits separados para CSS/JavaScript de portada y noticia. La rama `main` quedó limpia, siguiendo `origin/main`, con los cuatro commits publicados en el repositorio privado de GitHub.

Este refactor existe únicamente en el checkout y en GitHub: **no fue desplegado a PROD**. Producción conserva el baseline aprobado `27ca624` / `prod-2026-08-26`. Al regresar, comenzar leyendo `AGENDA.md`, revisar visualmente la página individual actual en escritorio y conversar la composición antes de editar. No iniciar otra tarea, no tocar móvil y no desplegar hasta que el usuario revise la propuesta de PC.

### Primera versión de la nueva portada PC — 26 de agosto de 2026

El usuario cambió explícitamente la prioridad: antes de la página individual se comenzó por desmantelar la portada PC debajo del header/slider. `partials/pc-feed.php` ahora presenta las siete noticias actuales como tarjetas editoriales completas, con portada `3:2`, categoría, fecha compacta, título, resumen y enlace al permalink. Las tarjetas son rectangulares y no tienen bordes redondeados.

La grilla usa tres columnas desde `1100px` y dos entre `769px` y `1099px`; la experiencia móvil continúa siendo independiente hasta `768px`. Se retiraron del home PC el antiguo artículo a pantalla completa, `scroll-snap`, anuncios intercalados, mini-slider, ampliación de galería y ayuda de scroll, junto con su CSS y JavaScript específicos. El header y su slider no fueron modificados.

Validación DEV: PHP, JavaScript y diff correctos; Chromium/Puppeteer a `1440×900`, `900×900` y `390×844`, con siete tarjetas, 3/2 columnas según el ancho, cero overflow y enlaces/imágenes válidos. El clic sobre la primera tarjeta abrió el permalink y el título esperado sin errores. Las capturas móvil anteriores y posteriores resultaron idénticas byte por byte. Browser plugin y Playwright no estaban disponibles, por lo que se reutilizó Puppeteer. Esta propuesta **no está desplegada en PROD** y debe recibir la revisión visual del usuario antes de cualquier publicación.

### Drawer de noticia completa para la portada PC — 26 de agosto de 2026

Después de aprobar la grilla como **«impecable»**, el usuario pidió que el clic normal no abandone la portada: ahora abre la noticia completa en un panel lateral recto desde la derecha, de ancho exacto `40vw` y altura completa. El fondo oscurece la portada y el panel se cierra con su cruz, el backdrop o `Escape`; al cerrar restaura el scroll y el foco a la tarjeta. Los clics modificados sobre el enlace mantienen el comportamiento nativo del permalink.

No se creó una segunda versión de la noticia. El drawer clona el mismo `<template>` inerte de `partials/nota-completa.php` que usa la hoja móvil, conservando slider con autoplay y puntos, galería fullscreen con navegación/zoom, contenido enriquecido, anuncios, audios/videos opcionales, votos definitivos y enlaces de Facebook/WhatsApp. En PC la misma hoja cambia únicamente su geometría mediante `@media (min-width: 769px)`; móvil conserva su panel inferior al `85svh`.

QA DEV con Chromium/Puppeteer: a `1440×900` el panel midió `576×900px`; a `900×900`, `360×900px`; en ambos casos fue exactamente 40%, borde derecho `0`, radio `0`, sin overflow ni errores. Una noticia real con tres fotos avanzó automáticamente del índice 0 al 1, el fullscreen navegó de `2 / 3` a `3 / 3` y `Escape` cerró primero el visor manteniendo abierto el drawer. Se comprobaron dos botones de voto y URLs válidas de Facebook/WhatsApp sin emitir votos. El cierre por backdrop limpió el contenido, restauró el scroll y devolvió el foco. A `390×844`, la hoja móvil siguió midiendo `717,4px` (85%), ancho completo, radio `28px 28px 0 0`; la captura cerrada del feed coincidió byte por byte con el baseline. **No desplegado en PROD.**

### Herramienta accesible de tamaño de lectura — 26 de agosto de 2026

Debajo del primer anuncio y antes del primer párrafo se agregaron dos controles circulares de `44×44px`, `A−` y `A+`, compartidos por el drawer PC y la hoja móvil. Ajustan únicamente el cuerpo editorial —párrafos, listas y subtítulos— en pasos de 10%, desde 90% hasta 140%; no alteran título, fotos, anuncios, medios ni acciones. Cada extremo deshabilita su botón, las etiquetas accesibles son «Achicar texto» y «Agrandar texto», y una región `aria-live` anuncia el porcentaje sin agregar ruido visual. Cada apertura comienza nuevamente en 100%.

QA DEV a `1440×900` y `390×844`: posición correcta entre anuncio y texto, botones circulares exactos, etiquetas correctas y cero overflow/errores. El párrafo real midió `16,8px` al 100%, `18,48px` al 110%, `23,52px` al 140% y `15,12px` al 90%; los límites, estados deshabilitados y reinicio a 100% se verificaron en ambas vistas. No se emitieron votos ni se modificaron datos. **No desplegado en PROD.**

### Filas publicitarias dentro de la grilla PC — 26 de agosto de 2026

Después de cada grupo completo de tres noticias, `partials/pc-feed.php` intercala una fila publicitaria independiente con `Publicidad-intendencia.jpg`, `Publicidad-Fenix.jpg` y `Publicidad-facha.jpg`, en ese orden. Las rutas y textos alternativos se centralizan en `partials/publicidad.php`. Con las siete noticias actuales aparecen dos filas: después de la tercera y de la sexta; no se agrega una fila después del último grupo incompleto.

Desde `1100px` los tres avisos ocupan las mismas columnas y prácticamente la misma dimensión total que las tarjetas de noticia. Entre `769px` y `1099px` la fila conserva su agrupación y responde en dos columnas. Las imágenes cuadradas se muestran completas con `object-fit: contain`, sin recorte ni bordes redondeados, y cada pieza incluye una franja discreta «Publicidad». No se agregaron enlaces porque todavía no se definieron destinos comerciales.

QA DEV con Chromium/Puppeteer: a `1440×900`, siete noticias, dos filas, seis avisos y secuencia estructural `NNNANNNAN`; tres columnas, ancho idéntico de `442,66px` y diferencia de alto menor a `0,3px`. A `900×900`, dos columnas, ancho idéntico de `417px` y diferencia de alto menor a `0,4px`. Las seis imágenes cargaron en `1200×1200`, el drawer continuó operativo y no hubo overflow ni errores. A `390×844`, el feed PC permanece oculto, las imágenes publicitarias PC no se descargan por su carga diferida y la captura móvil coincide byte por byte con los dos baselines anteriores. **No desplegado en PROD.**

### Prueba de filas híbridas con posición variable — 26 de agosto de 2026

La prueba anterior de tres anuncios juntos fue sustituida por una composición más editorial. Después de cada grupo completo de tres noticias, el sistema toma la noticia siguiente y crea una fila híbrida con esa tarjeta y dos avisos. La noticia no se duplica: se consume dentro de la fila y el listado continúa desde la siguiente. Con las siete noticias actuales la secuencia superior es `NNNMNNN`, donde `M` contiene `ANA` en este conjunto de datos.

La ubicación de la noticia se obtiene mediante una semilla estable basada en su ID, slug y número de fila; por eso puede ocupar izquierda, centro o derecha entre futuros bloques sin saltar de sitio en cada recarga. Los avisos rotan por el banco provisorio de Intendencia, Fenix y Facha y pueden invertir su orden. Este contrato permite reemplazar luego el arreglo local por el futuro administrador de publicidad sin rehacer la presentación. El marcado común de las noticias se extrajo a `partials/pc-news-card.php` para que la tarjeta conserve exactamente la misma anatomía y comportamiento en ambas ubicaciones.

QA DEV con Chromium/Puppeteer: a `1440×900`, siete noticias únicas, una fila híbrida, dos anuncios, tres columnas y alturas idénticas de `528,67px`; el bloque actual quedó Fenix–noticia–Intendencia. A `900×900`, la fila respondió en dos columnas con los tres elementos a `417px` de ancho y `484px` de alto. El clic sobre la noticia híbrida abrió correctamente el drawer; no hubo overflow, errores de consola ni imágenes faltantes. A `390×844`, PC permanece oculto y la captura móvil volvió a coincidir byte por byte con el baseline aprobado. **No desplegado en PROD.**

### Un anuncio por cada fila de noticias PC — 26 de agosto de 2026

La composición anterior de dos anuncios y una noticia fue sustituida por la regla elegida para esta prueba: cada pareja completa de noticias forma una fila de tres elementos con un solo anuncio. La semilla estable se calcula a partir del conjunto ordenado de noticias y determina tanto el punto inicial como el sentido de dos rotaciones independientes: ubicación del aviso y anunciante. De este modo los anuncios recorren izquierda, centro y derecha sin moverse caprichosamente en cada recarga, y las piezas rotan entre Intendencia, Fenix y Facha. Al cambiar el conjunto editorial la distribución puede renovarse. Si el total de noticias es impar, la última queda sola y nunca se duplica.

Con las siete noticias DEV actuales se generan tres filas híbridas y una noticia final: `NAN`, `NNA`, `ANN`, `N`. Los anunciantes correspondientes son Fenix, Facha e Intendencia. En escritorio ancho las nueve tarjetas de las filas completas miden aproximadamente `442,67×528,67px`, conservando una cuadrícula uniforme. El partial compartido `pc-news-card.php` sigue asegurando que las seis noticias mezcladas tengan el mismo drawer, enlaces y anatomía que la noticia final.

QA DEV con Chromium/Puppeteer a `1440×900`, `900×900` y `390×844`: siete IDs de noticia únicos, tres avisos distintos, tres posiciones diferentes, cero overflow, cero overlays y cero errores de consola; el clic de una tarjeta dentro de la fila abrió el drawer. En el rango compacto las filas responden en dos columnas según el comportamiento previamente aceptado. En móvil el feed PC permanece oculto, sus anuncios diferidos no se descargan y la captura coincide byte por byte con el baseline aprobado. **No desplegado en PROD.**

### Accesos sociales en las cards publicitarias PC — 26 de agosto de 2026

El pie de cada aviso conserva «Publicidad» a la izquierda y suma, después de un divisor vertical, cuatro accesos negros alineados a la derecha: Facebook, Instagram, WhatsApp y sitio web. Se reutilizó el lenguaje visual de las acciones de noticia: áreas de `24×24px`, SVG de `20×20px`, atenuación y desplazamiento sutil en hover, además de foco visible para teclado. Cada enlace abre en pestaña nueva con `noopener noreferrer` y anuncia tanto la red como el nombre del anunciante.

El banco provisorio de `partials/publicidad.php` ya modela por anuncio `nombre`, `facebook_url`, `instagram_url`, `whatsapp_url` y `sitio_web_url`. El render acepta solamente URLs válidas con protocolo HTTP/HTTPS y omite campos vacíos o inseguros, dejando listo el contrato para sustituir el arreglo por registros de base. Los valores actuales son demostrativos: no deben considerarse perfiles comerciales confirmados ni publicarse sin completar la futura administración.

QA DEV con Chromium/Puppeteer a `1440×900` y `900×900`: tres cards, cuatro accesos por card, doce enlaces totales, todos negros (`rgb(17,17,17)`), de `24×24px`, con etiquetas específicas, foco operativo, `_blank` y `noopener noreferrer`. Las filas conservaron sus dimensiones anteriores, el drawer continuó funcionando y no aparecieron overflow, overlays ni errores de consola. A `390×844`, la vista PC sigue oculta y la captura móvil permanece idéntica byte por byte al baseline. **No desplegado en PROD.**

### Jerarquía del menú admin y entradas de Publicidad — 26 de agosto de 2026

El menú lateral del panel ahora agrupa **Usuarios** como acordeón con las opciones **Usuarios** y **Roles**, respetando por separado los permisos existentes `usuarios.gestionar` y `roles.gestionar`. Se creó además el grupo **Publicidad**, con las opciones **Anuncios** y **Popups**. Al abrir un grupo se cierra el otro; la página activa deja su grupo desplegado desde el servidor y marca la subopción correspondiente. El comportamiento se conserva dentro del menú móvil.

`admin/anuncios.php` y `admin/popups.php` nacieron como pantallas iniciales deliberadamente mínimas para que las nuevas rutas fueran válidas. En ese checkpoint todavía no incluían formularios, consultas, persistencia, permisos publicitarios ni reglas de publicación; el estado posterior del módulo Anuncios se documenta al final de este archivo.

QA DEV con Chromium/Puppeteer: en escritorio `1440×950`, las cuatro subopciones, estados activos, apertura exclusiva y rutas de Usuarios, Roles, Anuncios y Popups funcionaron sin overflow ni errores. En móvil `390×844`, el hamburger, backdrop, acordeones y estado activo funcionaron sin recortes ni errores. PHP, CSS y diff también quedaron correctos. Browser plugin no estaba disponible, por lo que se reutilizó Puppeteer. **No desplegado en PROD.**

### Noticias y Análisis dentro de la jerarquía del admin — 26 de agosto de 2026

La misma navegación se extendió sin alterar las pantallas existentes: **Noticias** pasó a ser un grupo con **Noticias** y **Categorías**, manteniendo `categorias.gestionar` sobre la segunda opción. Debajo de Publicidad se creó **Análisis**, con icono de gráfica, y dentro se trasladó **Votaciones**, identificada por una mano de aprobación. La clave activa `votaciones` y toda la lógica del ranking permanecen intactas.

Los cuatro grupos —Noticias, Usuarios, Publicidad y Análisis— comparten el acordeón exclusivo. Cada ruta abre desde el servidor el grupo que le corresponde; en Votaciones queda seleccionado Análisis, y en Noticias/Categorías queda seleccionado Noticias.

QA DEV con Chromium/Puppeteer a `1440×950` y `390×844`: Votaciones devolvió HTTP 200, mostró el icono solicitado y el estado activo correcto; Noticias y Categorías conservaron sus títulos y rutas, abriendo su grupo correspondiente. El intercambio entre grupos cerró el anterior, no hubo overflow, overlays ni errores de consola. La sesión temporal fue de solo lectura y no modificó votos ni otros datos. Browser plugin no estaba disponible, por lo que se reutilizó Puppeteer. **No desplegado en PROD.**

### CRUD inicial de Publicidad → Anuncios — 26 de agosto de 2026

`admin/anuncios.php` dejó de ser un placeholder y ahora administra registros persistentes con imagen, nombre, Facebook, Instagram, WhatsApp, Web, fecha de vencimiento, fecha de creación y actualización técnica. La tabla muestra miniatura, los cuatro destinos mediante iconos, vencimiento, creación, estado Vigente/Vencido y acciones de editar y borrar. **Nuevo Anuncio** queda centrado sobre la grilla y abre un drawer lateral; el formulario mantiene todos los campos en una columna, comienza por el uploader con vista previa inmediata y conserva la imagen actual al editar si no se selecciona otra.

La imagen y el nombre son obligatorios. Las cuatro URLs son opcionales, se limitan a 500 caracteres y solo aceptan HTTP/HTTPS. La fecha puede quedar vacía para una pieza permanente; si existe, el anuncio se considera vencido desde el comienzo de esa fecha. Las imágenes viven en `uploads/publicidad/`, sin la marca de agua editorial, con tipos JPG/PNG/WEBP, máximo 5 MB, validación real de contenido, límite de píxeles, nombres aleatorios y bloqueo de ejecución. Al reemplazar o borrar un anuncio también se elimina su archivo anterior.

La migración CLI idempotente `install/publicidad-v1.php` crea `anuncios`, su índice `idx_anuncios_vencimiento` y el permiso `publicidad.gestionar`, asignado inicialmente al rol `admin`. El menú Publicidad, `anuncios.php` y el placeholder `popups.php` exigen ese permiso. Para instalaciones nuevas, `install/schema.sql` y `install/security-v1.php` contienen el mismo contrato.

La migración se aplicó **únicamente en DEV** después de confirmar que el runtime local correspondía exclusivamente a `databases.development`, huella `aae27debb310`, y crear el respaldo lógico privado `.deploy/respaldos-db/2026-08-26/dev-before-publicidad-v1.sql` de 29.886 bytes. La verificación posterior confirmó 10 columnas, índice, permiso, asignación al administrador y cero registros iniciales. PROD no fue migrado ni recibió archivos.

QA reversible con Chromium/Puppeteer a `1440×950` y `390×844`: HTTP 200, estado vacío, botón centrado, imagen como primer campo, formulario en una columna, vista previa local, cuatro URLs, alta con estado Vigente, edición sin exigir una nueva imagen, cambio a Vencido al usar la fecha actual, miniatura/iconos y borrado con confirmación. El drawer móvil midió `390px`, sin overflow. Cero errores de consola u overlays. Se repitió el flujo completo después de incorporar los iconos definitivos; DEV terminó nuevamente con cero anuncios y `uploads/publicidad/` sin archivos de prueba. La portada pública conserva por ahora `partials/publicidad.php`: conectar solamente registros vigentes será una etapa separada para no alterar el frente aprobado por inferencia. Browser plugin no estaba disponible, por lo que se reutilizó Puppeteer.

### Corrección de la vista previa al seleccionar un anuncio — 26 de agosto de 2026

La previsualización ya no muestra el `<img>` mientras el navegador todavía intenta resolver una URL temporal. El archivo se valida primero en el cliente, se lee con `FileReader`, se precarga en una imagen auxiliar y solamente después se vuelve visible. Durante la espera aparece un indicador con texto accesible; tipos no admitidos, archivos mayores a 5 MB o errores de lectura muestran una explicación dentro del recuadro, sin icono de imagen rota. Elegir luego un archivo válido recupera la vista sin recargar el drawer.

QA DEV con Chromium/Puppeteer: JPG de `1200px`, PNG de `250px` y WEBP de `600px` terminaron completos, con `naturalWidth > 0`, URL de datos y estado «vista previa lista». Un TXT mostró el error esperado y el siguiente JPG se previsualizó correctamente. En escritorio el drawer midió `560px` y la vista `512px`; en móvil midieron `390px` y `350px`, sin overflow, overlays ni errores de consola. No se guardaron anuncios ni archivos y no se modificó la base. **No desplegado en PROD.**

### Validación de anuncios dentro del drawer sin perder la imagen — 26 de agosto de 2026

El alta y la edición ahora envían el formulario con `FormData` sin recargar la pantalla. Las validaciones del servidor regresan asociadas a cada campo y se muestran en un bloque rojo dentro del drawer, inmediatamente antes de los botones. El primer campo incorrecto recibe foco y estado accesible `aria-invalid`; la imagen seleccionada y su vista previa permanecen intactas mientras se corrigen URLs, nombre, fecha u otros datos. La validación de tipo y tamaño de imagen usa el mismo resumen visual.

QA reversible en DEV con Chromium/Puppeteer a `1440×950` y `390×844`: una Web sin protocolo mantuvo el drawer abierto y la URL de la pantalla sin cambios, enfocó `#adWeb`, conservó un archivo seleccionado y la vista previa completa de `1200px`; el aviso quedó dentro del formulario y justo encima de las acciones. Al corregir la Web, el anuncio se creó correctamente y luego se eliminó; DEV terminó otra vez sin anuncios ni archivos de prueba. No hubo overflow ni errores de consola. No requiere migración y **no fue desplegado en PROD**.

### Vista lateral de consulta al pulsar la imagen del anuncio — 26 de agosto de 2026

La miniatura de cada fila es ahora un botón accesible que abre el drawer en modo consulta. La vista reúne la imagen completa, estado, ID, nombre, Facebook, Instagram, WhatsApp, sitio web, vencimiento, creación y última actualización; los destinos configurados son enlaces seguros y los vacíos se identifican como «Sin configurar». En la cabecera hay una única acción de negocio, **Editar**, además del control habitual de cierre. Al pulsarla, el mismo drawer cambia al formulario existente con los datos precargados, sin duplicar flujos ni persistencia.

QA de solo lectura en DEV con Chromium/Puppeteer a `1440×950` y `390×844`: el anuncio real existente abrió por su imagen, mostró los ocho datos, imagen de `1200px`, estado e ID correctos, y enfocó el botón Editar. La transición cargó nombre y Web correctos en el formulario; en móvil el drawer midió exactamente `390px`, sin overflow ni errores de consola. La base comenzó y terminó con el mismo anuncio; no se guardó ni borró información. Browser plugin no estaba disponible, por lo que se reutilizó Puppeteer. **No desplegado en PROD.**

La imagen del detalle dejó de estar contenida en un marco fijo `16:9`: ahora toma todo el ancho disponible y calcula su altura desde la proporción original, por lo que se ve completa, más grande y sin recorte. Con la pieza cuadrada real midió `512×512px` en escritorio y `350×350px` en móvil, sin overflow ni errores de consola. La miniatura de la tabla y la vista previa del formulario no cambiaron. **No desplegado en PROD.**

### Estado manual Activo/Inactivo para anuncios — 26 de agosto de 2026

`anuncios.activo` es ahora un `TINYINT(1) NOT NULL DEFAULT 1`, independiente de `fecha_vencimiento`. La tabla ofrece un switch con guardado inmediato y respuesta accesible; el alta y la edición incluyen el mismo estado con la explicación de que permite suspender una campaña aunque no tenga vencimiento. El detalle lateral refleja Activo/Inactivo. El contrato de publicación futura queda definido como `activo = 1` y fecha no vencida; la portada continúa usando por ahora el banco demostrativo estático y no se conectó a la tabla por inferencia.

La migración CLI idempotente `install/publicidad-v2.php` exige `--environment=development|production`, comprueba que el runtime coincida con el entorno explícito y agrega también `idx_anuncios_publicacion (activo, fecha_vencimiento)`. `publicidad-v1.php` y `schema.sql` incluyen el mismo esquema para instalaciones nuevas.

Se aplicó **solo en DEV**, después de validar la huella `aae27debb310` y crear el respaldo privado `.deploy/respaldos-db/2026-08-26/dev-before-publicidad-v2.sql` de 31.667 bytes, SHA-256 `7beb48f91daa71bbcad7a90c809dc102a6a9f654d17494c491fb3947c1434dc2`. La ejecución repetida confirmó idempotencia; los dos anuncios reales heredaron Activo y terminaron activos. PROD no fue migrado ni recibió archivos.

QA reversible con Chromium/Puppeteer a `1440×950` y `390×844`: el switch de tabla recorrió Activo → Inactivo → Activo sin recarga, actualizó texto, `aria-label`, dataset y detalle lateral. El formulario guardó Inactivo y luego Activo sin cambiar nombre, imagen ni Web. El switch renderizó gris a la izquierda al estar inactivo y verde a la derecha al estar activo; dos controles en móvil, cero overflow y cero errores de consola. Browser plugin no estaba disponible, por lo que se reutilizó Puppeteer.

### Desactivación automática al vencer un anuncio — 26 de agosto de 2026

Al entrar a `admin/anuncios.php`, una actualización acotada persiste `activo = 0` para cualquier registro activo cuya `fecha_vencimiento` sea igual o anterior al día actual. La misma regla se aplica al guardar: aunque el switch llegue marcado, una fecha ya vencida fuerza Inactivo. El endpoint del switch rápido también verifica la fecha y no permite reactivar el anuncio; la tabla lo muestra bloqueado con la leyenda **Vencido**. En edición, el switch queda deshabilitado y explica que primero debe cambiarse o quitarse la fecha; al colocar una fecha futura vuelve a habilitarse.

QA DEV aislada con un registro temporal creado en `activo = 1` y fecha del día anterior: la primera carga lo persistió en `activo = 0`, la tabla mostró Inactivo/Vencido y el intento directo de activación respondió nuevamente Inactivo con explicación. El formulario quedó bloqueado y se recuperó al asignar una fecha futura. Se verificó a `1440×950` y `390×844`, sin overflow ni errores de consola. El registro QA se eliminó sin tocar archivos; DEV terminó con sus tres anuncios reales, todos activos. No requiere migración adicional y **no fue desplegado en PROD**.

### Contador de clics y anuncios reales en la portada PC — 26 de agosto de 2026

`anuncios.clics` es un contador interno `INT UNSIGNED NOT NULL DEFAULT 0`. La tabla de Anuncios lo muestra como **Clics** y el detalle lateral incluye el mismo total; no forma parte del formulario y no puede editarse manualmente. La migración CLI idempotente `install/publicidad-v3.php` exige entorno explícito y `publicidad-v1.php`/`schema.sql` contienen el contrato para instalaciones nuevas.

La portada PC toma ahora los anuncios activos y no vencidos administrados en base. Conserva exactamente la distribución estable aprobada de dos noticias y un aviso por fila; si la tabla todavía no fue migrada o no hay registros publicables, mantiene el banco demostrativo como respaldo. La experiencia móvil y sus anuncios aprobados no fueron modificados.

Los cuatro iconos públicos —Facebook, Instagram, WhatsApp y sitio web— pasan por `publicidad-click.php`. El endpoint recibe únicamente ID y tipo de destino, obtiene la URL desde la base, valida HTTP/HTTPS y la vigencia del anuncio, incrementa `clics = clics + 1` de manera atómica sin alterar `updated_at`, y responde con redirección 302. Un destino inválido, vacío, inactivo o vencido devuelve 404 y no suma. Los accesos del administrador siguen abriendo directamente la URL y no contaminan la métrica.

La migración se aplicó **solo en DEV** después de validar la huella `aae27debb310` y crear el respaldo privado `.deploy/respaldos-db/2026-08-26/dev-before-publicidad-v3.sql` de 26.581 bytes, SHA-256 `109dc9ba926836738dd78d65017a22569358ba57e07ee95b74c2c16f79e137f3`. La ejecución repetida confirmó idempotencia. PROD no fue migrado ni recibió archivos.

QA reversible: un anuncio temporal recibió un clic válido, respondió `302` hacia su URL y pasó de 0 a 1; un destino inválido respondió `404` y permaneció en 1. Chromium/Puppeteer verificó portada dinámica, enlaces contabilizables, columna y detalle en escritorio, tabla responsive móvil, cero overflow y cero errores. El registro temporal se eliminó; DEV terminó con sus tres anuncios reales, activos y en 0 clics. Browser plugin no estaba disponible, por lo que se reutilizó Puppeteer. **No desplegado en PROD.**

La presentación de destinos quedó cerrada con el mismo criterio visual del administrador: cada card mantiene siempre Facebook, Instagram, WhatsApp y sitio web en posiciones estables. Los campos con URL HTTP/HTTPS válida son enlaces negros, contabilizables y marcados como contenido patrocinado; los vacíos o inválidos se muestran grises como indicadores deshabilitados, sin `href`, sin foco y sin posibilidad de sumar clics.

El encabezado de la tabla de Anuncios quedó ordenado en tres zonas: buscador por nombre a la izquierda, **Nuevo Anuncio** centrado y contador a la derecha. El filtrado es inmediato, ignora mayúsculas y tildes, actualiza el contador con la forma «coincidencias de total» y muestra un vacío específico cuando no encuentra resultados. En móvil el buscador ocupa la primera fila y botón/contador permanecen debajo sin desbordar.

### Publicidad dinámica en el feed móvil — 26 de agosto de 2026

Los anuncios demostrativos que aparecían entre noticias en `partials/mobile-feed.php` fueron sustituidos por registros reales de la tabla `anuncios`. Después de cada noticia, inmediatamente debajo de **Ver nota completa**, aparece una card rectangular con la misma anatomía visual de PC: imagen cuadrada completa, pie «Publicidad» y cuatro destinos sociales. Solo participan anuncios `activo = 1` y sin vencimiento o con fecha futura; si no existe ninguno, no se muestra un sustituto de demostración.

La selección usa una bolsa mezclada en cada carga: recorre todos los anuncios publicables antes de repetir y, cuando hay más de uno, evita que el mismo anunciante quede dos veces consecutivas al renovar la bolsa. Los cuatro destinos comparten el partial `partials/publicidad-social.php` con PC; las URLs HTTP/HTTPS válidas pasan por `publicidad-click.php`, suman el clic y redirigen, mientras los campos vacíos o inválidos quedan grises, sin enlace ni foco. Los anuncios internos de la hoja de nota completa permanecen sin cambios porque este alcance corresponde exclusivamente a la card ubicada bajo cada resumen móvil.

### Últimas Noticias al final del drawer PC — 26 de agosto de 2026

Después del anuncio final de la noticia completa, el lateral PC incorpora un separador titulado **Últimas Noticias** y hasta diez noticias recientes distintas de la que está abierta. Las cards reutilizan exactamente el componente editorial de la portada PC. Entre dos noticias consecutivas se intercala una card de publicidad real, activa y vigente, con imagen, cuatro destinos, estados grises y contador compartido; nunca se agrega un anuncio sobrante después de la última recomendación.

El bloque se define una sola vez en un `<template>` inerte de once candidatas y se clona únicamente al abrir el drawer PC: JavaScript excluye la noticia actual y limita el resultado a diez. Al pulsar una recomendación, su contenido reemplaza el lateral abierto, vuelve al inicio y conserva como foco de retorno la card original de la portada. La hoja móvil no clona ni muestra este bloque.

Cada card recomendada incorpora además un CTA visible **Ver nota completa**, rectangular, transparente y de borde negro, inspirado en el botón móvil. El CTA vive dentro del enlace integral de la card: se puede entrar por imagen, título, resumen o botón y todos conducen al mismo cambio de noticia dentro del drawer. Esta ayuda visual se limita a `story-latest`; las cards de la portada PC y el feed móvil no fueron alterados.

Los iconos activos de las publicidades intercaladas en **Últimas Noticias** se fuerzan a negro para neutralizar el color celeste heredado de los enlaces del texto enriquecido; los destinos deshabilitados permanecen grises.

### Próximo paso acordado: búsqueda y filtros en la portada PC

El usuario cerró la jornada del **26 de agosto de 2026** dejando agendada, sin implementación todavía, una barra de búsqueda para la portada de escritorio. Se ubicará entre el header con slider y las cards y reunirá en una sola línea: búsqueda libre por texto, selector de categoría, fecha desde, fecha hasta y botón **Filtrar**.

El filtrado deberá admitir cada criterio por separado o combinaciones para localizar noticias por palabras, categoría específica y rango de publicación. La primera etapa corresponde únicamente a PC; no debe alterar la experiencia móvil ni el estado aprobado de la grilla, el drawer, **Últimas Noticias** o las cards publicitarias. Retomar desde este punto solamente cuando el usuario lo indique.

### Barra de búsqueda y filtros de la portada PC — 26 de agosto de 2026

La tarea anterior quedó implementada en DEV. Entre el header con slider y la grilla aparece una barra recta de cinco controles en una sola línea: búsqueda por palabras, categoría, **Desde**, **Hasta** y **Filtrar**. El texto busca sin distinguir mayúsculas ni tildes dentro del título y el contenido; cada criterio funciona por separado o combinado. Las fechas son inclusivas y sus límites se sincronizan en el navegador para evitar rangos invertidos.

El formulario usa GET y conserva los criterios en la URL y en los controles después de filtrar. La grilla PC se recompone solamente con los resultados, manteniendo su distribución de noticias y publicidad; si no hay coincidencias muestra un estado vacío claro. La colección completa continúa alimentando el feed móvil y **Últimas Noticias**, por lo que la búsqueda de escritorio no elimina ni altera esos contenidos.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: a `1440×900` y `769×900` los cinco controles permanecieron en una línea y no hubo overflow. Se validaron texto, categoría, rango exacto, combinación de los cuatro criterios, persistencia de valores, estado vacío, apertura del drawer desde un resultado y sincronización Desde/Hasta. A `390×844`, aun cargando una URL sin coincidencias, el feed móvil conservó las siete noticias, el bloque PC siguió oculto y no hubo overflow ni errores de consola. Parámetros manipulados como arreglos respondieron HTTP 200 sin avisos PHP. **No desplegado en PROD.**

Corrección posterior: la búsqueda textual filtra automáticamente con un debounce de `280 ms`. Tanto esa búsqueda como el botón **Filtrar** solicitan el HTML al mismo endpoint y reemplazan únicamente `.pc-news-results`; no recargan el documento, conservan el foco, actualizan la URL mediante `history.replaceState()` y mantienen la posición vertical. El área de resultados conserva una altura mínima estable para evitar que una única coincidencia o el estado vacío acorten la página y desplacen el formulario. La grilla recibida continúa usando el render PHP canónico, por lo que se preservan las filas publicitarias y el drawer delegado.

QA específica a `1440×900`: escribir «fuente» redujo siete cards a una sin pulsar el botón, anunció «1 noticia encontrada», mantuvo el mismo documento y conservó exactamente `scrollY = 932`. Seleccionar Deportes y pulsar **Filtrar** también mantuvo `scrollY = 932`, sin navegación completa; la card resultante abrió el drawer. El estado vacío instantáneo, la independencia móvil a `390×844`, el overflow y la consola pasaron sin errores. **No desplegado en PROD.**

### CTA Ver nota completa en las cards de la portada PC — 26 de agosto de 2026

Todas las cards editoriales de la grilla principal PC muestran ahora **Ver nota completa**. Se reutilizó el mismo elemento compartido y el mismo estilo rectangular ya aprobado para las recomendaciones de **Últimas Noticias**; no se duplicó marcado ni lógica. El CTA continúa dentro del enlace integral de la tarjeta y abre el drawer derecho exactamente igual que un clic en su imagen, título o resumen.

Chromium/Puppeteer confirmó siete CTA para siete noticias a `1440×900`, estilo negro de `44px` y bordes rectos, clic directo abriendo el drawer, persistencia del CTA después del filtrado instantáneo y siete CTA a `769×900`, sin overflow ni errores. A `390×844`, el feed PC permaneció oculto y los siete botones móviles independientes siguieron intactos. **No desplegado en PROD.**

### Pie de página compartido en PC y móvil — 26 de agosto de 2026

Después de la grilla completa se agregó un footer compartido por las vistas de escritorio y móvil. La corrección visual definitiva elimina todo aspecto de barra, trazo sólido o relieve sobresaliente: la zona superior proyecta una sombra descendente de `18px` que se desvanece dentro del footer, creando la sensación de un escalón hacia un plano inferior. Sus extremos también se desvanecen y conserva márgenes laterales de `64px` a `1440px` —`40px` en PC compacto y `24px` en móvil—. Debajo aparece centrado el texto **«© rsmedios.com dev en Fenix»**; únicamente **Fenix** enlaza a `https://fenixlab.uno` y abre de forma segura en una pestaña nueva. Chromium/Puppeteer verificó a `1440px` y `390px` el degradado de profundidad, fondo transparente, ausencia de borde duro, destino, `noopener noreferrer`, ubicación al final del documento, cero overflow y consola limpia. **No desplegado en PROD.**

### Slider administrable desde Noticias — 26 de agosto de 2026

La tabla `noticias` incorpora `portada TINYINT(1) NOT NULL DEFAULT 0` y el índice compuesto `idx_noticias_portada_fecha (portada, created_at, id)`. La columna **Portada** del listado administrativo ofrece un switch con guardado inmediato protegido por permiso `noticias.editar` y CSRF; la edición de cada noticia incluye el mismo estado. Una noticia sin fotos no puede activarse, porque el slider necesita su primera imagen.

El hero dejó de contener los tres ejemplos de Unsplash. Ahora renderiza, en orden descendente de publicación, todas las noticias con `portada = 1` y al menos una foto; toma de la base la categoría, el título, un resumen de hasta 220 caracteres y la primera imagen de la galería. Los puntos y el autoplay se ajustan a la cantidad real, el JavaScript admite cero o una noticia sin errores y los feeds PC/móvil continúan recibiendo la colección completa.

La migración idempotente `install/portada-v1.php` se ejecutó **solo en DEV** (`fenixdev_noticias`) después del respaldo `.deploy/respaldos-db/2026-08-26/dev-before-portada-v1.sql`, de 31.082 bytes y SHA-256 `eb8756fcfc530dc579a9676f02f5d8e1b28e350eaa1daea41fc34b20d55206c1`. En su primera ejecución marcó las tres noticias más recientes con foto para sustituir inmediatamente los tres slides estáticos. PROD no fue migrado ni recibió archivos.

QA local con Chromium/Puppeteer a `1440×900` y `390×844`: tres noticias activas produjeron tres slides y tres indicadores con imágenes locales; al desactivar temporalmente una noticia aparecieron dos y, al restaurarla, volvieron a ser tres. El cambio de indicador, alto completo, ausencia de overflow y consola pasaron correctamente; DEV terminó restaurado con tres noticias de portada. El plugin Browser no estaba disponible. La interacción autenticada del switch no se automatizó porque el entorno impidió fabricar una sesión administrativa; requiere una comprobación manual desde el panel. **No desplegado en PROD.**

### Prueba tipográfica Oswald + Roboto — 26 de agosto de 2026

El portal público carga desde Google Fonts **Oswald** en todos los encabezados `h1`–`h6` y **Roboto** en el resto de los textos. La regla se aplica a portada PC, feed móvil, drawer/hoja de noticia completa y página individual; el cuerpo editorial de `noticia.php`, que antes usaba Georgia, pasa también a Roboto. El panel administrativo no fue modificado. Si Google Fonts no responde, quedan fallbacks locales `Arial Narrow` para títulos y Arial para textos.

Los títulos quedaron configurados con `font-weight: 800`, como en la referencia elegida. Google Fonts ofrece actualmente Oswald hasta peso 700; el navegador usa ese archivo y sintetiza el grosor adicional para representar 800.

Antes de la prueba se creó y publicó la etiqueta Git `pre-fuentes-google-2026-08-26` sobre el commit `6bd4a6e`, que permite restaurar exactamente el diseño anterior. Chromium/Puppeteer confirmó la carga real de Oswald 700 y Roboto 400, familias calculadas correctas, cambio de slide, portada y cards a `1440×900`/`390×844`, y noticia individual móvil sin overflow ni errores. El plugin Browser no estaba disponible. **No desplegado en PROD.**

### Prueba tipográfica Arial Black + Roboto — 26 de agosto de 2026

Por decisión visual posterior, los títulos públicos pasan a **Arial Black** manteniendo `font-weight: 800`; Roboto continúa en todo el texto general. Se retiró Oswald de la solicitud a Google Fonts. El fallback de títulos es Roboto 800 cuando el sistema no dispone de Arial Black. El respaldo `pre-fuentes-google-2026-08-26` y los commits anteriores permiten volver a cualquiera de las pruebas previas. **No desplegado en PROD.**

Chromium/Puppeteer verificó portada, cards y apertura de la noticia lateral en PC `1440×900`, además de portada y hoja completa móvil `390×844`: familia y peso calculados correctos, interacción funcional, cero errores y cero overflow. El entorno de QA no dispone del plugin Browser, por lo que se usó el navegador local existente.

### Enlace limpio al compartir en WhatsApp — 27 de agosto de 2026

El botón compartido de WhatsApp envía ahora únicamente el permalink canónico. Se retiró el título que antes se concatenaba delante de la URL porque WhatsApp ya obtiene título, descripción e imagen desde Open Graph y terminaba mostrando, además de la tarjeta SEO, una línea extensa repetida debajo. El ajuste vive una sola vez en `partials/acciones-noticia.php` y alcanza el drawer PC, la hoja móvil y la página individual sin modificar los metadatos SEO ni Facebook. **No desplegado en PROD.**

Chromium/Puppeteer comprobó el parámetro `text` exacto en las tres superficies: contiene una sola URL, sin título ni texto adicional. La página individual conserva `og:title`, `og:description`, `og:image` y canonical completos; la apertura de ambas hojas funciona, sin errores ni overflow a `1440×900` y `390×844`. La composición final y la caché propia de WhatsApp requieren confirmación en la aplicación real.

### Acceso temporal del crawler de Facebook en DEV — 27 de agosto de 2026

La tarjeta de Facebook llegaba vacía aunque el permalink público respondía 200, incluía todos los metadatos Open Graph y su imagen era descargable. La causa encontrada fue el `robots.txt` raíz de `proyectos.fenixdev.uno`, que bloqueaba `/` para todo crawler; el `robots.txt` interno del subdirectorio no gobierna el origen completo.

Con autorización expresa del usuario se agregó temporalmente un grupo exclusivo para `facebookexternalhit` con `Allow: /09portal-noticias/`. El grupo general `User-agent: *` conserva `Disallow: /` y siguen bloqueados GPTBot, OAI-SearchBot, ChatGPT-User, Google-Extended, ClaudeBot y CCBot. Antes del cambio, el archivo raíz medía 242 bytes y tenía SHA-256 `f792a1e2ab17286e53da616b91fb9a0e8180002fc5131f6d077f829159e6826d`. **Retirar esta excepción cuando el usuario termine las pruebas de Facebook; quedó también como prioridad activa en `AGENDA.md`.** No requiere ni autoriza despliegue a PROD.

La prueba posterior se realizó con una noticia ya publicada en PROD, cuyo `robots.txt` permite rastreo y cuyos metadatos Open Graph e imagen respondieron correctamente al user-agent de Facebook. El usuario confirmó que la tarjeta cargó «impecable». Inmediatamente después se retiró la excepción temporal de DEV: el `robots.txt` raíz volvió a sus 242 bytes y a su SHA-256 original `f792a1e2ab17286e53da616b91fb9a0e8180002fc5131f6d077f829159e6826d`; todo DEV queda nuevamente bloqueado para `facebookexternalhit` y para el resto de los crawlers. No se modificó ni desplegó PROD.

### Categorías múltiples por noticia — 27 de agosto de 2026

Cada noticia puede asociarse ahora a ninguna, una o varias categorías mediante `noticias_categorias`, con clave primaria compuesta para impedir duplicados, índice inverso para filtros y `posicion` para conservar el orden. `noticias.categoria_id` se mantiene como categoría principal sincronizada con la primera selección, evitando romper compatibilidad con instalaciones anteriores. La migración idempotente `install/categorias-multiples-v1.php` copia todas las asociaciones existentes sin perder datos.

El formulario administrativo reemplaza el selector único por un desplegable con checkboxes y resumen inmediato. El guardado valida todos los IDs y reemplaza las relaciones dentro de la misma transacción que la noticia. Portada, filtro PC, feeds, página individual, metadatos `article:section`, JSON-LD, listado, drawer, pantalla Categorías y Votaciones leen todas las asociaciones; el filtro encuentra una noticia por cualquiera de ellas.

La migración se aplicó **únicamente en DEV** después de validar la huella `aae27debb310` y crear el respaldo `.deploy/respaldos-db/2026-08-27/dev-before-categorias-multiples-v1.sql`, de 31.121 bytes y SHA-256 `ab946d04b3932592371e6d811018f01032ae1ff4e26a7df0b86b0b29900d7c1b`. Dos ejecuciones produjeron el mismo resultado: 7 noticias, 7 relaciones, cero duplicados y cero categorías heredadas sin copiar. Una prueba transaccional agregó temporalmente Tecnología + Economía y revirtió al estado original.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: el flujo `Editar noticia → abrir Categorías → marcar una segunda → ver ambas en el resumen` pasó a `1440×950` y `390×844`, sin overflow, overlays ni errores de consola. Capturas temporales: `/tmp/pntest/capturas/categorias-multiples-desktop.png` y `/tmp/pntest/capturas/categorias-multiples-mobile.png`. Portada y página individual respondieron HTTP 200; la noticia individual conservó su `article:section`. **PROD no fue migrado ni recibió archivos.**

El usuario aprobó el selector editorial como «impecable». A continuación, el filtro único de categorías de la portada PC fue reemplazado por el mismo patrón compacto de desplegable con checkboxes. Sin selección equivale a **Todas las categorías**; con varias selecciones aplica lógica inclusiva —una noticia aparece si pertenece a cualquiera de ellas— y combina el resultado con texto y fechas. Los parámetros repetidos `categoria[]` quedan en la URL, sobreviven a la recarga y siguen pasando por la búsqueda AJAX existente.

QA local con Chromium/Puppeteer: Deportes + Economía produjo exactamente la unión esperada de cuatro noticias, conservó dos parámetros en la URL y dos checks después de recargar. El selector cerró al filtrar, no generó overflow a `1440×900` ni `900×900`, no afectó el feed móvil a `390×844` y la consola quedó limpia. Capturas temporales en `/tmp/pntest/capturas/filtro-categorias-multiples-abierto.png`, `filtro-categorias-multiples-resultados.png` y `filtro-categorias-multiples-compacto.png`. **No desplegado en PROD.**

### Búsqueda de noticias en el menú fullscreen PC — 27 de agosto de 2026

Debajo de «Escuchá la radio» se agregó una caja exclusiva de PC con borde blanco redondeado, lupa interior y placeholder **Buscar noticias...**. Desde dos caracteres consulta por AJAX `buscar-noticias.php`; el endpoint es público, de solo lectura, limita la entrada a 100 caracteres, escapa comodines SQL, desactiva caché y devuelve como máximo cinco noticias recientes cuyo título o descripción coincidan. Cada resultado muestra portada —o fondo neutro si no existe—, título en una línea y descripción visualmente limitada a dos.

Al elegir un resultado se vacía y cierra el menú, se espera su transición de 400 ms y se abre el drawer PC existente al 40% con la plantilla completa de esa noticia. No se creó una segunda vista ni una nueva consulta de detalle; si excepcionalmente la noticia no estuviera en las plantillas de la página actual, se usa su permalink como fallback. Al cerrar el drawer, el foco vuelve al botón del menú.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: a `1440×900`, buscar «inteligencia» devolvió una miniatura cargada, título exacto y descripción de dos líneas; seleccionarla cerró completamente el menú y abrió el drawer correcto de `576px`. A `900×900`, «la» produjo el máximo de cinco resultados sin overflow. A `390×844`, la búsqueda permaneció oculta y las tres opciones originales del menú móvil siguieron intactas. Endpoint normal, mínimo de caracteres y comodín literal respondieron correctamente; consola limpia. Capturas temporales: `/tmp/pntest/capturas/menu-busqueda-noticias-resultados.png`, `menu-busqueda-noticias-drawer.png` y `menu-busqueda-noticias-compacto.png`. **No desplegado en PROD.**

### Extensión de la búsqueda al menú móvil — 27 de agosto de 2026

Después de la validación inicial de PC, el usuario pidió llevar la misma búsqueda al menú móvil. Se reutilizan el marcado, el endpoint AJAX y las plantillas inertes existentes; solo se adaptó la presentación responsive. La caja ocupa el ancho disponible, mantiene borde blanco redondeado y lupa, no abre el teclado automáticamente al desplegar el menú y limita el listado a `52svh` con scroll para que hasta cinco resultados sigan siendo accesibles en pantallas compactas.

Al seleccionar una noticia, el menú se cierra y la misma función compartida abre su vista completa según el viewport: drawer lateral de `40vw` en PC y hoja personalizada inferior de `85svh` en móvil. No se duplicaron consultas ni contenido, y el permalink continúa como fallback si una noticia no estuviera disponible en las plantillas de la portada.

QA local con Chromium/Puppeteer: a `390×844`, la caja midió `358×50px`, la búsqueda devolvió miniatura, título y descripción limitada a dos líneas, y la noticia elegida abrió en una hoja de `390×717,39px`, equivalente al 85% del viewport, con contenido completo y fondo bloqueado. A `360×800`, se mostraron cinco resultados con scroll y cero overflow. La regresión PC a `1440×900` conservó el drawer de `576px`; consola limpia. Capturas temporales: `/tmp/pntest/capturas/menu-busqueda-mobile-resultados.png`, `menu-busqueda-mobile-nota.png` y `menu-busqueda-mobile-compacto.png`. La limitación pendiente continúa siendo la prueba con teclado real y Safari iOS. **No desplegado en PROD.**

El usuario revisó esta extensión y la aprobó como «impecable». La búsqueda fullscreen de PC y móvil, la apertura lateral en PC y la hoja personalizada móvil quedan como baseline aprobado; no rediseñar estas superficies al retomar salvo pedido concreto o regresión comprobada.

### Menú fullscreen reducido al buscador — 27 de agosto de 2026

Por pedido posterior, el menú público de la portada y de la página individual conserva únicamente el buscador centrado, además del logo principal superior y la cruz de cierre. Se retiraron el logo **Escuchá la radio** y los enlaces Noticias, Videos y Contactos tanto en PC como en móvil. La búsqueda de la portada mantiene su comportamiento aprobado —drawer lateral en PC y hoja inferior en móvil—; en la página individual se incorporó la misma consulta y la selección navega al permalink correspondiente.

En PC el buscador mide hasta `680px` y `62px` de alto; en móvil ocupa `calc(100vw - 32px)` y `56px`. El campo conserva su contorno redondeado, mientras el despliegue solicitado es completamente rectangular: radio `0`, imágenes cuadradas de `112×78px` en PC y `88×68px` en móvil, título y resumen ampliados y separadores blancos finos de `1px` entre noticias. El menú móvil no enfoca automáticamente el campo para evitar abrir el teclado al desplegarlo.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: a `1440×900` el buscador midió `680px`, quedó centrado con diferencia `0`, devolvió cinco resultados, usó imágenes `112×78px`, separador de `1px`, radio `0` y no generó overflow. A `390×844` midió `358px`, también con diferencia `0`, imágenes `88×68px`, sin autofocus ni overflow. Elegir un resultado siguió abriendo correctamente la vista completa en ambas resoluciones. La página individual repitió la geometría PC, los cinco resultados y la ausencia del bloque de radio y navegación. El único 404 fue el `favicon.ico` local conocido. Capturas temporales: `/tmp/pntest/menu-solo-buscador-pc.png` y `/tmp/pntest/menu-solo-buscador-mobile.png`. La prueba pendiente continúa siendo teclado real y Safari iOS. **No desplegado en PROD.**

### Acceso al Admin desde el encabezado público — 27 de agosto de 2026

El logo **Escuchá la radio** del extremo derecho del encabezado fue sustituido por un enlace directo a `admin/login.php`. En PC muestra un icono lineal de usuario y el texto **Ingresar**; hasta `768px` oculta únicamente el texto y conserva un área táctil de `41×41px`, con `aria-label="Ingresar al panel de administración"`. El mismo encabezado se aplica en la portada y en la página individual.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: a `1440×900` el acceso midió `93×24px`, quedó a `24px` del borde, mostró icono y texto y navegó realmente a **Ingresar al panel**, donde se encontró el formulario de login. A `390×844` mostró solo el icono, midió `41×41px` y quedó a `16px` del borde. La noticia individual repitió el resultado de escritorio. En las tres vistas se confirmó que el antiguo logo no existe, no hubo overflow, errores de aplicación ni recursos fallidos. Capturas: `/tmp/pntest/admin-ingresar-pc.png`, `/tmp/pntest/admin-ingresar-mobile.png` y `/tmp/pntest/admin-ingresar-noticia.png`. **No desplegado en PROD.**

### Foto e identidad del usuario autenticado — 27 de agosto de 2026

La tabla `usuarios` incorpora `foto VARCHAR(255) NULL`. El drawer de **Usuarios** permite seleccionar JPG, PNG o WEBP de hasta 3 MB, muestra preview circular y guarda los archivos sin marca de agua en `uploads/usuarios/`. Las rutas se generan y validan con un patrón exclusivo; reemplazar una foto elimina la anterior solamente después de confirmar la transacción. La tabla de usuarios y el pie lateral del panel muestran la foto, con inicial como fallback.

El encabezado público consulta la sesión administrativa existente sin crear sesiones nuevas para visitantes anónimos. Sin sesión conserva el acceso aprobado a Login. Con sesión muestra, de derecha a izquierda, la foto circular y un bloque con nombre y rol en una segunda línea menor; el enlace abre directamente `admin/index.php`. En móvil conserva los tres datos en formato compacto y truncado, evitando invadir el logo central. La portada y la página individual comparten `partials/acceso-admin.php`.

La migración idempotente `install/usuarios-foto-v1.php` exige `--environment=development|production` y verifica el runtime contra el manifiesto privado. Se aplicó **solo en DEV** con huella `aae27debb310`, después del respaldo `.deploy/respaldos-db/2026-08-27/dev-before-usuarios-foto-v1.sql`, de 32.479 bytes y SHA-256 `7cee829e5b9a6e7baf71643500e0bcde9b84d87fa8c53c6a4e44e601a8584b71`. Dos ejecuciones confirmaron una única columna, 5 usuarios conservados y cero fotos iniciales. PROD no fue migrado ni recibió archivos.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: se editó temporalmente el administrador desde el drawer, se cargó una PNG real, apareció el preview y el guardado confirmó **Usuario actualizado correctamente**. A `1440×900`, el portal mostró foto `42×42px`, nombre `admin`, rol **Administrador**, cero overflow y el clic abrió el panel autenticado. A `390×844` mostró los mismos datos, foto `36×36px`, 17px libres respecto al logo y cero overflow. El estado invitado mantuvo únicamente el icono móvil y el vínculo a Login; la noticia individual también reconoció nombre y rol. Un archivo de texto fue rechazado sin guardar datos ni archivos. Al finalizar se restauraron exactamente `foto = NULL` y el `updated_at` original del administrador, se eliminó la imagen temporal y `uploads/usuarios/` quedó sin archivos. Capturas: `/tmp/pntest/usuario-foto-formulario.png`, `/tmp/pntest/usuario-logueado-pc.png` y `/tmp/pntest/usuario-logueado-mobile.png`. **No desplegado en PROD.**

Corrección posterior por reporte visual del usuario: la selección inmediata usaba `URL.createObjectURL()` y podía dejar el elemento de imagen en estado roto antes del guardado. Se sustituyó por el mismo patrón estable aprobado en Anuncios: `FileReader` genera un Data URL, una imagen aislada comprueba que el navegador la decodifique y recién entonces se reemplaza el preview. Durante la lectura se conserva el avatar anterior atenuado; si el formato, tamaño, lectura o decodificación fallan, se restaura la foto guardada —o la inicial— y aparece un mensaje claro, nunca un icono roto. Las fotos guardadas cuya ruta ya no existe también caen a la inicial.

QA específica sin guardar ni modificar datos: JPG `1280×720`, WEBP `600×483` y PNG `250×100` produjeron Data URL del MIME correcto, `naturalWidth > 0`, preview circular `78×78px` y mensaje **vista previa lista**. Pasó a `1440×950` y `390×844`, sin overflow, consola ni recursos fallidos. Un falso `.jpg` no decodificable restauró la foto real existente y mostró **La imagen no pudo mostrarse. Elegí otra.** DEV conservó exactamente un usuario con foto y un archivo de perfil; PROD continúa sin cambios. Capturas: `/tmp/pntest/usuario-preview-corregido-pc.png` y `/tmp/pntest/usuario-preview-corregido-mobile.png`. **No desplegado en PROD.**

Ajuste visual aprobado posteriormente: el contorno circular de la foto autenticada pasa de blanco semitransparente a negro sólido de `1px`, tanto en el encabezado público —portada y noticia individual— como en el usuario ubicado al pie del menú administrativo. Chromium/Puppeteer confirmó `rgb(0, 0, 0)`, `solid`, `1px` en PC `42×42px`, móvil `36×36px` y panel `40×40px`, sin overflow ni errores. Capturas: `/tmp/pntest/usuario-borde-negro-front-pc.png`, `/tmp/pntest/usuario-borde-negro-front-mobile.png` y `/tmp/pntest/usuario-borde-negro-admin.png`. **No desplegado en PROD.**

### Pausa y agenda de la próxima sesión — 27 de agosto de 2026

Se detiene el trabajo por pedido del usuario, conservando sin commit ni despliegue todos los cambios acumulados del árbol actual. **PROD continúa sin recibir esta tanda y la migración de categorías múltiples sigue aplicada únicamente en DEV.** Al retomar, leer `AGENDA.md`, este cierre y `git status --short --branch`; no descartar, separar, confirmar ni desplegar el estado existente por inferencia.

La próxima etapa acordada comienza por la escalabilidad de la portada: paginador tradicional en PC, manteniendo filtros y URL. Para móvil se recomienda conservar la experiencia continua aprobada, pero respaldarla con paginación real por bloques y una carga incremental —botón **Cargar más** o disparo al avanzar— para no descargar todas las noticias de una vez; el mecanismo exacto se decidirá antes de editar.

Después se crearán dos superficies nuevas a partir de código que aportará el usuario: la página de **Radio**, adaptada y mejorada dentro de la identidad del portal, y la página de **Canal de TV** con su reproductor, también mediante adaptación y mejora. No comenzar ninguna de las dos sin recibir y relevar primero ese código.

### Últimas Noticias dentro de la hoja móvil — 27 de agosto de 2026

Antes de continuar con la agenda se extendió a móvil el bloque **Últimas Noticias** ya aprobado en el drawer PC. Después de la última publicidad de la nota, la hoja muestra hasta 10 publicaciones recientes sin repetir la abierta. Entre cada recomendación intercala un anuncio administrado activo y vigente con el formato nuevo —imagen completa, etiqueta Publicidad y accesos sociales— y omite el aviso posterior a la última noticia.

Se reutilizan `storyLatestTemplate`, `pc-news-card.php` y el mismo manejador delegado: no existe un segundo feed de recomendaciones. En móvil se agregaron reglas responsive específicas porque las cards y los anuncios originales estaban estilizados únicamente desde `769px`. Al tocar una recomendación se reemplaza la noticia dentro de la misma hoja, el scroll vuelve a cero, el bloque se reconstruye y excluye la nueva noticia abierta; el permalink continúa siendo el fallback fuera de esta superficie.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: `1440×900`, `390×844` y `360×800` respondieron HTTP 200, sin overflow horizontal ni errores de aplicación. Con las 7 noticias DEV actuales se mostraron 6 recomendaciones y 5 anuncios visibles; la séptima noticia abierta quedó excluida y no apareció publicidad después de la última recomendación. A `360px`, el pie del anuncio midió 316px tanto de ancho visible como de contenido, con sus cuatro accesos sociales sin recorte. El clic cambió de la noticia 8 a la 7 dentro de la hoja, mantuvo el panel abierto, volvió a `scrollTop = 0` y regeneró el listado excluyendo la noticia 7. El único error de escritorio fue el `favicon.ico` 404 ya conocido. **No desplegado en PROD.**

### Ver + Noticias: carga incremental PC y móvil — 27 de agosto de 2026

Los dos primeros puntos de la agenda quedaron resueltos con un único patrón aprobado: la portada carga 6 noticias y un botón centrado **Ver + Noticias** agrega el siguiente bloque de hasta 6 debajo del contenido existente. El botón es rectangular, transparente, con texto y borde negros; durante la solicitud muestra **Cargando…**, queda deshabilitado, permite reintentar si falla y desaparece cuando no quedan publicaciones.

La carga es real en backend, no un recorte visual de una consulta completa. `partials/portada-paginacion.php` consulta MySQL con `LIMIT 7` para devolver seis registros y detectar continuidad; el cursor opaco contiene la pareja estable `created_at + id` y el desplazamiento acumulado. `cargar-noticias.php` valida vista, cursor, filtros y categorías y devuelve JSON con el marcado del bloque, los templates funcionales, el cursor siguiente y el estado final. Cursores o parámetros manipulados responden 400 sin avisos PHP.

PC conserva búsqueda, categorías múltiples y fechas en la URL. Cambiar un filtro reemplaza el bloque inicial y reinicia su cursor; cargar más reenvía esos mismos criterios. Las noticias continúan agrupadas de dos en dos con un anuncio rotativo y una última publicación impar queda sola. Móvil no recibe filtros y mantiene la secuencia noticia + anuncio. En ambos casos cada publicación agregada incorpora su template solo si todavía no existe, por lo que abre el drawer o la hoja completa sin navegar al permalink. Las 11 noticias recientes necesarias para **Últimas Noticias** se precargan de forma acotada aunque no pertenezcan al primer bloque.

QA local con Chromium/Puppeteer a `1440×900` y `390×844`: seis noticias iniciales en cada vista; el clic agregó la séptima sin IDs duplicados y retiró el botón al agotarse el conjunto. PC conservó tres filas publicitarias, abrió la noticia agregada y también una recomendación externa al primer bloque. La búsqueda «fuente» conservó `?buscar=fuente` y devolvió una noticia; dos categorías conservaron ambos parámetros y las cuatro cards coincidieron; el rango exacto `21/08/2026` devolvió tres cards, todas de esa fecha. Móvil pasó de 6 a 7 noticias y de 6 a 7 anuncios con `scrollY` idéntico antes y después (`10075`), abrió la noticia agregada y mantuvo **Últimas Noticias**. Botón PC de 190px centrado con diferencia 0; cero overflow y consola limpia salvo el `favicon.ico` 404 conocido. **No desplegado en PROD.**

### Anuncio exclusivo de encabezado y pie — 27 de agosto de 2026

`Publicidad → Anuncios` incorpora los switches **Encabezado** y **Pie** junto a Estado. Cada ubicación admite cero o un anuncio: al activar otro, el backend bloquea las filas dentro de una transacción, retira la selección previa y conserva únicamente la nueva. Las ubicaciones son independientes y un mismo anuncio puede ocupar ambas. Desactivar Estado o alcanzar el vencimiento no borra la selección editorial, pero impide su publicación.

`install/publicidad-v4.php` agrega idempotentemente `anuncios.en_encabezado`, `anuncios.en_pie` y sus índices, exige `--environment=development|production` y valida que el runtime coincida con el entorno declarado. Se aplicó **únicamente en DEV** después del respaldo privado `.deploy/respaldos-db/2026-08-27/dev-before-publicidad-v4.sql`, de 32.348 bytes y SHA-256 `5b7b032737395ec5b55c8f54ee875ad8d6053326d53dcf9dbeeb28ffba2b87d3`. Dos ejecuciones confirmaron idempotencia. DEV conserva sus 3 anuncios y, después de la prueba, quedó con cero selecciones para que el usuario elija ambas; PROD no fue migrado ni recibió archivos.

La tarjeta compartida `partials/anuncio-card.php` presenta imagen completa, etiqueta **Publicidad** y cuatro destinos sociales. Sustituye las piezas provisorias dentro de la nota y se muestra debajo de título/fecha/autor o al finalizar según los switches. El mismo contrato alcanza drawer PC, hoja móvil y página individual; solo participan anuncios activos y con vencimiento futuro o vacío.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: la tabla autenticada mostró las columnas en el orden Estado, Encabezado y Pie; elegir dos encabezados consecutivos dejó exactamente uno activo y Pie operó de forma independiente. A `1440×900` y `390×844`, drawer y hoja mostraron dos cards con etiqueta Publicidad y cuatro accesos sociales, sin overflow; la página individual también renderizó ambas. Las selecciones temporales se retiraron al finalizar. La única consola observada fue el `favicon.ico` 404 ya conocido. Capturas: `/tmp/pntest/anuncios-ubicaciones-admin.png`, `/tmp/pntest/anuncios-nota-pc.png` y `/tmp/pntest/anuncios-nota-mobile.png`. **No desplegado en PROD.**

Corrección posterior por reporte del usuario: las plantillas ya cargadas conservaban Encabezado y Pie hasta una recarga. `publicidad-ubicaciones.php` devuelve ahora ambas cards vigentes sin caché; el guardado administrativo emite una señal de `localStorage` y el portal sincroniza todos los templates inertes, la nota abierta y la página individual. El foco o regreso visible a la pestaña funciona como respaldo. Solo se reemplazan los dos slots publicitarios, sin navegación, cierre ni salto de lectura.

QA entre cuatro pestañas locales con Chromium/Puppeteer: desde Anuncios se cambió temporalmente Encabezado y Pie al anuncio 3 y PC `1440×900`, móvil `390×844` y página individual actualizaron imagen, etiqueta y cuatro destinos. `performance.timeOrigin` permaneció idéntico en las tres superficies, probando que no hubo recarga. Luego se restauraron exactamente las selecciones del usuario: Encabezado `#1` y Pie `#2`. Cero errores de aplicación. Capturas: `/tmp/pntest/publicidad-sync-pc.png` y `/tmp/pntest/publicidad-sync-mobile.png`. **No desplegado en PROD.**

### Login móvil contenido en una pantalla — 27 de agosto de 2026

El login administrativo móvil deja de depender de una altura mínima acumulada. La composición completa ocupa `100svh`, bloquea el scroll de la página y distribuye la imagen superior y el formulario en dos filas flexibles. La foto utiliza entre `105px` y `200px` según la altura disponible; logo, títulos, separaciones, campos, botón y aviso de seguridad se compactan de forma progresiva sin retirar ningún elemento. La composición PC de dos columnas permanece intacta.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: `390×844`, `360×640` y `320×568` tuvieron `scrollHeight` exactamente igual al alto del viewport, sin overflow horizontal ni vertical. En los tres tamaños quedaron completamente visibles **Ingresar** y el aviso **Acceso privado al panel editorial**; el control para mostrar la contraseña siguió funcionando. La regresión PC a `1440×900` conservó las dos columnas, también sin overflow. No hubo errores de aplicación; el único recurso fallido fue el `favicon.ico` local ya conocido. Capturas: `/tmp/pntest/login-mobile-ajustado-390x844.png`, `/tmp/pntest/login-mobile-ajustado-360x640.png`, `/tmp/pntest/login-mobile-ajustado-320x568.png` y `/tmp/pntest/login-desktop-regresion.png`. **No desplegado en PROD.**

### Card contraíble y tamaño de marca de agua — 27 de agosto de 2026

La card **Configuración → Marca de Agua** incorpora una flecha accesible en su extremo superior derecho. Comienza expandida; al contraer oculta vista previa, carga, controles, aviso y acciones, gira la flecha y conserva los valores sin guardar. `aria-expanded`, `aria-controls` y la etiqueta dinámica distinguen claramente ambos estados.

Se agregó **Tamaño de la marca**, un segundo deslizador de 15% a 65%. El valor representa el porcentaje del ancho de la fotografía que ocupará el PNG centrado, actualiza el porcentaje y la vista previa en vivo y se persiste como `marca_agua_tamano` en la tabla genérica `configuracion`. Las próximas imágenes subidas usan este porcentaje dentro del procesamiento GD; instalaciones anteriores mantienen el 36% aprobado como fallback, por lo que no cambian hasta mover y guardar el control. `install/configuracion-v1.php` incorpora el valor inicial para instalaciones nuevas.

QA local autenticada con Chromium/Puppeteer porque el plugin Browser no estaba disponible: en PC `1440×900`, mover 36% → 60% aumentó el ancho visible de la marca de `186,47px` a `310,80px`; en móvil `390×844`, de `113,75px` a `189,59px`. Contraer y expandir ocultó/restauró el contenido, conservó 60% y actualizó correctamente nombre accesible y estado. Se guardó temporalmente 52%, una recarga conservó valor, porcentaje y ancho CSS, y finalmente se restauró la configuración DEV efectiva a 36% de tamaño y 15% de intensidad. No hubo overflow, páginas vacías, overlays ni errores de aplicación; el único 404 PC fue el `favicon.ico` local conocido. Capturas: `/tmp/pntest/config-marca-tamano-pc.png`, `/tmp/pntest/config-marca-tamano-pc-contraida.png`, `/tmp/pntest/config-marca-tamano-mobile.png` y `/tmp/pntest/config-marca-tamano-mobile-contraida.png`. **No desplegado en PROD.**

### Logo del login administrable — 27 de agosto de 2026

El drawer **Configuración** incorpora una segunda card independiente, **Logo del Login**, con flecha para contraer/expandir, vista previa sobre fondo de transparencia, selector PNG y acciones propias. Acepta únicamente PNG reales de hasta 2 MB y 20 millones de píxeles. El navegador valida tipo y peso, lee el archivo mediante `FileReader`, comprueba su decodificación en una imagen aislada y recién entonces reemplaza el preview, evitando iconos rotos.

`admin/configuracion-logo-login.php` vuelve a validar sesión, permiso `configuracion.gestionar`, CSRF, MIME, peso, dimensiones y decodificación GD. Normaliza el PNG, lo guarda con nombre aleatorio en `uploads/configuracion/`, persiste `logo_login_ruta` en la tabla genérica y retira el archivo personalizado anterior solo después del guardado correcto. `admin/login.php` consulta esa configuración y agrega versión por `filemtime`; si la clave, fila o archivo no existe, conserva automáticamente `imagenes/Logo2027v3.png`. `install/configuracion-v1.php` incorpora ese fallback para instalaciones nuevas.

QA local autenticada con Chromium/Puppeteer porque el plugin Browser no estaba disponible: a `1440×900` se seleccionó el PNG actual de `250×100`, apareció como Data URL decodificado, la card conservó el archivo al contraer/expandir y el endpoint confirmó **Logo del login guardado correctamente**. Una visita sin sesión cargó la nueva ruta personalizada en el login PC y móvil `390×844`, con imagen visible, botón **Ingresar** dentro del viewport y cero overflow. La card móvil midió `354px` dentro del drawer de `390px`, sin recortes; no hubo errores de aplicación, páginas vacías ni overlays. Después de la prueba se borraron únicamente la fila y las copias temporales creadas por QA: DEV volvió al fallback original, sin `logo_login_ruta` ni archivos `logo_login_*.png`. Capturas: `/tmp/pntest/config-logo-login-pc.png`, `/tmp/pntest/config-logo-login-contraida.png`, `/tmp/pntest/config-logo-login-mobile.png`, `/tmp/pntest/login-logo-configurado-pc.png` y `/tmp/pntest/login-logo-configurado-mobile.png`. **No desplegado en PROD.**

### Logo del Portal separado de Logo del Admin y Favicon — 28 de agosto de 2026

La identidad quedó dividida en tres configuraciones sin cruces: **Logo del Login** afecta solamente la pantalla de ingreso; **Logo del Portal** afecta únicamente el encabezado y el menú fullscreen públicos; y la nueva cuarta card **Logo del Admin y Favicon** usa una misma imagen para la marca superior izquierda del menú interno y para el icono de pestaña de todo el sitio. El logo público continúa además como `publisher.logo` del JSON-LD de noticias. Mientras no exista `logo_admin_ruta`, el menú interno conserva la **N** histórica aprobada.

`admin/configuracion-logo-portal.php` ahora normaliza y persiste solamente `logo_portal_ruta`. El nuevo `admin/configuracion-logo-admin.php` valida permiso, CSRF, MIME PNG, 2 MB, dimensiones y GD; guarda `logo_admin_ruta`, genera el ICO real cuadrado de 256×256 como `favicon_admin_ruta` y reemplaza la pareja anterior únicamente después del commit. `favicon.php`, portada, noticia, panel, login y cambio de contraseña consultan esta nueva identidad Admin; el fallback continúa generándose desde `Logo2027v2.png`. `.htaccess` mantiene la derivación de `/favicon.ico` al endpoint en Apache.

QA local autenticada con Chromium/Puppeteer porque el plugin Browser no estaba disponible: PC `1440×900` y móvil `390×844` mostraron la nueva card sin overflow, con ancho móvil de `354px` dentro de `390px`. La carga generó previews decodificadas para logo y favicon, el guardado actualizó el logo del sidebar sin recargar y `favicon.php` respondió 200, `image/x-icon`, 7.473 bytes y firma ICO válida `00 00 01 00 01 00`. Se comprobó por separado que la card **Logo del Portal** ya no contiene favicon y que la portada siguió usando exactamente `logo_portal_20260828_000306_83d094a3.png`, nunca `logo_admin_*`. Tras QA se retiraron exclusivamente las dos filas y archivos temporales `logo_admin_*`/`favicon_admin_*`; los logos personalizados existentes del Portal y Login quedaron intactos. Capturas: `/tmp/pntest/config-logo-admin-favicon-pc.png` y `/tmp/pntest/config-logo-admin-favicon-mobile.png`. **No desplegado en PROD.**

### SEO administrable de la página principal — 28 de agosto de 2026

El drawer **Configuración** incorpora la card contraíble **SEO de la Página Principal**, basada en el patrón aprobado del formulario de noticias. Título y descripción comienzan en modo Automático, pueden personalizarse independientemente y volver al fallback sin copiar valores innecesarios a la base. Incluye contadores, imagen social JPG/PNG/WEBP opcional de hasta 5 MB, vista previa inmediata segura, acción para volver a la imagen automática, badge Automático/Personalizado y pestañas **Al compartir** / **En Google**.

`admin/configuracion-seo-portada.php` vuelve a validar permiso `configuracion.gestionar`, CSRF, longitudes, MIME, peso, dimensiones y decodificación GD. Persiste las claves dinámicas `seo_portada_titulo`, `seo_portada_descripcion` y `seo_portada_imagen` en la tabla genérica `configuracion`; los modos automáticos eliminan su override. Una imagen anterior se retira solamente después del commit exitoso. No requiere migración estructural.

La portada pública reemplazó el título provisorio **Landing - Slider** por los valores efectivos y declara `description`, canonical, Open Graph tipo `website`, Twitter Card `summary_large_image` y JSON-LD `WebSite` con publisher Radio Sur. Los fallbacks sin configuración son **Radio Sur | Noticias de Colonia y la región**, una descripción editorial estable y `Logo2027v3.png` como imagen social.

QA reversible con Chromium/Puppeteer porque el plugin Browser no estaba disponible: a `1440×950` la edición en vivo reflejó un título de 42 caracteres, descripción de 108, imagen Data URL decodificada y badge Personalizado; la pestaña Google alternó correctamente. El guardado temporal produjo metadatos idénticos en `index.php`, canonical local exacto, `og:type=website`, imagen `seo_portada_*`, Twitter Card y JSON-LD `WebSite`. A `390×844`, la card midió `354px` dentro de `390px`, sin overflow, conservó valores al recargar y se contrajo/expandió correctamente. Tras QA se eliminaron exclusivamente las tres claves y la imagen temporal: DEV volvió al modo automático y no se tocaron los logos existentes. Capturas: `/tmp/pntest/seo-portada-card-pc.png` y `/tmp/pntest/seo-portada-card-mobile.png`. **No desplegado en PROD.**

### Código administrable dentro del head público — 28 de agosto de 2026

El drawer **Configuración** incorpora la card contraíble **Código del Header** para Google Analytics, Meta Pixel, Google Tag Manager u otras integraciones confiables. Incluye editor monoespaciado oscuro, contador, soporte de tabulación, switch Activado/Desactivado, badge de estado y aviso explícito de que el snippet no se ejecuta dentro del Admin. Acepta hasta 60 KB y exige al menos una etiqueta `script`, `meta`, `link` o `noscript`; rechaza caracteres nulos, etiquetas PHP y aperturas/cierres de `html`, `head` o `body` para evitar romper el documento.

`admin/configuracion-codigo-header.php` valida permiso `configuracion.gestionar`, CSRF y contenido, y persiste `codigo_header_contenido` y `codigo_header_activo` en la tabla genérica `configuracion`; desactivar conserva el código y vaciarlo desactivado elimina ambas claves. `imprimir_codigo_header_publico()` inserta deliberadamente el contenido sin escape al final del `head` de `index.php` y `noticia.php`, entre comentarios identificables. No se incluye en ninguna página administrativa y no requiere migración estructural.

Como el drawer PC y la hoja móvil abren noticias sin navegar ni recargar, cada apertura emite información virtual completa: `gtag('event', 'page_view')` con título, URL canónica y path; Meta `PageView` y `ViewContent` con `content_ids`, nombre y categoría Noticia; un objeto `portal_noticia_abierta` en `dataLayer`; y el evento DOM `portal:noticia-abierta` con ID, título, URL y path para integraciones adicionales. Las URLs e identidades salen de atributos seguros renderizados en cada template de noticia.

QA reversible con Chromium/Puppeteer porque el plugin Browser no estaba disponible: un snippet simulado de 378 caracteres se guardó y activó desde la card, se ejecutó una sola vez en portada y noticia individual, y permaneció totalmente ausente del Admin. Abrir la noticia `#8` en PC produjo el `page_view` Google con título/URL/path exactos, Meta `PageView` + `ViewContent` con ID `8`, la entrada `dataLayer` y el evento DOM; móvil repitió Google, Meta y el evento con la misma identidad canónica. La card midió `354px` en viewport `390px`, sin overflow ni errores. Tras QA se borraron exclusivamente las dos claves temporales: DEV quedó sin snippet configurado. Capturas: `/tmp/pntest/codigo-header-card-pc.png` y `/tmp/pntest/codigo-header-card-mobile.png`. **No desplegado en PROD.**

Corrección visual posterior: el switch Activado/Desactivado reutilizaba la pista aprobada, pero fuera de `.form-group` su etiqueta quedaba en `display: block` y la pista se renderizaba con tamaño `0×0`, montando el texto. `.header-code-switch` es ahora un `inline-flex` alineado, con pista visible de `38×22px`, separación de 8px y el patrón compartido: verde `#10b981` al activar y gris `#cbd5e1` al desactivar. Chromium/Puppeteer confirmó PC `1440×950` y móvil `390×844`, sin superposición, overflow ni errores; el clic alternó ambos estados y se restauró en el navegador sin guardar, por lo que el código real y su estado persistido no cambiaron. Capturas: `/tmp/pntest/switch-header-final-pc.png` y `/tmp/pntest/switch-header-final-mobile.png`. **No desplegado en PROD.**

### Lupa de galería contenida en la foto lateral — 28 de agosto de 2026

Se corrigió un cierre condicional incorrecto en `partials/nota-completa.php` que, dentro de las plantillas iniciales, cerraba `.story-sheet-gallery` antes de renderizar la lupa. El botón quedaba fuera de su contenedor posicionado y por eso aparecía pegado al extremo inferior derecho del drawer. La pista de fotos ahora se cierra una sola vez en ambos modos de renderizado y la lupa permanece dentro de cada galería, sin modificar estilos ni comportamiento aprobado.

QA local con Chromium/Puppeteer porque el plugin Browser no estaba disponible: en PC `1440×900`, la lupa pasó de estar fuera de la galería en `top 842` a quedar dentro de la foto en `top 445`; en móvil `390×844` también quedó contenida dentro de la imagen. En ambas resoluciones el clic abrió el lightbox con imagen cargada, no hubo overflow horizontal ni errores de aplicación. `php -l` y `git diff --check` pasaron. Capturas temporales: `/tmp/pntest/lupa-lateral-antes.png`, `/tmp/pntest/lupa-lateral-despues-pc.png` y `/tmp/pntest/lupa-lateral-despues-mobile.png`. **No desplegado en PROD.**

### Pausa: validación real de Google Analytics — 28 de agosto de 2026

El usuario guardó y activó en **Configuración → Código del Header** un snippet real de Google Analytics. La inspección del navegador confirmó dos solicitudes diferentes al mismo flujo: la carga inicial de portada envía `page_view` con `/index.php` y abrir la noticia en el drawer envía otro `page_view` con su título y URL canónica `/noticia/{slug}`. La captura aportada por el usuario en **Páginas en tiempo real** mostró ambas filas, además de `/noticia.php`. Las seis vistas/usuarios de portada y la entrada `/noticia.php` incluyen tráfico producido por los perfiles nuevos de Chromium usados durante el diagnóstico; no representan un fallo de atribución. Cada visita que comienza en portada conserva legítimamente su vista Home y la noticia abierta agrega una segunda vista, no la sustituye.

Por pedido del usuario se creó `testgo.html`, una página estática mínima con el snippet exacto que estaba guardado y activo al momento de crearla. Su `gtag('config', ...)` genera automáticamente un único `page_view` al abrirla; no se agregó ningún evento manual. El archivo respondió HTTP 200 y contiene título, carga de `gtag.js` y configuración coincidente. La validación fue solamente HTTP y textual: el asistente no abrió la página en un navegador para no contaminar otra vez Analytics. La prueba pendiente para mañana es que el usuario abra el archivo y confirme si `/testgo.html` aparece en **Páginas en tiempo real** y cuánto demora.

Regla de continuación: no ejecutar QA de navegador contra el tag real salvo autorización expresa. Para pruebas técnicas, bloquear o simular `googletagmanager.com` y `google-analytics.com`, o usar un snippet temporal reversible que no alcance la propiedad real. No implementar `history.pushState` por inferencia: la captura de red ya demuestra que el drawer envía la URL de noticia, y habilitar History junto con la medición optimizada podría duplicar pageviews.

Se pausa por pedido del usuario con todo el árbol acumulado sin commit y sin despliegue. **PROD no recibió esta tanda, no se ejecutaron migraciones adicionales y no se eliminaron archivos ni datos.** Mañana comenzar leyendo `AGENDA.md`, este cierre y `git status --short --branch`; preservar el estado actual y esperar el resultado manual de `testgo.html`.

### Despliegue integral a PROD y habilitación de Analytics — 28 de agosto de 2026

Con autorización expresa del usuario se actualizó **PROD** en `https://digitales.uy/subir/`. Antes de tocar datos, el ejecutor remoto efímero confirmó la huella de producción `866bb80a5356`. Se generó y descargó el respaldo lógico privado `.deploy/respaldos-db/2026-08-28/prod-before-all-2026-08-28_141159.sql`: 12 tablas, 63 filas, 24.789 bytes y SHA-256 `8ce585f2488b0c58e5857d566f295963ad85feab94a383b5e76fb0e33ba053d8`.

La migración PROD incorporó Configuración, Publicidad v1–v4, portada, categorías múltiples y foto de usuario. La verificación repetida confirmó 6 noticias, 6 relaciones de categorías, ninguna categoría principal sin migrar, cero anuncios, cero fotos de perfil, ninguna selección publicitaria duplicada y los dos permisos requeridos. El ejecutor y el respaldo remoto temporal fueron eliminados; su URL terminó en 404. La copia recuperable permanece solamente en `.deploy/` con modo `600`.

El despliegue incremental publicó 59 rutas verificadas por SHA-256: 32 altas y 27 reemplazos, contando `testgo.html` como reemplazo porque ya existía fuera del manifiesto. No hubo borrados. Los uploads persistentes y datos de prueba de DEV quedaron fuera. El respaldo de archivos reemplazados vive en `.deploy/respaldos/2026-08-28_all-prod-2026-08-28_141330-bfb7cd8d/`.

La comprobación HTTP detectó que la CSP de PROD bloqueaba `googletagmanager.com` y los endpoints de Google Analytics. Después de una autorización específica, `.htaccess` permitió únicamente Google Analytics/GTM en `script-src`, `frame-src` y `connect-src`; no se habilitó Meta ni otro proveedor. El respaldo puntual anterior está en `.deploy/respaldos/2026-08-28_csp-analytics/.htaccess`.

Estado final automatizado: portada, login, favicon, CSS, JavaScript y `testgo.html` respondieron 200; instaladores y `servicios.local.json` respondieron 404. `testgo.html` quedó con el tag DEV `G-ZXFMBHWBCS`, la CSP pública lo permite y no se abrió en un navegador para evitar contaminar Analytics. `.deploy/estado.json` quedó en `verified_pending_user_confirmation`. Pendiente exclusivamente: que el usuario abra `https://digitales.uy/subir/testgo.html` y confirme la recepción en Tiempo real.

### Esquema inicial de Publicidad → Popups — 28 de agosto de 2026

Por pedido del usuario se preparó únicamente la capa de datos de Popups, sin diseñar todavía la pantalla administrativa ni el emergente público. La migración idempotente `install/popups-v1.php` exige `--environment=development|production` y compara el runtime con el entorno declarado.

`popups` replica los datos editoriales útiles de `anuncios` —nombre, cuatro destinos sociales/web, vencimiento, estado y clics— pero no contiene `en_encabezado` ni `en_pie`. Incorpora las dos piezas responsive obligatorias `imagen_vertical` e `imagen_horizontal`, además de `segundos_aparicion` —3 por defecto— y `limite_diario_por_visitante` —1 por defecto—.

Para que el límite diario sea verificable en servidor se agregó `popups_impresiones`, con clave primaria `(popup_id, visitante, fecha)`, contador diario, última impresión e índices para visitante y limpieza por fecha. El visitante usa el mismo UUID anónimo de 36 caracteres que el sistema de votos; la relación elimina sus contadores en cascada al borrar un popup.

La migración se aplicó **únicamente en DEV** después de validar la huella `aae27debb310` y crear `.deploy/respaldos-db/2026-08-28/dev-before-popups-v1.sql`, de 33.620 bytes y SHA-256 `7240a87c2fa1d364d31a42c04562b4eab43ea243586c9eb2936afa7a9bfc1f29`. Dos ejecuciones conservaron cero popups y cero contadores. Una prueba transaccional confirmó las 15 columnas, índices, defaults 3 segundos/1 vez diaria, inserción del contador, borrado en cascada y reversión completa: DEV terminó nuevamente vacío.

`install/schema.sql` refleja ambas tablas para instalaciones nuevas. **PROD no fue migrado y el placeholder `admin/popups.php` no fue modificado.** Próximo paso: recibir la propuesta visual del usuario para las vistas previas y diseñar el front administrativo antes de implementar el CRUD.

### Estudio visual inicial de Popups — 28 de agosto de 2026

Después de recibir la composición exacta del usuario se reemplazó el placeholder de `admin/popups.php` por una superficie visual sin persistencia. En escritorio, la preview vertical ocupa la columna izquierda y el alto disponible de la pantalla; a la derecha, la preview horizontal queda arriba y el formulario mínimo de configuración ocupa el espacio inferior. El formulario contiene únicamente los selectores de imagen vertical y horizontal, sin botón Guardar, CRUD, destinos, tiempos ni frecuencia todavía.

Ambos selectores aceptan JPG, PNG y WEBP de hasta 5 MB. `FileReader` genera una URL de datos, una imagen aislada valida la decodificación y recién entonces la pieza aparece en su preview; se muestran nombre de archivo, carga y errores sin enviar nada al servidor. En móvil los bloques se apilan como Vertical → Horizontal → Configuración, conservando las imágenes seleccionadas y sin overflow.

QA local mediante Chromium/Puppeteer porque el Browser plugin no estaba disponible. Analytics fue bloqueado durante la prueba. A `1440×950`, la preview vertical quedó a la izquierda, las dos columnas comenzaron en `top 220,08px` y terminaron juntas en `950,08px`; horizontal y configuración ocuparon la derecha sin overflow. Se cargaron un JPG real de 1200px y un WEBP real de 1024px, ambos se decodificaron como Data URL y actualizaron nombres/estado. A `390×844`, los tres bloques midieron 350px de ancho, conservaron el orden solicitado y el documento tuvo cero overflow y cero errores de consola. Capturas temporales: `/tmp/pntest/popups-layout-desktop-inicial.png`, `popups-layout-desktop-cargado.png` y `popups-layout-mobile-cargado.png`.

La implementación actual es deliberadamente visual y queda en **DEV**. No guarda archivos ni registros, no consulta las tablas nuevas, no muestra popups a visitantes y no fue desplegada a PROD. Próximo paso: esperar el nuevo alcance del usuario para completar el formulario y sus vistas previas.

### Formulario visual completo de Popups — 28 de agosto de 2026

Por aclaración del usuario, el formulario inferior derecho ya muestra todos los controles previstos por el esquema: nombre, segundos hasta aparecer, límite diario por usuario, vencimiento, estado y enlaces de Facebook, Instagram, WhatsApp y sitio web, además de los dos cargadores. La columna derecha dejó de tener altura fija: la configuración crece hacia abajo con el scroll normal, mientras la preview vertical conserva el alto de pantalla y queda fija durante el desplazamiento en escritorio. En móvil se mantiene el orden vertical, horizontal y configuración, con todos los campos en una columna.

Los campos aceptan edición visual y el switch actualiza su etiqueta Activo/Inactivo, pero el formulario continúa sin botón Guardar ni persistencia. Chromium/Puppeteer validó `1440×950` y `390×844`, carga y decodificación de las dos imágenes, nueve controles de configuración, interacción de nombre/segundos/frecuencia/estado, orden responsive, cero overflow horizontal y cero errores de consola. Analytics permaneció bloqueado durante QA. Capturas temporales: `/tmp/pntest/popups-form-completo-desktop-top.png`, `popups-form-completo-desktop.png` y `popups-form-completo-mobile.png`. **Sólo DEV; no desplegado en PROD.**

La preview vertical se ajustó después para quedar completa dentro del primer viewport también en notebooks y ventanas de menor altura. Su alto usa el espacio real disponible con límites seguros y deja margen inferior. Tras revisar una captura con una pieza vertical real, se corrigió además el ancho excesivo de la columna: queda limitado en función de la altura y conserva forma de soporte vertical aun en monitores anchos. La imagen ocupa una caja contenida exactamente por el marco y usa `object-fit: contain` centrado sobre fondo oscuro, por lo que entra completa sin deformarse ni desbordarse.

### Administración CRUD y pruebas fullscreen de Popups — 28 de agosto de 2026

Por corrección de la arquitectura visual solicitada por el usuario, `admin/popups.php` dejó de ser un estudio permanente y pasó a seguir el patrón de Anuncios. La pantalla principal contiene buscador, `Nuevo Popup`, contador y tabla con ambas piezas, nombre, destinos configurados, segundos de aparición, frecuencia diaria, vencimiento, switch de estado, clics y acciones de editar/eliminar. El backend permite crear, actualizar, activar/desactivar y borrar en las tablas DEV `popups`/`popups_impresiones`; valida nombre, dos imágenes obligatorias al crear, JPG/PNG/WEBP hasta 5 MB, URLs HTTP(S), fecha y rangos 0–3600 segundos/1–100 veces diarias. Al reemplazar o borrar se limpian las imágenes publicitarias correspondientes.

El alta y la edición viven en un drawer de 680px: primero preview móvil vertical y su carga, luego preview de escritorio horizontal y su carga, campos completos, botón Guardar y, en la misma línea, `Probar popup vertical` y `Probar popup horizontal`. Cada prueba abre un overlay que ocupa todo el viewport, contiene la pieza sin recortarla y mantiene un botón Cerrar visible arriba a la derecha. El contrato visual deja explícito que la futura publicación elegirá vertical en móvil y horizontal en PC; el emergente público **todavía no fue conectado a las noticias** para permitir los próximos ajustes del usuario.

QA local con Chromium/Puppeteer —Browser plugin ausente— en `1440×950` y `390×844`: alta con dos archivos 900×1600 y 1600×900, previews, ambos overlays, guardado real, fila/miniaturas, switch por AJAX, recuperación de datos al editar, drawer móvil dentro del viewport, cero overflow y cero errores de consola. Analytics se bloqueó durante QA. El popup temporal y sus dos archivos fueron eliminados al finalizar; DEV volvió a quedar sin registros. Capturas temporales: `/tmp/pntest/popups-tabla.png`, `popups-drawer-preview.png`, `popups-drawer-acciones.png`, `popups-prueba-vertical.png`, `popups-prueba-horizontal.png` y `popups-drawer-mobile.png`. **No desplegado en PROD.**

### Ejecución pública de Popups — 28 de agosto de 2026

Después de que el usuario creó `Pop Demo` y comprobó que no aparecía en el portal móvil, se confirmó que la administración todavía no estaba enlazada al front. Se agregó `popup-publico.php` como endpoint público de dos fases: GET selecciona el popup activo, vigente y aún disponible para el visitante; POST vuelve a bloquear y validar el registro dentro de una transacción antes de incrementar `popups_impresiones`. Así el límite diario se aplica en servidor y dos pestañas simultáneas no pueden superar el cupo por una carrera. El identificador anónimo reutiliza la cookie HTTP-only `portal_visitante` del sistema de votos.

`partials/popup-publico.php`, `assets/css/popup.css` y `assets/js/popup.js` se montan tanto en `index.php` como en `noticia.php`. El cliente espera `segundos_aparicion`, precarga la pieza, reserva la impresión y recién entonces abre el overlay. Usa el viewport para seleccionar vertical hasta 768px y horizontal por encima; si cambia el ancho antes o durante la apertura sincroniza la pieza. El anuncio se contiene completo con `object-fit: contain`, bloquea el scroll y mantiene Cerrar accesible; también cierra con fondo o Escape. Una falla de red o imagen nunca interrumpe la carga del portal.

QA real con el popup del usuario —activo, 3 segundos, límite 50— validó portada móvil `390×844` con la imagen vertical de 941px y noticia PC `1440×950` con la horizontal de 1682px. Las dos quedaron dentro del viewport, Cerrar midió al menos 44px, el fondo quedó bloqueado y se restauró al cerrar; no hubo overflow ni errores de consola. Analytics recibió respuestas locales 204 durante QA. Se eliminaron las dos filas de impresión de los visitantes automáticos y `popups_impresiones` terminó vacío, sin alterar `Pop Demo`. Capturas: `/tmp/pntest/popup-publico-mobile.png` y `/tmp/pntest/popup-publico-pc.png`. **Implementado únicamente en DEV; no desplegado a PROD.**

### Detalle lateral desde las piezas de Popups — 28 de agosto de 2026

Las dos miniaturas de cada fila de `admin/popups.php` ahora funcionan como un único acceso al detalle del popup. El mismo drawer de 680px cambia a modo consulta y muestra las piezas vertical y horizontal completas, estado, identificador, nombre, demora, frecuencia diaria, vencimiento, cuatro destinos, clics y fechas de creación/actualización. Incluye acceso directo a Editar y los botones `Probar popup vertical` y `Probar popup horizontal`, que reutilizan el overlay fullscreen existente sin registrar impresiones públicas ni consumir la frecuencia del visitante.

QA local con Chromium/Puppeteer —Browser plugin ausente— validó el popup real `Pop Demo` en escritorio `1440×950` y móvil `390×844`: ambas imágenes cargaron, los datos coincidieron con la fila —3 segundos, 50 veces diarias y estado activo—, el drawer permaneció dentro del viewport sin overflow, los dos relanzamientos conservaron imagen y Cerrar completamente visibles, y el paso Detalle → Editar recuperó el registro correcto. Cero errores de consola; Analytics respondió 204 durante QA. Capturas: `/tmp/pntest/popup-detalle-drawer.png`, `popup-detalle-mobile.png`, `popup-detalle-prueba-vertical.png` y `popup-detalle-prueba-horizontal.png`. **Sólo DEV; no desplegado a PROD.**

### Cierre y punto de pausa de Popups — 28 de agosto de 2026

El usuario aprobó como excelente la etapa completa de Popups y pidió cerrar para continuar más tarde. El baseline que debe preservarse es: tabla administrativa con CRUD y switch de estado; formulario lateral con carga y preview de ambas piezas; pruebas fullscreen; detalle lateral al pulsar las miniaturas con todos los datos, acceso a Editar y relanzamiento de ambas versiones; y ejecución pública automática con demora, límite diario por visitante y selección vertical móvil/horizontal PC. El popup real `Pop Demo` permanece intacto en DEV.

El cierre no agrega cambios funcionales posteriores a la aprobación. `AGENDA.md`, este archivo y `README.md` quedaron alineados. El worktree conserva todo el trabajo acumulado sin descartar archivos ni separar modificaciones preexistentes. **No se creó commit, no se hizo push, no se desplegaron archivos y PROD no recibió la migración `popups-v1`.** Al retomar, leer `AGENDA.md`, `CONTINUIDAD.md` y `git status --short --branch`, conservar esta interfaz aprobada y esperar la siguiente instrucción concreta del usuario.

### Cierre aprobado: panel móvil, métricas y análisis — 28 de agosto de 2026

Después del cierre inicial de Popups se completó una nueva tanda, revisada paso a paso por el usuario y aprobada finalmente como **«impresionante todo»**. Este punto reemplaza al cierre anterior como referencia más reciente, sin invalidar ninguna de las decisiones ya aprobadas.

- **Popup público:** la versión PC muestra los destinos activos en una columna vertical junto a la pieza y la etiqueta **Publicidad** con el lenguaje visual del cierre; móvil coloca una etiqueta pequeña superpuesta arriba a la izquierda y los destinos habilitados centrados al pie. La tabla administrativa replica los iconos de destinos de Anuncios y deja en gris claro los vínculos ausentes.
- **Métricas de noticias:** cada noticia conserva votos, vistas y compartidos. La tabla administrativa los presenta mediante iconos y permite ordenar por fecha, peso y las tres métricas con ciclo descendente → ascendente → neutro. La pantalla **Análisis → Vistas** compara Vistas y Compartidas, permite activar una o ambas series, alternar Área/Columnas y filtrar Desde/Hasta.
- **Actualización automática:** las pantallas de análisis consultan silenciosamente su misma URL cada 30 segundos únicamente mientras la pestaña está visible; conservan el modo de gráfico, filtros, series elegidas y posición de scroll.
- **Frente público:** el encabezado de la noticia completa queda centrado como **NOTICIA** con icono de periódico tanto en PC como en móvil. El pie compartido muestra **Software hecho en Fenix**.
- **Perfil propio:** los usuarios autenticados —incluido Editor— pueden abrir un lateral para actualizar su foto, nombre, correo y contraseña. La contraseña exige la clave actual y el flujo modifica solamente el usuario de la sesión, sin conceder gestión de terceros.
- **Noticias en móvil:** el encabezado de tabla oculta el contador, deja buscador y botón circular celeste `+` de 42px en una línea y centra el signo mediante un elemento independiente. Las filas usan imagen de 108×81px, acciones debajo y título/descripción a la derecha; debajo quedan únicamente vistas, fecha y categoría. Autor, peso, votos, compartidos y portada permanecen ocultos solo en móvil. Se retiraron la card mensual y el subtítulo **Últimas noticias**, de modo que la tabla comienza directamente después de la introducción.
- **Anuncios en móvil:** el encabezado adopta el mismo buscador y botón `+`. Cada fila compacta mide 153px: imagen 108×81px y acciones debajo a la izquierda; nombre, Creado y Vence a la derecha; Estado y Clics comparten la última línea. Destinos, Encabezado y Pie se ocultan solo en móvil y continúan disponibles en PC y en el detalle lateral.
- **Análisis → Publicaciones:** la antigua card **Publicaciones del mes** salió de Noticias y pasó a una pantalla propia. Cuenta `noticias.created_at` por día, rellena días sin publicaciones, muestra resumen de publicaciones/días activos/promedio/período, permite Área o Columnas y filtra Desde/Hasta con Aplicar. Deliberadamente no tiene selector de métricas, botón de tabla ni listado repetido de noticias. También usa la actualización automática visible de 30 segundos.

QA local acumulada con Chromium/Puppeteer —Browser plugin ausente— cubrió PC `1440×900/950`, móvil `390×844` y los controles compactos también a `360×800`. Se verificaron geometría, navegación, filtros inclusivos, cambio Área/Columnas, apertura de drawers, ausencia de overflow y consola limpia. Las sesiones y registros temporales de QA fueron eliminados; no se ejecutó el borrado de anuncios reales. Las capturas finales más útiles están en `/tmp/pntest/capturas/admin-noticias-tabla-limpia-mobile.png`, `admin-anuncios-tabla-compacta-mobile.png`, `admin-analisis-publicaciones-pc.png` y `admin-analisis-publicaciones-mobile.png`.

**Pausa operativa:** todo el bloque anterior queda aprobado como baseline de DEV. El worktree continúa acumulado y sin separar cambios preexistentes. **No se creó commit, no se hizo push, no se desplegó esta tanda y no se autorizó ninguna migración nueva en PROD.** Al retomar, leer `AGENDA.md`, este cierre y `git status --short --branch`; preservar las vistas PC aprobadas y los ajustes móviles actuales, y esperar una nueva instrucción del usuario.
