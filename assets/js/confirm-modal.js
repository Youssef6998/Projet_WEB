/**
 * @file confirm-modal.js
 * @description Modale de confirmation générique et réutilisable pour les formulaires de suppression.
 *
 * Fonctionnement général :
 *   Lorsqu'un formulaire portant l'attribut data-confirm-title est soumis,
 *   la soumission native est interceptée et une modale de confirmation s'affiche.
 *   L'utilisateur peut alors :
 *     - Cliquer "Annuler" / appuyer Échap / cliquer hors de la modale → annulation.
 *     - Cliquer "Confirmer" → le formulaire est soumis pour de bon.
 *
 * Attributs HTML reconnus sur le <form> (tous optionnels sauf data-confirm-title) :
 *   data-confirm-title   {string}  Titre affiché dans la modale (ex. "Supprimer l'offre").
 *   data-confirm-name    {string}  Nom de l'élément ciblé, mis en gras dans le corps (ex. "Stage chez LVMH").
 *   data-confirm-detail  {string}  Message de détail sous le nom (défaut : "Cette action est irréversible.").
 *   data-confirm-label   {string}  Libellé du bouton de confirmation (défaut : "Supprimer").
 *   data-confirm-type    {string}  Variante visuelle — 'danger' uniquement pour l'instant (défaut : 'danger').
 *
 * Technique anti-boucle :
 *   Après confirmation, un flag data-confirmed='1' est posé sur le formulaire.
 *   Au second événement 'submit' (déclenché par pendingForm.submit()), l'interception
 *   détecte ce flag et laisse la soumission se faire normalement.
 *
 * Délégation d'événements :
 *   Le listener 'submit' est posé sur document pour capturer tous les formulaires
 *   de la page, y compris ceux injectés après le chargement initial.
 *
 * Le module est encapsulé dans une IIFE pour éviter toute pollution du scope global.
 */

/* ═══════════════════════════════════════════
   Modale de confirmation — confirm-modal.js
   ═══════════════════════════════════════════ */

