<?php
/**
 * Authentication Controller
 * 
 * Handles splash, login, authentication, and logout
 */

namespace App\Controllers;

use App\Models\User;
use App\Middleware\RateLimitMiddleware;

class AuthController extends Controller {
    
    /**
     * Show splash screen
     */
    public function splash() {
        // If already authenticated, redirect to dashboard
        if (isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $this->view('auth/splash');
    }
    
    /**
     * Show login form
     */
    public function login() {
        // If already authenticated, redirect to dashboard
        if (isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        // Generate CSRF token
        $csrfToken = \App\Middleware\CsrfMiddleware::generateToken();
        
        // Get error message if any
        $error = $_GET['error'] ?? null;
        $message = null;
        
        switch ($error) {
            case 'unauthorized':
                $message = 'Debe iniciar sesión para acceder a esta página.';
                break;
            case 'invalid':
                $message = 'Usuario o contraseña incorrectos.';
                break;
            case 'inactive':
                $message = 'Su cuenta está inactiva.';
                break;
            case 'recaptcha':
                $message = 'Verificación reCAPTCHA fallida. Por favor, inténtelo de nuevo.';
                break;
            case 'session_invalid':
                $message = 'Sesión inválida. Por favor, inicie sesión nuevamente.';
                break;
        }
        
        $this->view('auth/login', [
            'csrf_token' => $csrfToken,
            'recaptcha_site_key' => RECAPTCHA_SITE_KEY,
            'error' => $message,
        ]);
    }
    
    /**
     * Authenticate user
     */
    public function authenticate() {
        // Apply rate limiting
        $rateLimit = new RateLimitMiddleware();
        $rateLimit->handle();
        
        // Validate CSRF token
        if (!\App\Middleware\CsrfMiddleware::validateToken()) {
            $this->redirect('/login?error=invalid');
        }
        
        // Get input data
        $input = $this->getPostData();
        $username = $input['usuario'] ?? '';
        $password = $input['contrasena'] ?? '';
        $recaptchaToken = $input['g-recaptcha-response'] ?? '';
        
        // Validate input
        $errors = $this->validate([
            'usuario' => $username,
            'contrasena' => $password,
        ], [
            'usuario' => 'required',
            'contrasena' => 'required',
        ]);
        
        if (!empty($errors)) {
            $this->redirect('/login?error=invalid');
        }
        
        // Verify reCAPTCHA
        if (!verifyRecaptcha($recaptchaToken)) {
            $this->redirect('/login?error=recaptcha');
        }
        
        // Find user by username
        $user = User::findByUsername($username);
        
        if (!$user) {
            $this->redirect('/login?error=invalid');
        }
        
        // Check if user is active
        if ($user->estado !== 'activo') {
            $this->redirect('/login?error=inactive');
        }
        
        // Verify password (temporary: plain text comparison)
        if (!$user->verifyPassword($password)) {
            $this->redirect('/login?error=invalid');
        }
        
        // Login successful
        login($user);
        
        // Log the login
        $this->logAction($user, 'Inicio de sesión exitoso');
        
        // Redirect to dashboard or saved redirect URL
        $redirectUrl = $_SESSION['redirect_after_login'] ?? '/dashboard';
        unset($_SESSION['redirect_after_login']);
        
        $this->redirect($redirectUrl);
    }
    
    /**
     * Logout
     */
    public function logout() {
        $user = user();
        if ($user) {
            $this->logAction($user, 'Cierre de sesión');
        }
        
        logout();
        $this->redirect('/login?message=logged_out');
    }
    
    /**
     * Log user action
     */
    private function logAction($user, $action) {
        try {
            $db = \Database::getConnection();
            $sql = "INSERT INTO logs (usuario, accion) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user->usuario, $action]);
        } catch (\PDOException $e) {
            error_log("Log action error: " . $e->getMessage());
        }
    }
}
