/**
 * menu-burger.js
 *
 * Gère le menu de navigation mobile :
 *  - Ouverture / fermeture du menu via le bouton burger.
 *  - Fermeture automatique du menu lors d'un clic sur un lien (navigation).
 *  - Ouverture / fermeture des sous-menus déroulants mobiles.
 */

document.addEventListener('DOMContentLoaded', () => {
    const toggle    = document.getElementById('mobile-toggle');
    const navMobile = document.getElementById('nav-mobile');

    // Sécurité : ne rien faire si les éléments ne sont pas présents sur la page.
    if (!toggle || !navMobile) return;

    // Bascule l'état ouvert/fermé du menu au clic sur le burger.
    toggle.addEventListener('click', () => {
        toggle.classList.toggle('active');
        navMobile.classList.toggle('active');
    });

    // Ferme le menu lorsque l'utilisateur clique sur un lien de navigation,
    // ce qui évite de laisser le menu ouvert après un changement de page SPA
    // ou un clic sur une ancre.
    navMobile.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            toggle.classList.remove('active');
            navMobile.classList.remove('active');
        });
    });
});

// Gestion des sous-menus déroulants mobiles (hors DOMContentLoaded car les
// boutons sont présents dans le HTML statique et disponibles immédiatement).
document.querySelectorAll('.nav-mobile-dropdown-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // Bascule la classe 'open' sur le conteneur parent du bouton.
        const dropdown = btn.closest('.nav-mobile-dropdown');
        dropdown.classList.toggle('open');
    });
});
