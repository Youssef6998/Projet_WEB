/**
 * CV.js
 *
 * Validation côté client des champs de téléversement de CV.
 * Vérifie la taille (max 2 Mo) et l'extension du fichier sélectionné,
 * puis affiche un message inline de succès ou d'erreur sous le champ.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Cible tous les champs fichier dont le name contient "cv".
    const cvInputs = document.querySelectorAll('input[type="file"][name*="cv"], input[name="cv"]');

    cvInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            const file = this.files[0];

            // Supprime le message précédent avant d'en créer un nouveau.
            const old = input.parentNode.querySelector('.cv-info');
            if (old) old.remove();

            if (!file) return; // Aucun fichier sélectionné (annulation du sélecteur).

            // Vérifie que le fichier ne dépasse pas 2 Mo.
            if (file.size > 2 * 1024 * 1024) {
                showMessage(this, '❌ Trop lourd ! Max 2 Mo — ' + (file.size / 1024 / 1024).toFixed(1) + ' Mo détectés', 'error');
                this.value = ''; // Vide le champ pour forcer une nouvelle sélection.
                return;
            }

            // Vérifie que l'extension fait partie des formats autorisés.
            const formats = ['.pdf', '.doc', '.docx', '.odt', '.rtf', '.jpg', '.jpeg', '.png'];
            const ext = file.name.toLowerCase().slice(file.name.lastIndexOf('.'));
            if (!formats.includes(ext)) {
                showMessage(this, '❌ Format ' + ext.toUpperCase() + ' non autorisé — formats acceptés : ' + formats.slice(0, 4).join(', ') + '...', 'error');
                this.value = '';
                return;
            }

            // Fichier valide : affiche le nom, la taille et l'extension.
            showMessage(this, '✅ ' + file.name + ' — ' + (file.size / 1024).toFixed(0) + ' Ko • ' + ext.toUpperCase(), 'success');
        });
    });

    /**
     * Crée et insère un message inline sous le champ fichier.
     *
     * @param {HTMLInputElement} input - Le champ fichier concerné.
     * @param {string}           text  - Le texte du message à afficher.
     * @param {string}           type  - 'success' ou 'error' (appliqué comme classe CSS).
     */
    function showMessage(input, text, type) {
        const msg = document.createElement('div');
        msg.className = 'cv-info cv-' + type;
        msg.textContent = text;
        input.parentNode.appendChild(msg);
    }
});
