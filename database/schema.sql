CREATE DATABASE IF NOT EXISTS cidb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cidb;

CREATE TABLE IF NOT EXISTS adscripciones (
  id_adscripcion INT NOT NULL AUTO_INCREMENT,
  nombre_adscripcion VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_adscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carreras (
  id_carrera INT NOT NULL AUTO_INCREMENT,
  nombre_carrera VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_carrera)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_usuarios (
  id_tipo_usuario INT NOT NULL AUTO_INCREMENT,
  nombre_tipo VARCHAR(50) DEFAULT NULL,
  numero_digitos_identificador INT DEFAULT NULL,
  PRIMARY KEY (id_tipo_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarioscid (
  id_usuario INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  apellido1 VARCHAR(100) NOT NULL,
  apellido2 VARCHAR(100) NOT NULL,
  id_tipo_usuario INT DEFAULT NULL,
  identificador VARCHAR(20) NOT NULL,
  id_carrera INT DEFAULT NULL,
  id_adscripcion INT DEFAULT NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY identificador_unique (identificador),
  KEY id_carrera (id_carrera),
  KEY id_adscripcion (id_adscripcion),
  CONSTRAINT usuarioscid_ibfk_1 FOREIGN KEY (id_carrera) REFERENCES carreras (id_carrera),
  CONSTRAINT usuarioscid_ibfk_2 FOREIGN KEY (id_adscripcion) REFERENCES adscripciones (id_adscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS libros (
  id_libro INT NOT NULL AUTO_INCREMENT,
  nombre_libro VARCHAR(150) DEFAULT NULL,
  identificador_libro VARCHAR(50) DEFAULT NULL,
  nombre_autor VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (id_libro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prestamos_libros (
  id_prestamo INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_libro INT NOT NULL,
  timpestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_inicio_prestamo DATE NOT NULL,
  fecha_fin_prestamo DATE NOT NULL,
  hora_inicio_prestamo TIME DEFAULT NULL,
  hora_fin_prestamo TIME DEFAULT NULL,
  PRIMARY KEY (id_prestamo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingresoscid (
  id_ingreso INT NOT NULL AUTO_INCREMENT,
  momento_ingreso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  servicio VARCHAR(55) DEFAULT NULL,
  actividad VARCHAR(55) DEFAULT NULL,
  detalle VARCHAR(255) DEFAULT NULL,
  id_usuario INT DEFAULT NULL,
  PRIMARY KEY (id_ingreso),
  KEY id_usuario (id_usuario),
  CONSTRAINT ingresoscid_ibfk_1 FOREIGN KEY (id_usuario) REFERENCES usuarioscid (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO adscripciones (id_adscripcion, nombre_adscripcion) VALUES
  (1, 'Biblioteca'),
  (2, 'CID'),
  (3, 'Dirección de Estudios');

INSERT IGNORE INTO carreras (id_carrera, nombre_carrera) VALUES
  (1, 'Ingeniería en Sistemas'),
  (2, 'Administración'),
  (3, 'Derecho'),
  (4, 'Arquitectura');

INSERT IGNORE INTO tipos_usuarios (id_tipo_usuario, nombre_tipo, numero_digitos_identificador) VALUES
  (1, 'Alumno', 8),
  (2, 'Docente', 8),
  (3, 'Administrativo', 8);
