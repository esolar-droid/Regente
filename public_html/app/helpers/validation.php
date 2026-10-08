<?php
/**
 * Validation Helpers
 */

if (!function_exists('validateRequired')) {
    function validateRequired($value, $fieldName = 'Field') {
        if (empty(trim($value))) {
            return "$fieldName es requerido.";
        }
        return null;
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($value, $fieldName = 'Email') {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "$fieldName no es válido.";
        }
        return null;
    }
}

if (!function_exists('validateMinLength')) {
    function validateMinLength($value, $min, $fieldName = 'Field') {
        if (strlen(trim($value)) < $min) {
            return "$fieldName debe tener al menos $min caracteres.";
        }
        return null;
    }
}

if (!function_exists('validateMaxLength')) {
    function validateMaxLength($value, $max, $fieldName = 'Field') {
        if (strlen(trim($value)) > $max) {
            return "$fieldName debe tener máximo $max caracteres.";
        }
        return null;
    }
}

if (!function_exists('validateNumeric')) {
    function validateNumeric($value, $fieldName = 'Field') {
        if (!empty($value) && !is_numeric($value)) {
            return "$fieldName debe ser numérico.";
        }
        return null;
    }
}

if (!function_exists('validatePhone')) {
    function validatePhone($value, $fieldName = 'Teléfono') {
        if (!empty($value)) {
            $cleaned = preg_replace('/[^0-9]/', '', $value);
            if (strlen($cleaned) < 7 || strlen($cleaned) > 15) {
                return "$fieldName no es válido.";
            }
        }
        return null;
    }
}

if (!function_exists('validateDate')) {
    function validateDate($value, $fieldName = 'Fecha') {
        if (!empty($value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                return "$fieldName no es válida (formato: YYYY-MM-DD).";
            }
        }
        return null;
    }
}

if (!function_exists('validateEnum')) {
    function validateEnum($value, array $allowedValues, $fieldName = 'Field') {
        if (!empty($value) && !in_array($value, $allowedValues)) {
            return "$fieldName no es válido.";
        }
        return null;
    }
}

if (!function_exists('validate')) {
    function validate(array $data, array $rules) {
        $errors = [];
        
        foreach ($rules as $field => $ruleList) {
            $fieldRules = explode('|', $ruleList);
            $fieldValue = $data[$field] ?? '';
            
            foreach ($fieldRules as $rule) {
                $parts = explode(':', $rule);
                $ruleName = $parts[0];
                $ruleParam = $parts[1] ?? null;
                
                switch ($ruleName) {
                    case 'required':
                        $error = validateRequired($fieldValue, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'email':
                        $error = validateEmail($fieldValue, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'min':
                        $error = validateMinLength($fieldValue, (int)$ruleParam, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'max':
                        $error = validateMaxLength($fieldValue, (int)$ruleParam, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'numeric':
                        $error = validateNumeric($fieldValue, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'phone':
                        $error = validatePhone($fieldValue, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'date':
                        $error = validateDate($fieldValue, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', $ruleParam);
                        $error = validateEnum($fieldValue, $allowed, ucfirst($field));
                        if ($error) {
                            $errors[$field] = $error;
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
}
