<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>404 - Página No Encontrada</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <style>
        body {
            background-color: #133b64;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .error-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-container .icon {
            font-size: 4rem;
            color: #f39200;
            margin-bottom: 20px;
        }
        
        .error-container .title {
            color: #133b64;
            margin-bottom: 20px;
        }
        
        .error-container .message {
            color: #666;
            margin-bottom: 30px;
        }
        
        .error-container .button {
            background-color: #133b64;
            color: white;
        }
        
        .error-container .button:hover {
            background-color: #0f2d4c;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1 class="title is-3">Página No Encontrada</h1>
        
        <p class="message">
            La página que estás buscando no existe o ha sido movida.
        </p>
        
        <a href="/dashboard" class="button">
            <span>Volver al Inicio</span>
        </a>
    </div>
</body>
</html>
