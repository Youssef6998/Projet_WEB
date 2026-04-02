/**
 * confirm-modal.js
 *
 * Modale de confirmation générique réutilisable pour tous les formulaires
 * de suppression de l'application.
 *
 * Utilisation dans un template Twig — ajouter sur le <form> :
 *   data-confirm-title   Titre affiché dans la modale.
 *   data-confirm-name    Nom de l'élément ciblé (optionnel, mis en gras).
 *   data-confirm-detail  Message de détail (défaut : "Cette action est irréversible.").
 *   data-confirm-label   Texte du bouton de confirmation (défaut : "Supprimer").
 *   data-confirm-type    Style de la modale (défaut : 'danger').
 *
 * La soumission du formulaire est interceptée via délégation sur document.
 * Un flag data-confirmed='1' est posé après confirmation pour laisser passer
 * la seconde soumission (celle déclenchée par le bouton "Confirmer").
 */

/* ═══════════════════════════════════════════
   Modale de confirmation — confirm-modal.js
   ═══════════════════════════════════════════ */

(function () {
    'use strict';

    // ── Injection de la modale dans le body ─────────────────────────────
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

    const overlay    = document.getElementById('cm-overlay');
    const titleEl    = document.getElementById('cm-title');
    const msgEl      = document.getElementById('cm-message');
    const cancelBtn  = document.getElementById('cm-cancel');
    const confirmBtn = document.getElementById('cm-confirm');

    /** Formulaire en attente de confirmation ; null si aucune modale n'est ouverte. */
    let pendingForm = null;

    /**
     * Échappe le HTML d'une chaîne pour l'insérer sans risque en innerHTML.
     * Technique : assigner en textContent (safe) puis relire innerHTML (encodé).
     *
     * @param {string} str - Chaîne à échapper.
     * @returns {string} Chaîne avec < > & " encodés en entités HTML.
     */
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // ── Ouvrir ───────────────────────────────────────────────────────────
    /**
     * Affiche la modale configurée avec les données du formulaire déclencheur.
     *
     * @param {HTMLFormElement} form - Le formulaire dont la soumission a été interceptée.
     */
    function open(form) {
        const type   = form.dataset.confirmType   || 'danger';
        const title  = form.dataset.confirmTitle  || 'Confirmer la suppression';
        const name   = form.dataset.confirmName   || '';
        const detail = form.dataset.confirmDetail || 'Cette action est irréversible.';
        const label  = form.dataset.confirmLabel  || 'Supprimer';

        overlay.dataset.type   = type;
        titleEl.textContent    = title;
        confirmBtn.textContent = label;

        msgEl.innerHTML = name
            ? `<strong>${escHtml(name)}</strong><br>${escHtml(detail)}`
            : escHtml(detail);

        overlay.classList.add('cm-visible');
        cancelBtn.focus();
        pendingForm = form;
    }

    // ── Fermer ───────────────────────────────────────────────────────────
    function close() {
        overlay.classList.remove('cm-visible');
        pendingForm = null;
    }

    // ── Événements ───────────────────────────────────────────────────────
    cancelBtn.addEventListener('click', close);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('cm-visible')) close();
    });

    confirmBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        pendingForm.submit();
        close();
    });

    // ── Interception des soumissions de formulaires ───────────────────────
    // Délégation sur document pour couvrir tous les formulaires de la page,
    // y compris ceux injectés dynamiquement après le chargement initial.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        // N'intercepte que les formulaires portant data-confirm-title.
        if (!('confirmTitle' in form.dataset)) return;
        // Laisse passer la soumission si l'utilisateur a déjà confirmé.
        if (form.dataset.confirmed === '1') return;
        e.preventDefault();
        open(form);
    });
}());
