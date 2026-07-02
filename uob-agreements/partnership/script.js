document.addEventListener('DOMContentLoaded', function () {
  const navbar = document.querySelector('.uob-site-navbar');
  if (navbar) {
    window.addEventListener('scroll', function () {
      navbar.style.boxShadow = window.pageYOffset > 100
        ? '0 12px 30px rgba(2,8,23,.12)'
        : '0 10px 30px rgba(2,8,23,.06)';
    });
  }
});
