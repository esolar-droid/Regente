-- =====================================================================
-- COPIA DE REGISTROS NUEVOS: rege_colmarista.eventos  ->  rege_colmarista2.eventos
-- Sistema Regente · migración de datos desde el sistema antiguo (v17)
-- =====================================================================
-- Ejecutar como usuario MySQL con permisos SELECT sobre la BD antigua e
-- INSERT sobre la nueva (p. ej. desde phpMyAdmin > pestaña SQL, o CLI).
-- Las dos BDs deben estar en el MISMO servidor MySQL/MariaDB.
-- =====================================================================

USE rege_colmarista2;

-- ---------------------------------------------------------------------
-- PASO 0 · RESPALDO (¡no saltar!)
-- Desde SSH/terminal del hosting:
--   mysqldump -u rege_colmarista2 -p rege_colmarista2 eventos > respaldo_eventos_nueva_$(date +%F).sql
-- (o Exportar tabla "eventos" desde phpMyAdmin)
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- PASO 1 · INSPECCIÓN PREVIA
-- 1a. Comparar volúmenes de ambas tablas:
-- ---------------------------------------------------------------------
SELECT
  (SELECT COUNT(*) FROM rege_colmarista.eventos)  AS total_viejo,
  (SELECT COUNT(*) FROM rege_colmarista2.eventos) AS total_nuevo;

-- 1b. Ver las columnas REALES de cada tabla (para adaptar los INSERT):
DESCRIBE rege_colmarista.eventos;   -- esquema antiguo
DESCRIBE rege_colmarista2.eventos;  -- esquema nuevo

-- 1c. Ver qué tipos de evento usa el sistema viejo (atraso/licencia/ausencia/otro):
SELECT tipo_evento, COUNT(*) AS cantidad
FROM rege_colmarista.eventos
GROUP BY tipo_evento;

-- ---------------------------------------------------------------------
-- PASO 2 · IDENTIFICAR LOS REGISTROS "NUEVOS" (que no están en la nueva)
-- Clave natural: id_estudiante + fecha_evento + tipo_evento
-- (es el criterio de duplicidad que ya usa Event.php al registrar asistencia)
-- ---------------------------------------------------------------------

-- 2a. Cuántos son los pendientes de copiar:
SELECT COUNT(*) AS nuevos_por_copiar
FROM rege_colmarista.eventos v
LEFT JOIN rege_colmarista2.eventos n
       ON  n.id_estudiante = v.id_estudiante
       AND n.fecha_evento  = v.fecha_evento
       AND LOWER(n.tipo_evento) = LOWER(v.tipo_evento)
WHERE n.id IS NULL;

-- 2b. Verlos antes de copiar (muestra los primeros 50):
SELECT v.id, v.id_estudiante, v.tipo_evento, v.fecha_evento, v.justificacion
FROM rege_colmarista.eventos v
LEFT JOIN rege_colmarista2.eventos n
       ON  n.id_estudiante = v.id_estudiante
       AND n.fecha_evento  = v.fecha_evento
       AND LOWER(n.tipo_evento) = LOWER(v.tipo_evento)
WHERE n.id IS NULL
ORDER BY v.fecha_evento DESC
LIMIT 50;

-- ---------------------------------------------------------------------
-- PASO 3 · COPIA REAL (solo si las dos tablas comparten las MISMAS
-- columnas: id, id_estudiante, tipo_evento, fecha_evento, justificacion,
-- id_usuario_registro, fecha_registro — confirmado en el código del
-- sistema nuevo, app/models/Event.php).
-- No se copia "id": se deja que AUTO_INCREMENT genere ids frescos para
-- no chocar con registros existentes.
-- WHERE ... NOT EXISTS garantiza que sea IDOMPOTENTE (ejecutable varias
-- veces sin duplicar nada).
-- ⚠️ ERROR #1265 "Datos truncados para la columna 'tipo_evento'":
--   ocurre porque el sistema viejo guarda valores fuera del ENUM físico
--   de la tabla nueva (p. ej. 'A', 'F', 'L', 'Ausente', 'Inasistencia').
--   La versión actual traduce cada valor al formato físico nuevo
--   ('atraso' | 'licencia' | 'falta'); cualquier otro valor queda NULL y
--   NO se copia (se reporta en el Paso 2c para revisión manual).
-- ---------------------------------------------------------------------

