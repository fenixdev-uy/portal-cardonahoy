# Landing + Panel de Noticias

Documentación del estado actual del proyecto. La prioridad activa y los pasos pendientes se mantienen en `AGENDA.md`; el detalle técnico y el punto de continuidad viven en `CONTINUIDAD.md`.

---

## 1. Resumen

Backend de noticias para la landing page. Las noticias se administran desde un panel (CRUD) y el feed de la **versión PC** se renderiza desde la base de datos en `landing/index.php`.

**Estado actual:** panel protegido con login, usuarios, roles/permisos, noticias, categorías y galería. Los dos feeds del front, PC y móvil, están conectados al backend. Lo único que sigue siendo contenido estático de ejemplo es el slider del home.

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
├── index.php                   ← front: datos, estructura, feeds e interacciones
├── noticia.php                 ← página pública individual + metatags SEO/sociales
├── sitemap.php / robots.php    ← descubrimiento e indexación de permalinks
├── db                          ← credenciales (texto plano, solo referencia)
├── assets/
│   ├── css/
│   │   └── portal.css          ← estilos compartidos de la portada PC/móvil
│   └── js/
│       └── portal.js           ← interacciones compartidas de la portada
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
│   ├── seo-v1.php              ← migración idempotente de slugs y overrides SEO
│   └── migrate.php             ← migración histórica v2, solo CLI
│
├── votar.php                   ← endpoint público de votos (POST, sin login)
│
├── partials/
│   ├── pc-feed.php             ← loop que renderiza el feed de noticias PC
│   ├── mobile-feed.php         ← loop que renderiza el feed de noticias móvil
│   ├── medios-noticia.php      ← audios HTML5 + videos opcionales compartidos
│   ├── publicidad.php          ← piezas de publicidad y su orden (fuente única)
│   ├── boton-nota-completa.php ← disparador aprobado de la vista completa móvil
│   ├── nota-completa.php       ← hoja móvil + contenido y anuncios
│   ├── lightbox.php            ← visor ampliado de galerías (PC + móvil)
│   └── acciones-noticia.php    ← bloque de voto y compartir (PC + móvil)
│
├── uploads/                    ← archivos persistentes subidos desde el panel
│   ├── noticias/
│   └── configuracion/          ← marcas de agua PNG (protegidas por .htaccess)
│
├── tools/                      ← utilidades CLI locales, nunca se despliega
│   └── validar-servicios.php   ← valida DEV/PROD sin revelar secretos
│
└── admin/                      ← panel de administración
    ├── login.php / logout.php  ← autenticación del panel
    ├── cambiar-password.php    ← cambio obligatorio de clave temporal
    ├── config.php              ← cargador de configuración privada
    ├── config.local.php        ← conexión PDO y credenciales, ignorado
    ├── index.php               ← listado de noticias + drawer de vista previa
    ├── noticia-form.php        ← crear/editar noticia (editor TipTap + galería)
    ├── noticia-borrar.php      ← eliminar noticia (y su galería)
    ├── noticia-detalle.php     ← endpoint JSON del detalle de una noticia
    ├── configuracion-marca-agua.php ← guarda logo y opacidad de la marca de agua
    ├── upload-imagen.php       ← endpoint de subida de imágenes (editor y galería)
    ├── upload-audio.php        ← endpoint de subida de audios (MP3/M4A/OGG/WAV)
    ├── galeria-borrar.php      ← elimina una foto recién subida (aún sin guardar)
    ├── categorias.php          ← CRUD de categorías
    ├── usuarios.php            ← alta y gestión de usuarios por administrador
    ├── roles.php               ← perfiles de roles y permisos
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

- `usuarios`: identidad, correo único, hash, rol, estado, cambio obligatorio y último acceso.
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
| created_at     | TIMESTAMP     |                                        |
| updated_at     | TIMESTAMP     | on update                              |

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

### Votos (`me_gusta` / `no_me_gusta`)

