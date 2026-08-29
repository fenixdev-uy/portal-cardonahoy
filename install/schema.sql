-- Portal de Noticias - esquema seguro v3 para instalaciones nuevas
-- Despues de importar, crear el administrador por CLI con security-v1.php.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  es_sistema TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permisos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(100) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_permisos_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rol_permisos (
  rol_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  bio VARCHAR(255) NULL,
  foto VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_usuarios_email (email), KEY idx_usuarios_rol (rol_id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  titulo VARCHAR(255) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  descripcion TEXT NOT NULL,
  seo_titulo VARCHAR(255) NULL,
  seo_descripcion VARCHAR(500) NULL,
  seo_imagen VARCHAR(255) NULL,
  youtube VARCHAR(255) NULL,
  youtube_2 VARCHAR(255) NULL,
  youtube_3 VARCHAR(255) NULL,
  audio_1 VARCHAR(500) NULL,
  audio_2 VARCHAR(500) NULL,
  audio_3 VARCHAR(500) NULL,
  me_gusta INT UNSIGNED NOT NULL DEFAULT 0,
  no_me_gusta INT UNSIGNED NOT NULL DEFAULT 0,
  vistas INT UNSIGNED NOT NULL DEFAULT 0,
  compartidos INT UNSIGNED NOT NULL DEFAULT 0,
  portada TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_noticias_slug (slug), KEY idx_noticias_categoria (categoria_id), KEY idx_noticias_usuario (usuario_id), KEY idx_noticias_portada_fecha (portada, created_at, id),
  CONSTRAINT fk_noticias_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_noticias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias_categorias (
  noticia_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  posicion SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (noticia_id, categoria_id),
  KEY idx_noticias_categorias_categoria (categoria_id, noticia_id),
  CONSTRAINT fk_nc_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_nc_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias_slugs_historial (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  noticia_id INT UNSIGNED NOT NULL,
  slug VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_noticias_slugs_historial_slug (slug), KEY idx_noticias_slugs_historial_noticia (noticia_id),
  CONSTRAINT fk_noticias_slugs_historial_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias_fotos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  noticia_id INT UNSIGNED NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  posicion INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_noticias_fotos_noticia (noticia_id),
  CONSTRAINT fk_noticias_fotos_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un voto por visitante y por noticia, definitivo: no se deshace ni se cambia.
-- La clave primaria compuesta es la que impide el segundo voto.
CREATE TABLE IF NOT EXISTS noticias_votos (
  noticia_id INT UNSIGNED NOT NULL,
  visitante CHAR(36) NOT NULL,
  valor TINYINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (noticia_id, visitante), KEY idx_noticias_votos_visitante (visitante),
  CONSTRAINT fk_noticias_votos_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una vista por visitante, noticia y dia para evitar inflar el contador al recargar.
CREATE TABLE IF NOT EXISTS noticias_vistas (
  noticia_id INT UNSIGNED NOT NULL,
  visitante CHAR(36) NOT NULL,
  fecha DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (noticia_id, visitante, fecha),
  KEY idx_noticias_vistas_fecha (fecha),
  KEY idx_noticias_vistas_visitante (visitante, fecha),
  CONSTRAINT fk_noticias_vistas_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial diario de clics de compartir, separado por destino.
CREATE TABLE IF NOT EXISTS noticias_compartidos_diarios (
  noticia_id INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  destino VARCHAR(20) NOT NULL,
  cantidad INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (noticia_id, fecha, destino),
  KEY idx_noticias_compartidos_fecha (fecha),
  CONSTRAINT fk_noticias_compartidos_noticia FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de votos por IP. La cookie no protege contra peticiones directas.
CREATE TABLE IF NOT EXISTS votos_limite (
  clave_hash CHAR(64) NOT NULL,
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ventana_inicio_at DATETIME NOT NULL,
  PRIMARY KEY (clave_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intentos_login (
  clave_hash CHAR(64) NOT NULL,
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  primer_intento_at DATETIME NOT NULL,
  bloqueado_hasta DATETIME NULL,
  PRIMARY KEY (clave_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion (
  clave VARCHAR(100) NOT NULL,
  valor TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracion (clave, valor) VALUES
  ('logo_login_tamano', '100'),
  ('logo_portal_tamano', '100'),
  ('mantenimiento_activo', '0'),
  ('mantenimiento_logo_ruta', ''),
  ('mantenimiento_logo_tamano', '58'),
  ('mantenimiento_mensaje', 'En mantenimiento, ¡volvemos pronto!'),
  ('mantenimiento_mostrar_login', '1')
ON DUPLICATE KEY UPDATE valor=valor;

CREATE TABLE IF NOT EXISTS anuncios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  imagen VARCHAR(255) NOT NULL,
  facebook_url VARCHAR(500) NULL,
  instagram_url VARCHAR(500) NULL,
  whatsapp_url VARCHAR(500) NULL,
  sitio_web_url VARCHAR(500) NULL,
  fecha_vencimiento DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  en_encabezado TINYINT(1) NOT NULL DEFAULT 0,
  en_pie TINYINT(1) NOT NULL DEFAULT 0,
  clics INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_anuncios_vencimiento (fecha_vencimiento),
  KEY idx_anuncios_publicacion (activo, fecha_vencimiento),
  KEY idx_anuncios_encabezado (en_encabezado),
  KEY idx_anuncios_pie (en_pie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS popups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  imagen_vertical VARCHAR(255) NOT NULL,
  imagen_horizontal VARCHAR(255) NOT NULL,
  facebook_url VARCHAR(500) NULL,
  instagram_url VARCHAR(500) NULL,
  whatsapp_url VARCHAR(500) NULL,
  sitio_web_url VARCHAR(500) NULL,
  fecha_vencimiento DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  segundos_aparicion SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  limite_diario_por_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  clics INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_popups_vencimiento (fecha_vencimiento),
  KEY idx_popups_publicacion (activo, fecha_vencimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS popups_impresiones (
  popup_id INT UNSIGNED NOT NULL,
  visitante CHAR(36) NOT NULL,
  fecha DATE NOT NULL,
  cantidad SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultima_impresion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (popup_id, visitante, fecha),
  KEY idx_popups_impresiones_fecha (fecha),
  KEY idx_popups_impresiones_visitante (visitante, fecha),
  CONSTRAINT fk_popups_impresiones_popup
    FOREIGN KEY (popup_id) REFERENCES popups(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
