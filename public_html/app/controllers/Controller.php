<?php
/**
 * Base Controller Class
 * 
 * Provides common functionality for all controllers
 */

namespace App\Controllers;

class Controller {
    /**
     * Render a view
     */
    protected function view($viewPath, $data = []) {
        extract($data);
        
        // Sanitize data for output
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $$key = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        
        $viewFile = APP_ROOT . '/app/views/' . $viewPath . '.php';
        
        if (!file_exists($viewFile)) {
            throw new \Exception("View file not found: $viewFile");
        }
        
        require $viewFile;
    }
    
    /**
     * Redirect to a URL
     */
    protected function redirect($url, $statusCode = 302) {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth() {
        if (!isAuthenticated()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('/login?error=unauthorized');
        }
    }
    
    /**
     * Require specific permission
     */
    protected function requirePermission($permission) {
        if (!can($permission)) {
            $this->redirect('/403');
        }
    }
    
    /**
     * Require any of the specified permissions
     */
    protected function requireAnyPermission(array $permissions) {
        foreach ($permissions as $permission) {
            if (can($permission)) {
                return;
            }
        }
        $this->redirect('/403');
    }
    
    /**
     * Require admin role
     */
    protected function requireAdmin() {
        if (!isAdmin()) {
            $this->redirect('/403');
        }
    }
    
    /**
     * Get POST data and sanitize
     */
    protected function getPostData() {
        return sanitizeArray($_POST);
    }
    
    /**
     * Get GET data and sanitize
     */
    protected function getQueryData() {
        return sanitizeArray($_GET);
    }
    
    /**
     * Get all input data (POST + GET)
     */
    protected function getInputData() {
        return array_merge($this->getQueryData(), $this->getPostData());
    }
    
    /**
     * Validate input and return errors
     */
    protected function validate($data, $rules) {
        return validate($data, $rules);
    }
    
    /**
     * Return JSON response
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Get current user
     */
    protected function user() {
        return user();
    }
    
    /**
     * Get current user ID
     */
    protected function userId() {
        return userId();
    }
}
