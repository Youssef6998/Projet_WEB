/* Navigation dropdown */
function toggleDropdown(btn) {
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('open');
    // Ferme tous les dropdowns ouverts
    document.querySelectorAll('.nav-dropdown-menu.open').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.nav-dropdown-btn.open').forEach(b => b.classList.remove('open'));
    if (!isOpen) {
        menu.classList.add('open');
        btn.classList.add('open');
    }
}

// Ferme au clic en dehors
document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-dropdown')) {
        document.querySelectorAll('.nav-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.nav-dropdown-btn.open').forEach(b => b.classList.remove('open'));
    }
});

// Champ Nom → majuscules
document.querySelectorAll('input[name="nom"]').forEach(function(input) { // Sélectionne tous les champs dont le name est "nom"
    input.addEventListener('input', function() { // Déclenche la fonction à chaque frappe dans le champ
        const pos = this.selectionStart; // Mémorise la position actuelle du curseur
        this.value = this.value.toUpperCase(); // Convertit toute la valeur saisie en majuscules
        this.setSelectionRange(pos, pos); // Replace le curseur à sa position d'origine après la modification
    });
});

// Champ Email → validation format
document.querySelectorAll('input[type="email"], input[name="email"]').forEach(function(input) { // Sélectionne tous les champs email (par type ou par name)
    let errorEl = null; // Référence au message d'erreur, null tant qu'il n'a pas encore été créé

    function getOrCreateError() { // Fonction utilitaire : retourne le span d'erreur existant ou en crée un nouveau
        if (!errorEl) { // Si le span d'erreur n'existe pas encore
            errorEl = document.createElement('span'); // Crée un élément <span> pour afficher le message d'erreur
            errorEl.className = 'email-error'; // Ajoute une classe CSS pour identifier le span
            input.parentNode.insertBefore(errorEl, input.nextSibling); // Insère le span juste après le champ email dans le DOM
        }
        return errorEl; // Retourne le span (qu'il vienne d'être créé ou existait déjà)
    }

    input.addEventListener('blur', function() { // Déclenche la validation quand l'utilisateur quitte le champ
        const val = this.value.trim(); // Récupère la valeur saisie en supprimant les espaces en début/fin
        if (!val) return; // Si le champ est vide, on ne valide pas (le required HTML s'en charge)
        const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val); // Teste le format email avec une expression régulière : texte@texte.texte
        const err = getOrCreateError(); // Récupère ou crée le span d'erreur
        if (!valid) { // Si le format est invalide
            err.textContent = 'Adresse email invalide.'; // Affiche le message d'erreur sous le champ
            this.setAttribute('aria-invalid', 'true'); // Marque le champ comme invalide pour l'accessibilité
        } else { // Si le format est valide
            err.textContent = ''; // Efface le message d'erreur
            this.removeAttribute('aria-invalid'); // Retire l'attribut d'invalidité
        }
    });

    input.addEventListener('input', function() { // Déclenche la fonction à chaque frappe dans le champ
        if (errorEl) errorEl.textContent = ''; // Efface le message d'erreur dès que l'utilisateur retape
        this.removeAttribute('aria-invalid'); // Retire immédiatement le marquage invalide pendant la saisie
    });
});

// Carrousel statistiques
(function () {
    const track = document.getElementById('statsTrack');
    if (!track) return;

    const cards  = track.querySelectorAll('.stat-card');
    const dots   = document.querySelectorAll('#statsDots .carousel-dot');
    const btnPrev = document.getElementById('statsPrev');
    const btnNext = document.getElementById('statsNext');
    let current = 0;

    function goTo(n) {
        current = Math.max(0, Math.min(n, cards.length - 1));
        track.scrollTo({ left: current * track.offsetWidth, behavior: 'smooth' });
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
        if (btnPrev) btnPrev.disabled = current === 0;
        if (btnNext) btnNext.disabled = current === cards.length - 1;
    }

    btnPrev && btnPrev.addEventListener('click', () => goTo(current - 1));
    btnNext && btnNext.addEventListener('click', () => goTo(current + 1));
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));

    // Sync dots on native swipe/scroll
    track.addEventListener('scrollend', () => {
        const idx = Math.round(track.scrollLeft / track.offsetWidth);
        if (idx !== current) goTo(idx);
    });

    goTo(0);
}());
