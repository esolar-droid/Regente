<?php
namespace App\Controllers;

abstract class Controller {
    protected function view($viewPath, $data = []) {
        extract($data);
        
        $viewFile = APP_ROOT . '/app/views/' . $viewPath . '.php';
        
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            http_response_code(404);
            require_once APP_ROOT . '/app/views/errors/404.php';
        }
    }
    
    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }
    
    protected function requireAuth() {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            $this->redirect('/login');
        }
    }
    
    protected function requirePermission($permission) {
        $this->requireAuth();
        
        if (!isset($_SESSION['user']) || !$_SESSION['user']->hasPermission($permission)) {
            $this->redirect('/error/403');
        }
    }
    
    protected function generateCsrfToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    protected function validateCsrfToken() {
        if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== ($_SESSION[CSRF_TOKEN_NAME] ?? '')) {
            die("Error: Token CSRF no válido.");
        }
        return true;
    }
    
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}
