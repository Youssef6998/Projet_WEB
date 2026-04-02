/**
 * @file favoris.js
 * @description Gestion complète des interactions utilisateur liées aux favoris (wishlist).
 *
 * Ce module encapsule plusieurs comportements distincts mais liés :
 *
 *  1. Toast animé "Retiré des favoris" (showRemoveToast)
 *     Crée (ou réutilise) un élément toast, l'anime en entrée/sortie,
 *     puis appelle un callback une fois l'animation terminée.
 *
 *  2. Animation du bouton "Ajouter aux favoris" (animateAdd)
 *     Change temporairement le texte du bouton en "♥ Ajouté !" et applique
 *     la classe CSS 'adding' (effet visuel), puis restaure l'état original.
 *
 *  3. Bouton .btn-favori (page détail d'une offre)
 *     Intercepte la soumission du formulaire pour jouer l'animation adéquate
 *     (ajout ou retrait) AVANT de soumettre.
 *
 *  4. Bouton cœur AJAX .btn-heart (liste des stages)
 *     Bascule l'état favori sans rechargement de page via fetch POST.
 *     Lit le token CSRF depuis la meta tag <meta name="csrf-token">.
 *
 *  5. Bouton .btn-retirer (page profil — liste des favoris)
 *     Anime la carte avant soumission du formulaire de suppression.
 *
 * Tout le module est encapsulé dans une IIFE pour éviter la pollution du scope global.
 */

