(function () {

    // ── Toast "Retiré des favoris" ────────────────────────────────────────────
    function showRemoveToast(onDone) {
        let toast = document.getElementById('toast-favoris');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-favoris';
            toast.className = 'toast-favoris';
            toast.innerHTML =
                '<span class="toast-favoris-icon">🗑️</span>' +
                '<span class="toast-favoris-text">' +
                    '<span class="toast-favoris-title">Retiré des favoris</span>' +
                    '<span class="toast-favoris-sub">L\'offre a été supprimée de votre liste</span>' +
                '</span>';
            document.body.appendChild(toast);
        }

        // Petite pause pour que le DOM soit prêt
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('visible');
            });
        });

        setTimeout(function () {
            toast.classList.remove('visible');
            setTimeout(onDone, 300);
        }, 1000);
    }

    // ── Animation bouton "Ajouter aux favoris" ───────────────────────────────
    function animateAdd(btn, onDone) {
        var original = btn.textContent;
        btn.textContent = '♥ Ajouté !';
        btn.classList.add('adding');
        setTimeout(function () {
            btn.classList.remove('adding');
            btn.textContent = original;
            onDone();
        }, 430);
    }

    // ── Interception btn-favori (page offre) ─────────────────────────────────
    document.querySelectorAll('.btn-favori').forEach(function (btn) {
        var form = btn.closest('form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            var isRemoving = btn.classList.contains('actif');
            if (isRemoving) {
                e.preventDefault();
                var f = this;
                showRemoveToast(function () { f.submit(); });
            } else {
                e.preventDefault();
                var f = this;
                animateAdd(btn, function () { f.submit(); });
            }
        });
    });

    // ── Bouton cœur AJAX (liste des stages) ──────────────────────────────────
    document.querySelectorAll('.btn-heart').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idOffre  = btn.dataset.offreId;
            var isActif  = btn.classList.contains('actif');

            // Désactive pendant la requête
            btn.disabled = true;

            var body = new URLSearchParams();
            body.append('id_offre', idOffre);

            fetch('/?uri=wishlist-toggle', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.en_favori) {
                    // Vient d'être ajouté
                    btn.classList.add('actif');
                    btn.title = 'Retirer des favoris';
                    btn.classList.add('adding');
                    setTimeout(function () { btn.classList.remove('adding'); }, 430);
                } else {
                    // Vient d'être retiré
                    btn.classList.remove('actif');
                    btn.title = 'Ajouter aux favoris';
                    showRemoveToast(function () {});
                }
            })
            .catch(function () { btn.disabled = false; });
        });
    });

    // ── Interception btn-retirer (page profil) ───────────────────────────────
    document.querySelectorAll('.btn-retirer').forEach(function (btn) {
        var form = btn.closest('form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var f = this;
            var card = btn.closest('.favori-card');
            if (card) {
                card.classList.add('removing');
            }
            showRemoveToast(function () { f.submit(); });
        });
    });

}());
