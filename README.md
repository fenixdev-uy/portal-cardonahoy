# Landing + Panel de Noticias

Documentación del estado actual del proyecto. La prioridad activa y los pasos pendientes se mantienen en `AGENDA.md`; el detalle técnico y el punto de continuidad viven en `CONTINUIDAD.md`; las fallas operativas, sus causas y medidas preventivas se registran en `INCIDENTES.md`.

---

## 1. Resumen

Backend de noticias para la landing page. Las noticias se administran desde un panel (CRUD) y el feed de la **versión PC** se renderiza desde la base de datos en `landing/index.php`.

**Estado actual:** panel protegido con login, usuarios, roles/permisos, noticias, categorías y galería. El slider y los dos feeds del front, PC y móvil, están conectados al backend.

---

## 2. Requisitos y credenciales

- **Base de datos:** `fenixdev_noticias` (MySQL).
- **Credenciales:** definidas únicamente en `landing/admin/config.local.php`.
- `config.local.php` está ignorado por Git y bloqueado para acceso web.
- `landing/admin/config.php` es solamente el cargador de la configuración privada.

- **PHP:** probado con PHP 8.2. Requiere extensiones:
  - `pdo_mysql`
  - `dom` (para saneamiento de HTML del editor)

- `.user.ini` eleva `upload_max_filesize` a 25 MB y `post_max_size` a 27 MB para las subidas de audio. El endpoint también detecta cuerpos descartados por PHP y responde con un error de tamaño, no con un falso error de sesión/CSRF.

---

## 3. Estructura de archivos

```
landing/
├── README.md                   ← este documento
├── index.php                   ← front: datos, estructura y feeds PC/móvil
├── buscar-noticias.php         ← búsqueda JSON del menú fullscreen PC/móvil
├── cargar-noticias.php         ← bloques JSON paginados para la portada PC/móvil
├── noticia.php                 ← marcado individual + metatags SEO/sociales
├── sitemap.php / robots.php    ← descubrimiento e indexación de permalinks
├── db                          ← credenciales (texto plano, solo referencia)
├── assets/
│   ├── css/
│   │   ├── portal.css          ← estilos compartidos de la portada PC/móvil
│   │   └── noticia.css         ← estilos de la página pública individual
│   └── js/
│       ├── portal.js           ← interacciones compartidas de la portada
│       └── noticia.js          ← interacciones de la página individual
├── imagenes/
│   ├── Logo2027.png
│   ├── Logo2027-radiosur.png
│   ├── Logo2027v2.png
│   └── Logo2027v3.png
│
├── install/
│   ├── schema.sql              ← esquema seguro v3 para instalaciones nuevas
│   ├── schema-v2-legacy.sql    ← esquema histórico, no usar en clientes nuevos
│   ├── security-v1.php         ← migración CLI de autores a usuarios/roles
│   ├── configuracion-v1.php    ← migración CLI de configuración y su permiso
│   ├── publicidad-v1.php       ← tabla de anuncios + permiso de publicidad
│   ├── publicidad-v2.php       ← estado manual de publicación
│   ├── publicidad-v3.php       ← contador interno de clics
│   ├── publicidad-v4.php       ← anuncio exclusivo de encabezado y pie
│   ├── popups-v1.php           ← popups responsive + frecuencia diaria por visitante
│   ├── portada-v1.php          ← campo e índice para administrar el slider
│   ├── seo-v1.php              ← migración idempotente de slugs y overrides SEO
│   ├── categorias-multiples-v1.php ← varias categorías por noticia
│   ├── usuarios-foto-v1.php   ← foto opcional en perfiles de usuario
│   └── migrate.php             ← migración histórica v2, solo CLI
│
├── votar.php                   ← endpoint público de votos (POST, sin login)
├── favicon.php                 ← favicon ICO ligado al logo configurable del Admin
├── publicidad-ubicaciones.php  ← refresco JSON de encabezado y pie sin recarga
│
├── partials/
│   ├── pc-feed.php             ← loop que renderiza el feed de noticias PC
│   ├── mobile-feed.php         ← loop que renderiza el feed de noticias móvil
│   ├── mobile-news-items.php   ← bloque incremental de noticias + anuncios móvil
│   ├── pc-news-items.php       ← bloque incremental de filas editoriales PC
│   ├── portada-paginacion.php  ← consulta MySQL por cursor, filtros y galerías
│   ├── medios-noticia.php      ← audios HTML5 + videos opcionales compartidos
│   ├── publicidad.php          ← piezas de publicidad y su orden (fuente única)
│   ├── anuncio-card.php        ← formato administrado compartido de cada anuncio
│   ├── acceso-admin.php        ← login o identidad activa en el encabezado público
│   ├── boton-nota-completa.php ← disparador aprobado de la vista completa móvil
│   ├── nota-completa.php       ← vista completa compartida, recomendaciones y anuncios
│   ├── lightbox.php            ← visor ampliado de galerías (PC + móvil)
│   └── acciones-noticia.php    ← bloque de voto y compartir (PC + móvil)
│
├── uploads/                    ← archivos persistentes subidos desde el panel
│   ├── noticias/
│   ├── configuracion/          ← marcas de agua PNG (protegidas por .htaccess)
│   └── publicidad/             ← imágenes de anuncios, sin marca editorial
│
├── tools/                      ← utilidades CLI locales, nunca se despliega
│   └── validar-servicios.php   ← valida DEV/PROD sin revelar secretos
│
└── admin/                      ← panel de administración
    ├── login.php / logout.php  ← autenticación del panel
    ├── cambiar-password.php    ← cambio obligatorio de clave temporal
    ├── config.php              ← cargador de configuración privada
    ├── config.instance.php     ← identidad pública única de la instalación
    ├── config.local.php        ← conexión PDO y credenciales, ignorado
    ├── index.php               ← listado de noticias + drawer de vista previa
    ├── noticia-form.php        ← crear/editar noticia (editor TipTap + galería)
    ├── noticia-borrar.php      ← eliminar noticia (y su galería)
    ├── noticia-detalle.php     ← endpoint JSON del detalle de una noticia
    ├── configuracion-marca-agua.php ← guarda logo, opacidad y tamaño de la marca de agua
    ├── configuracion-logo-login.php ← guarda el logo de la pantalla de ingreso
    ├── configuracion-logo-portal.php ← guarda únicamente el logo público
    ├── configuracion-logo-admin.php ← guarda logo interno y genera el favicon
    ├── configuracion-seo-portada.php ← guarda título, descripción e imagen SEO global
    ├── configuracion-codigo-header.php ← guarda integraciones del head público
    ├── upload-imagen.php       ← endpoint de subida de imágenes (editor y galería)
    ├── upload-audio.php        ← endpoint de subida de audios (MP3/M4A/OGG/WAV)
    ├── galeria-borrar.php      ← elimina una foto recién subida (aún sin guardar)
    ├── categorias.php          ← CRUD de categorías
    ├── usuarios.php            ← alta y gestión de usuarios por administrador
    ├── roles.php               ← perfiles de roles y permisos
    ├── anuncios.php            ← CRUD de Publicidad → Anuncios
    ├── popups.php              ← CRUD, detalle lateral y previews fullscreen de Popups
    ├── votaciones.php          ← ranking de noticias más votadas (gráfico)
    ├── assets/
    │   ├── admin.css           ← estilos del panel, editor, drawer, galería y responsive
    │   ├── seo-noticia.js      ← modo automático/manual y previews SEO en vivo
    │   └── votaciones.js       ← dibuja el gráfico de votaciones (SVG a mano)
    └── includes/
        ├── funciones.php       ← helpers (sesión, flash, subida, sanitización, etc.)
        ├── votos.php           ← cookie de visitante, límite por IP, registro del voto
        ├── header.php          ← layout: sidebar + topbar
        └── footer.php          ← cierre del layout + JS del menú
```

