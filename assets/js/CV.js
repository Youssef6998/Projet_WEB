document.addEventListener('DOMContentLoaded', function () {
    const cvInputs = document.querySelectorAll('input[type="file"][name*="cv"], input[name="cv"]');

    cvInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            const file = this.files[0];

            const old = input.parentNode.querySelector('.cv-info');
            if (old) old.remove();

            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                showMessage(this, '❌ Trop lourd ! Max 2 Mo — ' + (file.size / 1024 / 1024).toFixed(1) + ' Mo détectés', 'error');
                this.value = '';
                return;
            }

            const formats = ['.pdf', '.doc', '.docx', '.odt', '.rtf', '.jpg', '.jpeg', '.png'];
            const ext = file.name.toLowerCase().slice(file.name.lastIndexOf('.'));
            if (!formats.includes(ext)) {
                showMessage(this, '❌ Format ' + ext.toUpperCase() + ' non autorisé — formats acceptés : ' + formats.slice(0, 4).join(', ') + '...', 'error');
                this.value = '';
                return;
            }

            showMessage(this, '✅ ' + file.name + ' — ' + (file.size / 1024).toFixed(0) + ' Ko • ' + ext.toUpperCase(), 'success');
        });
    });

    function showMessage(input, text, type) {
        const msg = document.createElement('div');
        msg.className = 'cv-info cv-' + type;
        msg.textContent = text;
        input.parentNode.appendChild(msg);
    }
});