- `noticias.me_gusta` y `noticias.no_me_gusta`: `INT UNSIGNED NOT NULL DEFAULT 0`. Solo se incrementan; nunca se restan.
- `noticias_votos`: un voto por visitante y por noticia, **definitivo**. Clave primaria `(noticia_id, visitante)` — es lo que impide el segundo voto.
- `votos_limite`: techo de 60 votos por hora, por hash de IP (nunca la IP en claro).

Las migraciones para bases existentes incluyen `install/votos-v1.php`, `install/medios-v1.php` e `install/seo-v1.php`; son idempotentes.

El esquema completo y los datos de ejemplo están en `install/schema.sql`. La migración de la versión anterior (que reemplaza `foto_principal` por la galería) está en `install/migrate.php`.

---

## 5. Panel de administración (funcionalidades)

- **Menú lateral** (izquierda) + contenido a la derecha. Responsive (hamburguesa en móvil).
- **Noticias** (`index.php`):
  - Listado (foto de portada, título, descripción, categoría, autor, fecha y acciones), con buscador instantáneo y orden por fecha en ambos sentidos.
  - Columna **Peso** calculada desde los archivos locales reales: galería, imágenes insertadas en el editor y audios subidos. Ordena en ambos sentidos y muestra el desglose Fotos/Audios; YouTube y URLs externas no se cuentan porque no consumen disco local.
  - Vista previa en drawer lateral (40% del ancho) al hacer clic en la foto: foto de portada, miniaturas de la galería, categoría, título, fecha larga, autor, descripción formateada y reproductor de YouTube embebido si tiene URL.
  - Acciones de editar / eliminar. El borrado limpia galería, imágenes internas y audios locales únicamente cuando ningún otro contenido conserva la misma referencia.
- **Crear/editar noticia** (`noticia-form.php`):
  - Campos: categoría, autor, título, descripción (editor TipTap), hasta 3 audios, hasta 3 videos de YouTube y **galería de fotos**.
  - Medios: dos cards en columnas; los audios aceptan URL HTTPS o subida inmediata MP3/M4A/OGG/WAV (máx. 25 MB) y la base guarda únicamente la URL.
  - Galería: subida **inmediata** por AJAX al elegir cada foto (muestra la miniatura al instante, sin esperar el guardado), **arrastrar y soltar** para reordenar (SortableJS), eliminar con × y la primera foto es la portada.
- **Categorías** (`categorias.php`):
  - CRUD completo con buscador, contador y alta/edición en panel lateral; cada categoría muestra cuántas noticias la utilizan.
- **Usuarios** (`usuarios.php`):
  - Alta, edición, activación y desactivación solamente por administradores.
  - Rol, firma editorial, contraseña temporal y cambio obligatorio al ingresar.
  - Vista principal sin formulario: título y tabla con acciones por iconos.
  - Alta y edición dentro de un panel lateral derecho; la edición incluye acceso a **Editar roles**.
- **Roles** (`roles.php`): perfiles Administrador, Editor y Autor con permisos configurables.
- **Seguridad**:
  - Login, sesiones seguras con vencimiento, control de intentos y respuestas genéricas.
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
- [x] Configuración de marca de agua en un drawer lateral: carga PNG, intensidad entre 5% y 100% y vista previa inmediata sobre una foto real. Se aplica centrada al 36% del ancho en las imágenes nuevas; el valor inicial y fallback sigue siendo `Logo2027v2.png` al 15%.

### Hecho — front PC
- [x] Front conectado al backend (`index.php` + `partials/pc-feed.php`).
- [x] 1 noticia por pantalla (scroll-snap), imagen izquierda + texto derecha.
- [x] Galería como mini-slider si hay más de una foto (auto-rotación + puntos).
- [x] Ampliación de galerías en PC: lupa, visor a pantalla completa, anterior/siguiente, cierre, teclado y zoom de 100% a 400% con la rueda del mouse.
- [x] Orden: la última noticia creada sale primera.
- [x] Descripción HTML renderizada con estilos (títulos, listas, citas, imágenes, enlaces, etc.).
- [x] Video de YouTube embebido debajo de la descripción.
- [x] Ajustes visuales: título más grande (`clamp(2.8rem, 5vw, 4.5rem)`, peso 900) y categoría con mejor contraste (teal oscuro `#0f766e`).
- [x] Bloques provisorios de publicidad después de cada noticia en PC, alternando dos parejas de piezas cuadradas.