---

## 4. Base de datos

### Tabla `categorias`

| Campo        | Tipo         | Notas                          |
|--------------|--------------|--------------------------------|
| id           | INT UNSIGNED | PK, autoincrement              |
| nombre       | VARCHAR(100) |                                |
| slug         | VARCHAR(120) | único                          |
| created_at   | TIMESTAMP    |                                |
| updated_at   | TIMESTAMP    | on update                      |

### Tablas de seguridad

- `usuarios`: identidad, correo único, hash, rol, foto opcional (`VARCHAR(255)`), estado, cambio obligatorio y último acceso.
- `roles`: Administrador, Editor y Autor.
- `permisos`: acciones individuales del panel.
- `rol_permisos`: asignación configurable de permisos a cada rol.
- `intentos_login`: limitación de intentos por correo e IP sin guardar la IP en claro.

### Tabla `noticias`

| Campo          | Tipo          | Notas                                  |
|----------------|---------------|----------------------------------------|
| id             | INT UNSIGNED  | PK, autoincrement                      |
| categoria_id   | INT UNSIGNED  | FK → categorias.id (on delete set null)|
| usuario_id     | INT UNSIGNED  | FK → usuarios.id (on delete set null)  |
| titulo         | VARCHAR(255)  |                                        |
| slug           | VARCHAR(190)  | único; permalink público estable       |
| descripcion    | TEXT          | HTML enriquecido (saneado)             |
| seo_titulo     | VARCHAR(255)  | override opcional; `NULL` = automático |
| seo_descripcion| VARCHAR(500)  | override opcional; `NULL` = automático |
| seo_imagen     | VARCHAR(255)  | override opcional; `NULL` = portada    |
| youtube        | VARCHAR(255)  | URL de YouTube 1 (se convierte a embed)|
| youtube_2      | VARCHAR(255)  | URL opcional de YouTube 2              |
| youtube_3      | VARCHAR(255)  | URL opcional de YouTube 3              |
| audio_1        | VARCHAR(500)  | URL opcional de audio 1                |
| audio_2        | VARCHAR(500)  | URL opcional de audio 2                |
| audio_3        | VARCHAR(500)  | URL opcional de audio 3                |
| portada        | TINYINT(1)    | `1` = mostrar la noticia en el slider  |
| created_at     | TIMESTAMP     |                                        |
| updated_at     | TIMESTAMP     | on update                              |

### Tabla `noticias_categorias`

Relaciona cada noticia con una o más categorías. Su clave primaria compuesta `(noticia_id, categoria_id)` evita asociaciones duplicadas y `posicion` conserva el orden. `noticias.categoria_id` permanece sincronizado con la primera selección como categoría principal para compatibilidad con instalaciones anteriores.

### Tabla `noticias_fotos` (galería)

| Campo      | Tipo         | Notas                                        |
|------------|--------------|----------------------------------------------|
| id         | INT UNSIGNED | PK, autoincrement                            |
| noticia_id | INT UNSIGNED | FK → noticias.id (on delete cascade)         |
| ruta       | VARCHAR(255) | ruta local o URL externa                     |
| posicion   | INT          | orden; `0` = portada (la primera)            |
| created_at | TIMESTAMP    |                                              |

> La foto con `posicion = 0` es siempre la portada. El formulario permite subir varias, reordenarlas y eliminar.

### Tabla `noticias_slugs_historial`

Conserva cada slug anterior con su `noticia_id`. La URL vieja responde 301 hacia el slug vigente y la tabla se elimina en cascada si se borra la noticia.

### Tabla `anuncios`

| Campo               | Tipo         | Notas                                      |
|---------------------|--------------|--------------------------------------------|
| id                  | INT UNSIGNED | PK, autoincrement                          |
| nombre              | VARCHAR(120) | nombre comercial obligatorio               |
| imagen              | VARCHAR(255) | ruta local obligatoria                     |
| facebook_url        | VARCHAR(500) | URL HTTP/HTTPS opcional                    |
| instagram_url       | VARCHAR(500) | URL HTTP/HTTPS opcional                    |
| whatsapp_url        | VARCHAR(500) | URL HTTP/HTTPS opcional                    |
| sitio_web_url       | VARCHAR(500) | URL HTTP/HTTPS opcional                    |
| fecha_vencimiento   | DATE         | `NULL` = permanente; vencido desde esa fecha |
| activo              | TINYINT(1)   | estado manual de publicación                |
| en_encabezado       | TINYINT(1)   | seleccionado debajo del título de la noticia |
| en_pie              | TINYINT(1)   | seleccionado al terminar la noticia         |
| clics               | INT UNSIGNED | contador interno no editable                 |
| created_at          | TIMESTAMP    | fecha de creación automática               |
| updated_at          | TIMESTAMP    | actualización automática                   |

La migración idempotente `install/publicidad-v1.php` crea la tabla completa y el permiso `publicidad.gestionar`, asignado inicialmente al rol administrador. Para instalaciones existentes, `publicidad-v2.php`, `publicidad-v3.php` y `publicidad-v4.php` agregan respectivamente Estado, Clics y las dos ubicaciones exclusivas.

