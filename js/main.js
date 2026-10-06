// Script para la animación de la página de inicio

document.addEventListener('DOMContentLoaded', () => {
    const splashContent = document.querySelector('.splash-content');

    // Esperar 2 segundos antes de iniciar la animación
    setTimeout(() => {
        splashContent.classList.add('fade-out');

        // Redirigir a la página de login después de que la animación termine
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500); // 1.5 segundos para la animación
    }, 2000); // 2 segundos de espera inicial
});
