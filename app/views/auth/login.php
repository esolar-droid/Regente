<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema para Regentes</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/login.css">
    
    <!-- reCAPTCHA -->
    <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header has-text-centered">
                <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" 
                     alt="Logo Colegio Marista" 
                     class="login-logo">
                <h2 class="login-title">SISTEMA PARA REGENTES</h2>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="notification is-danger is-light">
                    <?= sanitize($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form id="login-form" method="POST" action="/login" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= \App\Middleware\CsrfMiddleware::generateToken() ?>">
                
                <div class="field">
                    <div class="control has-icons-left">
                        <input class="input is-large" 
                               type="text" 
                               name="username" 
                               placeholder="Usuario" 
                               required 
                               autofocus>
                        <span class="icon is-left">
                            <i class="fas fa-user"></i>
                        </span>
                    </div>
                </div>
                
                <div class="field">
                    <div class="control has-icons-left">
                        <input class="input is-large" 
                               type="password" 
                               name="password" 
                               placeholder="Contraseña" 
                               required>
                        <span class="icon is-left">
                            <i class="fas fa-lock"></i>
                        </span>
                    </div>
                </div>
                
                <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
                    <div class="field">
                        <div class="control">
                            <div class="g-recaptcha" 
                                 data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"
                                 data-callback="onRecaptchaSuccess"
                                 data-action="login"></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="field">
                    <div class="control">
                        <button type="submit" 
                                class="button is-large is-fullwidth login-button">
                            <span class="icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </span>
                            <span>INICIAR SESIÓN</span>
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="has-text-centered mt-4">
                <a href="/forgot-password" class="has-text-grey">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
        </div>
    </div>
    
    <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
        <script>
            function onRecaptchaSuccess() {
                document.getElementById('login-form').submit();
            }
        </script>
    <?php endif; ?>
</body>
</html>
