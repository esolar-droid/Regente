<?php
/**
 * CourseController — CRUD de cursos (tabla `cursos`)
 *
 * Rutas: /courses, /courses/create, /courses/store,
 *        /courses/{id}/edit, /courses/{id}/update, /courses/{id}/delete
 *
 * Nota de menú: el sidebar/header ya no enlazan a /courses directamente;
 * la página «Buscar» (/search) es la cara pública de los cursos. Este
 * controlador existe para sanear las rutas y permitir poblar/editar
 * cursos desde una URL directa si hiciera falta durante los ensayos.
 */

namespace App\Controllers;

use App\Models\Course;
use App\Models\Student;
use App\Middleware\CsrfMiddleware;

class CourseController extends Controller
{
    private function canManage()
    {
        // manage_courses puede no existir aún en la BD; create_student es el
        // proxy natural del rol regente/administrador para mantener el catálogo.
        return isAdmin() || can('manage_courses') || can('create_student');
    }

    private function normalize($nivel)
    {
        $n = mb_strtolower(trim((string)$nivel), 'UTF-8');
        if ($n === 'primaria') return 'Primaria';
        if ($n === 'secundaria') return 'Secundaria';
        return '';
    }

    public function index()
    {
        $this->requireAuth();

        $courses = Course::query(
            'SELECT c.*, (SELECT COUNT(*) FROM estudiantes s WHERE s.id_curso = c.id) AS alumnos
             FROM cursos c ORDER BY c.nivel, c.grado, c.paralelo'
        );

        $this->view('courses/index', [
            'courses'    => $courses,
            'canManage'  => $this->canManage(),
            'csrf_token' => CsrfMiddleware::generateToken(),
        ]);
    }

    public function create()
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            $this->redirect('/403');
        }
        $this->view('courses/form', [
            'course'     => null,
            'csrf_token' => CsrfMiddleware::generateToken(),
        ]);
    }

    public function store()
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            $this->redirect('/403');
        }
        $p = $this->getPostData();

        $nivel   = $this->normalize($p['nivel'] ?? '');
        $grado   = strtoupper(trim((string)($p['grado'] ?? '')));
        $paralelo = strtoupper(trim((string)($p['paralelo'] ?? '')));

        if ($nivel === '' || $grado === '' || $paralelo === '') {
            $_SESSION['course_flash'] = ['is-danger', 'Completa nivel, grado y paralelo.'];
            $this->redirect('/courses/create');
        }
        // Código único tipo P1A / S6B: prefijo del nivel + grado + paralelo
        $prefijo = ($nivel === 'Primaria') ? 'P' : 'S';
        $curso = $prefijo . preg_replace('/[^A-Z0-9]/', '', $grado) . $paralelo;

        if (Course::firstWhere('curso', '=', $curso)) {
            $_SESSION['course_flash'] = ['is-warning', "El curso $curso ya existe."];
            $this->redirect('/courses/create');
        }

        Course::execute(
            'INSERT INTO cursos (nivel, grado, paralelo, curso) VALUES (?,?,?,?)',
            [$nivel, $grado, $paralelo, $curso]
        );
        $_SESSION['course_flash'] = ['is-success', "Curso $curso creado."];
        $this->redirect('/courses');
    }

    public function edit($id)
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            $this->redirect('/403');
        }
        $course = Course::find((int)$id);
        if (!$course) {
            $this->view('errors/404');
            return;
        }
        $this->view('courses/form', [
            'course'     => $course,
            'csrf_token' => CsrfMiddleware::generateToken(),
        ]);
    }

    public function update($id)
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            $this->redirect('/403');
        }
        $course = Course::find((int)$id);
        if (!$course) {
            $this->redirect('/courses');
        }
        $p = $this->getPostData();
        $nivel    = $this->normalize($p['nivel'] ?? '');
        $grado    = strtoupper(trim((string)($p['grado'] ?? '')));
        $paralelo = strtoupper(trim((string)($p['paralelo'] ?? '')));

        if ($nivel === '' || $grado === '' || $paralelo === '') {
            $_SESSION['course_flash'] = ['is-danger', 'Completa nivel, grado y paralelo.'];
            $this->redirect("/courses/$id/edit");
        }
        $prefijo = ($nivel === 'Primaria') ? 'P' : 'S';
        $curso = $prefijo . preg_replace('/[^A-Z0-9]/', '', $grado) . $paralelo;

        $dup = Course::firstWhere('curso', '=', $curso);
        if ($dup && (int)$dup->id !== (int)$course->id) {
            $_SESSION['course_flash'] = ['is-warning', "Otro curso ya usa el código $curso."];
            $this->redirect("/courses/$id/edit");
        }

        Course::execute(
            'UPDATE cursos SET nivel=?, grado=?, paralelo=?, curso=? WHERE id=?',
            [$nivel, $grado, $paralelo, $curso, (int)$course->id]
        );
        $_SESSION['course_flash'] = ['is-success', "Curso $curso actualizado."];
        $this->redirect('/courses');
    }

    public function destroy($id)
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            $this->redirect('/403');
        }
        $id = (int)$id;
        $course = Course::find($id);
        if (!$course) {
            $this->redirect('/courses');
        }
        try {
            $db = \Database::getConnection();
            // Bloquear borrado con alumnos encima: protege el historial.
            $stmt = $db->prepare('SELECT COUNT(*) FROM estudiantes WHERE id_curso = ?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $_SESSION['course_flash'] = ['is-danger', 'No se puede eliminar: el curso tiene estudiantes asignados. Muévelos primero.'];
                $this->redirect('/courses');
            }
            // Desvincular horarios huérfanos posibles y eliminar el curso.
            $db->prepare('DELETE FROM horarios WHERE id_curso = ?')->execute([$id]);
            $db->prepare('DELETE FROM cursos WHERE id = ?')->execute([$id]);
            $_SESSION['course_flash'] = ['is-success', 'Curso eliminado.'];
        } catch (\Throwable $e) {
            error_log('CourseController.destroy: ' . $e->getMessage());
            $_SESSION['course_flash'] = ['is-danger', 'Error al eliminar: ' . $e->getMessage()];
        }
        $this->redirect('/courses');
    }
}
