<?php
$currentPath = $_SERVER['PHP_SELF'] ?? '';

$isAdmin = str_contains($currentPath, '/admin/');
$isPartnership = str_contains($currentPath, '/partnership/');

$base = ($isAdmin || $isPartnership) ? '../' : '';
?>
</main>

<footer class="uob-footer">
  <div class="container py-5">
    <div class="row g-4 align-items-start">
      
      <!-- Brand / About -->
      <div class="col-md-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <img src="<?= $base ?>assets/image/THEM/uob_logo.png" alt="UOB Logo" style="height:48px;width:auto;">
          <div>
            <div class="fw-bold fs-5">University of Bahrain</div>
            <div class="fw-bold">جامعة البحرين</div>
          </div>
        </div>

        <p class="mb-2 fw-semibold">UOB Partnerships & Sustainable Impact</p>
        <p class="mb-2 text-muted">الشراكات والأثر المستدام – جامعة البحرين</p>
        <div class="small text-muted">Sakhir, Kingdom of Bahrain | الصخير، مملكة البحرين</div>
      </div>

      <!-- Quick Links -->
      <div class="col-md-4">
        <h6 class="fw-bold mb-3">Quick Links | روابط سريعة</h6>
        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <a class="uob-footer-link" href="<?= $base ?>index.php">
              Home | الرئيسية
            </a>
          </li>
          <li class="mb-2">
            <a class="uob-footer-link" href="<?= $base ?>agreements.php">
              Agreements | الاتفاقيات
            </a>
          </li>
          <li class="mb-2">
            <a class="uob-footer-link" href="<?= $base ?>initiatives.php">
              Initiatives | المبادرات
            </a>
          </li>
          <li class="mb-2">
            <a class="uob-footer-link" href="<?= $base ?>sdg.php">
              SDGs | أهداف التنمية المستدامة
            </a>
          </li>
          <?php if (!empty($agreementWorkspaceEnabled)): ?>
            <li class="mb-2">
              <a class="uob-footer-link" href="<?= $base ?>workspace/agreements.php">
                Agreement Workspace | مساحة عمل الاتفاقيات
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Contact -->
      <div class="col-md-4">
        <h6 class="fw-bold mb-3">Contact Us | تواصل معنا</h6>
        <div class="small text-muted mb-2">Email | البريد: rankings@uob.edu.bh</div>
        <div class="small text-muted mb-2">Phone | الهاتف: 17xxxxxx</div>
      </div>

    </div>

    

    <hr class="my-4">

<div class="small text-muted text-center">
  © <?= date('Y') ?> University of Bahrain - UOB Partnerships & Sustainable Impact <br>
  جميع الحقوق محفوظة © <?= date('Y') ?> جامعة البحرين - الشراكات والأثر المستدام
  <br><br>
  <span style="opacity:.75">Designed & Developed by Deema Falah</span>

</div>

  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base ?>js/script.js"></script>

<script>
/**
 * UOB Global Scroll Animations (REPLAY)
 * - Re-animates every time you enter viewport
 */
(function(){
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReduced) return;

  const selectors = [
    'section', 'main section', '.uob-card', '.kpi', '.chart-wrap', '.details-wrap', '.map-wrap',
    '.card', '.table-responsive', 'table', '.alert', '.accordion', '.list-group',
    '.row > [class*="col-"]',
    '.hero-wow', '.sdg-hero', '.sdg-grid-pro', '.sdg-card-pro',
    '.rk-hero', '.rk-section', '.rk-card', '.rk-photo', '.rk-cta'
  ];

  const nodes = new Set();
  selectors.forEach(sel => document.querySelectorAll(sel).forEach(el => nodes.add(el)));

  const items = [...nodes].filter(el => {
    const r = el.getBoundingClientRect();
    return (r.height > 40 && r.width > 120);
  });

  items.forEach((el, i) => {
    if (!el.classList.contains('uob-reveal')) {
      el.classList.add('uob-reveal');
      if (i % 6 === 1) el.classList.add('left');
      else if (i % 6 === 2) el.classList.add('right');
      else if (i % 6 === 3) el.classList.add('zoom');
    }
  });

  if (!('IntersectionObserver' in window)) {
    items.forEach(el => el.classList.add('in'));
    return;
  }

  const io = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      const el = entry.target;

      if (entry.isIntersecting) {
        el.classList.remove('in');
        void el.offsetWidth;
        el.classList.add('in');
      } else {
        el.classList.remove('in');
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -10% 0px' });

  items.forEach(el => io.observe(el));
})();
</script>

<script>
(function(){
  const slider = document.getElementById('initSlider');
  const track  = document.getElementById('initTrack');
  const prev   = document.getElementById('initPrev');
  const next   = document.getElementById('initNext');
  if(!slider || !track) return;

  let x = 0;
  let speed = 0.35;
  let paused = false;

  const cards = Array.from(track.children).filter(el => el.classList && el.classList.contains('init-card'));
  if(cards.length >= 4){
    cards.forEach(c => track.appendChild(c.cloneNode(true)));
  }

  function maxScroll(){
    return track.scrollWidth / (cards.length >= 4 ? 2 : 1);
  }

  function tick(){
    if(!paused){
      x += speed;
      const m = maxScroll();
      if(m > 0 && x >= m) x = 0;
      track.style.transform = `translateX(${-(x)}px)`;
    }
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);

  slider.addEventListener('mouseenter', ()=> paused = true);
  slider.addEventListener('mouseleave', ()=> paused = false);
  slider.addEventListener('touchstart', ()=> paused = true, {passive:true});
  slider.addEventListener('touchend', ()=> paused = false, {passive:true});

  function jump(dir){
    paused = true;
    x += dir * 380;
    const m = maxScroll();
    if(m > 0){
      if(x < 0) x = m - 10;
      if(x >= m) x = 0;
    }
    track.style.transform = `translateX(${-(x)}px)`;
    setTimeout(()=> paused = false, 900);
  }
  prev && prev.addEventListener('click', ()=> jump(-1));
  next && next.addEventListener('click', ()=> jump(1));
})();
</script>

</body>
</html>