### Votos (`me_gusta` / `no_me_gusta`)

- `noticias.me_gusta` y `noticias.no_me_gusta`: `INT UNSIGNED NOT NULL DEFAULT 0`. Solo se incrementan; nunca se restan.
- `noticias_votos`: un voto por visitante y por noticia, **definitivo**. Clave primaria `(noticia_id, visitante)` — es lo que impide el segundo voto.
- `votos_limite`: techo de 60 votos por hora, por hash de IP (nunca la IP en claro).

Las migraciones para bases existentes incluyen `install/votos-v1.php`, `install/medios-v1.php`, `install/seo-v1.php` e `install/categorias-multiples-v1.php`; son idempotentes.

El esquema completo y los datos de ejemplo están en `install/schema.sql`. La migración de la versión anterior (que reemplaza `foto_principal` por la galería) está en `install/migrate.php`.

---

## 5. Panel de administración (funcionalidades)

- **Menú lateral** (izquierda) + contenido a la derecha. Responsive (hamburguesa en móvil). **Noticias**, **Usuarios**, **Publicidad** y **Análisis** funcionan como grupos desplegables y solo mantienen uno abierto a la vez.
- **Noticias** (`index.php`):
  - Listado (foto, título, categorías, fecha, peso, votos, estado de portada y acciones), con buscador instantáneo y orden por fecha en ambos sentidos.
  - La columna **Portada** incorpora un switch con guardado inmediato. Solo permite activarlo cuando la noticia tiene al menos una foto.
  - Columna **Peso** calculada desde los archivos locales reales: galería, imágenes insertadas en el editor y audios subidos. Ordena en ambos sentidos y muestra el desglose Fotos/Audios; YouTube y URLs externas no se cuentan porque no consumen disco local.
  - Vista previa en drawer lateral (40% del ancho) al hacer clic en la foto: foto de portada, miniaturas de la galería, categoría, título, fecha larga, autor, descripción formateada y reproductor de YouTube embebido si tiene URL.
  - Acciones de editar / eliminar. El borrado limpia galería, imágenes internas y audios locales únicamente cuando ningún otro contenido conserva la misma referencia.
- **Crear/editar noticia** (`noticia-form.php`):
  - Campos: una o más categorías, autor, título, descripción (editor TipTap), hasta 3 audios, hasta 3 videos de YouTube y **galería de fotos**.
  - El selector permite marcar varias; filtros, portada, feeds, página individual, drawer, listado y votaciones reconocen todas las asociaciones.
  - En la portada PC, el filtro de categorías también admite varias selecciones y devuelve noticias asociadas a cualquiera de ellas; la URL conserva la combinación junto con texto y fechas.
  - El menú fullscreen de PC, móvil y página individual conserva únicamente el buscador centrado, además del logo principal y el cierre. Se retiraron «Escuchá la radio», Noticias, Videos y Contactos. Desde dos caracteres muestra por AJAX hasta cinco noticias en un panel rectangular con miniaturas y textos ampliados, separados por líneas blancas finas; en la portada elegir una abre la vista completa existente —drawer lateral en PC y hoja personalizada inferior en móvil— y en la página individual navega al permalink elegido.
  - Medios: dos cards en columnas; los audios aceptan URL HTTPS o subida inmediata MP3/M4A/OGG/WAV (máx. 25 MB) y la base guarda únicamente la URL.
  - Galería: subida **inmediata** por AJAX al elegir cada foto (muestra la miniatura al instante, sin esperar el guardado), **arrastrar y soltar** para reordenar (SortableJS), eliminar con × y la primera foto es la portada.
  - La edición incluye el switch **Portada** para mostrar u ocultar la noticia en el slider del encabezado.
- **Categorías** (`categorias.php`):
  - CRUD completo con buscador, contador y alta/edición en panel lateral; cada categoría muestra cuántas noticias la utilizan.
- **Usuarios** (`usuarios.php`):
  - Alta, edición, activación y desactivación solamente por administradores.
  - Rol, firma editorial, contraseña temporal y cambio obligatorio al ingresar.
  - Vista principal sin formulario: título y tabla con acciones por iconos.
  - Alta y edición dentro de un panel lateral derecho; admite foto de perfil JPG/PNG/WEBP de hasta 3 MB, con preview circular que espera la lectura y decodificación completa antes de reemplazar la imagen, y acceso a **Editar roles**.
- **Roles** (`roles.php`): perfiles Administrador, Editor y Autor con permisos configurables.
- **Publicidad**:
  - **Anuncios** (`anuncios.php`) incluye tabla con miniatura, destinos, vencimiento, creación, Estado, Encabezado, Pie, clics y acciones; alta/edición en drawer, vista previa local, borrado e imágenes aisladas en `uploads/publicidad/`.
  - Encabezado y Pie son switches independientes y exclusivos: al elegir otro anuncio se retira automáticamente el anterior de esa ubicación. Solo se renderiza el elegido cuando además está activo y vigente.
  - Facebook, Instagram, WhatsApp y Web son URLs opcionales HTTP/HTTPS. La imagen y el nombre son obligatorios; la fecha vacía significa que no vence.
  - El acceso exige `publicidad.gestionar`. **Popups** (`popups.php`) ofrece CRUD completo, piezas vertical/horizontal, demora de aparición, límite diario por visitante, estado, vencimiento, destinos, clics, detalle lateral al pulsar las miniaturas y pruebas fullscreen. El portal selecciona la pieza vertical en móvil y la horizontal en PC; el límite se reserva en servidor mediante `popups_impresiones`.
- **Análisis**:
  - **Votaciones** (`votaciones.php`) reúne el ranking y sus métricas dentro de este grupo, identificado con iconos de gráfica y aprobación.
- **Seguridad**:
  - Login, sesiones seguras con vencimiento, control de intentos y respuestas genéricas. `config.instance.php` asigna una identidad única y versionada que, junto con el alcance limitado a la ruta de la aplicación, aísla las cookies administrativas y públicas de otros portales instalados en el mismo dominio. En móvil, imagen, identidad, campos, botón y aviso de seguridad se adaptan a `100svh` para quedar visibles en una sola pantalla sin scroll, incluso en `320×568`.
  - Autorización por rol y permiso en páginas y endpoints.
  - Tokens CSRF en todas las operaciones que modifican datos.
  - Instaladores, logs, configuración local y listados de directorio bloqueados por Apache.
  - Subidas autenticadas, limitadas, validadas por contenido y sin ejecución de PHP.
  - Descripción saneada en el servidor (`sanitizar_html`): permite solo etiquetas seguras, elimina atributos peligrosos, valida protocolos de enlaces.
  - Títulos/categorías/fechas/autores renderizados con escape (`htmlspecialchars`).
  - URLs de YouTube normalizadas a `youtube-nocookie.com` (`youtube_embed_url`).

