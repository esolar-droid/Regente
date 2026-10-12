-- =============================================================
-- MIGRACIÓN 001 · FASE 1: Hoja de estudiantes (estados + historial)
-- Ejecutar en phpMyAdmin / MySQL. Idempotente: puede re-ejecutarse.
-- Tablas del sistema usan nombres en español (coherentes con el
-- código existente: estudiantes, eventos, cursos, roles...).
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- 1) Nuevas columnas en `estudiantes`
-- -------------------------------------------------------------
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER $$
CREATE PROCEDURE add_col_if_missing(
    IN tbl VARCHAR(64), IN col VARCHAR(64), IN definition VARCHAR(255))
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

CALL add_col_if_missing('estudiantes','fecha_nacimiento','DATE NULL DEFAULT NULL COMMENT ''Fecha de nacimiento (edad y cumpleaños del dia)''');
CALL add_col_if_missing('estudiantes','estado',"ENUM('regular','retirado','receso_medico','receso_pedagogico','receso_administrativo','asistencia_virtual') NOT NULL DEFAULT 'regular' COMMENT 'Estado academico actual'");
CALL add_col_if_missing('estudiantes','fecha_ini','DATE NULL DEFAULT NULL COMMENT ''Inicio del estado actual (inicio de clases si es regular)''');
CALL add_col_if_missing('estudiantes','fecha_fin','DATE NULL DEFAULT NULL COMMENT ''Fin del estado actual (NULL = vigente)''');

DROP PROCEDURE add_col_if_missing;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='estudiantes' AND INDEX_NAME='idx_estado');
SET @ddl := IF(@idx=0,'ALTER TABLE `estudiantes` ADD INDEX `idx_estado` (`estado`)','SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- -------------------------------------------------------------
-- 2) Historial de estados (una fila por cambio de estado)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estudiante_estados (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante    INT UNSIGNED NOT NULL,
    estado           ENUM('regular','retirado','receso_medico','receso_pedagogico','receso_administrativo','asistencia_virtual')
                     NOT NULL,
    fecha_ini        DATE NOT NULL COMMENT 'Inicio del estado',
    fecha_fin        DATE NULL DEFAULT NULL COMMENT 'NULL = vigente',
    motivo           VARCHAR(255) NULL COMMENT 'Motivo u observacion del cambio',
    id_usuario       INT UNSIGNED NULL COMMENT 'Usuario que registro el cambio',
    creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estudiante (id_estudiante),
    KEY idx_estado (estado),
    CONSTRAINT fk_est_estado_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_est_estado_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de estados academicos de estudiantes';

-- -------------------------------------------------------------
-- 3) Sembrar datos coherentes para registros existentes
--    Cada estudiante sin historial recibe su estado actual
--    como primer asiento historico.
-- -------------------------------------------------------------
INSERT INTO estudiante_estados (id_estudiante, estado, fecha_ini, fecha_fin, motivo, id_usuario)
SELECT e.id,
       IF(e.estado IS NULL OR e.estado='', 'regular', e.estado),
       COALESCE(e.fecha_ini, CURDATE()),
       e.fecha_fin,
       'Registro inicial (migracion)',
       NULL
FROM estudiantes e
WHERE NOT EXISTS (SELECT 1 FROM estudiante_estados h WHERE h.id_estudiante = e.id);

UPDATE estudiantes SET fecha_ini = CURDATE() WHERE fecha_ini IS NULL;

-- -------------------------------------------------------------
-- 4) Permiso especifico de edicion de hoja de estudiantes
--    (roles: administrador y regente lo reciben; docente NO)
-- -------------------------------------------------------------
INSERT IGNORE INTO permisos (name, description)
VALUES ('students.edit', 'Editar ficha completa de estudiantes (datos personales, acudientes y estados)');

SET @pid := (SELECT id FROM permisos WHERE name='students.edit' LIMIT 1);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, @pid FROM roles r WHERE r.name IN ('administrador','regente');

-- -------------------------------------------------------------
-- NOTAS
-- -------------------------------------------------------------
-- * El ENUM usa 'asistencia_virtual' como valor fisico; la
--   etiqueta visible en la app sera "Asistencia a distancia".
-- * Si tus roles se llaman distinto en la tabla `roles`,
--   ajusta el paso 4 antes de ejecutar.
