<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Inicio de Sesión - Sistema para Regentes</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY ?>"></script>
    
    <style>
        body {
            background-color: #133b64;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-box {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .login-box .logo {
            max-width: 300px;
            margin: 0 auto 30px;
            display: block;
        }
        
        .login-box .title {
            text-align: center;
            color: #133b64;
            margin-bottom: 30px;
        }
        
        .login-box .field {
            margin-bottom: 20px;
        }
        
        .login-box .control {
            position: relative;
        }
        
        .login-box .icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6a8cc0;
            pointer-events: none;
        }
        
        .login-box .input {
            padding-left: 40px;
            border-radius: 5px;
            border: 1px solid #dddee6;
        }
        
        .login-box .input:focus {
            border-color: #f39200;
            box-shadow: 0 0 0 0.125em rgba(243, 146, 0, 0.25);
        }
        
        .login-box .button {
            width: 100%;
            background-color: #133b64;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        
        .login-box .button:hover {
            background-color: #0f2d4c;
        }
        
        .login-box .button.is-loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .login-box .help {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-box .help a {
            color: #133b64;
        }
        
        .login-box .notification {
            margin-bottom: 20px;
        }
        
        .login-box .recaptcha-info {
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            margin-top: 15px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .login-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" 
             alt="Colegio Marista Logo" 
             class="logo">
        
        <h1 class="title is-4">SISTEMA PARA REGENTES</h1>
        
        <?php if (!empty($error)): ?>
            <div class="notification is-danger is-light">
                <button class="delete"></button>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($_GET['message']) && $_GET['message'] === 'logged_out'): ?>
            <div class="notification is-success is-light">
                <button class="delete"></button>
                Sesión cerrada correctamente.
            </div>
        <?php endif; ?>
        
        <form id="loginForm" action="/login" method="POST">
            <?= \App\Middleware\CsrfMiddleware::getTokenInput() ?>
            
            <div class="field">
                <div class="control has-icons-left">
                    <span class="icon">
                        <i class="fas fa-user"></i>
                    </span>
                    <input class="input" 
                           type="text" 
                           name="usuario" 
                           placeholder="Usuario" 
                           autocomplete="username" 
                           required>
                </div>
            </div>
            
            <div class="field">
                <div class="control has-icons-left">
                    <span class="icon">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input class="input" 
                           type="password" 
                           name="contrasena" 
                           placeholder="Contraseña" 
                           autocomplete="current-password" 
                           required>
                </div>
            </div>
            
            <!-- reCAPTCHA v3 - Invisible -->
            <input type="hidden" 
                   id="g-recaptcha-response" 
                   name="g-recaptcha-response" 
                   value="">
            
            <div class="recaptcha-info">
                <i class="fas fa-shield-alt"></i> Protegido por reCAPTCHA
            </div>
            
            <div class="field">
                <div class="control">
                    <button type="submit" 
                            class="button is-fullwidth" 
                            id="loginButton">
                        <span>INICIAR SESIÓN</span>
                        <span class="icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        // Execute reCAPTCHA on form submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const button = document.getElementById('loginButton');
            button.classList.add('is-loading');
            button.disabled = true;
            
            // Execute reCAPTCHA v3
            grecaptcha.ready(function() {
                grecaptcha.execute('<?= RECAPTCHA_SITE_KEY ?>', {action: 'login'}).then(function(token) {
                    document.getElementById('g-recaptcha-response').value = token;
                    e.target.submit();
                }).catch(function(error) {
                    button.classList.remove('is-loading');
                    button.disabled = false;
                    console.error('reCAPTCHA error:', error);
                });
            });
        });
        
        // Close notifications
        document.querySelectorAll('.notification .delete').forEach(function(deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                this.parentNode.style.display = 'none';
            });
        });
    </script>
</body>
</html>