(function () {
    'use strict';

    // ── Injection de la structure HTML de la modale dans le <body> ──────────
    /**
     * On insère la modale une seule fois au chargement du script, avant la balise </body>.
     * Elle est invisible par défaut (pas de classe 'cm-visible') et s'affiche
     * via CSS uniquement quand cette classe est ajoutée dynamiquement.
     *
     * Structure :
     *   #cm-overlay  : fond semi-transparent couvrant toute la fenêtre.
     *     .cm-modal  : boîte de dialogue centrée.
     *       .cm-icon : icône SVG de corbeille (suppression).
     *       .cm-title : titre dynamique (rempli au moment de l'ouverture).
     *       .cm-message : corps du message (nom de l'élément + détail).
     *       .cm-actions : boutons Annuler et Confirmer.
     *
     * Attributs d'accessibilité :
     *   role="dialog"         : annonce aux lecteurs d'écran que c'est une boîte de dialogue.
     *   aria-modal="true"     : indique que le contenu derrière est inactif.
     *   aria-labelledby       : lie le titre de la boîte (#cm-title) à l'élément pour les AT.
     */
    document.body.insertAdjacentHTML('beforeend', `
<div id="cm-overlay" class="cm-overlay" data-type="danger" role="dialog" aria-modal="true" aria-labelledby="cm-title">
  <div class="cm-modal">
    <div class="cm-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="3 6 5 6 21 6"/>
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
        <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
      </svg>
    </div>
    <h3 class="cm-title" id="cm-title"></h3>
    <p class="cm-message" id="cm-message"></p>
    <div class="cm-actions">
      <button class="cm-cancel" id="cm-cancel" type="button">Annuler</button>
      <button class="cm-confirm" id="cm-confirm" type="button">Supprimer</button>
    </div>
  </div>
</div>`);

    // ── Références aux éléments de la modale ────────────────────────────────
    /** @type {HTMLElement} Fond semi-transparent (overlay) — contient aussi la boîte modale. */
    const overlay    = document.getElementById('cm-overlay');
    /** @type {HTMLElement} Élément <h3> du titre — son textContent est mis à jour à l'ouverture. */
    const titleEl    = document.getElementById('cm-title');
    /** @type {HTMLElement} Élément <p> du corps — son innerHTML est mis à jour à l'ouverture. */
    const msgEl      = document.getElementById('cm-message');
    /** @type {HTMLButtonElement} Bouton "Annuler" — ferme la modale sans rien faire. */
    const cancelBtn  = document.getElementById('cm-cancel');
    /** @type {HTMLButtonElement} Bouton "Confirmer" — soumet le formulaire en attente. */
    const confirmBtn = document.getElementById('cm-confirm');

    /**
     * Référence au formulaire dont la soumission a été interceptée et est en attente
     * de confirmation de l'utilisateur.
     * Vaut null si aucune modale n'est actuellement ouverte.
     *
     * @type {HTMLFormElement|null}
     */
    let pendingForm = null;

    // ── Utilitaire de sécurité HTML ─────────────────────────────────────────
    /**
     * Échappe les caractères spéciaux HTML d'une chaîne pour l'insérer sans risque
     * en tant que innerHTML (prévention des injections XSS).
     *
     * Technique : on affecte la chaîne à textContent d'un <div> temporaire
     * (le navigateur la stocke telle quelle, sans interprétation HTML),
     * puis on relit innerHTML (le navigateur a encodé < > & " en entités).
     *
     * @param {string} str - Chaîne potentiellement dangereuse provenant des attributs HTML.
     * @returns {string}     Chaîne avec les caractères < > & " encodés en entités HTML.
     *
     * @example
     *   escHtml('<script>alert(1)</script>')
     *   // → '&lt;script&gt;alert(1)&lt;/script&gt;'
     */
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // ── Ouverture de la modale ──────────────────────────────────────────────
    /**
     * Lit les attributs data-confirm-* du formulaire intercepté,
     * remplit les éléments de la modale avec ces valeurs,
     * puis affiche la modale en ajoutant la classe 'cm-visible' sur l'overlay.
     *
     * Le focus est automatiquement déplacé sur le bouton "Annuler"
     * pour faciliter la navigation clavier (l'action safe par défaut).
     *
     * @param {HTMLFormElement} form - Le formulaire dont la soumission est interceptée.
     * @returns {void}
     */
    function open(form) {
        // Lecture des attributs avec valeurs par défaut si non définis.
        const type   = form.dataset.confirmType   || 'danger';
        const title  = form.dataset.confirmTitle  || 'Confirmer la suppression';
        const name   = form.dataset.confirmName   || '';       // Peut être vide.
        const detail = form.dataset.confirmDetail || 'Cette action est irréversible.';
        const label  = form.dataset.confirmLabel  || 'Supprimer';

        /**
         * Mise à jour de l'attribut data-type sur l'overlay.
         * Permet au CSS de changer la couleur du bouton de confirmation
         * selon la variante (ex. 'danger' → rouge, extensible à 'warning' → orange).
         */
        overlay.dataset.type   = type;

        // Remplissage des éléments textuels de la modale.
        titleEl.textContent    = title;
        confirmBtn.textContent = label;

        /**
         * Corps du message :
         *   - Si un nom est fourni, il est mis en gras sur la première ligne.
         *   - Le message de détail suit, toujours présent.
         * On échappe les deux pour éviter toute injection HTML depuis les attributs.
         */
        msgEl.innerHTML = name
            ? `<strong>${escHtml(name)}</strong><br>${escHtml(detail)}`
            : escHtml(detail);

        /**
         * Affichage de la modale via l'ajout de la classe CSS 'cm-visible'.
         * La transition d'apparition (fade, scale…) est gérée intégralement par CSS.
         */
        overlay.classList.add('cm-visible');

        /**
         * Déplacement du focus sur "Annuler" pour :
         *   1. Permettre la fermeture immédiate avec Entrée ou Espace (action safe).
         *   2. Assurer la conformité accessibilité (focus visible dans la modale).
         */
        cancelBtn.focus();

        // Mémorisation du formulaire en attente pour la confirmation ultérieure.
        pendingForm = form;
    }

    // ── Fermeture de la modale ──────────────────────────────────────────────
    /**
     * Retire la classe 'cm-visible' de l'overlay pour masquer la modale
     * (la transition de fermeture CSS s'exécute automatiquement).
     * Remet pendingForm à null pour indiquer qu'aucune action n'est en attente.
     *
     * @returns {void}
     */
    function close() {
        overlay.classList.remove('cm-visible');
        pendingForm = null;
    }

    // ── Événements de fermeture ─────────────────────────────────────────────

    /**
     * Clic sur le bouton "Annuler" → fermeture sans action.
     */
    cancelBtn.addEventListener('click', close);

    /**
     * Clic sur le fond de l'overlay (hors de la boîte modale) → fermeture.
     * On vérifie que e.target est bien l'overlay lui-même (et non un enfant
     * de la boîte modale) pour ne pas fermer accidentellement au clic dans la modale.
     */
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    /**
     * Appui sur la touche Échap → fermeture si la modale est visible.
     * Comportement standard attendu pour les boîtes de dialogue (ARIA Authoring Practices).
     */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('cm-visible')) close();
    });

    // ── Événement de confirmation ───────────────────────────────────────────
    /**
     * Clic sur le bouton "Confirmer" :
     *   1. Pose le flag data-confirmed='1' sur le formulaire en attente.
     *      Ce flag sera lu lors du prochain événement 'submit' pour laisser
     *      passer la soumission sans ré-ouvrir la modale (évite la boucle infinie).
     *   2. Soumet le formulaire — déclenche un événement 'submit' natif.
     *   3. Ferme la modale.
     */
    confirmBtn.addEventListener('click', function () {
        if (!pendingForm) return; // Sécurité : ne rien faire si aucun formulaire n'attend.
        pendingForm.dataset.confirmed = '1'; // Flag anti-boucle.
        pendingForm.submit();               // Soumission effective du formulaire.
        close();                            // Fermeture de la modale.
    });

    // ── Interception des soumissions de formulaires ─────────────────────────
    /**
     * Listener 'submit' délégué sur document.
     * Capte TOUS les événements de soumission de formulaires sur la page,
     * y compris ceux créés après le chargement initial du script.
     *
     * Conditions pour interception :
     *   1. Le formulaire doit posséder l'attribut data-confirm-title.
     *      Seuls les formulaires opt-in à la confirmation sont concernés.
     *   2. L'attribut data-confirmed ne doit pas valoir '1'.
     *      Cette seconde condition permet à la soumission réelle (post-confirmation)
     *      de se faire normalement sans être à nouveau interceptée.
     */
    document.addEventListener('submit', function (e) {
        /** @type {HTMLFormElement} */
        const form = e.target;

        // Vérification 1 : le formulaire est-il opt-in à la confirmation ?
        if (!('confirmTitle' in form.dataset)) return;

        // Vérification 2 : l'utilisateur a-t-il déjà confirmé (second submit) ?
        if (form.dataset.confirmed === '1') return;

        // Interception : on bloque la soumission native et on ouvre la modale.
        e.preventDefault();
        open(form);
    });

}()); // Fin de l'IIFE — le scope interne est isolé du reste de l'application.
