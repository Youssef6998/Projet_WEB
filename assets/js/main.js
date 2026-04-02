/**
 * @file main.js
 * @description Comportements globaux partagés par toutes les pages de l'application.
 *
 * Ce fichier regroupe quatre fonctionnalités indépendantes qui s'appliquent
 * à l'ensemble du site et sont donc chargées sur chaque page :
 *
 *  1. Menus déroulants de la barre de navigation (desktop)
 *     Ouverture/fermeture au clic, fermeture au clic en dehors.
 *
 *  2. Conversion automatique en majuscules des champs "nom"
 *     Tout caractère saisi dans un champ name="nom" est converti en temps réel.
 *
 *  3. Validation inline du format des champs email
 *     Vérifie la structure à la perte de focus, affiche/masque un span d'erreur.
 *
 *  4. Carrousel de statistiques
 *     Défilement fluide entre les cartes, navigation par dots et boutons prev/next,
 *     synchronisation avec le défilement natif (swipe tactile).
 *
 * Aucune bibliothèque externe — JavaScript vanilla pur.
 * Ce fichier ne contient aucune requête réseau.
 */

/* ══════════════════════════════════════════════════════════════════════════
   1. Navigation dropdown (menus déroulants desktop)
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Bascule l'état ouvert/fermé du menu déroulant associé au bouton cliqué.
 *
 * Logique :
 *   - On lit l'état actuel du menu frère (nextElementSibling du bouton).
 *   - On ferme TOUS les dropdowns ouverts sur la page (pour en avoir un seul à la fois).
 *   - Si le menu n'était PAS ouvert avant la fermeture globale, on l'ouvre.
 *     (Si c'était lui qui était ouvert, la fermeture globale l'a déjà fermé → résultat : toggle.)
 *
 * Structure HTML attendue :
 *   <div class="nav-dropdown">
 *     <button class="nav-dropdown-btn" onclick="toggleDropdown(this)">Mon Menu</button>
 *     <ul class="nav-dropdown-menu">...</ul>
 *   </div>
 *
 * @param {HTMLElement} btn - L'élément bouton (.nav-dropdown-btn) qui a été cliqué.
 * @returns {void}
 */
function toggleDropdown(btn) {
    /**
     * nextElementSibling : l'élément HTML immédiatement après le bouton dans le DOM.
     * Par convention, c'est toujours l'élément .nav-dropdown-menu.
     *
     * @type {HTMLElement}
     */
    const menu = btn.nextElementSibling;

    /**
     * Capture de l'état avant la fermeture globale ci-dessous.
     * Permet de savoir si on doit ouvrir ou simplement garder fermé.
     *
     * @type {boolean}
     */
    const isOpen = menu.classList.contains('open');

    /**
     * Fermeture de tous les menus déroulants actuellement ouverts.
     * On retire la classe 'open' sur chaque menu et chaque bouton déclencheur.
     * Cette étape garantit qu'un seul dropdown est visible à la fois.
     */
    document.querySelectorAll('.nav-dropdown-menu.open').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.nav-dropdown-btn.open').forEach(b => b.classList.remove('open'));

    /**
     * Si le menu n'était pas ouvert avant la fermeture globale,
     * on l'ouvre maintenant en ajoutant la classe 'open'.
     * La transition d'apparition est gérée par CSS (max-height, opacity, etc.).
     */
    if (!isOpen) {
        menu.classList.add('open');
        btn.classList.add('open');
    }
    // Si isOpen était true, la fermeture globale a déjà tout fermé → aucune action supplémentaire.
}

/**
 * Listener 'click' délégué sur l'ensemble du document.
 * Ferme tous les dropdowns ouverts lorsque l'utilisateur clique n'importe où
 * en dehors d'un élément portant la classe .nav-dropdown.
 *
 * e.target.closest('.nav-dropdown') remonte dans les ancêtres pour vérifier
 * si le clic s'est produit à l'intérieur d'un menu déroulant ou de son bouton.
 * Si null est retourné, le clic est "hors menu" → on ferme tout.
 */
document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-dropdown')) {
        // Clic en dehors de tout dropdown : fermeture de tous les menus ouverts.
        document.querySelectorAll('.nav-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.nav-dropdown-btn.open').forEach(b => b.classList.remove('open'));
    }
});

/* ══════════════════════════════════════════════════════════════════════════
   2. Champs "nom" → conversion automatique en majuscules
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Sélection de tous les champs <input name="nom"> de la page.
 * Typiquement : champ nom de famille dans un formulaire d'inscription ou de profil.
 * L'événement 'input' se déclenche à chaque frappe (contrairement à 'change' qui attend le blur).
 */
