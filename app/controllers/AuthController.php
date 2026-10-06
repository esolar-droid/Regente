<?php
namespace App\Controllers;

use App\Models\User;
use App\Middleware\RateLimitMiddleware;

class AuthController extends Controller {
    
    public function splash() {
        // Check if user is already logged in
        if (isset($_SESSION['user'])) {
            $this->redirect('/dashboard');
        }
        
        // Show splash screen
        $this->view('auth/splash');
    }
    
    public function login() {
        // Check if user is already logged in
        if (isset($_SESSION['user'])) {
            $this->redirect('/dashboard');
        }
        
        $this->view('auth/login');
    }
    
    public function authenticate() {
        // Check rate limiting
        RateLimitMiddleware::check($_SERVER['REMOTE_ADDR'], 'login');
        
        // Validate CSRF token
        $this->validateCsrfToken();
        
        // Sanitize inputs
        $username = $this->sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate reCAPTCHA if enabled
        if (RECAPTCHA_ENABLED && !empty(RECAPTCHA_SECRET_KEY)) {
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            if (!$this->verifyRecaptcha($recaptchaResponse)) {
                $_SESSION['error'] = 'Error: CAPTCHA no válido.';
                $this->redirect('/login');
            }
        }
        
        // Find user
        $user = User::findByUsername($username);
        if (!$user || !$user->verifyPassword($password)) {
            // Log failed attempt
            $this->logFailedLogin($username, $_SERVER['REMOTE_ADDR']);
            $_SESSION['error'] = 'Usuario o contraseña incorrectos.';
            $this->redirect('/login');
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Set user session
        $_SESSION['user'] = $user;
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['logged_in_at'] = time();
        
        // Redirect to dashboard or previous page
        $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard';
        unset($_SESSION['redirect_after_login']);
        $this->redirect($redirect);
    }
    
    public function logout() {
        // Clear session
        $_SESSION = [];
        session_destroy();
        
        // Redirect to login
        $this->redirect('/login');
    }
    
    private function verifyRecaptcha($response) {
        if (empty($response)) {
            return false;
        }
        
        $url = "https://www.google.com/recaptcha/api/siteverify";
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $json = json_decode($result, true);
        
        return ($json['success'] ?? false) && ($json['score'] ?? 0) >= 0.5;
    }
    
    private function logFailedLogin($username, $ip) {
        $logMessage = date('Y-m-d H:i:s') . " - Failed login attempt for user: {$username} from IP: {$ip}\n";
        file_put_contents(APP_ROOT . '/app/logs/security.log', $logMessage, FILE_APPEND);
    }
}
