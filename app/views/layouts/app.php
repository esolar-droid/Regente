<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema para Regentes - Colegio Marista">
    <title><?= $title ?? 'Sistema para Regentes' ?> - Colegio Marista</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <!-- reCAPTCHA -->
    <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    
    <!-- CSP Nonce for inline scripts -->
    <meta property="csp-nonce" content="<?= $cspNonce ?? '' ?>">
</head>
<body>
    <!-- Navigation -->
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notification is-danger is-light">
                <button class="delete"></button>
                <?= sanitize($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="notification is-success is-light">
                <button class="delete"></button>
                <?= sanitize($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <!-- Page Content -->
        <?= $content ?? '' ?>
    </main>
    
    <!-- Footer -->
    <?php require_once __DIR__ . '/footer.php'; ?>
    
    <!-- Custom JS -->
    <script src="/assets/js/app.js"></script>
    
    <!-- Close notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const deleteButtons = document.querySelectorAll('.notification .delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', () => {
                    button.parentNode.remove();
                });
            });
            
            // Auto-close notifications after 5 seconds
            setTimeout(() => {
                const notifications = document.querySelectorAll('.notification');
                notifications.forEach(notification => {
                    notification.remove();
                });
            }, 5000);
        });
    </script>
    
    <!-- reCAPTCHA initialization -->
    <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
        <script>
            function onSubmit(token) {
                document.getElementById("login-form").submit();
            }
        </script>
    <?php endif; ?>
</body>
</html>
