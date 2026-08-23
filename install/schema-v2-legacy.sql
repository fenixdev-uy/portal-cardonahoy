-- =============================================================
-- Esquema historico v2 de la base de datos para el backend de Noticias
-- Base de datos: fenixdev_noticias
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- Tabla: categorias
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabla: autores (usuarios que escriben las noticias)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS autores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  bio VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_autores_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabla: noticias
-- Campos: categoria, autor, titulo, descripcion, youtube
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS noticias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NULL,
  autor_id INT UNSIGNED NULL,
  titulo VARCHAR(255) NOT NULL,
  descripcion TEXT NOT NULL,
  youtube VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_noticias_categoria (categoria_id),
  KEY idx_noticias_autor (autor_id),
  CONSTRAINT fk_noticias_categoria FOREIGN KEY (categoria_id)
    REFERENCES categorias (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_noticias_autor FOREIGN KEY (autor_id)
    REFERENCES autores (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabla: noticias_fotos (galería de fotos, con orden)
-- La foto con posicion = 0 es la portada (la primera).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS noticias_fotos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  noticia_id INT UNSIGNED NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  posicion INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_noticias_fotos_noticia (noticia_id),
  CONSTRAINT fk_noticias_fotos_noticia FOREIGN KEY (noticia_id)
    REFERENCES noticias (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Datos iniciales de ejemplo
-- =============================================================

INSERT INTO categorias (nombre, slug) VALUES
  ('Tecnología', 'tecnologia'),
  ('Economía', 'economia'),
  ('Deportes', 'deportes')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO autores (nombre, email, password_hash, bio) VALUES
  ('Redacción Tecnología', 'redaccion.tecnologia@radiosur.com', '', 'Cobertura de tecnología e innovación.'),
  ('Marta Gómez', 'marta.gomez@radiosur.com', '', 'Periodista de economía y finanzas.'),
  ('Diego Fernández', 'diego.fernandez@radiosur.com', '', 'Redactor de la sección deportes.'),
  ('Laura Pérez', 'laura.perez@radiosur.com', '', 'Editora general de noticias.')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO noticias (categoria_id, autor_id, titulo, descripcion) VALUES
  (
    (SELECT id FROM categorias WHERE slug = 'tecnologia'),
    (SELECT id FROM autores WHERE nombre = 'Redacción Tecnología'),
    'La inteligencia artificial transforma la industria en América Latina',
    '<p>La adopción de la inteligencia artificial crece a un ritmo sin precedentes en la región. Cada vez más empresas incorporan estas herramientas para optimizar sus procesos y reducir costos.</p>'
  ),
  (
    (SELECT id FROM categorias WHERE slug = 'economia'),
    (SELECT id FROM autores WHERE nombre = 'Marta Gómez'),
    'Crecimiento económico abre nuevas oportunidades para los emprendedores',
    '<p>El panorama económico de la región muestra señales alentadoras. El acceso al crédito y la digitalización de los negocios impulsan una nueva ola de emprendimientos.</p>'
  ),
  (
    (SELECT id FROM categorias WHERE slug = 'deportes'),
    (SELECT id FROM autores WHERE nombre = 'Diego Fernández'),
    'El deporte nacional vive una temporada histórica',
    '<p>Los equipos locales protagonizan un año inolvidable. Con triunfos en torneos nacionales e internacionales, el deporte del país celebra una de sus mejores temporadas.</p>'
  );

INSERT INTO noticias_fotos (noticia_id, ruta, posicion) VALUES
  (
    (SELECT id FROM noticias WHERE titulo = 'La inteligencia artificial transforma la industria en América Latina'),
    'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1600&q=80',
    0
  ),
  (
    (SELECT id FROM noticias WHERE titulo = 'Crecimiento económico abre nuevas oportunidades para los emprendedores'),
    'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=1600&q=80',
    0
  ),
  (
    (SELECT id FROM noticias WHERE titulo = 'El deporte nacional vive una temporada histórica'),
    'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1600&q=80',
    0
  );
