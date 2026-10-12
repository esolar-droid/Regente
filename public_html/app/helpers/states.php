<?php
/**
 * Helpers de estados académicos de estudiantes
 *
 * Valores físicos (ENUM en BD) <-> etiquetas visibles (UI).
 * 'asistencia_virtual' se muestra como "Asistencia a distancia".
 */

if (!function_exists('student_states')) {
    /** Mapa estado_fisico => etiqueta visible */
    function student_states() {
        return [
            'regular'               => 'Regular',
            'retirado'              => 'Retirado',
            'receso_medico'         => 'Receso médico',
            'receso_pedagogico'     => 'Receso pedagógico',
            'receso_administrativo' => 'Receso administrativo',
            'asistencia_virtual'    => 'Asistencia a distancia',
        ];
    }
}

if (!function_exists('student_state_label')) {
    function student_state_label($state) {
        $map = student_states();
        return $map[$state] ?? ucfirst(str_replace('_', ' ', (string)$state));
    }
}

if (!function_exists('student_state_class')) {
    /** Clase de tag Bulma según el estado */
    function student_state_class($state) {
        switch ($state) {
            case 'regular':               return 'is-success';
            case 'retirado':              return 'is-dark';
            case 'receso_medico':         return 'is-warning';
            case 'receso_pedagogico':     return 'is-info';
            case 'receso_administrativo': return 'is-light has-text-grey-dark';
            case 'asistencia_virtual':    return 'is-link';
            default:                      return 'is-light';
        }
    }
}

if (!function_exists('student_is_active_state')) {
    /** Estados que cuentan como "en sistema" (no retirado) */
    function student_is_active_state($state) {
        return $state !== 'retirado';
    }
}

if (!function_exists('student_age')) {
    /** Edad en años completos a partir de fecha_nacimiento ('' si no aplica) */
    function student_age($birthDate) {
        if (empty($birthDate) || substr((string)$birthDate, 0, 4) === '0000') {
            return null;
        }
        try {
            $birth = new DateTime($birthDate);
        } catch (Exception $e) {
            return null;
        }
        $age = $birth->diff(new DateTime('today'))->y;
        return $age >= 0 && $age < 120 ? $age : null;
    }
}