document.querySelectorAll('input[name="nom"]').forEach(function(input) {
    /**
     * Listener 'input' : appelé en temps réel à chaque modification du champ.
     * On transforme la valeur en majuscules et on repositionne le curseur
     * pour éviter qu'il saute en fin de chaîne après la modification.
     */
    input.addEventListener('input', function() {
        /**
         * Mémorisation de la position du curseur avant la transformation.
         * selectionStart : position du début de la sélection (= position du curseur si rien n'est sélectionné).
         * Exemple : l'utilisateur a tapé "Dupond" avec le curseur entre "u" et "p" → selectionStart = 2.
         *
         * @type {number}
         */
        const pos = this.selectionStart;

        /**
         * Transformation de la valeur en majuscules.
         * this.value = ... déclenche un nouveau rendu du champ par le navigateur,
         * ce qui peut déplacer le curseur en fin de chaîne (comportement natif indésirable).
         */
        this.value = this.value.toUpperCase();

        /**
         * Restauration de la position du curseur après la transformation.
         * setSelectionRange(start, end) : si start === end, c'est un curseur simple (pas de sélection).
         * L'utilisateur peut ainsi continuer à taper au milieu du mot sans être renvoyé en fin.
         */
        this.setSelectionRange(pos, pos);
    });
});

/* ══════════════════════════════════════════════════════════════════════════
   3. Validation du format email
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Sélection des champs email par deux sélecteurs alternatifs :
 *   - input[type="email"]  : champs déclarés avec le type HTML5 email.
 *   - input[name="email"]  : champs déclarés sans type ou avec un type différent,
 *                            mais dont le nom est "email" (ex. certains vieux formulaires).
 *
 * La validation HTML5 native (type="email") est volontairement complétée ici
 * pour afficher un message en français et personnaliser le retour visuel.
 */
document.querySelectorAll('input[type="email"], input[name="email"]').forEach(function(input) {
    /**
     * Référence au span d'erreur associé à CE champ.
     * Créé à la demande lors du premier besoin (pattern "lazy initialization").
     * Une seule instance par champ, réutilisée à chaque validation.
     *
     * @type {HTMLElement|null}
     */
    let errorEl = null;

    /**
     * Retourne le span d'erreur associé au champ, en le créant si nécessaire.
     * Le span est inséré juste après le champ dans le DOM (insertBefore sur le nœud suivant).
     *
     * @returns {HTMLElement} Élément <span> de classe 'email-error'.
     */
    function getOrCreateError() {
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'email-error'; // Classe CSS pour la couleur rouge et la typographie.
            /**
             * insertBefore(newNode, referenceNode) insère newNode avant referenceNode.
             * input.nextSibling : nœud (texte ou élément) immédiatement après le champ.
             * Si nextSibling est null, insertBefore se comporte comme appendChild.
             */
            input.parentNode.insertBefore(errorEl, input.nextSibling);
        }
        return errorEl;
    }

    /**
     * Validation à la perte de focus (événement 'blur').
     * On choisit 'blur' plutôt que 'input' pour ne pas perturber la saisie en cours —
     * l'utilisateur voit l'erreur uniquement quand il quitte le champ.
     */
    input.addEventListener('blur', function() {
        const val = this.value.trim(); // Suppression des espaces en début/fin.

        /**
         * Champ vide : on ne valide pas ici.
         * La contrainte "required" est gérée par l'attribut HTML ou la validation serveur.
         */
        if (!val) return;

        /**
         * Expression régulière de validation minimale.
         * Vérifie la structure "quelquechose@quelquechose.quelquechose".
         * [^\s@]+ : un ou plusieurs caractères qui ne sont ni un espace, ni un @.
         * Cette regex est volontairement simpliste : elle accepte "a@b.c" mais rejette les erreurs
         * évidentes. La validation stricte (RFC 5322) est assurée côté serveur.
         *
         * @type {boolean}
         */
        const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
        const err = getOrCreateError();

        if (!valid) {
            /**
             * Email invalide : affichage du message d'erreur.
             * aria-invalid="true" : attribut ARIA signalant aux lecteurs d'écran
             * que la valeur du champ est invalide (conformité accessibilité WCAG 2.1).
             */
            err.textContent = 'Adresse email invalide.';
            this.setAttribute('aria-invalid', 'true');
        } else {
            /**
             * Email valide : effacement du message d'erreur et suppression du marqueur ARIA.
             */
            err.textContent = '';
            this.removeAttribute('aria-invalid');
        }
    });

    /**
     * Listener 'input' : efface le message d'erreur dès que l'utilisateur recommence à taper.
     * Cela évite que le message rouge reste affiché pendant toute la saisie de correction.
     * La validation ne sera ré-évaluée qu'au prochain 'blur'.
     */
    input.addEventListener('input', function() {
        if (errorEl) errorEl.textContent = ''; // Efface le texte d'erreur si le span existe.
        this.removeAttribute('aria-invalid');   // Retire le marqueur d'invalidité ARIA.
    });
});

/* ══════════════════════════════════════════════════════════════════════════
   4. Carrousel de statistiques
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Bloc de code encapsulé dans une IIFE pour isoler les variables locales
 * (current, cards, dots, etc.) du scope global de la page.
 *
 * Structure HTML attendue :
 *   <div id="statsPrev">←</div>
 *   <div id="statsTrack">
 *     <div class="stat-card">...</div>
 *     <div class="stat-card">...</div>
 *     ...
 *   </div>
 *   <div id="statsNext">→</div>
 *   <div id="statsDots">
 *     <span class="carousel-dot"></span>
 *     <span class="carousel-dot"></span>
 *     ...
 *   </div>
 *
 * Le carrousel n'est présent que sur certaines pages (ex. page d'accueil).
 * La garde `if (!track) return;` évite toute erreur JavaScript sur les autres pages.
 */