---

## 6. Editor de texto enriquecido (TipTap)

- Cargado por CDN (esm.sh) mediante ES modules + import map. TipTap v3 no trae build UMD.
- Extensiones: StarterKit, Link, Underline, Placeholder, TextStyle, Color, Highlight, TextAlign, Subscript, Superscript e Image.
- Toolbar: negrita, cursiva, subrayado, tachado, resaltado, color de texto, alineación (izquierda/centro/derecha/justificado), subíndice, superíndice, H2/H3, listas, cita, código en línea, bloque de código, línea separadora, enlace, imagen, deshacer/rehacer.
- La inserción de imágenes sube el archivo vía `upload-imagen.php` y lo guarda en `uploads/noticias/` (JPG/PNG/WEBP, máx. 5 MB).
- Al guardar, el HTML del editor se envía en un campo oculto `descripcion`.

> Nota: el contenido de `descripcion` se guarda como HTML (ya saneado) y el front PC lo renderiza con sus propios estilos en `.pc-content`.

---

## 7. Estado actual

### Hecho — backend y panel
- [x] Tablas editoriales + usuarios, roles, permisos e intentos de acceso.
- [x] Conexión PDO (singleton) funcional.
- [x] Panel de administración con menú lateral responsive.
- [x] CRUD de noticias y categorías; gestión administrativa de usuarios y roles.
- [x] Login/logout, cambio obligatorio de contraseña y permisos por rol.
- [x] Editor de texto enriquecido TipTap para la descripción (con subida de imágenes).
- [x] Galería de fotos por noticia:
  - Subida **inmediata** por AJAX al elegir cada foto (miniatura al instante).
  - **Arrastrar y soltar** para reordenar (SortableJS).
  - Eliminar fotos y portada = primera foto.
- [x] Vista previa (drawer) con galería, autor y fecha larga.
- [x] Hasta tres audios con reproductor HTML5 y tres videos de YouTube embebidos (`youtube-nocookie.com`); los campos vacíos no generan ningún bloque.
- [x] Fecha en español largo (`fecha_larga()`: "22 de agosto de 2026").
- [x] Configuración de marca de agua en un drawer lateral: card contraíble, carga PNG, intensidad entre 5% y 100%, tamaño entre 15% y 65% y vista previa inmediata sobre una foto real. Se aplica centrada a las imágenes nuevas; el valor inicial y fallback sigue siendo `Logo2027v2.png`, 15% de intensidad y 36% de ancho.

### Hecho — front PC
- [x] Front conectado al backend (`index.php` + `partials/pc-feed.php`).
- [x] Header y slider principal a pantalla completa, alimentado por las noticias de base con `portada = 1` y al menos una foto.
- [x] El extremo derecho del encabezado reconoce la sesión: sin usuario enlaza a `admin/login.php` —icono + **Ingresar** en PC y solo icono móvil—; autenticado muestra foto, nombre y rol y abre directamente `admin/index.php`.
- [x] Portada editorial con todas las noticias en tarjetas: tres columnas desde `1100px` y dos columnas entre `769px` y `1099px`.
- [x] Tarjetas rectangulares, sin bordes redondeados: portada `3:2`, categoría, fecha compacta, título y resumen; toda la tarjeta abre el permalink público.
- [x] Cada pareja completa de noticias forma una fila híbrida con un anuncio. Una semilla estable distribuye tanto el anunciante como su posición —izquierda, centro o derecha— y garantiza que las tres ubicaciones roten; si el total es impar, la última noticia queda sola. Los tres elementos igualan dimensiones en escritorio ancho y en el rango compacto se reorganizan en dos columnas.
- [x] El pie publicitario conserva la etiqueta a la izquierda y agrega a la derecha cuatro iconos negros para Facebook, Instagram, WhatsApp y sitio web. Cada destino es un dato independiente del anunciante, se valida como URL HTTP/HTTPS y abre en una pestaña nueva; los valores actuales son únicamente demostrativos hasta crear la administración y persistencia en base.
- [x] El clic normal sobre una tarjeta abre la noticia completa en un drawer derecho recto al `40%` del viewport. Reutiliza el mismo contenido funcional de móvil: galería con autoplay, fullscreen y zoom, texto, publicidad, audios/videos, votos definitivos y compartir en Facebook/WhatsApp. `Ctrl`/`Cmd`/`Shift` + clic conserva la apertura normal del permalink.
- [x] La portada PC carga inicialmente 6 noticias y agrega bloques de 6 mediante **Ver + Noticias**. El cursor combina `created_at` e `id`, conserva texto, categorías múltiples y fechas en la URL, mantiene la rotación de anuncios y desaparece cuando no quedan resultados.
- [x] Herramienta accesible de lectura `A−` / `A+` debajo del primer anuncio: escala únicamente párrafos, listas y subtítulos editoriales entre 90% y 140%, anuncia el porcentaje a lectores de pantalla y vuelve a 100% al reabrir.
- [x] Orden: la última noticia creada sale primera.
- [x] El antiguo feed de una noticia por pantalla, sus pantallas publicitarias, mini-slider, lupa y `scroll-snap` fueron retirados de la portada PC. El contenido completo y las galerías permanecen en el drawer y en el permalink individual.

