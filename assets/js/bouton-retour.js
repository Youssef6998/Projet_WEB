/**
 * bouton-retour.js
 *
 * Gère la navigation "retour" pour tous les éléments portant la classe .btn-back.
 *
 * Comportement :
 *  - Si l'utilisateur vient d'une page précédente (document.referrer valide et
 *    différent de la page courante), le redirige vers cette page.
 *  - Sinon, utilise l'URL de repli définie dans data-fallback sur le bouton,
 *    ou '/?uri=home' par défaut.
 *
 * Utilise la délégation d'événements sur document pour fonctionner même
 * avec des boutons injectés dynamiquement après le chargement de la page.
 */

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-back');
    if (!btn) return; // Le clic ne concerne pas un bouton retour.

    e.preventDefault();

    const referrer = document.referrer;
    // URL de repli lue depuis l'attribut data-fallback, ou page d'accueil par défaut.
    const fallback = btn.dataset.fallback || '/?uri=home';

    if (referrer && referrer !== window.location.href) {
        // Une page précédente existe : y retourner directement.
        window.location.href = referrer;
    } else {
        // Pas de referrer (accès direct, nouvel onglet) : utiliser le repli.
        window.location.href = fallback;
    }
});
