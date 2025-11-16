// Main JavaScript file

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds (but not modal alerts)
    const alerts = document.querySelectorAll('.alert:not(#modalError):not(#modalSuccess)');
    alerts.forEach(alert => {
        // Check if alert is inside modal
        if (alert.closest('#smsModal')) {
            return; // Skip modal alerts
        }
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert && alert.parentNode && !alert.closest('#smsModal')) {
                    alert.remove();
                }
            }, 500);
        }, 5000);
    });

    // Confirm delete actions
    const deleteLinks = document.querySelectorAll('a[href*="delete"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Haqiqatan ham o\'chirmoqchimisiz?')) {
                e.preventDefault();
            }
        });
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('998')) {
                value = value.substring(3);
            }
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            e.target.value = value;
        });
    });
});