### Hecho — front móvil
- [x] Feed móvil conectado al mismo backend paginado que PC (`index.php` + `partials/mobile-feed.php`), con presentación y cursor propios para no mezclar los filtros exclusivos de escritorio.
- [x] El feed móvil carga inicialmente 6 noticias y sus anuncios; **Ver + Noticias** agrega bloques de 6 sin filtros, duplicados, recarga ni desplazamiento del scroll. Cada bloque incorpora los templates necesarios para abrir la hoja completa.
- [x] Feed resumido aprobado: una portada a `100svh`, categoría y título encima; debajo, fecha, autor, extracto en texto plano de hasta 280 caracteres y botón «Ver nota completa».
- [x] Sin `scroll-snap` en móvil, a propósito: el encaje pelea con las noticias de texto largo.
- [x] El feed muestra solamente la portada aunque haya varias fotos; galería, texto completo, audios, videos y votos viven en la vista completa.
- [x] Fotos como `<img loading="lazy">` en lugar de `background-image`, para que el lazy loading se aplique de verdad.
- [x] Vista completa en hoja al `85%`, aprobada: encabezado blanco, portada o galería, datos editoriales, primer anuncio, contenido completo, medios, votos y segundo anuncio. Cierra con cruz, fondo, `Escape` o arrastre del encabezado.
- [x] Después del último anuncio, la hoja incorpora **Últimas Noticias** con hasta 10 publicaciones recientes —sin repetir la abierta— y alterna entre ellas anuncios activos y vigentes con imagen, identificación y accesos sociales. Elegir una recomendación cambia la noticia dentro de la misma hoja y vuelve al inicio.
- [x] Galería de la hoja 30% más alta que el antiguo 4:3, sin franjas: autoplay cada 3,2s y puntos cuando hay varias fotos; la lupa y la apertura fullscreen tocando directamente la imagen están disponibles incluso con una única portada.
- [x] Visor móvil: deslizar para cambiar/cerrar al 100%, pinza de dos dedos de 100% a 400% y arrastre de la imagen ampliada con un dedo, limitado al área visible.
- [x] Video de YouTube embebido y fondo degradado neutro para noticias sin fotos.
- [x] Un anuncio provisorio a ancho completo después de cada noticia, rotando por las cuatro piezas.

### Hecho — votos
- [x] Botones de "me gusta / no me gusta" funcionales en los dos feeds, con el contador al lado de cada opción.
- [x] Un voto por visitante y por noticia, **definitivo**: no se deshace ni se cambia. Los contadores solo suman y nunca pueden ser negativos.
- [x] Contadores `me_gusta` y `no_me_gusta` en `noticias` como caché de lectura; la verdad vive en `noticias_votos`.
- [x] Visitante identificado por una cookie propia de la instalación (`portal_visitante_<instancia>`, UUID v4, `httpOnly`, `SameSite=Lax`, un año). Se emite al votar, no al mirar.
- [x] Endpoint `votar.php` en la raíz, sin login, idempotente, con límite de 60 votos por hora y por IP.
- [x] Contadores visibles en el listado del panel y en el JSON del detalle.
- [x] Bloque de voto y compartir unificado en `partials/acciones-noticia.php`; antes estaba duplicado en los dos feeds.

### Hecho — panel: Votaciones
- [x] Pantalla `admin/votaciones.php`, enlazada como **Análisis → Votaciones** debajo de Publicidad.
- [x] Ranking de noticias por total de votos, de mayor a menor. Top 8 en el gráfico; si hay más, se avisa cuántas quedan afuera y la tabla las lista todas.
- [x] Dos lecturas del mismo dato a elección del usuario: área degradada y columnas, con un solo eje vertical.
- [x] Colores azul/rojo del par divergente de la paleta de referencia, validados con el script del skill de dataviz contra la superficie real del panel (no la de referencia).
- [x] Tooltip por hover con el detalle de cada noticia, anclado a su propia marca y con volteo automático cuando no entra arriba.
- [x] Gemelo en tabla con todas las noticias, oculto por defecto.
- [x] El SVG ocupa el ancho disponible del panel y se redibuja con `ResizeObserver` al cambiar de tamaño.
- [x] Estado vacío cuando todavía no hay votos.
- [x] Estilos del HTML enriquecido unificados en una clase `.rich-text` compartida por los dos feeds. Antes solo existían para PC, así que en móvil los subtítulos y las listas se veían sin formato.

### Pendiente / próximas versiones
- [x] **SEO por noticia desplegado en PROD:** permalink estable, historial 301, título/descripción/imagen automáticos con overrides opcionales, previews Google/social, página pública, canonical, Open Graph, Twitter Card, `NewsArticle`, sitemap, robots y botones Compartir. La base de producción fue respaldada y migrada de forma idempotente; la salida pública pasó QA HTTPS.
- [x] **SEO de la página principal en DEV:** card global con modo automático/personalizado, imagen social opcional y previews Google/social. La portada consume los valores efectivos en title, description, canonical, Open Graph, Twitter Card y JSON-LD `WebSite`.
- [x] **Código del Header en DEV:** card para guardar y activar snippets confiables de Google Analytics, Meta Pixel u otras integraciones únicamente en el `head` público. Las aperturas de noticias dentro del drawer/hoja emiten eventos virtuales con ID, título y URL.
- [x] **Página individual móvil desplegada y aprobada:** encabezado del portal, hero/galería con lupa incluso para una foto, visor con zoom de 100% a 400%, fecha, autor, HTML enriquecido, medios, votos, redes alineadas y regreso a la portada. La rotación automática mueve solamente el carrusel horizontal y no altera el scroll vertical.
- [ ] **Etapa activa:** terminar de dar forma a la nueva grilla de la portada PC después de la revisión visual del usuario. Luego se retomará el rediseño de la página individual en PC. La versión móvil es el baseline aprobado y debe permanecer intacta.
- [x] **Asistente editorial desplegado:** el botón **«Crear con IA»** abre un drawer lateral donde el periodista puede pegar información cruda o fragmentos de otras fuentes y agregar indicaciones. Siempre que TipTap tenga contenido —especialmente al editar una noticia— su texto actual reemplaza la información base al abrir el asistente; si está vacío no la sobrescribe. DeepSeek construye una propuesta, permite crear otra versión y solo la agrega a TipTap al confirmar. Para garantizar exactitud no admite URLs: el periodista debe copiar el contenido relevante del enlace. La clave nunca llega al navegador. Código, runtime privado y bloqueo HTTP quedaron publicados y verificados en producción el 25 de agosto de 2026.
- [x] Slider del home conectado al backend y administrable mediante switches desde la tabla y la edición de noticias.
- [x] Autenticación y protección completa del panel.
- [x] Botones de **compartir** funcionales para Facebook y WhatsApp mediante el permalink canónico. WhatsApp recibe únicamente la URL para que su tarjeta social no repita debajo el título y el enlace.
- [ ] Limpiar archivos huérfanos: si se suben fotos y se abandona el formulario sin guardar, quedan en `uploads/noticias/` sin asociar.
- [ ] Mejoras futuras: subir videos al servidor (hoy es URL de YouTube), más de un video por noticia, arrastrar archivos desde el escritorio a la galería, previsualizar fotos antes de subir.
- [x] Sección **Configuración** del panel, ubicada encima del usuario conectado, con gestión visual de la marca de agua, el logo del login y la identidad del portal/favicon.
- [x] CRUD inicial de **Publicidad → Anuncios** en DEV: tabla, permiso, imágenes, destinos, vencimiento, alta, edición y borrado.
- [x] Anuncios vigentes conectados con portada, feed, recomendaciones y ubicaciones exclusivas dentro de cada noticia.
- [x] **Publicidad → Popups completo en DEV:** migración, CRUD, dos piezas responsive, detalle lateral, previews fullscreen y ejecución pública con demora y frecuencia diaria por visitante. Pendiente únicamente de autorización expresa para migrar y desplegar en PROD.
- [ ] Probar los gestos ya aprobados en un teléfono real, especialmente Safari iOS, para evaluar sensibilidad, inercia y rendimiento fuera de Chrome headless.
- [ ] Evaluar unificar `pc-feed.php` y `mobile-feed.php` en un único partial responsive. Hoy cada dispositivo descarga el marcado del otro oculto por CSS, con el contenido duplicado que eso implica para lectores de pantalla y para SEO.

