/**
 * @file CV.js
 * @description Validation côté client des champs de téléversement de CV.
 *
 * Ce script s'exécute dès que le DOM est entièrement chargé.
 * Il cible tous les champs <input type="file"> dont l'attribut name contient "cv",
 * puis applique deux règles de validation à chaque sélection de fichier :
 *   1. La taille du fichier ne doit pas dépasser 2 Mo.
 *   2. L'extension du fichier doit faire partie d'une liste de formats autorisés.
 *
 * En cas d'erreur ou de succès, un message est affiché directement sous le champ
 * concerné (aucune alerte navigateur, aucun rechargement).
 *
 * Aucune requête réseau n'est effectuée par ce fichier — tout est local.
 */

document.addEventListener('DOMContentLoaded', function () {
    /**
     * Sélection des champs fichier cibles.
     *
     * On cible deux types de sélecteurs pour être robuste face à différentes
     * conventions de nommage dans les formulaires :
     *   - input[type="file"][name*="cv"] : name contenant "cv" (ex. "upload_cv", "cv_file")
     *   - input[name="cv"]               : name exactement égal à "cv"
     *
     * @type {NodeList}
     */
    const cvInputs = document.querySelectorAll('input[type="file"][name*="cv"], input[name="cv"]');

    /**
     * On attache un listener 'change' à chaque champ trouvé.
     * L'événement 'change' se déclenche dès que l'utilisateur sélectionne
     * (ou annule la sélection de) un fichier via le sélecteur natif du navigateur.
     */
    cvInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            /**
             * this.files[0] : premier fichier sélectionné (objet File).
             * this.files est un FileList ; on ne gère ici que la sélection simple.
             *
             * @type {File|undefined}
             */
            const file = this.files[0];

            /**
             * Nettoyage du message précédent.
             * On cherche dans le nœud parent du champ un éventuel élément
             * portant la classe .cv-info (créé lors d'une validation antérieure)
             * et on le supprime pour éviter l'accumulation de messages.
             */
            const old = input.parentNode.querySelector('.cv-info');
            if (old) old.remove();

            /**
             * Si l'utilisateur a annulé la sélection dans le sélecteur de fichier,
             * this.files[0] est undefined — on sort immédiatement sans rien afficher.
             */
            if (!file) return;

            // ── Règle 1 : taille maximale ─────────────────────────────────────
            /**
             * file.size est exprimé en octets.
             * 2 * 1024 * 1024 = 2 097 152 octets = 2 Mo.
             * Si le fichier est trop lourd, on affiche un message d'erreur
             * et on vide le champ (this.value = '') pour obliger l'utilisateur
             * à en choisir un autre ; puis on sort.
             */
            if (file.size > 2 * 1024 * 1024) {
                showMessage(
                    this,
                    '❌ Trop lourd ! Max 2 Mo — ' + (file.size / 1024 / 1024).toFixed(1) + ' Mo détectés',
                    'error'
                );
                this.value = ''; // Vide le champ pour forcer une nouvelle sélection.
                return;
            }

            // ── Règle 2 : extension autorisée ──────────────────────────────────
            /**
             * Liste blanche des extensions acceptées.
             * On inclut les formats bureautiques courants et les images,
             * car certaines structures acceptent un scan de CV au format image.
             *
             * @type {string[]}
             */
            const formats = ['.pdf', '.doc', '.docx', '.odt', '.rtf', '.jpg', '.jpeg', '.png'];

            /**
             * Extraction de l'extension depuis le nom du fichier.
             * - file.name.toLowerCase() : normalisation en minuscules pour la comparaison.
             * - lastIndexOf('.')        : position du dernier point (ex. "cv.tar.gz" → position de ".gz").
             * - slice(...)              : extrait à partir de ce point jusqu'à la fin.
             *
             * @type {string}  ex. ".pdf", ".DOCX" → ".docx"
             */
            const ext = file.name.toLowerCase().slice(file.name.lastIndexOf('.'));

            if (!formats.includes(ext)) {
                /**
                 * Extension non reconnue : on affiche un message d'erreur listant
                 * les 4 premiers formats autorisés (les plus courants), puis on vide le champ.
                 */
                showMessage(
                    this,
                    '❌ Format ' + ext.toUpperCase() + ' non autorisé — formats acceptés : ' + formats.slice(0, 4).join(', ') + '...',
                    'error'
                );
                this.value = '';
                return;
            }

            // ── Succès ─────────────────────────────────────────────────────────
            /**
             * Les deux règles sont passées : on affiche un message de confirmation
             * avec le nom du fichier, sa taille en Ko et son extension.
             *
             * (file.size / 1024).toFixed(0) : taille arrondie à l'entier en Ko.
             */
            showMessage(
                this,
                '✅ ' + file.name + ' — ' + (file.size / 1024).toFixed(0) + ' Ko • ' + ext.toUpperCase(),
                'success'
            );
        });
    });

    /**
     * Crée un élément <div> contenant le message de retour et l'insère
     * à la fin du nœud parent du champ fichier (juste après le champ dans le flux HTML).
     *
     * Les classes CSS appliquées ('cv-info' + 'cv-success' ou 'cv-error')
     * doivent être définies dans le fichier CSS correspondant (assets/css/).
     * Elles contrôlent la couleur, la typographie et l'espacement du message.
     *
     * @param {HTMLInputElement} input - Le champ fichier concerné.
     * @param {string}           text  - Le texte du message à afficher.
     * @param {string}           type  - 'success' ou 'error' (suffixe de classe CSS).
     * @returns {void}
     */
    function showMessage(input, text, type) {
        const msg = document.createElement('div');
        msg.className = 'cv-info cv-' + type; // ex. "cv-info cv-error" ou "cv-info cv-success"
        msg.textContent = text;
        input.parentNode.appendChild(msg);
    }
});
