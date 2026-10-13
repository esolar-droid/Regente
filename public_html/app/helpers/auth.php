<?php
/**
 * Authentication Helpers
 */

if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }
}

if (!function_exists('user')) {
    /**
     * Devuelve el usuario logueado en un formato compatible con acceso tipo
     * array ($u['usuario']) Y tipo objeto ($u->usuario) simultaneamente.
     * v7: corrige "Cannot use object of type App\Models\User as array".
     */
    function user($asObject = false) {
        if (!isAuthenticated()) {
            return null;
        }

        static $obj = null;
        if ($obj === null && isset($_SESSION['user_id'])) {
            $obj = \App\Models\User::find($_SESSION['user_id']) ?: false;
        }
        if (!$obj) return null;
        if ($asObject) return $obj; // objeto App\Models\User (tiene hasPermission(), etc.)
        $data = method_exists($obj, 'toArray') ? $obj->toArray() : (array)$obj;
        // Objeto anonimo compatible con [] y -> al mismo tiempo
        return new class($data) implements \ArrayAccess {
            private $d;
            public function __construct(array $d) { $this->d = $d; }
            public function offsetExists($k): bool { return isset($this->d[$k]); }
            public function offsetGet($k): mixed { return $this->d[$k] ?? null; }
            public function offsetSet($k, $v): void { $this->d[$k] = $v; }
            public function offsetUnset($k): void { unset($this->d[$k]); }
            public function __get($k) { return $this->d[$k] ?? null; }
            public function toArray(): array { return $this->d; }
        };
    }
}

if (!function_exists('userId')) {
    function userId() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('userRole')) {
    function userRole() {
        return $_SESSION['user_role'] ?? null;
    }
}

if (!function_exists('userRoleId')) {
    function userRoleId() {
        return $_SESSION['user_role_id'] ?? null;
    }
}

if (!function_exists('login')) {
    function login($user) {
        // Store user data in session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_nombre'] = $user->nombre;
        $_SESSION['user_apellido'] = $user->apellido;
        $_SESSION['user_username'] = $user->usuario;
        $_SESSION['user_role'] = $user->rol;
        $_SESSION['user_role_id'] = $user->role_id;
        $_SESSION['user_estado'] = $user->estado;
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_activity'] = time();
        
        // Clear rate limit on successful login
        \App\Middleware\RateLimitMiddleware::clearOnSuccess();
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        return true;
    }
}

if (!function_exists('logout')) {
    function logout() {
        // Clear session data
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        return true;
    }
}
