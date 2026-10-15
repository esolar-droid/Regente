-- =============================================================
-- Migración 003: historial de mensajes (tabla `mensajes_enviados`)
-- Página «Hoja de mensajería»: pestaña Enviar + pestaña Historial.
-- Idempotente: puede ejecutarse varias veces. Ejecutar en phpMyAdmin.
--
-- Nota "snapshot": se guarda UNA COPIA de texto del nombre y teléfono
-- del destinatario en el momento del envío (no es una captura gráfica,
-- no pesa). Así el historial queda fiel aunque después cambie la ficha
-- del estudiante.
-- =============================================================

CREATE TABLE IF NOT EXISTS mensajes_enviados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_curso INT DEFAULT NULL,
  fecha_evento DATE DEFAULT NULL,
  tipo_evento ENUM('A','L','F') DEFAULT NULL,     -- único evento por estudiante+fecha
  justificacion TEXT DEFAULT NULL,                 -- snapshot al momento del envío
  destinatario VARCHAR(20) NOT NULL,               -- papa | mama
  nombre_destinatario VARCHAR(160) DEFAULT NULL,   -- snapshot del contacto
  telefono VARCHAR(30) DEFAULT NULL,               -- snapshot E.164 usado en wa.me
  mensaje TEXT NOT NULL,                           -- texto final renderizado (WhatsApp)
  id_usuario_envio INT DEFAULT NULL,
  fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_msg_estudiante (id_estudiante),
  KEY idx_msg_curso_fecha (id_curso, fecha_evento),
  KEY idx_msg_fecha_envio (fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la tabla ya existía con la estructura antigua (solo 6 columnas),
-- agregar las nuevas columnas sin perder datos.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mensajes_enviados' AND COLUMN_NAME = 'id_curso');
SET @s := IF(@c = 0, 'ALTER TABLE mensajes_enviados ADD COLUMN id_curso INT DEFAULT NULL AFTER id_estudiante, ADD COLUMN fecha_evento DATE DEFAULT NULL AFTER id_curso, ADD COLUMN tipo_evento ENUM(''A'',''L'',''F'') DEFAULT NULL AFTER fecha_evento, ADD COLUMN justificacion TEXT DEFAULT NULL AFTER tipo_evento, ADD COLUMN nombre_destinatario VARCHAR(160) DEFAULT NULL AFTER destinatario, ADD COLUMN telefono VARCHAR(30) DEFAULT NULL AFTER nombre_destinatario', 'DO 0');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índices nuevos (idempotente)
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mensajes_enviados' AND INDEX_NAME = 'idx_msg_curso_fecha');
SET @s := IF(@i = 0, 'ALTER TABLE mensajes_enviados ADD INDEX idx_msg_curso_fecha (id_curso, fecha_evento)', 'DO 0');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mensajes_enviados' AND INDEX_NAME = 'idx_msg_fecha_envio');
SET @s := IF(@i = 0, 'ALTER TABLE mensajes_enviados ADD INDEX idx_msg_fecha_envio (fecha_envio)', 'DO 0');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Permisos del módulo de mensajería
INSERT IGNORE INTO permisos (name, description) VALUES
 ('send_whatsapp', 'Enviar mensajes por WhatsApp'),
 ('view_messages', 'Ver historial de mensajes');

-- Administrador (rol 1): todos; Regente (rol 2): enviar + ver historial.
-- El PROFESOR NO recibe send_whatsapp: solo ve el historial (view_messages
-- ya está sembrado para él) y únicamente de los cursos donde dicta clases
-- (vía tabla `horarios`).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.name IN ('send_whatsapp','view_messages')
WHERE r.name IN ('administrador','regente');

-- =============================================================
-- Tabla `horarios`: curso, día, periodo, asignatura, profesor.
-- Vital para: filtrar con qué cursos trabaja un profesor (ahora)
-- y la futura página «Cursos y horarios» (a editar desde
-- Administración). Se crea aquí porque la mensajería YA la usa.
-- =============================================================

CREATE TABLE IF NOT EXISTS horarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_curso INT NOT NULL,
  id_profesor INT NOT NULL,               -- usuarios.id (rol profesor)
  dia ENUM('Lunes','Martes','Miércoles','Jueves','Viernes') NOT NULL,
  hora_inicio TIME NOT NULL,              -- periodo = rango de horas
  hora_fin TIME NOT NULL,
  asignatura VARCHAR(120) NOT NULL,
  anio SMALLINT NOT NULL DEFAULT YEAR(CURDATE()),
  UNIQUE KEY uq_horario (id_curso, dia, hora_inicio, anio),
  KEY idx_horario_profesor (id_profesor, anio),
  KEY idx_horario_curso (id_curso, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
