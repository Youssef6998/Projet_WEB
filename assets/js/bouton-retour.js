/**
 * @file bouton-retour.js
 * @description Navigation "retour" intelligente pour tous les boutons portant la classe .btn-back.
 *
 * Comportement attendu :
 *   1. Si l'utilisateur est arrivé sur la page en naviguant depuis une autre page
 *      du site (document.referrer renseigné et différent de l'URL courante),
 *      il est renvoyé vers cette page précédente.
 *   2. Sinon (accès direct par URL, nouvel onglet, referrer identique à la page courante),
 *      on utilise l'URL de repli définie dans l'attribut data-fallback du bouton,
 *      ou '/?uri=home' par défaut si aucun attribut n'est présent.
 *
 * Technique de délégation d'événements :
 *   On attache le listener sur `document` plutôt que sur chaque bouton individuellement.
 *   Cela permet de gérer les boutons .btn-back injectés dynamiquement après le
 *   chargement initial de la page (ex. contenu chargé en AJAX, modales, etc.)
 *   sans avoir à ré-attacher des listeners à chaque injection.
 *
 * Aucune dépendance externe — pas de bibliothèque requise.
 */

/**
 * Listener 'click' délégué sur l'ensemble du document.
 *
 * e.target : l'élément exact qui a été cliqué (peut être un enfant du bouton).
 * e.target.closest('.btn-back') : remonte dans les ancêtres jusqu'à trouver
 *   un élément avec la classe .btn-back, ou retourne null si aucun n'existe.
 * Cela permet de cliquer sur une icône ou un span à l'intérieur du bouton
 * et de quand même déclencher la logique retour.
 */
document.addEventListener('click', function(e) {
    /**
     * On tente de trouver l'élément .btn-back le plus proche du clic.
     * Si le clic ne concerne pas un bouton retour, on sort immédiatement
     * pour ne pas interférer avec les autres clics sur la page.
     *
     * @type {HTMLElement|null}
     */
    const btn = e.target.closest('.btn-back');
    if (!btn) return; // Clic hors d'un bouton retour → on n'intervient pas.

    /**
     * On annule le comportement par défaut du lien/bouton (navigation ou soumission)
     * pour prendre entièrement la main sur la destination.
     */
    e.preventDefault();

    /**
     * document.referrer : URL de la page depuis laquelle l'utilisateur est arrivé.
     * Vaut une chaîne vide si :
     *   - L'utilisateur a tapé l'URL directement.
     *   - La page a été ouverte dans un nouvel onglet.
     *   - La page précédente avait la politique Referrer-Policy: no-referrer.
     *
     * @type {string}
     */
    const referrer = document.referrer;

    /**
     * URL de repli lue depuis l'attribut HTML data-fallback du bouton cliqué.
     * Exemple dans un template Twig :
     *   <button class="btn-back" data-fallback="/?uri=liste-offres">Retour</button>
     *
     * Si l'attribut est absent, on revient à la page d'accueil par défaut.
     *
     * @type {string}
     */
    const fallback = btn.dataset.fallback || '/?uri=home';

    if (referrer && referrer !== window.location.href) {
        /**
         * Un referrer valide et différent de la page courante existe :
         * on y retourne directement. C'est le comportement le plus naturel
         * pour l'utilisateur, équivalent au bouton "Précédent" du navigateur
         * mais sans dépendre de l'historique de navigation (qui peut être vide).
         */
        window.location.href = referrer;
    } else {
        /**
         * Pas de referrer utilisable (accès direct, nouvel onglet, même page) :
         * on utilise l'URL de repli définie par le développeur dans le template,
         * afin de toujours offrir un chemin de navigation cohérent.
         */
        window.location.href = fallback;
    }
});
