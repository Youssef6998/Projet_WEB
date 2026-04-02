/**
 * bouton-haut.js
 *
 * Injecte dynamiquement un bouton "retour en haut de page" et le rend
 * visible uniquement lorsque l'utilisateur a défilé de plus de 300 px.
 *
 * Technique requestAnimationFrame + flag `ticking` : évite d'exécuter
 * la mise à jour de classe à chaque pixel défilé en la planifiant une
 * seule fois par frame (throttling passif).
 */

document.addEventListener('DOMContentLoaded', function () {
    // Crée le bouton et l'insère à la fin du body (positionné en CSS en fixed).
    const btn = document.createElement('button');
    btn.id = 'scroll-top-btn';
    btn.innerHTML = '↑';
    btn.setAttribute('aria-label', 'Retour en haut');
    document.body.appendChild(btn);

    // Flag pour éviter d'empiler plusieurs appels rAF lors d'un défilement rapide.
    let ticking = false;
    window.addEventListener('scroll', function () {
        if (!ticking) {
            requestAnimationFrame(function () {
                // Affiche le bouton après 300 px de défilement, le cache sinon.
                btn.classList.toggle('visible', window.scrollY > 300);
                ticking = false;
            });
            ticking = true;
        }
    });

    // Remonte en douceur jusqu'en haut de la page au clic.
    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
