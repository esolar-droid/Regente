<?php
/**
 * Report Model — capa de datos del módulo Reportes (sin tabla propia).
 *
 * Todas las rejillas derivan de `eventos` (A/L/F; presente = días hábiles
 * sin evento) cruzadas con estudiantes activos de la gestión vigente,
 * cursos y feriados. Los totales 0 se devuelven como '' para que la vista
 * deje las celdas vacías (decisión de producto: facilitar lectura).
 */

namespace App\Models;

class Report {

    /** Gestión activa => ['id'=>int|null,'anio'=>int]. Fallback: año actual. */
    public static function activeManagement() {
        try {
            $g = Management::getActive();
            if ($g) return ['id' => (int)$g->id, 'anio' => (int)$g->anio];
        } catch (\Throwable $e) { error_log('Report.activeManagement: ' . $e->getMessage()); }
        return ['id' => null, 'anio' => (int)date('Y')];
    }

    /** Nómina activa de un curso (mismo criterio que el registro masivo). */
    public static function roster($courseId, $anio = null) {
        try {
            $db = \Database::getConnection();
            // v16.3: si se indica $anio explicito (reporte anual de otra gestion),
            // se lista la nomina de esa gestion; si no, la gestion activa actual.
            if ($anio !== null) {
                $stmt = $db->prepare(
                    "SELECT e.id, e.nombre_completo, e.codigo_bnb
                     FROM estudiantes e
                     JOIN gestion g ON g.anio = ?
                     WHERE e.id_curso = ? AND e.id_gestion = g.id AND e.estado <> 'retirado'
                     ORDER BY e.nombre_completo"
                );
                $stmt->execute([(int)$anio, (int)$courseId]);
            } else {
                $stmt = $db->prepare(
                    "SELECT e.id, e.nombre_completo, e.codigo_bnb
                     FROM estudiantes e
                     JOIN gestion g ON g.anio = YEAR(CURDATE()) AND g.estado = 'activa'
                     WHERE e.id_curso = ? AND e.id_gestion = g.id AND e.estado <> 'retirado'
                     ORDER BY e.nombre_completo"
                );
                $stmt->execute([(int)$courseId]);
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Report.roster: ' . $e->getMessage());
            return [];
        }
    }

    /** Lista de cursos ordenada (para selects). Filtra por nivel si se indica. */
    public static function courses($nivel = null) {
        try {
            $db = \Database::getConnection();
            if ($nivel && in_array($nivel, ['Primaria', 'Secundaria'], true)) {
                $stmt = $db->prepare("SELECT id, curso, nivel, grado, paralelo FROM cursos WHERE nivel = ? ORDER BY grado, paralelo");
                $stmt->execute([$nivel]);
            } else {
                $stmt = $db->query("SELECT id, curso, nivel, grado, paralelo FROM cursos ORDER BY nivel, grado, paralelo");
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Report.courses: ' . $e->getMessage());
            return [];
        }
    }

    /** Asignaturas distintas registradas en horarios (para selectores). */
    public static function subjects($anio = null) {
        try {
            $db = \Database::getConnection();
            if ($anio) {
                $stmt = $db->prepare("SELECT DISTINCT asignatura FROM horarios WHERE anio = ? AND asignatura IS NOT NULL AND asignatura <> '' ORDER BY asignatura");
                $stmt->execute([(int)$anio]);
            } else {
                $stmt = $db->query("SELECT DISTINCT asignatura FROM horarios WHERE asignatura IS NOT NULL AND asignatura <> '' ORDER BY asignatura");
            }
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            error_log('Report.subjects: ' . $e->getMessage());
            return [];
        }
    }

    /** Profesores (usuarios con rol profesor/docente). */
    public static function teachers() {
        try {
            $db = \Database::getConnection();
            $stmt = $db->query(
                "SELECT u.id, TRIM(CONCAT(u.nombre,' ',u.apellido)) AS nombre
                 FROM usuarios u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE COALESCE(r.name,'') IN ('profesor','docente') OR u.rol IN ('profesor','docente')
                 ORDER BY u.apellido, u.nombre"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // fallback si la columna `rol` no existe en usuarios
            try {
                $db = \Database::getConnection();
                $stmt = $db->query(
                    "SELECT u.id, TRIM(CONCAT(u.nombre,' ',u.apellido)) AS nombre
                     FROM usuarios u JOIN roles r ON r.id = u.role_id
                     WHERE r.name IN ('profesor','docente')
                     ORDER BY u.apellido, u.nombre"
                );
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e2) {
                error_log('Report.teachers: ' . $e2->getMessage());
                return [];
            }
        }
    }

    /**
     * Marks de eventos A/L/F para un conjunto de estudiantes en un rango.
     * Devuelve [id_estudiante][fecha] => ['t'=>'A|L|F','j'=>justificacion].
     */
    public static function marks(array $studentIds, $from, $to) {
        $ids = array_values(array_filter(array_map('intval', $studentIds)));
        if (!$ids || !$from || !$to) return [];
        try {
            $db = \Database::getConnection();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare(
                "SELECT id_estudiante, DATE_FORMAT(fecha_evento,'%Y-%m-%d') AS f, tipo_evento, justificacion
                 FROM eventos WHERE fecha_evento BETWEEN ? AND ? AND id_estudiante IN ($ph)"
            );
            $stmt->execute(array_merge([$from, $to], $ids));
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $short = Event::typeToShort($r['tipo_evento']);
                if ($short !== '') {
                    $out[(int)$r['id_estudiante']][$r['f']] = [
                        't' => $short, 'j' => (string)($r['justificacion'] ?? ''),
                    ];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Report.marks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * v16.3: totales A/L/F por estudiante y mes, agregados EN SQL.
     * Devuelve [id_estudiante][mes 0..11] => ['A'=>n,'L'=>n,'F'=>n].
     * Reemplaza a marks() en el reporte anual: antes se traian TODAS las filas
     * del ano por cada rebuild (pantalla+CSV+PDF) y PHP las expandia en arrays
     * gigantes -> "Allowed memory size exhausted". Con GROUP BY solo llegan
     * ~12 filas por estudiante y la memoria es O(nomina), no O(eventos*copias).
     */
    public static function marksAgg(array $studentIds, $from, $to) {
        $ids = array_values(array_filter(array_map('intval', $studentIds)));
        if (!$ids || !$from || !$to) return [];
        try {
            $db = \Database::getConnection();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare(
                "SELECT id_estudiante, MONTH(fecha_evento) AS m, tipo_evento, COUNT(*) AS n
                 FROM eventos
                 WHERE fecha_evento BETWEEN ? AND ? AND id_estudiante IN ($ph)
                 GROUP BY id_estudiante, MONTH(fecha_evento), tipo_evento"
            );
            $stmt->execute(array_merge([$from, $to], $ids));
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $short = Event::typeToShort($r['tipo_evento']);
                if ($short === 'P' || $short === '') continue; // solo A/L/F
                $m = ((int)$r['m']) - 1;
                if ($m < 0 || $m > 11) continue;
                $out[(int)$r['id_estudiante']][$m][$short] = (int)$r['n'];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Report.marksAgg: ' . $e->getMessage());
            return [];
        }
    }

    /** Mapa fecha=>nombre de feriados dentro de un rango. */
    public static function holidayMap($from, $to) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m-%d') AS f, nombre FROM feriados WHERE fecha BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) $out[$r['f']] = $r['nombre'];
            return $out;
        } catch (\Throwable $e) {
            error_log('Report.holidayMap: ' . $e->getMessage());
            return [];
        }
    }

    /** Días hábiles (L-V, sin feriados) entre dos fechas. */
    public static function businessDays($from, $to, array $holidays) {
        $days = [];
        $ts = strtotime(substr($from, 0, 10));
        $te = strtotime(substr($to, 0, 10));
        if ($ts === false || $te === false || $te < $ts) return $days;
        for ($d = $ts; $d <= $te; $d = strtotime('+1 day', $d)) {
            $iso = date('Y-m-d', $d);
            $w = (int)date('N', $d);           // 1=Lun .. 7=Dom
            if ($w > 5) continue;              // sáb/dom inhábiles
            if (isset($holidays[$iso])) continue;
            $days[] = $iso;
        }
        return $days;
    }

    /** Semana (L-V) que contiene una fecha. */
    public static function weekDays($date) {
        $ts = strtotime(substr($date, 0, 10));
        if ($ts === false) return [];
        $mon = strtotime('monday this week', $ts);
        $days = [];
        for ($i = 0; $i < 5; $i++) $days[] = date('Y-m-d', strtotime("+$i day", $mon));
        return $days;
    }

    /**
     * Matriz de asistencia: filas = estudiantes, columnas = días hábiles.
     * Cada celda: ['t'=>'P'|'A'|'L'|'F','j'=>just]. t='P' cuando no hay evento.
     * Incluye además los eventos caídos en sábado/domingo/feriado como fila
     * aparte ($extraMarks) para que ningún registro quede invisible en PDF.
     */
    public static function matrix(array $roster, array $businessDays, array $marks) {
        // v16.2: soporta días en formato 'YYYY-MM-DD' o arrays ['f'=>...].
        // (lapso pasa $bdays como arrays -> antes $marks[$sid][array] reventaba
        //  con "Undefined array key 0" y tipos erróneos.)
        // Clave de celda = fecha ISO normalizada (funciona con días pasados
        // como string o como array ['f'=>...]).
        $grid = [];
        foreach ($roster as $st) {
            $sid = (int)$st['id'];
            $row = [];
            foreach (array_values($businessDays) as $d) {
                $iso = is_array($d) ? (string)($d['f'] ?? '') : (string)$d;
                $m = $marks[$sid][$iso] ?? null;
                $row[$iso] = ['t' => $m ? $m['t'] : 'P', 'j' => $m ? $m['j'] : ''];
            }
            $grid[$sid] = $row;
        }
        return $grid;
    }

    /** Totales P/A/L/F por estudiante sobre una matriz ya construida. */
    public static function totals(array $grid) {
        $tot = [];
        foreach ($grid as $sid => $row) {
            $c = ['P' => 0, 'A' => 0, 'L' => 0, 'F' => 0];
            foreach ($row as $cell) $c[$cell['t']]++;
            $tot[$sid] = $c;
        }
        return $tot;
    }

    /** Eventos fuera de los días hábiles mostrados (sáb/dom/feriado). */
    public static function offGridEvents(array $roster, array $businessDays, array $marks) {
        $bd = array_flip($businessDays);
        $out = [];
        foreach ($roster as $st) {
            $sid = (int)$st['id'];
            foreach (($marks[$sid] ?? []) as $d => $m) {
                if (!isset($bd[$d])) $out[$sid][] = ['f' => $d] + $m;
            }
        }
        return $out;
    }

    /** Detalle cronológico de eventos (para reporte individual). */
    public static function detailForStudent($studentId, $from, $to) {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(fecha_evento,'%Y-%m-%d') AS f, tipo_evento, justificacion
                 FROM eventos WHERE id_estudiante = ? AND fecha_evento BETWEEN ? AND ?
                 ORDER BY fecha_evento"
            );
            $stmt->execute([(int)$studentId, $from, $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$r) $r['t'] = Event::typeToShort($r['tipo_evento']);
            return $rows;
        } catch (\Throwable $e) {
            error_log('Report.detailForStudent: ' . $e->getMessage());
            return [];
        }
    }

    /** Conteo mensual P vs A (y L,F) para gráficos del reporte individual. */
    public static function monthlyCounts($studentId, $from, $to) {
        $detail = self::detailForStudent($studentId, $from, $to);
        $holidays = self::holidayMap($from, $to);
        $bdays = self::businessDays($from, $to, $holidays);
        $perMonth = [];
        foreach ($bdays as $d) {
            $k = substr($d, 0, 7);
            if (!isset($perMonth[$k])) $perMonth[$k] = ['habiles' => 0, 'A' => 0, 'L' => 0, 'F' => 0];
            $perMonth[$k]['habiles']++;
        }
        foreach ($detail as $r) {
            $k = substr($r['f'], 0, 7);
            if (!isset($perMonth[$k])) $perMonth[$k] = ['habiles' => 0, 'A' => 0, 'L' => 0, 'F' => 0];
            if (isset($perMonth[$k][$r['t']])) $perMonth[$k][$r['t']]++;
        }
        ksort($perMonth);
        $labels = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
        $out = [];
        foreach ($perMonth as $k => $v) {
            $v['mes'] = ($labels[substr($k,5,2)] ?? substr($k,5,2)) . ' ' . substr($k,0,4);
            $v['P'] = max(0, $v['habiles'] - $v['A'] - $v['L'] - $v['F']);
            $out[] = $v;
        }
        return $out;
    }

    /** Acumulado mensual de A/L/F por curso (reporte anual): [curso][mes] => counts. */
    public static function monthlyByCourse($courseId, $yearStart, $yearEnd) {
        $roster = self::roster($courseId);
        $ids = array_map(fn($s) => (int)$s['id'], $roster);
        $marks = self::marks($ids, $yearStart, $yearEnd);
        $months = [];
        for ($m = 1; $m <= 12; $m++) $months[sprintf('%02d', $m)] = ['A' => 0, 'L' => 0, 'F' => 0];
        foreach ($marks as $sid => $byDate) {
            foreach ($byDate as $d => $mm) {
                $k = substr($d, 5, 2);
                if (isset($months[$k][$mm['t']])) $months[$k][$mm['t']]++;
            }
        }
        return $months;
    }

    /** Cursos de un nivel listos para el reporte semanal (multi-hoja). */
    public static function coursesWithRoster($nivel) {
        $out = [];
        foreach (self::courses($nivel) as $c) {
            $roster = self::roster((int)$c['id']);
            if (!$roster) continue;
            $out[] = ['course' => $c, 'roster' => $roster];
        }
        return $out;
    }

    // ====================================================================
    // v17 — DASHBOARD: agregaciones para gráficos y rankings
    // ====================================================================

    /**
     * Totales acumulados de A/L/F por curso desde el inicio de la gestión
     * hasta hoy. Una sola consulta con GROUP BY (memoria O(cursos)).
     * Devuelve [id_curso] => ['A'=>n,'L'=>n,'F'=>n].
     */
    public static function courseEventTotals(?int $managementId = null) {
        try {
            $db = \Database::getConnection();
            $where = "WHERE ev.fecha_evento <= CURDATE()";
            $params = [];
            if ($managementId !== null) {
                $where .= " AND s.id_gestion = ?";
                $params[] = $managementId;
            }
            $stmt = $db->prepare(
                "SELECT s.id_curso AS cid, ev.tipo_evento AS tipo, COUNT(*) AS n
                 FROM eventos ev
                 JOIN estudiantes s ON s.id = ev.id_estudiante
                 $where
                 GROUP BY s.id_curso, ev.tipo_evento"
            );
            $stmt->execute($params);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $short = Event::typeToShort($r['tipo']);
                if ($short === '' || $short === 'P') continue;
                $cid = (int)$r['cid'];
                if (!isset($out[$cid])) $out[$cid] = ['A' => 0, 'L' => 0, 'F' => 0];
                $out[$cid][$short] += (int)$r['n'];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Report.courseEventTotals: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ranking de estudiantes por cantidad de eventos de un tipo, agrupado
     * por nivel educativo ('Primaria' | 'Secundaria').
     * @param string $type 'atraso'|'licencia'|'falta'
     * @return array [['nombre'=>..., 'curso'=>'P1A', 'n'=>int], ...] top N
     */
    public static function studentRankingByLevel(string $type, string $nivel, int $limit = 20, ?int $managementId = null) {
        // 'falta' incluye restos legados 'ausencia' (mismo criterio que getStatistics)
        $types = $type === 'falta' ? ['falta', 'ausencia'] : [$type];
        try {
            $db = \Database::getConnection();
            $ph = implode(',', array_fill(0, count($types), '?'));
            $sql = "SELECT st.nombre_completo AS nombre, c.curso AS curso, COUNT(*) AS n
                    FROM eventos ev
                    JOIN estudiantes st ON st.id = ev.id_estudiante
                    JOIN cursos c ON c.id = st.id_curso
                    WHERE c.nivel = ? AND ev.tipo_evento IN ($ph) AND ev.fecha_evento <= CURDATE()";
            $params = array_merge([$nivel], $types);
            if ($managementId !== null) {
                $sql .= " AND st.id_gestion = ?";
                $params[] = $managementId;
            }
            $sql .= " GROUP BY st.id, st.nombre_completo, c.curso ORDER BY n DESC, st.nombre_completo ASC LIMIT " . (int)$limit;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $out[] = ['nombre' => $r['nombre'], 'curso' => $r['curso'], 'n' => (int)$r['n']];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Report.studentRankingByLevel: ' . $e->getMessage());
            return [];
        }
    }
}
