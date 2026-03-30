document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Marque erreurs serveur au chargement
        const required = form.querySelectorAll('[required]');
        required.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error-field');
            }
        });
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Reset
            document.querySelectorAll('.error-field, .error-message').forEach(el => {
                el.classList.remove('error-field');
                if (el.classList.contains('error-message')) el.remove();
            });
            
            // Validation
            required.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error-field');
                    const msg = document.createElement('div');
                    msg.className = 'error-message';
                    msg.textContent = 'Requis.';
                    field.parentNode.appendChild(msg);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                document.querySelector('.error-field')?.focus();
                return false;
            }
        });
    });
});