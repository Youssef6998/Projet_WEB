/**
 * main.js
 *
 * Comportements globaux partagés par toutes les pages de l'application :
 *  - Gestion des menus déroulants de la barre de navigation
 *  - Conversion automatique en majuscules des champs "nom"
 *  - Validation inline du format des champs email
 *  - Carrousel de statistiques (défilement, dots, boutons prev/next)
 */

/* ── Navigation dropdown ───────────────────────────────────────────────── */

/**
 * Ouvre ou ferme le menu déroulant associé au bouton cliqué.
 * Tous les autres menus ouverts sont refermés avant d'ouvrir le nouveau,
 * afin qu'un seul dropdown soit visible à la fois.
 *
 * @param {HTMLElement} btn - Le bouton déclencheur (.nav-dropdown-btn)
 * @returns {void}
 */
function toggleDropdown(btn) {
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('open');

    // Ferme tous les dropdowns ouverts avant d'en ouvrir un nouveau
    document.querySelectorAll('.nav-dropdown-menu.open').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.nav-dropdown-btn.open').forEach(b => b.classList.remove('open'));

    if (!isOpen) {
        menu.classList.add('open');
        btn.classList.add('open');
    }
}

// Ferme tous les dropdowns ouverts lorsque l'utilisateur clique en dehors
document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-dropdown')) {
        document.querySelectorAll('.nav-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.nav-dropdown-btn.open').forEach(b => b.classList.remove('open'));
    }
});

/* ── Champ Nom → majuscules ─────────────────────────────────────────────── */

// Tous les champs dont le name est "nom" sont automatiquement mis en majuscules à la saisie
document.querySelectorAll('input[name="nom"]').forEach(function(input) {
    input.addEventListener('input', function() {
        const pos = this.selectionStart; // Mémorise la position du curseur avant la transformation
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos); // Replace le curseur à la même position après la mise en majuscules
    });
});

/* ── Champ Email → validation format ───────────────────────────────────── */

// Sélectionne les champs email par type HTML ou par attribut name
document.querySelectorAll('input[type="email"], input[name="email"]').forEach(function(input) {
    let errorEl = null; // Référence unique au span d'erreur pour ce champ (créé à la demande)

    /**
     * Retourne le span d'erreur associé au champ, en le créant s'il n'existe pas encore.
     * Le span est inséré directement après le champ dans le DOM.
     *
     * @returns {HTMLElement} Le span d'erreur (.email-error)
     */
    function getOrCreateError() {
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'email-error';
            input.parentNode.insertBefore(errorEl, input.nextSibling);
        }
        return errorEl;
    }

    // Valide le format à la perte de focus (blur) pour ne pas perturber la saisie en cours
    input.addEventListener('blur', function() {
        const val = this.value.trim();
        if (!val) return; // Champ vide : la validation HTML (required) gère ce cas

        // Regex minimaliste : vérifie la structure "texte@texte.texte"
        const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
        const err = getOrCreateError();

        if (!valid) {
            err.textContent = 'Adresse email invalide.';
            this.setAttribute('aria-invalid', 'true'); // Accessibilité : signale le champ invalide aux lecteurs d'écran
        } else {
            err.textContent = '';
            this.removeAttribute('aria-invalid');
        }
    });

    // Efface l'erreur immédiatement dès que l'utilisateur recommence à saisir
    input.addEventListener('input', function() {
        if (errorEl) errorEl.textContent = '';
        this.removeAttribute('aria-invalid');
    });
});

/* ── Carrousel statistiques ─────────────────────────────────────────────── */

(function () {
    const track = document.getElementById('statsTrack');
    if (!track) return; // Le carrousel n'est pas présent sur toutes les pages

    const cards   = track.querySelectorAll('.stat-card');
    const dots    = document.querySelectorAll('#statsDots .carousel-dot');
    const btnPrev = document.getElementById('statsPrev');
    const btnNext = document.getElementById('statsNext');
    let current = 0; // Index de la carte actuellement visible

    /**
     * Fait défiler le carrousel jusqu'à la carte d'index n,
     * met à jour les indicateurs (dots) et l'état des boutons de navigation.
     *
     * @param {number} n - Index de destination (sera borné entre 0 et cards.length - 1)
     * @returns {void}
     */
    function goTo(n) {
        current = Math.max(0, Math.min(n, cards.length - 1));

        // Défilement horizontal : chaque carte occupe toute la largeur du conteneur
        track.scrollTo({ left: current * track.offsetWidth, behavior: 'smooth' });

        // Met à jour les points de navigation en activant uniquement celui de la carte courante
        dots.forEach((d, i) => d.classList.toggle('active', i === current));

        // Désactive le bouton prev sur la première carte et next sur la dernière
        if (btnPrev) btnPrev.disabled = current === 0;
        if (btnNext) btnNext.disabled = current === cards.length - 1;
    }

    btnPrev && btnPrev.addEventListener('click', () => goTo(current - 1));
    btnNext && btnNext.addEventListener('click', () => goTo(current + 1));

    // Clic sur un dot pour accéder directement à la carte correspondante
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));

    // Synchronise l'index interne après un défilement natif (swipe tactile ou molette)
    track.addEventListener('scrollend', () => {
        const idx = Math.round(track.scrollLeft / track.offsetWidth);
        if (idx !== current) goTo(idx);
    });

    goTo(0); // Initialise l'affichage sur la première carte
}());