### Hecho — front móvil
- [x] Feed móvil conectado al backend (`index.php` + `partials/mobile-feed.php`), sin consultas adicionales: reutiliza los datos del feed PC.
- [x] Feed resumido aprobado: una portada a `100svh`, categoría y título encima; debajo, fecha, autor, extracto en texto plano de hasta 280 caracteres y botón «Ver nota completa».
- [x] Sin `scroll-snap` en móvil, a propósito: el encaje pelea con las noticias de texto largo.
- [x] El feed muestra solamente la portada aunque haya varias fotos; galería, texto completo, audios, videos y votos viven en la vista completa.
- [x] Fotos como `<img loading="lazy">` en lugar de `background-image`, para que el lazy loading se aplique de verdad.
- [x] Vista completa en hoja al `85%`, aprobada: encabezado blanco, portada o galería, datos editoriales, primer anuncio, contenido completo, medios, votos y segundo anuncio. Cierra con cruz, fondo, `Escape` o arrastre del encabezado.
- [x] Galería de la hoja 30% más alta que el antiguo 4:3, sin franjas: autoplay cada 3,2s y puntos cuando hay varias fotos; la lupa y la apertura fullscreen tocando directamente la imagen están disponibles incluso con una única portada.
- [x] Visor móvil: deslizar para cambiar/cerrar al 100%, pinza de dos dedos de 100% a 400% y arrastre de la imagen ampliada con un dedo, limitado al área visible.
- [x] Video de YouTube embebido y fondo degradado neutro para noticias sin fotos.
- [x] Un anuncio provisorio a ancho completo después de cada noticia, rotando por las cuatro piezas.

### Hecho — votos
- [x] Botones de "me gusta / no me gusta" funcionales en los dos feeds, con el contador al lado de cada opción.
- [x] Un voto por visitante y por noticia, **definitivo**: no se deshace ni se cambia. Los contadores solo suman y nunca pueden ser negativos.
- [x] Contadores `me_gusta` y `no_me_gusta` en `noticias` como caché de lectura; la verdad vive en `noticias_votos`.
- [x] Visitante identificado por cookie `portal_visitante` (UUID v4, `httpOnly`, `SameSite=Lax`, un año). Se emite al votar, no al mirar.
- [x] Endpoint `votar.php` en la raíz, sin login, idempotente, con límite de 60 votos por hora y por IP.
- [x] Contadores visibles en el listado del panel y en el JSON del detalle.
- [x] Bloque de voto y compartir unificado en `partials/acciones-noticia.php`; antes estaba duplicado en los dos feeds.