-- 3a. Traducir/normalizar los tipos usados por el sistema viejo:
SELECT DISTINCT v.tipo_evento AS tipo_viejo,
       CASE UPPER(TRIM(v.tipo_evento))
            WHEN 'A'          THEN 'atraso'
            WHEN 'ATRASO'     THEN 'atraso'
            WHEN 'L'          THEN 'licencia'
            WHEN 'LICENCIA'   THEN 'licencia'
            WHEN 'F'          THEN 'falta'
            WHEN 'FALTA'      THEN 'falta'
            WHEN 'AUSENCIA'   THEN 'falta'
            WHEN 'AUSENTE'    THEN 'falta'
            WHEN 'INASISTENCIA' THEN 'falta'
            ELSE NULL
       END AS tipo_traducido
FROM rege_colmarista.eventos v;

-- 3b. Copia con traducción (omite tipos desconocidos → no lanza #1265):
INSERT INTO rege_colmarista2.eventos
        (id_estudiante, tipo_evento, fecha_evento, justificacion,
         id_usuario_registro, fecha_registro)
SELECT v.id_estudiante,
       CASE UPPER(TRIM(v.tipo_evento))
            WHEN 'A'          THEN 'atraso'
            WHEN 'ATRASO'     THEN 'atraso'
            WHEN 'L'          THEN 'licencia'
            WHEN 'LICENCIA'   THEN 'licencia'
            WHEN 'F'          THEN 'falta'
            WHEN 'FALTA'      THEN 'falta'
            WHEN 'AUSENCIA'   THEN 'falta'
            WHEN 'AUSENTE'    THEN 'falta'
            WHEN 'INASISTENCIA' THEN 'falta'
       END,
       v.fecha_evento, v.justificacion, v.id_usuario_registro, v.fecha_registro
FROM rege_colmarista.eventos v
WHERE CASE UPPER(TRIM(v.tipo_evento))
            WHEN 'A' THEN 'atraso'      WHEN 'ATRASO' THEN 'atraso'
            WHEN 'L' THEN 'licencia'    WHEN 'LICENCIA' THEN 'licencia'
            WHEN 'F' THEN 'falta'       WHEN 'FALTA' THEN 'falta'
            WHEN 'AUSENCIA' THEN 'falta' WHEN 'AUSENTE' THEN 'falta'
            WHEN 'INASISTENCIA' THEN 'falta'
       END IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM rege_colmarista2.eventos n
    WHERE  n.id_estudiante = v.id_estudiante
      AND  n.fecha_evento  = v.fecha_evento
      AND  LOWER(n.tipo_evento) = CASE UPPER(TRIM(v.tipo_evento))
            WHEN 'A' THEN 'atraso'      WHEN 'ATRASO' THEN 'atraso'
            WHEN 'L' THEN 'licencia'    WHEN 'LICENCIA' THEN 'licencia'
            WHEN 'F' THEN 'falta'       WHEN 'FALTA' THEN 'falta'
            WHEN 'AUSENCIA' THEN 'falta' WHEN 'AUSENTE' THEN 'falta'
            WHEN 'INASISTENCIA' THEN 'falta'
       END
);

-- 2c/3c. Registros del viejo que NO se pudieron traducir (revisión manual):
SELECT v.*
FROM rege_colmarista.eventos v
WHERE CASE UPPER(TRIM(v.tipo_evento))
            WHEN 'A' THEN 1 WHEN 'ATRASO' THEN 1 WHEN 'L' THEN 1
            WHEN 'LICENCIA' THEN 1 WHEN 'F' THEN 1 WHEN 'FALTA' THEN 1
            WHEN 'AUSENCIA' THEN 1 WHEN 'AUSENTE' THEN 1
            WHEN 'INASISTENCIA' THEN 1 ELSE 0
      END = 0;

-- ---------------------------------------------------------------------
-- PASO 4 · VERIFICACIÓN POSTERIOR
-- 4a. Conteo de pendientes YA TRADUCIDO (debe quedar solo lo no mapeable,
-- que se revisó en el Paso 3c):
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS quedan_por_copiar
FROM rege_colmarista.eventos v
JOIN (SELECT DISTINCT tipo_evento FROM rege_colmarista.eventos) t
  ON t.tipo_evento = v.tipo_evento
LEFT JOIN rege_colmarista2.eventos n
       ON  n.id_estudiante = v.id_estudiante
       AND n.fecha_evento  = v.fecha_evento
       AND LOWER(n.tipo_evento) = CASE UPPER(TRIM(v.tipo_evento))
             WHEN 'A' THEN 'atraso'    WHEN 'ATRASO' THEN 'atraso'
             WHEN 'L' THEN 'licencia'  WHEN 'LICENCIA' THEN 'licencia'
             WHEN 'F' THEN 'falta'     WHEN 'FALTA' THEN 'falta'
             WHEN 'AUSENCIA' THEN 'falta' WHEN 'AUSENTE' THEN 'falta'
             WHEN 'INASISTENCIA' THEN 'falta'
       END
