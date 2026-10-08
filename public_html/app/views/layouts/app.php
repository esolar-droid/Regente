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
    
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY ?>"></script>
    
    <?php if (isset($csrf_token)): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <?php endif; ?>
</head>
<body>
    <?php require_once 'header.php'; ?>
    
    <main class="main-content">
        <?= $content ?? '' ?>
    </main>
    
    <?php require_once 'footer.php'; ?>
    
    <!-- Custom JS -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
