/**
 * @file bouton-haut.js
 * @description Bouton flottant "retour en haut de page".
 *
 * Ce script injecte dynamiquement dans le DOM un bouton positionné en fixe
 * (via CSS) en bas à droite de la fenêtre. Ce bouton :
 *   - Apparaît progressivement (transition CSS gérée par la classe .visible)
 *     dès que l'utilisateur a défilé de plus de 300 px vers le bas.
 *   - Se masque automatiquement dès que le défilement redescend sous le seuil.
 *   - Remonte la page en douceur jusqu'au sommet au clic (scroll smooth natif).
 *
 * Optimisation des performances :
 *   L'événement 'scroll' peut se déclencher des dizaines de fois par seconde.
 *   Pour éviter de recalculer la visibilité du bouton à chaque pixel défilé,
 *   on utilise le pattern "requestAnimationFrame + flag ticking" :
 *   - `ticking = true`  : une frame de mise à jour est déjà planifiée, on ignore les scroll suivants.
 *   - `ticking = false` : remis à false à l'intérieur du callback rAF, autorisant la prochaine planification.
 *   Ce mécanisme est équivalent à un throttle passif lié au framerate de l'écran (60 fps typiquement).
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Création du bouton ──────────────────────────────────────────────────
    /**
     * On crée un élément <button> programmatiquement plutôt que de le mettre
     * dans le HTML, car il n'a de sens que si JavaScript est actif.
     * L'id 'scroll-top-btn' permet au CSS de le cibler pour le positionner
     * en fixe et de définir l'animation d'apparition via la classe .visible.
     */
    const btn = document.createElement('button');
    btn.id = 'scroll-top-btn';       // Identifiant utilisé par le CSS pour le style et la position.
    btn.innerHTML = '↑';             // Flèche Unicode indiquant la direction "haut".
    btn.setAttribute('aria-label', 'Retour en haut'); // Label accessible pour les lecteurs d'écran.

    /**
     * Insertion du bouton à la fin du <body>.
     * Le positionnement visuel (fixed, bas-droite) est entièrement géré par CSS.
     */
    document.body.appendChild(btn);

    // ── Gestion de la visibilité au défilement ──────────────────────────────
    /**
     * Flag de throttle via requestAnimationFrame.
     * `false` : aucune frame n'est en attente, on peut en planifier une nouvelle.
     * `true`  : une frame est déjà planifiée, les événements scroll intermédiaires
     *           sont ignorés pour ne pas empiler les callbacks inutilement.
     *
     * @type {boolean}
     */
    let ticking = false;

    /**
     * Listener sur l'événement 'scroll' de la fenêtre.
     * Se déclenche à chaque changement de position de défilement vertical.
     * On n'exécute la logique de mise à jour qu'une fois par frame d'affichage.
     */
    window.addEventListener('scroll', function () {
        if (!ticking) {
            /**
             * requestAnimationFrame planifie l'exécution du callback
             * juste avant que le navigateur repeigne l'écran.
             * C'est le moment idéal pour modifier le DOM/classes CSS.
             */
            requestAnimationFrame(function () {
                /**
                 * classList.toggle('visible', condition) :
                 *   - Ajoute la classe 'visible' si window.scrollY > 300 (le bouton apparaît).
                 *   - Retire la classe 'visible' sinon (le bouton disparaît).
                 *
                 * Le seuil de 300 px correspond à environ une hauteur de fenêtre mobile —
                 * en deçà, le bouton est inutile car on est encore "en haut" de la page.
                 *
                 * L'animation d'apparition/disparition est gérée par une transition CSS
                 * sur la propriété opacity (ou transform) définie dans le fichier CSS.
                 */
                btn.classList.toggle('visible', window.scrollY > 300);

                // On libère le flag pour permettre la prochaine mise à jour.
                ticking = false;
            });

            // On pose le flag pour bloquer les scroll intermédiaires.
            ticking = true;
        }
    });

    // ── Listener clic sur le bouton ─────────────────────────────────────────
    /**
     * Au clic, on remonte en haut de la page via l'API native window.scrollTo.
     * L'option `behavior: 'smooth'` active le défilement animé du navigateur,
     * ce qui donne un retour visuel fluide à l'utilisateur.
     * Navigateurs ne supportant pas smooth : retour instantané (dégradation gracieuse).
     */
    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
