
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-back');
    if (!btn) return;

    e.preventDefault();

    const referrer = document.referrer;
    const fallback = btn.dataset.fallback || '/?uri=home';

    if (referrer && referrer !== window.location.href) {
        window.location.href = referrer;
    } else {
        window.location.href = fallback;
    }
});