WHERE n.id IS NULL
  AND CASE UPPER(TRIM(t.tipo_evento))
        WHEN 'A' THEN 1 WHEN 'ATRASO' THEN 1 WHEN 'L' THEN 1
        WHEN 'LICENCIA' THEN 1 WHEN 'F' THEN 1 WHEN 'FALTA' THEN 1
        WHEN 'AUSENCIA' THEN 1 WHEN 'AUSENTE' THEN 1
        WHEN 'INASISTENCIA' THEN 1 ELSE 0
      END = 1;

-- 4b. Totales por tipo en la nueva BD (los valores físicos usados por la
-- app son 'atraso', 'licencia' y 'falta'/'ausencia'):
SELECT tipo_evento, COUNT(*) AS cantidad
FROM rege_colmarista2.eventos
GROUP BY tipo_evento;

-- 4c. Últimos eventos copiados visibles en el orden que usa el sistema:
SELECT id, id_estudiante, tipo_evento, fecha_evento, fecha_registro
FROM rege_colmarista2.eventos
ORDER BY id DESC
LIMIT 20;

-- =====================================================================
-- VARIANTES PARA CASOS PARTICULARES
-- =====================================================================

-- ---------------------------------------------------------------------
-- VARIANTE A · Si el sistema viejo guarda la FALTA como 'ausencia' y la
-- nueva usa 'falta' en su ENUM, normalizar durante la copia:
-- (reemplazar el paso 3 por este)
-- ---------------------------------------------------------------------
/*
INSERT INTO rege_colmarista2.eventos
        (id_estudiante, tipo_evento, fecha_evento, justificacion,
         id_usuario_registro, fecha_registro)
SELECT v.id_estudiante,
       CASE LOWER(v.tipo_evento)
            WHEN 'ausencia' THEN 'falta'
            ELSE LOWER(v.tipo_evento)
       END,
       v.fecha_evento, v.justificacion, v.id_usuario_registro, v.fecha_registro
FROM rege_colmarista.eventos v
WHERE NOT EXISTS (
    SELECT 1 FROM rege_colmarista2.eventos n
    WHERE  n.id_estudiante = v.id_estudiante
      AND  n.fecha_evento  = v.fecha_evento
      AND  LOWER(n.tipo_evento) IN (LOWER(v.tipo_evento),
                                    CASE LOWER(v.tipo_evento)
                                         WHEN 'ausencia' THEN 'falta'
                                         WHEN 'falta'    THEN 'ausencia'
                                         ELSE LOWER(v.tipo_evento) END)
);
*/

-- ---------------------------------------------------------------------
-- VARIANTE B · Si la tabla vieja tiene COLUMNAS EXTRA que la nueva no
-- (p. ej. hora_detalle, observacion), NO incluirlas: copiar solo las 6
-- columnas comunes del Paso 3 (ya es así). Si la nueva tuviera una
-- columna extra obligatoria sin valor por defecto, agrégala al SELECT
-- con un literal, p. ej.: ..., NOW() AS created_at
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- VARIANTE C · Copia POR GESTIÓN/AÑO (si la vieja mezcla años antiguos):
-- agregar al WHERE del Paso 3:
--   AND YEAR(v.fecha_evento) = 2026
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- VARIANTE D · Copia SIMPLE SIN ANTI-JOIN (solo si estás 100% seguro de
-- que NO existe ningún registro repetido en la nueva; más rápido):
--   INSERT INTO rege_colmarista2.eventos SELECT * FROM rege_colmarista.eventos;
-- ⚠️ Incluye el "id" original → puede chocar con PRIMARY KEY. Usar solo
-- como último recurso y tras vaciar la tabla destino.
-- ---------------------------------------------------------------------

-- =====================================================================
-- NOTES FINALES
-- • Después de copiar, limpia cachés del sistema nuevo si aplica
--   (reportes PDF cacheados usan snapshots de esta tabla).
-- • Revisa la pestaña Administración > Diagnóstico: el apartado
--   "Integridad de datos" valida consistencia entre eventos y estudiantes.
-- • Para deshacer: restaura el respaldo del Paso 0 o borra por rango:
--     DELETE FROM rege_colmarista2.eventos
--     WHERE fecha_evento >= '2026-08-01';  -- fechas recién copiadas
-- =====================================================================
