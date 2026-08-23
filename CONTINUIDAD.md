# Continuidad — Portal de Noticias

Fecha del punto de pausa: **23 de agosto de 2026** (segunda revisión del mismo día, tras aprobar el feed móvil).

## Carpeta oficial

El proyecto oficial y único sobre el que se debe continuar es:

`/home/fenixdev/public_html/proyectos.fenixdev.uno/09portal-noticias`

No mezclar cambios con las demás carpetas que pertenecen al repositorio Git superior.

## Control de versiones

Este proyecto tiene **su propio repositorio git**, independiente del repo grande de `/home/fenixdev/public_html` (que mezcla otros proyectos de clientes sin relación). Se creó el 23 de agosto de 2026, rama `main`, sin remoto configurado — vive solo en este disco por ahora.

- El primer commit (`440a41b`) incluye todo el estado aprobado hasta esa fecha: feeds, votos, panel de votaciones, administración completa.
- El `.gitignore` propio del proyecto ya excluye `admin/config.local.php` (credenciales de la base de datos), `/error_log` y las imágenes subidas en `/uploads/noticias/` (contenido de usuarios, no código fuente). **Las fotos de las noticias no viajan con el repo** — si se clona en otra máquina, `uploads/noticias/` aparece vacío salvo el `.htaccess`.
- Antes de cualquier commit, revisar `git status --short` y `git diff --cached --name-only` para confirmar que no se cuela nada de `config.local.php` ni de logs.
- Si en el futuro se agrega un remoto (GitHub/GitLab privado), documentarlo acá.

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

### Feed móvil desde base de datos — aprobado

El usuario aprobó este feed como impecable el 23 de agosto de 2026. Conservarlo.

- El feed móvil **ya no es estático**: se renderiza desde la base de datos en `partials/mobile-feed.php`. Se eliminaron de `index.php` las tres noticias de ejemplo con imágenes de Unsplash.
- No agrega consultas: consume las mismas `$noticias` y `$fotosPorNoticia` que ya prepara `index.php` para el feed PC.
- Estructura por noticia, estilo red social: foto a `100svh` con degradado inferior, categoría y título encima; a continuación el texto **completo** de la nota, el video de YouTube si lo hay, y los botones de voto y compartir.
- **Sin `scroll-snap` en móvil**: el feed se lee corrido. Fue una decisión deliberada, porque el encaje pelea con las noticias de texto largo. El `scroll-snap` sigue activo solo en PC.
- Las fotos son `<img loading="lazy" decoding="async">` con `object-fit: cover`, no `background-image`. El cambio fue necesario para que el lazy loading funcione de verdad: con imágenes de fondo el navegador no lo aplica.
- Galerías de más de una foto: carrusel horizontal con `scroll-snap-type: x mandatory` y `overflow-x: auto`, es decir deslizamiento nativo sin JavaScript. Los puntos indicadores se sincronizan con un `IntersectionObserver` sobre el track.
- **No hay rotación automática en móvil**, a diferencia del mini slider de PC. Decisión deliberada: en un feed vertical la foto que se mueve sola molesta. No agregar temporizadores acá.
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
- La clase se aplica en los dos feeds: `class="pc-content rich-text"` y `class="feed-text rich-text"`.
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

### Ampliación de galerías, PC y móvil

- El visor es **uno solo y compartido**, en `partials/lightbox.php`, incluido una única vez desde `index.php`. Antes vivía dentro de `partials/pc-feed.php`; no volver a duplicarlo.
- Solo las noticias con más de una foto muestran la lupa. En PC va abajo a la derecha de la imagen izquierda; en móvil, abajo a la derecha de la foto a pantalla completa.
- La lupa abre un visor negro a pantalla completa con la foto que estaba activa.
- **PC**: navegación circular mediante botones anterior/siguiente o teclas izquierda/derecha; zoom con la rueda del mouse entre 100% y 400%, orientado al punto del cursor.
- **Móvil**: sin flechas. Se navega deslizando en horizontal (umbral de 50px), se cierra deslizando hacia abajo (umbral de 90px) y se amplía con **pinza de dos dedos** entre 100% y 400%, orientada al punto medio entre los dedos. Con zoom activo el dedo deja de navegar y queda reservado para la pinza.
- Cierre en ambos: cruz superior derecha, tecla `Escape` o clic en el fondo exterior.
- Cada cambio de foto reinicia el zoom al 100%.
- El texto de ayuda del visor cambia según el dispositivo: «Rueda del mouse para ampliar» o «Pinza para ampliar».
- Archivos: `partials/lightbox.php`, `partials/pc-feed.php`, `partials/mobile-feed.php` e `index.php`.

### Marca de agua automática