### Hecho — panel: Votaciones
- [x] Pantalla `admin/votaciones.php`, enlazada en el menú entre Categorías y Usuarios.
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
- [x] **Página individual móvil desplegada y aprobada:** encabezado del portal, hero/galería con lupa incluso para una foto, visor con zoom de 100% a 400%, fecha, autor, HTML enriquecido, medios, votos, redes alineadas y regreso a la portada. La rotación automática mueve solamente el carrusel horizontal y no altera el scroll vertical.
- [ ] **Próxima etapa:** rediseñar la experiencia de la página individual en PC. La versión móvil es el baseline aprobado y debe permanecer intacta; el trabajo de escritorio comenzará en la próxima sesión después de acordar la nueva composición visual.
- [x] **Asistente editorial desplegado:** el botón **«Crear con IA»** abre un drawer lateral donde el periodista puede pegar información cruda o fragmentos de otras fuentes y agregar indicaciones. Siempre que TipTap tenga contenido —especialmente al editar una noticia— su texto actual reemplaza la información base al abrir el asistente; si está vacío no la sobrescribe. DeepSeek construye una propuesta, permite crear otra versión y solo la agrega a TipTap al confirmar. Para garantizar exactitud no admite URLs: el periodista debe copiar el contenido relevante del enlace. La clave nunca llega al navegador. Código, runtime privado y bloqueo HTTP quedaron publicados y verificados en producción el 25 de agosto de 2026.
- [ ] Conectar el slider del home (`hero`) al backend; es lo último del front que sigue estático.
- [x] Autenticación y protección completa del panel.
- [x] Botones de **compartir** funcionales para Facebook y WhatsApp mediante el permalink canónico.
- [ ] Limpiar archivos huérfanos: si se suben fotos y se abandona el formulario sin guardar, quedan en `uploads/noticias/` sin asociar.
- [ ] Mejoras futuras: subir videos al servidor (hoy es URL de YouTube), más de un video por noticia, arrastrar archivos desde el escritorio a la galería, previsualizar fotos antes de subir.
- [x] Sección **Configuración** del panel, ubicada encima del usuario conectado, con gestión visual de la marca de agua.
- [ ] Evaluar el formato provisorio de anuncios antes de convertirlo en una gestión dinámica desde el panel.
- [ ] Probar los gestos ya aprobados en un teléfono real, especialmente Safari iOS, para evaluar sensibilidad, inercia y rendimiento fuera de Chrome headless.
- [ ] Evaluar unificar `pc-feed.php` y `mobile-feed.php` en un único partial responsive. Hoy cada dispositivo descarga el marcado del otro oculto por CSS, con el contenido duplicado que eso implica para lectores de pantalla y para SEO.

---

## 8. Acceso rápido

- **Panel:** `/landing/admin/index.php`
- **Nueva noticia:** `/landing/admin/noticia-form.php`
- **Categorías:** `/landing/admin/categorias.php`
- **Usuarios:** `/landing/admin/usuarios.php`
- **Roles:** `/landing/admin/roles.php`
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
- El único bloque que sigue siendo HTML estático es el slider del home (`hero`).
- Los estilos y las interacciones de la portada viven en `assets/css/portal.css` y `assets/js/portal.js`, cargados con versión automática por `filemtime()`. `index.php` entrega al script solamente la URL dinámica de votos mediante `data-vote-url`; los partials solo aportan marcado.
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

### Publicidad provisoria
- Las piezas y su orden viven en `partials/publicidad.php`, del que se alimentan los dos feeds. No duplicar rutas de anuncios en otro lugar.
- Pareja 1: `Publicidad-facha.jpg` + `Publicidad-intendencia.jpg`.
- Pareja 2: `Publicidad-Fenix.jpg` + `Publicidad-Digitales.jpg`.
- **PC:** después de cada noticia se inserta una pantalla completa con la pareja correspondiente; las parejas se alternan por noticia y vuelven a comenzar al terminar la secuencia. Presentación aprobada: fondo blanco, piezas cuadradas sin bordes redondeados, margen exterior de `64px`, separación de `40px` y sombra inferior con relieve.
- **Móvil:** después de cada noticia se inserta **un solo** anuncio a ancho completo, cuadrado, para que se lea como una tarjeta más del feed y no encadene dos pantallas de publicidad seguidas. La secuencia recorre las cuatro piezas de a una. Dentro de la nota completa se conserva el encabezado blanco y primero aparece la portada o galería; el primer anuncio de la pareja asignada queda después de categoría, título, fecha y autor, antes del cuerpo, y el segundo permanece al final.
- **Flujo móvil aprobado:** «Ver nota completa» abre una hoja al `85%`. Conserva el encabezado blanco y comienza con la portada o galería; después de categoría, título, fecha y autor aparece la primera pieza cuadrada, seguida por el cuerpo, los medios y votos, y al final la segunda pieza. Los anuncios siguen siendo provisorios y este flujo no constituye una gestión dinámica.
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
- El visitante es una cookie `portal_visitante` con UUID v4, `httpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS, un año. Se descartó identificar por IP porque los celulares salen por NAT del operador.
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
   - Logo PNG e intensidad configurables desde el drawer **Configuración**, con vista previa en vivo.
   - `Logo2027v2.png` al 15% se conserva como valor inicial y fallback.
   - Procesamiento seguro con GD para JPG, PNG y WEBP.
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
