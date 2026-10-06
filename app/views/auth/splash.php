<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema para Regentes - Colegio Marista</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* Inline styles for splash screen */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #fef2e3;
            height: 100vh;
            overflow: hidden;
        }
        
        .splash-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #fef2e3;
        }
        
        .splash-content {
            text-align: center;
            opacity: 1;
            transition: opacity 1.5s ease-out, transform 1.5s ease-out;
            padding: 0 1rem;
        }
        
        .logo {
            width: 90%;
            max-width: 500px;
            height: auto;
            margin-bottom: 1.5rem;
        }
        
        .title {
            color: #133b64;
            font-size: clamp(1.5rem, 5vw, 2.5rem);
            font-weight: bold;
            letter-spacing: 0.05em;
        }
        
        .splash-content.fade-out {
            opacity: 0;
            transform: translateY(-20px);
        }
        
        @media (max-width: 768px) {
            .logo {
                width: 95%;
                max-width: 350px;
            }
            
            .title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="splash-container">
        <div class="splash-content" id="splashContent">
            <img src="https://colmarista.com/wp-content/uploads/2026/10/logohorizontal.png" 
                 alt="Logo Colegio Marista" 
                 class="logo">
            <h1 class="title">SISTEMA PARA REGENTES</h1>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const splashContent = document.getElementById('splashContent');
            
            // Esperar 2 segundos antes de iniciar la animación
            setTimeout(() => {
                splashContent.classList.add('fade-out');
                
                // Redirigir a la página de login después de que la animación termine
                setTimeout(() => {
                    window.location.href = '/login';
                }, 1500); // 1.5 segundos para la animación
            }, 2000); // 2 segundos de espera inicial
        });
    </script>
</body>
</html>
