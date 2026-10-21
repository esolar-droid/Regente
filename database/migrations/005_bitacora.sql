-- =============================================================
-- Migración 005 · Módulo «Bitácora» (registro de actividades del
-- profesorado — rol regente/administrador)
--
-- Aporta:
--   1) Tabla `bitacora`: registros de actividad/novedad asociados a un
--      PROFESOR (usuario con rol 'profesor'). NO es asistencia de
--      estudiantes, por lo que NO está restringida por el calendario
--      académico ni por feriados.
--   2) Columna `fecha` (DATE): si al registrar no se especifica fecha,
--      la aplicación asume por defecto la fecha actual (hoy).
--   3) Permisos: view_bitacora / create_bitacora / edit_bitacora /
--      delete_bitacora — otorgados a administrador y regente.
--
-- Idempotente: puede ejecutarse varias veces (phpMyAdmin → SQL).
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bitacora (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_profesor INT NOT NULL COMMENT 'Usuario con rol profesor/docente (usuarios.id)',
  tipo ENUM('actividad','novedad','reunion','capacitacion','otro') NOT NULL DEFAULT 'actividad',
  titulo VARCHAR(160) NOT NULL,
  detalle TEXT NULL COMMENT 'Texto libre del registro (se busca también por aquí)',
  fecha DATE NOT NULL COMMENT 'Fecha del registro; si no se indica, se asume hoy',
  id_usuario_registro INT NULL COMMENT 'Regente/administrador que creó el registro',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bit_profe (id_profesor),
  KEY idx_bit_fecha (fecha),
  KEY idx_bit_usuario (id_usuario_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Permisos del módulo (los mismos nombres que usa Permission::seed
-- para que aparezcan en la matriz de la pestaña «Permisos» de Admin)
-- -------------------------------------------------------------
INSERT IGNORE INTO permisos (name, description) VALUES
 ('view_bitacora',   'Ver Bitácora del profesorado'),
 ('create_bitacora', 'Crear registros de Bitácora'),
 ('edit_bitacora',   'Editar registros de Bitácora'),
 ('delete_bitacora', 'Eliminar registros de Bitácora');

-- Administrador: todos los permisos de Bitácora
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name IN
 ('view_bitacora','create_bitacora','edit_bitacora','delete_bitacora')
WHERE r.name = 'administrador';

-- Regente: acceso completo al módulo (es el rol destinatario)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name IN
 ('view_bitacora','create_bitacora','edit_bitacora','delete_bitacora')
WHERE r.name = 'regente';

-- Profesor: solo lectura de su propia bitácora (la vista filtra por él)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name = 'view_bitacora'
WHERE r.name = 'profesor';
