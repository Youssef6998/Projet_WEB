/**
 * @file menu-burger.js
 * @description Gestion du menu de navigation mobile (hamburger menu).
 *
 * Ce script prend en charge deux mécanismes distincts :
 *
 *  1. Ouverture / fermeture du menu mobile principal
 *     Le bouton burger (#mobile-toggle) bascule la classe 'active' sur lui-même
 *     et sur le panneau de navigation mobile (#nav-mobile).
 *     La classe 'active' est responsable de l'affichage/masquage via CSS
 *     (ex. max-height, transform: translateX, opacity, etc.).
 *     Le menu se ferme automatiquement au clic sur n'importe quel lien de navigation.
 *
 *  2. Sous-menus déroulants mobiles (.nav-mobile-dropdown)
 *     Chaque bouton .nav-mobile-dropdown-btn bascule la classe 'open'
 *     sur son conteneur parent (.nav-mobile-dropdown).
 *     Plusieurs sous-menus peuvent être ouverts simultanément sur mobile.
 *
 * Structure HTML attendue :
 *   <!-- Bouton burger -->
 *   <button id="mobile-toggle">☰</button>
 *
 *   <!-- Panneau de navigation mobile -->
 *   <nav id="nav-mobile">
 *     <a href="...">Accueil</a>
 *
 *     <!-- Sous-menu déroulant mobile -->
 *     <div class="nav-mobile-dropdown">
 *       <button class="nav-mobile-dropdown-btn">Mon compte ▾</button>
 *       <ul>
 *         <li><a href="...">Profil</a></li>
 *       </ul>
 *     </div>
 *   </nav>
 *
 * Ce fichier est inclus dans le layout de base et s'exécute sur toutes les pages.
 * Aucune dépendance externe — JavaScript vanilla pur.
 */

/**
 * Listener 'DOMContentLoaded' : exécuté dès que le HTML est analysé et le DOM prêt,
 * sans attendre le chargement des images et autres ressources lourdes.
 *
 * On enveloppe ici uniquement la logique du menu burger principal,
 * car elle dépend des éléments #mobile-toggle et #nav-mobile.
 */
document.addEventListener('DOMContentLoaded', () => {
    /**
     * Bouton hamburger (☰) — déclenche l'ouverture/fermeture du menu mobile.
     * Attendu : un élément unique portant l'id 'mobile-toggle'.
     *
     * @type {HTMLElement|null}
     */
    const toggle    = document.getElementById('mobile-toggle');

    /**
     * Panneau de navigation mobile — contient tous les liens et sous-menus.
     * Attendu : un élément unique portant l'id 'nav-mobile'.
     *
     * @type {HTMLElement|null}
     */
    const navMobile = document.getElementById('nav-mobile');

    /**
     * Garde de sécurité : si l'un des deux éléments est absent du DOM
     * (ex. page sans navigation mobile, template partiel), on sort immédiatement
     * pour éviter toute erreur JavaScript.
     */
    if (!toggle || !navMobile) return;

    // ── Ouverture / fermeture du menu au clic sur le burger ──────────────────
    /**
     * Listener 'click' sur le bouton burger.
     *
     * classList.toggle('active') :
     *   - Ajoute la classe 'active' si elle est absente (menu fermé → ouvert).
     *   - Retire la classe 'active' si elle est présente (menu ouvert → fermé).
     *
     * La classe 'active' est appliquée sur les DEUX éléments simultanément :
     *   - toggle    : pour changer l'icône ou la couleur du bouton burger via CSS.
     *   - navMobile : pour afficher le panneau de navigation via CSS (ex. translateX(0)).
     */
    toggle.addEventListener('click', () => {
        toggle.classList.toggle('active');    // Mise à jour visuelle du bouton burger.
        navMobile.classList.toggle('active'); // Affichage/masquage du panneau de navigation.
    });

    // ── Fermeture automatique au clic sur un lien de navigation ──────────────
    /**
     * Sélection de tous les liens <a> à l'intérieur du panneau mobile.
     * On y attache un listener 'click' individuel pour fermer le menu
     * dès qu'un lien est suivi.
     *
     * Cas d'usage couverts :
     *   - Navigation vers une autre page (rechargement complet).
     *   - Clic sur une ancre (#section) dans la même page (défilement).
     *   - Application SPA : navigation sans rechargement.
     *
     * Sans ce comportement, le menu resterait ouvert après le changement de page
     * dans une SPA ou après avoir cliqué sur une ancre.
     */
    navMobile.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            /**
             * Fermeture du menu en retirant la classe 'active' des deux éléments.
             * On utilise remove() plutôt que toggle() pour s'assurer
             * que le menu est fermé même si l'état était incohérent.
             */
            toggle.classList.remove('active');
            navMobile.classList.remove('active');
        });
    });
});

// ── Sous-menus déroulants mobiles ───────────────────────────────────────────
/**
 * Gestion des sous-menus déroulants dans le menu mobile.
 *
 * Ce bloc est VOLONTAIREMENT placé hors du listener DOMContentLoaded :
 * querySelectorAll() fonctionne dès que le script est exécuté (les boutons
 * sont dans le HTML statique et disponibles immédiatement).
 * Placer ce code dans DOMContentLoaded fonctionnerait aussi, mais ce n'est pas nécessaire.
 *
 * Pour chaque bouton de sous-menu trouvé, on attache un listener 'click'.
 */
document.querySelectorAll('.nav-mobile-dropdown-btn').forEach(btn => {
    /**
     * Listener 'click' sur chaque bouton de sous-menu déroulant.
     *
     * btn.closest('.nav-mobile-dropdown') :
     *   Remonte dans les ancêtres du bouton jusqu'à trouver le conteneur
     *   portant la classe .nav-mobile-dropdown.
     *   Ce conteneur englobe le bouton ET le contenu déroulant.
     *
     * dropdown.classList.toggle('open') :
     *   - Ajoute 'open' si absent → le sous-menu s'étend (CSS : max-height, height…).
     *   - Retire 'open' si présent → le sous-menu se replie.
     *
     * Contrairement aux dropdowns desktop (main.js), plusieurs sous-menus mobiles
     * peuvent être ouverts en même temps — on ne ferme pas les autres.
     */
    btn.addEventListener('click', () => {
        /**
         * Recherche du conteneur parent du sous-menu.
         *
         * @type {HTMLElement}
         */
        const dropdown = btn.closest('.nav-mobile-dropdown');
        dropdown.classList.toggle('open'); // Bascule l'affichage du sous-menu (géré en CSS).
    });
});
