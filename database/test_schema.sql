-- =============================================================
-- ESQUELA MÍNIMA DE PRUEBA (solo para validar la migración 004
-- en un MySQL/MariaDB local). NO es el esquema de producción:
-- reproduce las tablas que 004 toca o referencia.
-- =============================================================
CREATE DATABASE IF NOT EXISTS colegio_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE colegio_test;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100), apellido VARCHAR(100),
  email VARCHAR(150) UNIQUE, password_hash VARCHAR(255),
  role_id INT NULL, activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) UNIQUE, description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT, permission_id INT,
  PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estudiante_estados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  estado VARCHAR(30) NOT NULL,
  fecha_ini DATE, fecha_fin DATE, motivo VARCHAR(255),
  id_usuario INT
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (id, name) VALUES
 (1,'administrador'),(2,'regente'),(3,'profesor');

INSERT IGNORE INTO usuarios (id, nombre, apellido, email, role_id)
 VALUES (1,'Admin','General','admin@test.local',1);