(function () {

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Toast "Retiré des favoris"
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Affiche un toast de notification "Retiré des favoris" en bas de l'écran.
     *
     * Le toast est créé lors du premier appel et réutilisé ensuite (singleton DOM).
     * L'animation d'apparition utilise deux requestAnimationFrame imbriqués :
     * le premier attend que le navigateur ait peint l'élément (display:block),
     * le second applique la classe 'visible' pour déclencher la transition CSS opacity/transform.
     *
     * Séquence temporelle :
     *   t=0ms    : toast inséré dans le DOM (invisible).
     *   t~2 frames : classe 'visible' ajoutée → transition CSS d'entrée.
     *   t=1000ms : classe 'visible' retirée → transition CSS de sortie.
     *   t=1300ms : callback `onDone` appelé (la transition de sortie dure ~300ms).
     *
     * @param {function(): void} onDone - Callback exécuté après la disparition complète du toast.
     *                                    Typiquement : soumettre le formulaire de suppression.
     * @returns {void}
     */
    function showRemoveToast(onDone) {
        /**
         * Récupération ou création du toast.
         * On cherche d'abord dans le DOM pour éviter d'en créer plusieurs.
         *
         * @type {HTMLElement}
         */
        let toast = document.getElementById('toast-favoris');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-favoris';
            toast.className = 'toast-favoris'; // Classe CSS : position fixe, style visuel.
            toast.innerHTML =
                '<span class="toast-favoris-icon">🗑️</span>' +
                '<span class="toast-favoris-text">' +
                    '<span class="toast-favoris-title">Retiré des favoris</span>' +
                    '<span class="toast-favoris-sub">L\'offre a été supprimée de votre liste</span>' +
                '</span>';
            document.body.appendChild(toast); // Inséré en fin de body pour le z-index maximum.
        }

        /**
         * Double requestAnimationFrame pour garantir que le navigateur a bien
         * rendu l'élément (au moins une frame de "display: block") avant d'appliquer
         * la transition CSS — sinon la transition est ignorée car l'élément
         * passe de "inexistant" à "visible" en une seule frame.
         */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('visible'); // Déclenche la transition CSS d'apparition.
            });
        });

        /**
         * Après 1000ms d'affichage, on retire la classe 'visible' pour lancer
         * la transition de disparition (durée ~300ms côté CSS).
         * Une fois cette transition terminée (~300ms plus tard), on appelle onDone.
         */
        setTimeout(function () {
            toast.classList.remove('visible'); // Déclenche la transition CSS de disparition.
            setTimeout(onDone, 300);           // onDone est appelé après la fin de la transition.
        }, 1000);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Animation du bouton "Ajouter aux favoris"
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Anime le bouton d'ajout aux favoris pour donner un retour visuel immédiat.
     *
     * Séquence :
     *   1. Le texte du bouton est remplacé par "♥ Ajouté !" et la classe 'adding' est ajoutée
     *      (le CSS peut y associer un changement de couleur, un scale, etc.).
     *   2. Après 430ms (durée de l'animation CSS), la classe est retirée,
     *      le texte original est restauré, et le callback onDone est appelé.
     *
     * Le délai de 430ms est choisi pour correspondre à la durée de la transition CSS
     * définie dans le fichier CSS associé (class .adding).
     *
     * @param {HTMLButtonElement}  btn    - Le bouton "Ajouter aux favoris" à animer.
     * @param {function(): void}   onDone - Callback appelé après la fin de l'animation.
     *                                      Typiquement : soumettre le formulaire d'ajout.
     * @returns {void}
     */
    function animateAdd(btn, onDone) {
        var original = btn.textContent; // Sauvegarde du texte original pour le restaurer après.
        btn.textContent = '♥ Ajouté !';
        btn.classList.add('adding'); // Classe CSS déclenchant l'animation visuelle (définie en CSS).

        setTimeout(function () {
            btn.classList.remove('adding'); // Fin de l'animation CSS.
            btn.textContent = original;     // Restauration du texte d'origine.
            onDone();                       // Soumission du formulaire différée.
        }, 430); // 430ms = durée de la transition CSS de la classe .adding.
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. Interception du formulaire .btn-favori (page détail d'une offre)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Pour chaque bouton .btn-favori sur la page (il n'y en a généralement qu'un
     * sur la page détail d'une offre), on intercepte la soumission de son formulaire.
     *
     * Deux cas de figure :
     *   a) Le bouton a la classe 'actif' : l'offre est déjà en favoris, l'utilisateur
     *      veut la retirer → on affiche le toast "Retiré", puis on soumet.
     *   b) Le bouton n'a pas la classe 'actif' : l'utilisateur veut ajouter l'offre
     *      → on joue l'animation d'ajout, puis on soumet.
     *
     * Dans les deux cas, e.preventDefault() bloque la soumission native le temps
     * de l'animation, et le formulaire est soumis programmatiquement en callback.
     */
    document.querySelectorAll('.btn-favori').forEach(function (btn) {
        /**
         * Recherche du formulaire parent du bouton.
         * Si le bouton n'est pas dans un formulaire (erreur de template), on ignore.
         *
         * @type {HTMLFormElement|null}
         */
        var form = btn.closest('form');
        if (!form) return;

        /**
         * Listener 'submit' sur le formulaire (et non sur le bouton)
         * pour intercepter aussi une soumission via la touche Entrée.
         */
        form.addEventListener('submit', function (e) {
            /**
             * Détermination de l'action en cours selon l'état du bouton.
             * La classe 'actif' est ajoutée par le backend lors du rendu Twig
             * quand l'offre est déjà dans la wishlist de l'utilisateur.
             *
             * @type {boolean}
             */
            var isRemoving = btn.classList.contains('actif');

            if (isRemoving) {
                /**
                 * L'offre est en favoris : l'utilisateur la retire.
                 * On bloque la soumission, on affiche le toast "Retiré des favoris",
                 * puis on soumet après l'animation (1000ms affichage + 300ms sortie).
                 */
                e.preventDefault();
                var f = this; // Sauvegarde de 'this' (le formulaire) pour le callback.
                showRemoveToast(function () { f.submit(); });
            } else {
                /**
                 * L'offre n'est pas encore en favoris : l'utilisateur l'ajoute.
                 * On bloque, on anime le bouton (430ms), puis on soumet.
                 */
                e.preventDefault();
                var f = this;
                animateAdd(btn, function () { f.submit(); });
            }
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Bouton cœur AJAX .btn-heart (liste des stages)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Gestion AJAX des boutons cœur sur la liste des offres de stage.
     * Chaque bouton .btn-heart bascule l'état favori d'une offre sans rechargement.
     *
     * Attribut HTML requis sur le bouton :
     *   data-offre-id {string|number} — identifiant unique de l'offre concernée.
     *
     * CSRF :
     *   Le token est lu depuis <meta name="csrf-token" content="..."> dans le <head>.
     *   Il est obligatoire pour que le backend accepte la requête POST.
     *
     * Endpoint appelé :
     *   URL     : /?uri=wishlist-toggle
     *   Méthode : POST
     *   Headers : X-Requested-With: XMLHttpRequest (permet au backend de détecter les requêtes AJAX).
     *   Corps   : application/x-www-form-urlencoded
     *               id_offre   = identifiant de l'offre
     *               csrf_token = token CSRF
     *
     * Réponse attendue (JSON) :
     *   { "en_favori": true }  → l'offre vient d'être ajoutée aux favoris.
     *   { "en_favori": false } → l'offre vient d'être retirée des favoris.
     */
    document.querySelectorAll('.btn-heart').forEach(function (btn) {
        /**
         * Listener 'click' sur chaque bouton cœur.
         * Le bouton est désactivé pendant la requête pour éviter les doubles soumissions.
         */
        btn.addEventListener('click', function () {
            /**
             * Lecture de l'identifiant de l'offre depuis l'attribut data-offre-id.
             *
             * @type {string}
             */
            var idOffre  = btn.dataset.offreId;

            /**
             * État actuel du bouton — true si l'offre est déjà en favoris.
             * (Variable disponible pour une logique optimiste future si besoin.)
             *
             * @type {boolean}
             */
            var isActif  = btn.classList.contains('actif');

            /**
             * Désactivation du bouton pendant la requête réseau.
             * Empêche les clics multiples et les doubles requêtes simultanées.
             */
            btn.disabled = true;

            // ── Construction du corps de la requête POST ──────────────────────
            /**
             * Lecture du token CSRF depuis la meta tag du <head>.
             * Si la meta n'existe pas, on utilise une chaîne vide (le backend rejettera la requête).
             *
             * Exemple de meta dans le template base :
             *   <meta name="csrf-token" content="{{ csrf_token }}">
             *
             * @type {string}
             */
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            /**
             * URLSearchParams encode les données au format application/x-www-form-urlencoded,
             * le format standard des formulaires HTML POST.
             *
             * @type {URLSearchParams}
             */
            var body = new URLSearchParams();
            body.append('id_offre', idOffre);
            body.append('csrf_token', csrfToken);

            // ── Requête fetch vers l'endpoint de bascule ──────────────────────
            /**
             * fetch() envoie la requête POST de manière asynchrone.
             * L'en-tête X-Requested-With permet au backend PHP de distinguer
             * les requêtes AJAX des soumissions de formulaire classiques.
             */
            fetch('/?uri=wishlist-toggle', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            })
            /**
             * Première promesse : conversion de la réponse HTTP en objet JavaScript.
             * r.json() lit le corps de la réponse et le parse en JSON.
             *
             * @param {Response} r - Réponse HTTP brute.
             * @returns {Promise<Object>} Objet JSON parsé.
             */
            .then(function (r) { return r.json(); })
            /**
             * Deuxième promesse : traitement des données JSON reçues.
             * On met à jour l'état visuel du bouton selon data.en_favori.
             *
             * @param {{ en_favori: boolean }} data - Réponse JSON du serveur.
             */
            .then(function (data) {
                btn.disabled = false; // Réactivation du bouton après la réponse.

                if (data.en_favori) {
                    // ── L'offre vient d'être ajoutée aux favoris ──────────────
                    btn.classList.add('actif');
                    btn.title = 'Retirer des favoris'; // Mise à jour du tooltip d'accessibilité.

                    /**
                     * Animation d'ajout : ajout temporaire de la classe 'adding'
                     * (déclenche une animation CSS, ex. un pulse ou un scale).
                     * Retirée après 430ms (durée de la transition CSS).
                     */
                    btn.classList.add('adding');
                    setTimeout(function () { btn.classList.remove('adding'); }, 430);
                } else {
                    // ── L'offre vient d'être retirée des favoris ──────────────
                    btn.classList.remove('actif');
                    btn.title = 'Ajouter aux favoris'; // Mise à jour du tooltip d'accessibilité.

                    /**
                     * Toast de confirmation "Retiré des favoris".
                     * Le callback est vide car il n'y a pas de formulaire à soumettre ici —
                     * l'état a déjà été mis à jour côté serveur par la requête AJAX.
                     */
                    showRemoveToast(function () {});
                }
            })
            /**
             * Gestion des erreurs réseau (perte de connexion, timeout, erreur serveur).
             * On se contente de réactiver le bouton — l'état n'a pas changé côté serveur.
             * On pourrait ici afficher un message d'erreur à l'utilisateur.
             */
            .catch(function () { btn.disabled = false; });
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Interception du bouton .btn-retirer (page profil)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Pour chaque bouton .btn-retirer sur la page profil (liste des favoris),
     * on intercepte la soumission du formulaire de suppression pour :
     *   1. Appliquer la classe CSS 'removing' sur la carte favorite parente
     *      (animation de sortie, ex. fondu ou glissement vers la gauche).
     *   2. Afficher le toast "Retiré des favoris".
     *   3. Soumettre le formulaire après l'animation (1300ms au total).
     *
     * Cela évite que la carte disparaisse brutalement du DOM à la soumission.
     */
    document.querySelectorAll('.btn-retirer').forEach(function (btn) {
        /**
         * Recherche du formulaire parent du bouton.
         * Si le bouton n'est pas dans un formulaire, on ignore.
         *
         * @type {HTMLFormElement|null}
         */
        var form = btn.closest('form');
        if (!form) return;

        /**
         * Listener 'submit' sur le formulaire.
         * Bloque la soumission native, joue l'animation, puis soumet.
         */
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Bloque la soumission immédiate.
            var f = this;

            /**
             * Recherche de la carte favorite parente (.favori-card).
             * La classe 'removing' déclenche une animation CSS de sortie (ex. opacity→0).
             * On l'ajoute avant le toast pour que les deux animations se jouent en parallèle.
             *
             * @type {HTMLElement|null}
             */
            var card = btn.closest('.favori-card');
            if (card) {
                card.classList.add('removing'); // Animation de sortie de la carte (définie en CSS).
            }

            /**
             * Affichage du toast "Retiré des favoris".
             * Le formulaire est soumis dans le callback, après la fin du toast (1300ms).
             */
            showRemoveToast(function () { f.submit(); });
        });
    });

}()); // Fin de l'IIFE — scope isolé du reste de l'application.
