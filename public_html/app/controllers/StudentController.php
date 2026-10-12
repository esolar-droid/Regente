<?php
/**
 * StudentController
 *
 * Hoja de estudiantes: listado con filtros, ficha individual (historial de
 * estados + eventos), alta/edición (solo con permiso students.edit) y
 * cambios de estado académico con asiento histórico.
 */

namespace App\Controllers;

use App\Models\Student;
use App\Models\Course;
use App\Models\Event;
use App\Middleware\CsrfMiddleware;

class StudentController extends Controller {

    /** Permiso para editar fichas: admin o roles con create/edit student. */
    private function canEdit() {
        return isAdmin() || can('create_student') || can('edit_student');
    }

    private function requireEditPermission() {
        if (!$this->canEdit()) {
            $_SESSION['flash']['error'] = 'No tienes permiso para editar estudiantes.';
            $this->redirect('/dashboard');
        }
    }

    // ------------------------------------------------------------------
    // Listado
    // ------------------------------------------------------------------
    public function index() {
        $this->requireAuth();

        $q       = $this->getQueryData();
        $filters = [
            'search'   => trim($q['q'] ?? ''),
            'estado'   => $q['estado'] ?? '',
            'id_curso' => (int)($q['curso'] ?? 0),
            'page'     => (int)($q['page'] ?? 1),
        ];

        $result  = Student::filter($filters);
        $counts  = Student::stateCounts();
        $courses = Course::orderBy('curso');
        $birthdays = method_exists(Student::class, 'birthdaysToday') ? Student::birthdaysToday() : [];

        $this->view('students/index', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'result'     => $result,
            'filters'    => $filters,
            'counts'     => $counts,
            'courses'    => $courses,
            'birthdays'  => $birthdays,
            'canEdit'    => $this->canEdit(),
        ]);
    }

    // ------------------------------------------------------------------
    // Ficha individual
    // ------------------------------------------------------------------
    public function show($id) {
        $this->requireAuth();

        $student = Student::find((int)$id);
        if (!$student) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $history = Student::getStateHistory($student->id);

        // Eventos del estudiante (ultimos 50) - filas planas para la vista
        try {
            $db   = \Database::getConnection();
            $stmt = $db->prepare(
                "SELECT * FROM eventos WHERE id_estudiante = ? ORDER BY fecha_evento DESC LIMIT 50"
            );
            $stmt->execute([$student->id]);
            $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('StudentController.show events: ' . $e->getMessage());
            $events = [];
        }

        $this->view('students/show', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'student'    => $student,
            'history'    => $history,
            'events'     => $events,
            'canEdit'    => $this->canEdit(),
        ]);
    }

    // ------------------------------------------------------------------
    // Alta
    // ------------------------------------------------------------------
    public function create() {
        $this->requireAuth();
        $this->requireEditPermission();

        $this->view('students/form', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'student'    => null,
            'courses'    => Course::orderBy('curso'),
            'mode'       => 'create',
        ]);
    }

    public function store() {
        $this->requireAuth();
        $this->requireEditPermission();

        $p  = $this->getPostData();
        $er = $this->validateStudent($p, null);

        if ($er) {
            $_SESSION['flash']['error'] = implode(' ', $er);
            $this->redirect('/students/create');
        }

        $gestion = \App\Models\Management::getActive();

        // Normalizar telefonos a formato internacional wa.me (Bolivia +591)
        $wa = function ($t) {
            $t = preg_replace('/\D/', '', (string)$t);
            if ($t === '') return null;
            if (strlen($t) === 8) return '591' . $t;                      // fijo
            if (strlen($t) === 9 && $t[0] === '0') return '591' . substr($t, 1); // celular 0X
            if (strlen($t) >= 11) return ltrim($t, '0');                   // ya con codigo pais
            return '591' . $t;
        };

        $student = new Student([
            'nombre_completo'    => $p['nombre_completo'],
            'codigo_bnb'         => $p['codigo_bnb'] ?: null,
            'id_curso'           => $p['id_curso'] ?: null,
            'telefono_estudiante'=> $wa($p['telefono_estudiante'] ?? ''),
            'telefono_papa'      => $wa($p['telefono_papa'] ?? ''),
            'telefono_mama'      => $wa($p['telefono_mama'] ?? ''),
            'id_gestion'         => $gestion ? $gestion->id : null,
            'fecha_nacimiento'   => $p['fecha_nacimiento'] ?: null,
            'estado'             => $p['estado'],
            'fecha_ini'          => $p['fecha_ini'],
            'fecha_fin'          => null,
        ]);

        $newId = $student->save();
        if (!$newId) {
            $_SESSION['flash']['error'] = 'No se pudo guardar el estudiante. Revisa los datos e inténtalo de nuevo.';
            $this->redirect('/students/create');
        }

        // Primer asiento historico
        Student::changeState($newId, $p['estado'], $p['fecha_ini'], 'Alta en el sistema', $this->userId());

        $_SESSION['flash']['success'] = 'Estudiante registrado correctamente.';
        $this->redirect('/students/' . $newId);
    }

    // ------------------------------------------------------------------
    // Edición
    // ------------------------------------------------------------------
    public function edit($id) {
        $this->requireAuth();
        $this->requireEditPermission();

        $student = Student::find((int)$id);
        if (!$student) { $this->redirect('/students'); }

        $this->view('students/form', [
            'csrf_token' => CsrfMiddleware::generateToken(),
            'student'    => $student,
            'courses'    => Course::orderBy('curso'),
            'mode'       => 'edit',
        ]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireEditPermission();

        $student = Student::find((int)$id);
        if (!$student) { $this->redirect('/students'); }

        $p  = $this->getPostData();
        $er = $this->validateStudent($p, $student->id);

        if ($er) {
            $_SESSION['flash']['error'] = implode(' ', $er);
            $this->redirect("/students/{$id}/edit");
        }

        // Datos personales (el estado se cambia SOLO via change-state para
        // mantener la integridad del historial)
        $wa = function ($t) {
            $t = preg_replace('/\D/', '', (string)$t);
            if ($t === '') return null;
            if (strlen($t) === 8) return '591' . $t;
            if (strlen($t) === 9 && $t[0] === '0') return '591' . substr($t, 1);
            if (strlen($t) >= 11) return ltrim($t, '0');
            return '591' . $t;
        };

        $student->nombre_completo     = $p['nombre_completo'];
        $student->codigo_bnb          = $p['codigo_bnb'] ?: null;
        $student->id_curso            = $p['id_curso'] ?: null;
        $student->telefono_estudiante = $wa($p['telefono_estudiante'] ?? '');
        $student->telefono_papa       = $wa($p['telefono_papa'] ?? '');
        $student->telefono_mama       = $wa($p['telefono_mama'] ?? '');
        $student->fecha_nacimiento    = $p['fecha_nacimiento'] ?: null;

        // Guardar explicitamente solo las columnas editables de datos
        // personales. No usar $student->save() con todos los campos porque
        // 'fecha_fin' no debe modificarse aqui (lo gestiona changeState()).
        try {
            $db   = \Database::getConnection();
            $stmt = $db->prepare(
                "UPDATE estudiantes SET nombre_completo=?, codigo_bnb=?, id_curso=?,
                        telefono_estudiante=?, telefono_papa=?, telefono_mama=?,
                        fecha_nacimiento=? WHERE id=?"
            );
            $ok = $stmt->execute([
                $student->nombre_completo, $student->codigo_bnb, $student->id_curso,
                $student->telefono_estudiante, $student->telefono_papa,
                $student->telefono_mama, $student->fecha_nacimiento, $student->id,
            ]);
        } catch (\Throwable $e) {
            error_log('StudentController.update: ' . $e->getMessage());
            $ok = false;
        }

        $_SESSION['flash'][$ok ? 'success' : 'error'] = $ok
            ? 'Ficha actualizada correctamente.'
            : 'No se pudo actualizar la ficha.';
        $this->redirect('/students/' . $id);
    }

    // ------------------------------------------------------------------
    // Cambio de estado academico (con historial)
    // ------------------------------------------------------------------
    public function changeState($id) {
        $this->requireAuth();
        $this->requireEditPermission();

        $student = Student::find((int)$id);
        if (!$student) { $this->redirect('/students'); }

        $p = $this->getPostData();
        $newState = $p['nuevo_estado'] ?? '';
        $dateIni  = $p['fecha_ini'] ?? date('Y-m-d');
        $motivo   = $p['motivo'] ?? null;

        if (!in_array($newState, Student::STATES, true)) {
            $_SESSION['flash']['error'] = 'Estado no válido.';
            $this->redirect('/students/' . $id);
        }
        if (!validateDate($dateIni, 'Fecha de inicio')) {
            $_SESSION['flash']['error'] = 'La fecha de inicio no es válida (YYYY-MM-DD).';
            $this->redirect('/students/' . $id);
        }

        // Regla de negocio: si pasa a 'retirado' desde un receso/virtual,
        // queda constancia en el motivo automático del cierre anterior.
        $ok = Student::changeState($student->id, $newState, $dateIni, $motivo, $this->userId());

        $_SESSION['flash'][$ok ? 'success' : 'error'] = $ok
            ? 'Estado actualizado: ' . student_state_label($newState) . '.'
            : 'No se pudo registrar el cambio de estado.';
        $this->redirect('/students/' . $id);
    }

    // ------------------------------------------------------------------
    // Baja (blanda por defecto: retirar)
    // ------------------------------------------------------------------
    public function destroy($id) {
        $this->requireAuth();
        if (!isAdmin()) {
            $_SESSION['flash']['error'] = 'Solo el administrador puede eliminar registros. Usa "Retirado" para bajas.';
            $this->redirect('/students/' . $id);
        }

        $student = Student::find((int)$id);
        if ($student && $student->delete()) {
            $_SESSION['flash']['success'] = 'Estudiante eliminado.';
        } else {
            $_SESSION['flash']['error'] = 'No se pudo eliminar al estudiante.';
        }
        $this->redirect('/students');
    }

    // ------------------------------------------------------------------
    // Validación común create/update
    // ------------------------------------------------------------------
    private function validateStudent(array $p, $ignoreId) {
        $errors = [];

        $errors[] = validateRequired($p['nombre_completo'] ?? '', 'Nombre completo');
        $errors[] = validateMinLength($p['nombre_completo'] ?? '', 3, 'Nombre completo');

        if (!empty($p['codigo_bnb'])) {
            $dup = Student::firstWhere('codigo_bnb', '=', $p['codigo_bnb']);
            if ($dup && (int)$dup->id !== (int)$ignoreId) {
                $errors[] = 'Ya existe un estudiante con ese código BNB.';
            }
        }
        foreach (['fecha_nacimiento' => 'Fecha de nacimiento', 'fecha_ini' => 'Fecha de inicio del estado'] as $f => $lbl) {
            if (!empty($p[$f])) {
                $errors[] = validateDate($p[$f], $lbl);
            }
        }
        foreach (['telefono_estudiante' => 'Teléfono estudiante', 'telefono_papa' => 'Teléfono papá', 'telefono_mama' => 'Teléfono mamá'] as $f => $lbl) {
            if (!empty($p[$f])) {
                $errors[] = validatePhone($p[$f], $lbl);
            }
        }
        $errors[] = validateEnum($p['estado'] ?? 'regular', Student::STATES, 'Estado');

        return array_values(array_filter($errors));
    }
}