---

## 8. Acceso rápido

- **Panel:** `/landing/admin/index.php`
- **Nueva noticia:** `/landing/admin/noticia-form.php`
- **Categorías:** `/landing/admin/categorias.php`
- **Usuarios:** `/landing/admin/usuarios.php`
- **Roles:** `/landing/admin/roles.php`
- **Anuncios:** `/landing/admin/anuncios.php`
- **Popups:** `/landing/admin/popups.php`
- **Front (feeds PC y móvil desde la BD):** `/landing/index.php`
- **Noticia pública:** `/landing/noticia/{slug}`
- **Sitemap:** `/landing/sitemap.xml`
- **Estándar de publicación:** `ESTANDAR_DESPLIEGUE_FTPS.md`
- **Plantilla de servicios sin secretos:** `servicios.example.json`
- **Validador privado DEV/PROD:** `php tools/validar-servicios.php`

El archivo real `servicios.local.json` es privado y usa el esquema **versión 2**: reúne proyecto, despliegue, `databases.development`, `databases.production` y DeepSeek. `deployment.database_environment` declara qué base corresponde al sitio publicado. Está ignorado por Git, bloqueado por Apache y conserva permisos `600`. Nunca se sube completo: cada ambiente mantiene su propio `admin/config.local.php` y producción recibe únicamente los runtimes específicos necesarios, jamás credenciales FTPS. Toda migración debe nombrar DEV o PROD y generar el respaldo de ese mismo entorno. Los despliegues directos siguen el estándar documentado; tampoco se publican archivos de continuidad ni estado local de despliegue.

En el cloud cPanel/WHM actual todas las cuentas usan el servicio FTPS global mediante `vps-4962765-x.dattaweb.com:21`. El certificado y su cadena se corrigen una vez por servidor; cada proyecto mantiene usuario, contraseña y ruta confinada propios. El procedimiento, la recuperación y las verificaciones posteriores a cambios de cPanel están documentados en la sección 14 del estándar.

---

## 9. Notas técnicas

### Render del front
- `index.php` es PHP (antes era `index.html`). Incluye `admin/includes/funciones.php`, consulta las noticias (`ORDER BY created_at DESC, id DESC`) más las galerías en una segunda consulta, y delega el dibujo de los feeds en `partials/pc-feed.php` y `partials/mobile-feed.php`. Ambos partials consumen las mismas variables, así que agregar el feed móvil no sumó consultas.
- El slider del home (`hero`) toma categoría, título, resumen y primera foto de cada noticia marcada como portada.
- La tipografía pública usa **Arial Black** con `font-weight: 800` para `h1`–`h6` y carga **Roboto** desde Google Fonts para textos, controles y contenido editorial. Cuando Arial Black no está instalada, Roboto 800 queda como fallback grueso. El administrador conserva su tipografía independiente.
- Los estilos y las interacciones de la portada viven en `assets/css/portal.css` y `assets/js/portal.js`, cargados con versión automática por `filemtime()`. La página individual usa `assets/css/noticia.css` y `assets/js/noticia.js` con el mismo criterio. Cada PHP entrega al script solamente la URL dinámica de votos mediante `data-vote-url`; los partials solo aportan marcado.
- `partials/nota-completa.php` aporta un único template funcional por noticia. En móvil se clona dentro de la hoja inferior aprobada y en PC dentro del drawer derecho, evitando mantener dos versiones distintas del contenido completo.
- Los estilos del HTML de la descripción están en la clase global `.rich-text`, fuera de media queries, y se aplican al contenido PC y a la hoja móvil. El resumen del feed es texto plano. Al permitir una etiqueta nueva en `sanitizar_html()`, darle estilo en `.rich-text`.

### Galería y subida de fotos
- Las fotos se guardan en `noticias_fotos` (ruta relativa a `landing/` o URL externa). La de `posicion = 0` es la portada.
- El formulario envía un campo `fotos_json` con la lista ordenada (mezcla de `{id}` existentes y `{url}` recién subidas). Al guardar: se borran las existentes que faltan, se reordena por `posicion` y se insertan las nuevas.
- Subida inmediata: `upload-imagen.php` recibe un archivo (`imagen`) y devuelve `{url}`; `galeria-borrar.php` elimina una foto recién subida (solo rutas `uploads/noticias/`).
- Arrastrar y soltar: se usa **SortableJS** (CDN jsdelivr). Si el CDN no carga, la galería sigue funcionando (subir/eliminar/mostrar) y solo se deshabilita el arrastre.
- **Caveat:** si se suben fotos y se abandona el formulario sin guardar, quedan huérfanas en `uploads/noticias/` (ver pendientes).
- Cada imagen nueva pasa por `aplicar_marca_agua_centrada()` antes de quedar publicada. La función consulta `marca_agua_ruta` y `marca_agua_opacidad` en la tabla `configuracion`; centra el PNG al 36% del ancho. Si la tabla, el archivo o el valor aún no están disponibles, conserva como fallback seguro `imagenes/Logo2027v2.png` al 15%.
- La marca se aplica a JPG, PNG y WEBP. Si el procesamiento falla, se elimina el archivo incompleto y la subida devuelve error.
- Las imágenes existentes no se modifican retroactivamente.