- Todas las imágenes **nuevas** subidas por `admin/upload-imagen.php` pasan por `subir_imagen()` y reciben la marca antes de publicarse.
- Logo actual fijo: `imagenes/Logo2027v2.png` (PNG transparente de 250×100).
- Configuración aprobada: centrado, ancho equivalente al 36% de la foto y aproximadamente 15% de opacidad.
- Compatible con JPG, PNG y WEBP mediante GD.
- Salida: JPEG calidad 90, PNG compresión 6 y WEBP calidad 90.
- La escritura usa un archivo temporal y reemplazo final; ante un error se elimina el archivo incompleto.
- No modifica imágenes existentes.
- Archivo: `admin/includes/funciones.php`, función `aplicar_marca_agua_centrada()`.

## Próximos pasos acordados

1. Crear más adelante una sección **Configuración** en el panel.
2. Permitir subir o reemplazar desde esa sección el logo utilizado como marca de agua, en lugar de depender siempre de `Logo2027v2.png`.
3. Evaluar el formato provisorio de publicidad antes de diseñar una gestión dinámica de anuncios.
4. Alimentar el slider del hero desde la base de datos. **Sigue estático** con las tres noticias de ejemplo de Unsplash, en PC y en móvil (`index.php`, bloque `.slider`). Es lo único del front que todavía no sale de la base.
5. Hacer funcionar los botones de **compartir**. Siguen siendo `href="#"` y no hacen nada; para que sirvan hace falta primero un permalink por noticia, que todavía no existe. Los de voto ya funcionan.
6. Evaluar si el zoom táctil de galerías necesita también arrastre de la imagen ampliada. Hoy la pinza amplía orientada al punto medio entre los dedos, pero **no se puede desplazar la foto ya ampliada**; se confirmó en captura al 311%, donde solo se ve una parte de la imagen.
7. El sitio no tiene `favicon.ico` y el navegador lo pide en cada carga, devolviendo 404. Hay un `isotipo.png` en el directorio padre que podría servir.

El punto que antes figuraba como «implementar la ampliación y el zoom táctil de galerías cuando se trabaje en el feed móvil» quedó **hecho**; ver la sección de galerías.

## Validaciones realizadas en este punto

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
- Ejecutar con `~/tools/pruebas-navegador/correr.sh`. El script deja los votos en cero antes y después, levanta `php -S` si no está corriendo y lo baja al terminar, así que es repetible e inocuo.
- Archivos: `prueba-feed.js` (29 pruebas), `medir.js` (geometría del feed móvil), `correr.sh` (envoltorio).
- Capturas en `/tmp/pntest/capturas`. Son temporales: `/tmp` se limpia.
- Al escribir pruebas de votos, que las aserciones sean **relativas** al conteo previo, no a un número fijo. Cada corrida usa un perfil nuevo de navegador, o sea un visitante nuevo, así que el contador sube. Ese error ya se cometió una vez.

## Límites de validación

- **Ya hay navegador en el entorno** (ver la sección anterior). Las 29 pruebas pasan, incluidas la pinza de dos dedos, el deslizamiento del carrusel, el visor en ambos dispositivos y el ciclo completo de voto con clic real. Se revisaron además las capturas.
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
| `index.php` | Consultas a la base, todo el CSS, todo el JavaScript, el hero con su slider y los `include` de los partials. |
| `partials/mobile-feed.php` | Marcado del feed móvil. |
| `partials/pc-feed.php` | Marcado del feed PC. |
| `partials/publicidad.php` | Piezas de publicidad y su orden. Fuente única. |
| `partials/lightbox.php` | Marcado del visor ampliado. Compartido. |
| `partials/acciones-noticia.php` | Marcado de voto y compartir. Compartido. |
| `votar.php` | Endpoint público de votos. Raíz, sin login. |
| `admin/includes/votos.php` | Cookie de visitante, límite por IP y registro del voto. |
| `admin/votaciones.php` | Pantalla del panel: ranking de noticias más votadas. |
| `admin/assets/votaciones.js` | Dibuja el gráfico (SVG a mano, sin librería). |

Se decidió mantener **dos partials de feed** en lugar de uno responsive, para no reescribir el marcado del feed PC que ya estaba aprobado. La contrapartida asumida es que cada dispositivo descarga el marcado del otro oculto por CSS, con el contenido duplicado que eso implica para lectores de pantalla y para SEO. Unificarlos en un solo partial sigue siendo el camino más limpio a largo plazo, pero requiere pedido explícito.

## Regla al retomar

Leer primero este archivo y `README.md`, confirmar la carpeta oficial y revisar el estado de los archivos sin descartar ni sobrescribir cambios existentes. No continuar con Configuración, el slider del hero, los permalinks, la gestión dinámica de anuncios ni la unificación de los partials hasta recibir una solicitud explícita.
