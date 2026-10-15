-- =============================================================
-- MIGRACIÓN 004 · Página de Administración (pestañas 1-4)
-- Ejecutar en phpMyAdmin / MySQL. Idempotente: puede re-ejecutarse.
--
-- Aporta:
--   1) Tabla `snapshots`  : registro de respaldos "cambio de ropa"
--                           (backup/restauración por gestión escolar).
--   2) Tabla `admin_log`  : auditoría simple (quién, qué, cuándo).
--   3) Columna `snapshot_json` en `estudiante_estados`: radiografía del
--      alumno al momento de cambiar de estado (nombre/curso/teléfonos),
--      para que el historial sobreviva a la migración anual.
--   4) Nuevo permiso `manage_admin` (solo administrador): puerta de la
--      página /admin. Se asigna también al rol regente si se desea.
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- Helper: agregar columna solo si falta (mismo patrón que 001)
-- -------------------------------------------------------------
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER $$
CREATE PROCEDURE add_col_if_missing(
    IN tbl VARCHAR(64), IN col VARCHAR(64), IN definition VARCHAR(512))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- -------------------------------------------------------------
-- 1) Snapshots de gestiones (backup interno tipo "cambio de ropa")
--    Cada fila guarda el JSON comprimido (base64) de TODAS las tablas
--    de datos de una gestión: gestion, cursos, estudiantes, eventos,
--    mensajes_enviados, feriados, horarios y estudiante_estados.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS snapshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anio SMALLINT NOT NULL,                      -- gestión respaldada (ej. 2026)
  nombre VARCHAR(120) NOT NULL,                -- "Gestión 2026"
  data LONGBLOB NOT NULL,                      -- JSON gzip (binario)
  tamano_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  tabla_stats TEXT NULL,                       -- resumen filas por tabla (JSON)
  creado_por INT NULL,                         -- usuarios.id
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_snapshot_anio (anio),
  CONSTRAINT fk_snapshot_user FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2) Auditoría administrativa
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  accion VARCHAR(60) NOT NULL,                 -- user_create, csv_import, snapshot_backup...
  detalle VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_user (usuario_id),
  KEY idx_log_fecha (creado_en),
  CONSTRAINT fk_log_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3) Radiografía del alumno en cada cambio de estado
-- -------------------------------------------------------------
CALL add_col_if_missing('estudiante_estados','snapshot_json','TEXT NULL COMMENT ''JSON con nombre, curso y teléfonos al momento del cambio''');

-- -------------------------------------------------------------
-- 4) Permiso de la página de administración
-- -------------------------------------------------------------
INSERT IGNORE INTO permisos (name, description)
VALUES ('manage_admin', 'Acceder a la página de Administración (usuarios, horarios, gestiones, diagnóstico)');

-- Solo el rol administrador lo recibe por defecto (id 1 según seed).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permisos WHERE name = 'manage_admin';
