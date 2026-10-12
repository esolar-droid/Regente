/**
 * Custom JavaScript for Sistema para Regentes
 */

// Document ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize all components
    initNavbar();
    initNotifications();
    initModals();
    initTooltips();
    initFormValidation();
    initTableSorting();
});

/**
 * Initialize navbar
 */
function initNavbar() {
    const navbarBurgers = Array.prototype.slice.call(
        document.querySelectorAll('.navbar-burger'), 0
    );
    
    if (navbarBurgers.length > 0) {
        navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const targetElement = document.getElementById(target);
                
                el.classList.toggle('is-active');
                targetElement.classList.toggle('is-active');
            });
        });
    }
}

/**
 * Initialize notifications
 */
function initNotifications() {
    // Close notifications
    document.querySelectorAll('.notification .delete').forEach(deleteBtn => {
        deleteBtn.addEventListener('click', () => {
            deleteBtn.parentNode.style.display = 'none';
        });
    });
    
    // Auto-close notifications after 5 seconds
    document.querySelectorAll('.notification').forEach(notification => {
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    });
}

/**
 * Initialize modals
 */
function initModals() {
    // Open modals
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modalId = trigger.dataset.modal;
            const modal = document.getElementById(modalId);
            
            if (modal) {
                modal.classList.add('is-active');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    // Close modals
    document.querySelectorAll('.modal .delete, .modal .modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            const modal = closeBtn.closest('.modal');
            modal.classList.remove('is-active');
            document.body.style.overflow = '';
        });
    });
    
    // Close modal on background click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('is-active');
                document.body.style.overflow = '';
            }
        });
    });
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', () => {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = el.dataset.tooltip;
            tooltip.style.position = 'absolute';
            tooltip.style.backgroundColor = '#333';
            tooltip.style.color = '#fff';
            tooltip.style.padding = '5px 10px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '12px';
            tooltip.style.zIndex = '1000';
            tooltip.style.whiteSpace = 'nowrap';
            
            const rect = el.getBoundingClientRect();
            tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 5) + 'px';
            tooltip.style.left = (rect.left + window.scrollX + (rect.width - tooltip.offsetWidth) / 2) + 'px';
            
            document.body.appendChild(tooltip);
            
            el.addEventListener('mouseleave', () => {
                document.body.removeChild(tooltip);
            }, { once: true });
        });
    });
}

/**
 * Initialize form validation
 */
function initFormValidation() {
    // Add required field validation
    document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
        field.addEventListener('blur', () => {
            if (!field.value.trim()) {
                field.classList.add('is-danger');
            } else {
                field.classList.remove('is-danger');
            }
        });
    });
    
    // Form submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            let hasError = false;
            
            form.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-danger');
                    hasError = true;
                } else {
                    field.classList.remove('is-danger');
                }
            });
            
            if (hasError) {
                e.preventDefault();
                showErrorNotification('Por favor, complete todos los campos requeridos.');
            }
        });
    });
}

/**
 * Initialize table sorting
 */
function initTableSorting() {
    document.querySelectorAll('table.is-sortable').forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.sort;
                const direction = header.dataset.direction || 'asc';
                
                sortTable(table, column, direction);
                
                // Update header direction
                headers.forEach(h => {
                    h.dataset.direction = '';
                    h.classList.remove('is-sorted', 'is-sorted-asc', 'is-sorted-desc');
                });
                
                header.dataset.direction = direction === 'asc' ? 'desc' : 'asc';
                header.classList.add('is-sorted', `is-sorted-${direction === 'asc' ? 'desc' : 'asc'}`);
            });
        });
    });
}

/**
 * Sort table by column
 */
function sortTable(table, column, direction) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(table.querySelectorAll('th')).indexOf(
        table.querySelector(`th[data-sort="${column}"]`)
    );
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Numeric comparison
        if (!isNaN(aValue) && !isNaN(bValue)) {
            return direction === 'asc' ? parseFloat(aValue) - parseFloat(bValue) : parseFloat(bValue) - parseFloat(aValue);
        }
        
        // Date comparison
        if (isDateString(aValue) && isDateString(bValue)) {
            const aDate = new Date(aValue);
            const bDate = new Date(bValue);
            return direction === 'asc' ? aDate - bDate : bDate - aDate;
        }
        
        // String comparison
        return direction === 'asc' 
            ? aValue.localeCompare(bValue) 
            : bValue.localeCompare(aValue);
    });
    
    // Re-append sorted rows
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Check if string is a date
 */
function isDateString(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(value) || /^\d{2}\/\d{2}\/\d{4}$/.test(value);
}

/**
 * Show error notification
 */
function showErrorNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'notification is-danger is-light';
    notification.innerHTML = `
        <button class="delete"></button>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    // Close button
    notification.querySelector('.delete').addEventListener('click', () => {
        notification.remove();
    });
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Show success notification
 */
function showSuccessNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'notification is-success is-light';
    notification.innerHTML = `
        <button class="delete"></button>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    // Close button
    notification.querySelector('.delete').addEventListener('click', () => {
        notification.remove();
    });
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Show loading overlay
 */
function showLoading() {
    let overlay = document.querySelector('.loading-overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = '<div class="loading-spinner"></div>';
        document.body.appendChild(overlay);
    }
    
    overlay.classList.add('is-active');
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) {
        overlay.classList.remove('is-active');
    }
}

/**
 * Confirm action
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

/**
 * Format date with time for display
 */
function formatDateTime(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB'
    }).format(amount);
}

/**
 * Truncate text
 */
function truncateText(text, length) {
    if (text.length <= length) return text;
    return text.substring(0, length) + '...';
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showSuccessNotification('Copiado al portapapeles');
    }).catch(err => {
        showErrorNotification('Error al copiar al portapapeles');
    });
}

// Export for use in other scripts
window.showErrorNotification = showErrorNotification;
window.showSuccessNotification = showSuccessNotification;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.confirmAction = confirmAction;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.formatCurrency = formatCurrency;
window.truncateText = truncateText;
window.copyToClipboard = copyToClipboard;

// ==========================================================================
// Sidebar toggle
// ==========================================================================
(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('sidebarToggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            if (window.innerWidth <= 1023) {
                document.body.classList.toggle('sidebar-open');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        });

        // Cerrar el sidebar móvil al hacer clic fuera de él
        document.addEventListener('click', (e) => {
            const sidebar = document.getElementById('appSidebar');
            if (!sidebar) return;
            if (window.innerWidth <= 1023 &&
                document.body.classList.contains('sidebar-open') &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)) {
                document.body.classList.remove('sidebar-open');
            }
        });
    });
})();