(function () {
    /**
     * Conteneur de défilement horizontal des cartes statistiques.
     * C'est l'élément dont on contrôle scrollLeft pour le défilement.
     *
     * @type {HTMLElement|null}
     */
    const track = document.getElementById('statsTrack');
    if (!track) return; // Le carrousel n'est pas présent sur cette page → on sort.

    /**
     * Liste de toutes les cartes statistiques dans le conteneur.
     *
     * @type {NodeList}
     */
    const cards   = track.querySelectorAll('.stat-card');

    /**
     * Points de navigation (dots) — un dot par carte.
     * Chaque dot, quand il a la classe 'active', représente la carte visible.
     *
     * @type {NodeList}
     */
    const dots    = document.querySelectorAll('#statsDots .carousel-dot');

    /**
     * Boutons de navigation précédent et suivant.
     * Peuvent être null si absents du template — protégé par `btnPrev &&`.
     *
     * @type {HTMLElement|null}
     */
    const btnPrev = document.getElementById('statsPrev');
    const btnNext = document.getElementById('statsNext');

    /**
     * Index (0-based) de la carte actuellement visible.
     * Mis à jour par la fonction goTo() à chaque navigation.
     *
     * @type {number}
     */
    let current = 0;

    /**
     * Navigue jusqu'à la carte d'index n.
     *
     * Actions effectuées :
     *   1. Borne n entre 0 et cards.length - 1 (évite les index hors limites).
     *   2. Fait défiler le track horizontalement via scrollTo (animation CSS smooth).
     *      Chaque carte occupant toute la largeur du track (overflow hidden + CSS),
     *      la position cible est simplement n * largeur du conteneur.
     *   3. Met à jour les dots : seul le dot d'index current reçoit la classe 'active'.
     *   4. Désactive le bouton prev sur la première carte et next sur la dernière.
     *
     * @param {number} n - Index de destination (peut être hors limites, sera corrigé).
     * @returns {void}
     */
    function goTo(n) {
        /**
         * Correction de l'index : Math.max évite les valeurs négatives,
         * Math.min évite de dépasser la dernière carte.
         */
        current = Math.max(0, Math.min(n, cards.length - 1));

        /**
         * Défilement horizontal fluide.
         * track.offsetWidth : largeur visible du conteneur en pixels.
         * En multipliant par l'index, on obtient la position de la n-ième carte.
         * behavior: 'smooth' active l'animation CSS native du navigateur.
         */
        track.scrollTo({ left: current * track.offsetWidth, behavior: 'smooth' });

        /**
         * Mise à jour des dots de navigation.
         * classList.toggle('active', condition) :
         *   - Ajoute 'active' si i === current (le dot courant).
         *   - Retire 'active' sinon (les autres dots).
         */
        dots.forEach((d, i) => d.classList.toggle('active', i === current));

        /**
         * Mise à jour de l'état des boutons de navigation.
         * - btnPrev est désactivé sur la première carte (current === 0) : pas de carte précédente.
         * - btnNext est désactivé sur la dernière carte : pas de carte suivante.
         * L'opérateur && court-circuite si l'élément est null.
         */
        if (btnPrev) btnPrev.disabled = current === 0;
        if (btnNext) btnNext.disabled = current === cards.length - 1;
    }

    // ── Listeners de navigation ─────────────────────────────────────────────

    /**
     * Clic sur "Précédent" → carte d'avant.
     * L'opérateur && évite l'erreur si btnPrev est null.
     */
    btnPrev && btnPrev.addEventListener('click', () => goTo(current - 1));

    /**
     * Clic sur "Suivant" → carte d'après.
     */
    btnNext && btnNext.addEventListener('click', () => goTo(current + 1));

    /**
     * Clic sur un dot → navigation directe vers la carte correspondante.
     * L'index i du dot correspond à l'index de la carte cible.
     */
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));

    /**
     * Listener 'scrollend' sur le track.
     * Déclenché après la fin d'un défilement natif (swipe tactile sur mobile,
     * défilement à la molette si overflow: scroll est actif, etc.).
     *
     * On recalcule l'index courant en divisant scrollLeft par la largeur d'une carte.
     * Math.round() gère les valeurs non entières dues aux erreurs de virgule flottante.
     * Si l'index calculé diffère de current (défilement natif hors goTo), on appelle
     * goTo pour resynchroniser les dots et les boutons.
     *
     * Note : 'scrollend' est un événement récent (Chrome 114+, Firefox 109+).
     * Les navigateurs plus anciens ignoreront ce listener silencieusement.
     */
    track.addEventListener('scrollend', () => {
        const idx = Math.round(track.scrollLeft / track.offsetWidth);
        if (idx !== current) goTo(idx);
    });

    /**
     * Initialisation du carrousel sur la première carte (index 0).
     * Met à jour dots et boutons pour refléter l'état initial.
     */
    goTo(0);

}()); // Fin de l'IIFE carrousel.
