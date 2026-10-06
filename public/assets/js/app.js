// Main application JavaScript

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Bulma components
    initBulmaComponents();
    
    // Initialize custom functionality
    initCustomFunctionality();
});

function initBulmaComponents() {
    // Navbar burger menu for mobile
    const navbarBurgers = Array.prototype.slice.call(
        document.querySelectorAll('.navbar-burger'), 0
    );
    
    navbarBurgers.forEach(el => {
        el.addEventListener('click', () => {
            const target = el.dataset.target;
            const $target = document.getElementById(target);
            
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');
        });
    });
    
    // Modal functionality
    const modalButtons = document.querySelectorAll('[data-target]');
    modalButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = button.dataset.target;
            const modal = document.getElementById(target);
            
            if (modal) {
                modal.classList.add('is-active');
            }
        });
    });
    
    // Close modal buttons
    const closeButtons = document.querySelectorAll('.modal-close, .modal-background, .modal-card-head .delete');
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            if (modal) {
                modal.classList.remove('is-active');
            }
        });
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.is-active');
            modals.forEach(modal => {
                modal.classList.remove('is-active');
            });
        }
    });
    
    // Notification close buttons
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
}

function initCustomFunctionality() {
    // Confirm deletion actions
    const deleteLinks = document.querySelectorAll('a[data-confirm]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            if (!confirm(link.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
    
    // Toggle password visibility
    const passwordToggles = document.querySelectorAll('.toggle-password');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.dataset.target;
            const input = document.getElementById(targetId);
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                toggle.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Form submission with loading state
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton) {
                submitButton.classList.add('is-loading');
                submitButton.disabled = true;
            }
        });
    });
    
    // Remove loading state on page load (in case of redirect back)
    const loadingButtons = document.querySelectorAll('.button.is-loading');
    loadingButtons.forEach(button => {
        button.classList.remove('is-loading');
        button.disabled = false;
    });
    
    // Search functionality
    const searchInputs = document.querySelectorAll('input[type="search"]');
    searchInputs.forEach(input => {
        input.addEventListener('input', (e) => {
            const form = input.closest('form');
            if (form) {
                // Submit form on search input (with debounce)
                clearTimeout(input.searchTimeout);
                input.searchTimeout = setTimeout(() => {
                    form.submit();
                }, 500);
            }
        });
    });
    
    // Table sorting
    const sortableTables = document.querySelectorAll('table.sortable');
    sortableTables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.sort;
                const order = header.dataset.order || 'asc';
                
                // Toggle order
                header.dataset.order = order === 'asc' ? 'desc' : 'asc';
                
                // Remove sort indicators from other headers
                headers.forEach(h => {
                    if (h !== header) {
                        h.classList.remove('is-sorted', 'is-sorted-asc', 'is-sorted-desc');
                        delete h.dataset.order;
                    }
                });
                
                // Add sort indicator to current header
                header.classList.add('is-sorted', `is-sorted-${order}`);
                
                // Here you would typically submit a request to sort the data
                // For demo purposes, we'll just log it
                console.log(`Sorting by ${column} ${order}`);
            });
        });
    });
}

// Utility functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification is-${type} is-light`;
    notification.innerHTML = `
        <button class="delete"></button>
        ${message}
    `;
    
    // Add delete button functionality
    const deleteButton = notification.querySelector('.delete');
    deleteButton.addEventListener('click', () => {
        notification.remove();
    });
    
    // Add to notifications container or body
    const notificationsContainer = document.querySelector('.notifications');
    if (notificationsContainer) {
        notificationsContainer.appendChild(notification);
    } else {
        document.body.appendChild(notification);
    }
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Export functions for use in inline scripts
window.App = {
    showNotification
};
