// menu-burger.js

document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("mobile-toggle");
  const navMobile = document.getElementById("nav-mobile");

  if (!toggle || !navMobile) return;

  toggle.addEventListener("click", () => {
    toggle.classList.toggle("active");
    navMobile.classList.toggle("active");
  });

  // Fermer le menu en cliquant sur un lien
  navMobile.querySelectorAll("a").forEach(link => {
    link.addEventListener("click", () => {
      toggle.classList.remove("active");
      navMobile.classList.remove("active");
    });
  });
});

document.querySelectorAll('.nav-mobile-dropdown-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const dropdown = btn.closest('.nav-mobile-dropdown');
    dropdown.classList.toggle('open');
  });
});