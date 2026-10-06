<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página No Encontrada - Sistema para Regentes</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f5f5f5;
        }
        
        .error-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 1rem;
        }
        
        .error-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #133b64;
            margin-bottom: 1rem;
        }
        
        .error-title {
            color: #363636;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .error-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        
        .error-actions .button {
            min-width: 120px;
        }
        
        @media (max-width: 768px) {
            .error-box {
                padding: 2rem 1rem;
            }
            
            .error-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-box">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="error-title">Página No Encontrada</h1>
            <p class="error-message">
                La página que estás buscando no existe o ha sido movida.
            </p>
            <div class="error-actions">
                <a href="/dashboard" class="button is-primary">
                    <span class="icon">
                        <i class="fas fa-home"></i>
                    </span>
                    <span>Ir al Inicio</span>
                </a>
                <a href="/login" class="button is-light">
                    <span class="icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </span>
                    <span>Iniciar Sesión</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
