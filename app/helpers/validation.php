<?php
// Validation helper functions

if (!function_exists('validateRequired')) {
    function validateRequired($value) {
        return !empty(trim($value));
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('validateLength')) {
    function validateLength($value, $min = 0, $max = PHP_INT_MAX) {
        $length = strlen(trim($value));
        return $length >= $min && $length <= $max;
    }
}

if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        // Basic phone validation (adjust as needed)
        return preg_match('/^[0-9\-\+\(\)\/\s]{8,20}$/', $phone);
    }
}

if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('validatePassword')) {
    function validatePassword($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
    }
}

if (!function_exists('validate')) {
    function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $value = $data[$field] ?? '';
            
            foreach ($rulesList as $rule) {
                $parts = explode(':', $rule);
                $ruleName = $parts[0];
                $ruleParam = $parts[1] ?? null;
                
                switch ($ruleName) {
                    case 'required':
                        if (!validateRequired($value)) {
                            $errors[$field] = "El campo {$field} es obligatorio.";
                        }
                        break;
                    case 'email':
                        if (!validateEmail($value)) {
                            $errors[$field] = "El campo {$field} debe ser un email válido.";
                        }
                        break;
                    case 'min':
                        if (!validateLength($value, (int)$ruleParam)) {
                            $errors[$field] = "El campo {$field} debe tener al menos {$ruleParam} caracteres.";
                        }
                        break;
                    case 'max':
                        if (!validateLength($value, 0, (int)$ruleParam)) {
                            $errors[$field] = "El campo {$field} debe tener como máximo {$ruleParam} caracteres.";
                        }
                        break;
                    case 'phone':
                        if (!validatePhone($value)) {
                            $errors[$field] = "El campo {$field} debe ser un teléfono válido.";
                        }
                        break;
                    case 'date':
                        if (!validateDate($value)) {
                            $errors[$field] = "El campo {$field} debe ser una fecha válida.";
                        }
                        break;
                    case 'password':
                        if (!validatePassword($value)) {
                            $errors[$field] = "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.";
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
}
