<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sistema para Regentes - Colegio Marista</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <style>
        body {
            background-color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .splash-container {
            text-align: center;
            animation: fadeIn 1s ease-in-out;
        }
        
        .splash-container .logo {
            max-width: 400px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        .splash-container .title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #133b64;
            margin-bottom: 20px;
        }
        
        .splash-container .subtitle {
            font-size: 1.2rem;
            color: #6a8cc0;
            margin-bottom: 40px;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 5px solid #f39200;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .fade-out {
            animation: fadeOut 1.5s ease-in-out forwards;
        }
    </style>
</head>
<body>
    <div class="splash-container" id="splash">
        <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" 
             alt="Colegio Marista Logo" 
             class="logo">
        
        <h1 class="title">SISTEMA PARA REGENTES</h1>
        
        <p class="subtitle">Colegio Marista</p>
        
        <div class="loading-spinner"></div>
    </div>
    
    <script>
        // Wait 2 seconds, then fade out and redirect to login
        setTimeout(() => {
            const splash = document.getElementById('splash');
            splash.classList.add('fade-out');
            
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
        }, 2000);
    </script>
</body>
</html>