### Publicidad administrada
- Las piezas y su orden viven en `partials/publicidad.php`, del que se alimentan los dos feeds. No duplicar rutas de anuncios en otro lugar.
- Pareja 1: `Publicidad-facha.jpg` + `Publicidad-intendencia.jpg`.
- Pareja 2: `Publicidad-Fenix.jpg` + `Publicidad-Digitales.jpg`.
- **PC:** cada pareja completa de noticias se presenta con un aviso para formar una fila de tres elementos. La semilla de la portada elige un punto inicial y un sentido de rotación para que el anuncio recorra izquierda, centro y derecha sin cambiar caprichosamente al recargar; el anunciante rota con el mismo criterio entre Intendencia, Fenix y Facha. Desde `1100px` los tres elementos ocupan columnas iguales; entre `769px` y `1099px` la fila responde en dos columnas. Las imágenes publicitarias se muestran completas, sin recorte ni bordes redondeados, y cada pieza lleva la identificación «Publicidad».
- Cada aviso PC incluye `nombre`, `facebook_url`, `instagram_url`, `whatsapp_url` y `sitio_web_url`. El render omite cualquier destino vacío, inválido o con un protocolo distinto de HTTP/HTTPS. Los iconos mantienen el estilo negro de las acciones de noticia, cuentan con nombre accesible y usan `target="_blank"` con `noopener noreferrer`.
- **Móvil:** después de cada noticia se inserta **un solo** anuncio administrado a ancho completo, cuadrado, para que se lea como una tarjeta más del feed y no encadene dos pantallas de publicidad seguidas.
- **Noticia completa:** el switch **Encabezado** elige el único aviso ubicado después de título, fecha y autor; **Pie** elige el único aviso posterior a contenido, medios, votos y acciones. Ambos usan la misma card de imagen completa, etiqueta «Publicidad» y cuatro destinos sociales que el resto del portal. Una selección inactiva o vencida permanece configurada, pero no se publica. Este contrato alcanza el drawer PC, la hoja móvil y la página individual.
- Los cambios de Encabezado y Pie se propagan entre pestañas mediante una señal local y `publicidad-ubicaciones.php`. Las notas ya abiertas reemplazan solamente esos dos espacios, sin recargar la página, cerrar el drawer/hoja ni perder la posición de lectura. Volver a enfocar la pestaña también fuerza una sincronización segura.
- **Flujo móvil aprobado:** «Ver nota completa» abre una hoja al `85%`. A continuación del anuncio de Pie, **Últimas Noticias** intercala anuncios administrados activos y vigentes; no muestra publicidad después de la última recomendación.
- La hoja conserva un encabezado fijo «RADIO SUR - NOTICIAS» y puede cerrarse arrastrándolo hacia abajo. Un gesto corto rebota a su posición; al superar el umbral termina de bajar. Detalle técnico y validaciones en `CONTINUIDAD.md`.

### Ampliación de galerías
- El visor es uno solo, en `partials/lightbox.php`, incluido una única vez desde `index.php` y compartido por los dos feeds.
- En PC, la lupa aparece abajo a la derecha cuando la noticia tiene más de una foto. En la hoja móvil aparece siempre que exista al menos una portada, incluso si es la única imagen.
- El visor ocupa la pantalla completa y recorre las fotos de forma circular. Cerrar: la cruz, `Escape` o clic en el fondo exterior.
- **PC:** botones anterior/siguiente o teclas de dirección; la rueda del mouse controla el zoom entre 100% y 400%, orientado al punto del cursor.
- **Hoja móvil:** galería 30% más alta que el 4:3 anterior; con varias fotos suma carrusel horizontal, autoplay de 3,2s y puntos. La lupa queda siempre superpuesta y tocar la foto también abre el visor. Las fotos cubren el marco completo con `object-fit: cover`.
- **Visor móvil:** sin flechas. Al 100% se navega deslizando en horizontal y se cierra hacia abajo. La pinza de dos dedos amplía entre 100% y 400%; con zoom activo, un dedo arrastra la imagen dentro de límites seguros.
- Cambiar de imagen reinicia el zoom al 100%.

### Votos del feed
- Regla de negocio: **un voto por visitante y por noticia, definitivo.** No se deshace ni se cambia, y por eso los contadores solo se incrementan. `INT UNSIGNED` es seguro porque nunca se resta.
- La verdad vive en `noticias_votos`, cuya clave primaria `(noticia_id, visitante)` es la que impide el segundo voto. `noticias.me_gusta` y `noticias.no_me_gusta` son un caché para que el feed no agrupe en cada carga; viajan en la consulta que ya existía, sin costo extra.
- El visitante usa una cookie aislada por instalación (`portal_visitante_<instancia>`) con UUID v4, `httpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS y un año de vigencia. Se descartó identificar por IP porque los celulares salen por NAT del operador.
- `index.php` lee los votos del visitante en una sola consulta (`votos_del_visitante()`) para marcar los botones. Pasa de 2 a 3 consultas por carga.
- `votar.php` es POST, público y **idempotente**: si ya se votó, devuelve el estado actual con `nuevo: false` sin tocar nada. Corre en transacción con `SELECT ... FOR UPDATE` y preserva `updated_at` para que votar no figure como edición.
- El techo real de abuso es `votos_limite`: 60 votos por hora por hash de IP. La cookie no protege contra peticiones directas.
- En la interfaz el cero no se muestra (`.vote-count:empty`), los números usan `tabular-nums` para que el botón no cambie de ancho, y al votar los dos botones de esa noticia quedan bloqueados. En móvil los botones están más separados (`gap: 22px`) a propósito, para reducir los toques por error.
- Migración para bases existentes: `php install/votos-v1.php`, idempotente.

### Gráfico de Votaciones (panel)
- `admin/votaciones.php` consulta `noticias` filtrando las que tienen algún voto, ordenadas por total; `admin/assets/votaciones.js` dibuja el SVG a mano, sin librería de gráficos.
- Colores: azul `#2a78d6` / rojo `#e34948`, el par divergente de la paleta de referencia del skill de dataviz. Se leen como opuestos, igual que las dos opciones de voto. Validados con `validate_palette.js` **contra la superficie real del panel** (`#ffffff`), no contra la de referencia — si el panel suma modo oscuro, revalidar antes de reusar estos hex.
- El `viewBox` del SVG se calcula descontando el padding del contenedor. Sin ese descuento el dibujo entero sale escalado y las medidas fijas (la columna de máximo 24px) dejan de ser exactas — fue un defecto real, encontrado con la suite de pruebas y corregido.
- El tooltip se ancla a la marca de la noticia resaltada (no a un punto fijo del trazado), se voltea hacia abajo cuando no entra arriba, y queda acotado al área de trazado para no tapar nunca las etiquetas del eje vertical.
- Un solo dato lleva etiqueta directa (la noticia líder); el resto se lee por eje, leyenda y tooltip.
- Gemelo en tabla siempre disponible, para que ningún valor dependa solo del tooltip.
- Se redibuja con `ResizeObserver` al cambiar el ancho del panel.

