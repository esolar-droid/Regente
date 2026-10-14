-- =============================================================
-- Migración 002: tabla de feriados / días inhábiles
-- Gestión a gestión los feriados varían; se gestionan desde
-- Administración. Idempotente: puede ejecutarse varias veces.
-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la BD del sistema.
-- =============================================================

CREATE TABLE IF NOT EXISTS feriados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  anio YEAR NOT NULL,
  UNIQUE KEY uq_feriado_fecha (fecha),
  KEY idx_feriado_anio (anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos nuevos para el módulo de Asistencia y Feriados
INSERT IGNORE INTO permisos (name, description) VALUES
 ('manage_feriados', 'Gestionar feriados y días inhábiles'),
 ('attendance_massive', 'Registrar asistencia masiva por curso');

-- Otorgar manage_feriados al administrador (role_id según name, no hardcodeado)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name='manage_feriados' WHERE r.name='administrador';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name='attendance_massive' WHERE r.name='administrador';
-- El regente también gestiona feriados y asistencia masiva
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name IN ('manage_feriados','attendance_massive') WHERE r.name='regente';