### Pruebas de navegador
- Suite en `~/tools/pruebas-navegador`, **fuera de `public_html`** para que `node_modules` no quede accesible por HTTP.
- Puppeteer 20.9.0 (la rama 21+ exige Node 18 y el servidor tiene v16.20.2) con su propio Chrome for Testing 115. Instalado sin root.
- `correr.sh` y `prueba-feed.js` son el baseline del feed anterior y todavía esperan carrusel/votos dentro del feed. **Deben actualizarse antes de volver a usarlos**; el wrapper además deja los votos en cero antes y después.
- La etapa actual se validó con scripts temporales en `/tmp/pntest/`: resumen **17/17**, galería/autoplay/fullscreen **19/19**, zoom y arrastre **14/14**, cobertura y altura **9/9**, en móvil `390×844` y con regresiones PC `1440×900`.
- `medir.js` mide la geometría del feed móvil; sirvió para detectar que el título se montaba sobre la lupa.
- Al escribir pruebas de votos, usar aserciones **relativas** al conteo previo: cada corrida es un visitante nuevo y el contador sube.

### Rutas de imagen
- `url_imagen()` → para el admin (antepone `../`).
- `url_imagen_front()` → para el front (raíz de `landing/`, sin prefijo).
- Las imágenes del cuerpo del editor se guardan relativas a `landing/`; en el admin se antepone `../` para mostrarlas.

### Seguridad y saneamiento
- `sanitizar_html()` usa `DOMDocument` para limpiar el HTML del editor: permite etiquetas seguras, elimina atributos peligrosos, valida protocolos de enlaces y un `style` restringido (solo `color` y `text-align`).
- Títulos/categorías/fechas/autores renderizados con `htmlspecialchars` (`e()`).
- URLs de YouTube normalizadas a `youtube-nocookie.com` (`youtube_embed_url()`).
- El listado muestra la descripción como texto plano con `html_a_texto()`.

### Fechas
- `fecha_larga()` formatea en español largo: "22 de agosto de 2026".

### Migración
- `install/security-v1.php` solo puede ejecutarse por CLI. Conserva IDs y firmas, convierte los autores existentes en usuarios inactivos y crea el primer administrador.
- `install/schema.sql` refleja el esquema seguro final para instalaciones nuevas.
- `install/migrate.php` y `schema-v2-legacy.sql` se conservan solo como historia de la versión anterior.

---

## 10. Historial de iteraciones

1. **Base previa**: panel de administración (CRUD noticias/categorías, editor TipTap, campo YouTube, drawer de vista previa) con tabla `noticias.foto_principal`.
2. **Autores + galería + front PC conectado**:
   - Tablas `autores` y `noticias_fotos`, `noticias.autor_id`.
   - Página de autores (registro con `password_hash`).
   - Galería (reemplaza `foto_principal`), reordenar y portada = primera.
   - `index.html` → `index.php` con el feed PC dinámico, fecha en español y mini-slider.
3. **Cargador de fotos eficiente**:
   - Subida inmediata por AJAX al elegir cada foto (miniatura al instante) y reordenar antes de guardar.
   - Endpoint `galeria-borrar.php` para eliminar fotos recién subidas.
4. **Arrastrar y soltar en la galería**:
   - Se reemplazaron las flechas por SortableJS (drag & drop, mouse y táctil).
5. **Ajustes visuales del front**:
   - Título de cada noticia más grande (`clamp(2.8rem, 5vw, 4.5rem)`, peso 900).
   - Categoría con mejor contraste (teal oscuro `#0f766e` en lugar del celeste suave).
6. **Gestión de usuarios reorganizada**:
   - Encabezado principal y tabla como superficie inicial.
   - Alta y edición dentro de un panel lateral derecho.
   - Acciones modernas por iconos para editar y activar/desactivar.
7. **Prueba de publicidad en el feed PC**:
   - Dos anuncios cuadrados después de cada noticia.
   - Dos parejas alternadas, fondo blanco, separación amplia y sombra inferior.
8. **Galería ampliada en PC**:
   - Lupa solo en noticias con varias fotos.
   - Lightbox de pantalla completa con navegación, cierre y zoom por rueda.
9. **Marca de agua automática**:
   - Logo PNG, intensidad y tamaño configurables desde el drawer **Configuración**, con card contraíble y vista previa en vivo.
   - `Logo2027v2.png` al 15% se conserva como valor inicial y fallback.
   - Procesamiento seguro con GD para JPG, PNG y WEBP.
   - Una segunda card contraíble permite cambiar el logo PNG del login con vista previa segura; `Logo2027v3.png` permanece como fallback.
   - **Logo del Portal** administra el PNG del encabezado y del menú fullscreen. Al guardar genera un ICO cuadrado de 256×256 sobre fondo azul noche, lo aplica como favicon en front, noticia y administración y actualiza el logo del publisher en `NewsArticle`; `Logo2027v2.png` permanece como fallback.
10. **Vista completa, resumen y publicidad móvil**:
   - Evolucionó al flujo resumido aprobado: portada, fecha, autor, resumen de 280 caracteres y botón exclusivo del feed móvil; PC conserva su diseño.
   - Hoja inferior al 85%, encabezado compacto y cierre por botón/fondo/teclado/arrastre.
   - Primer anuncio antes de la noticia completa y segundo anuncio al final.
   - Galería alta con autoplay, apertura por lupa/foto y visor con pinza + arrastre de un dedo.
   - Estado: funcional, validado y aprobado por el usuario; queda pendiente solamente la prueba de sensibilidad en hardware real/Safari iOS.
11. **SEO y página pública individual**:
   - Permalinks, metadatos sociales, datos estructurados, sitemap y robots desplegados en PROD.
   - La experiencia móvil de la página individual quedó unificada con el portal, con galería, zoom, contenido enriquecido, medios y acciones.
   - La corrección final evita que el autoplay lleve la página nuevamente al hero y conserva citas, negritas, títulos y demás formato seguro del editor.
   - Estado al 26 de agosto de 2026: móvil totalmente aprobado y PROD confirmado; queda como siguiente trabajo diseñar la experiencia específica de PC.
