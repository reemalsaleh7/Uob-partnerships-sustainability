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
  <!-- Toast Notification System -->
<script>
// ============================================
// Toast Notification System
// ============================================

const ToastManager = {
    container: null,
    lang: 'ar', // Default to Arabic
    
    init() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
        
        // Detect language from HTML lang attribute
        this.lang = document.documentElement.lang || 'ar';
    },
    
    show(titleAr, titleEn, messageAr, messageEn, type = 'system', duration = 5000) {
        this.init();
        
        // Choose language based on page language
        const isArabic = this.lang === 'ar';
        const title = isArabic ? titleAr : titleEn;
        const message = isArabic ? messageAr : messageEn;
        
        const icons = {
            'system': '🔔',
            'workflow': '📋',
            'approval': '✅',
            'rejected': '❌',
            'success': '✅',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        
        const icon = icons[type] || '🔔';
        const time = new Date().toLocaleTimeString();
        const isRtl = document.documentElement.dir === 'rtl';
        
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            ${isRtl ? `
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                    <div class="toast-time">${time}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
                <div class="toast-icon">${icon}</div>
            ` : `
                <div class="toast-icon">${icon}</div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                    <div class="toast-time">${time}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
            `}
        `;
        
        this.container.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duration);
        
        toast.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') {
                toast.remove();
            }
        });
    },
    
    // Convenience methods
    test() {
        this.show(
            '📌 اختبار الإشعارات',           // Arabic Title
            '📌 Notification Test',          // English Title
            'تم تشغيل نظام الإشعارات بنجاح!', // Arabic Message
            'Notification system started successfully!', // English Message
            'system',
            5000
        );
    },
    
    system(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'system', duration);
    },
    
    workflow(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'workflow', duration);
    },
    
    approval(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'approval', duration);
    },
    
    success(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'success', duration);
    },
    
    warning(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'warning', duration);
    },
    
    info(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'info', duration);
    },
    
    rejected(titleAr, titleEn, messageAr, messageEn, duration = 5000) {
        this.show(titleAr, titleEn, messageAr, messageEn, 'rejected', duration);
    }
};

// Make available globally
window.ToastManager = ToastManager;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    ToastManager.init();
});

// Show test toast after 2 seconds
setTimeout(() => {
    ToastManager.test();
}, 2000);
// ============================================
// Check for New Notifications (Polling)
// ============================================

let lastCheckTime = Math.floor(Date.now() / 1000);
let isChecking = false;

function checkForNewNotifications() {
    if (isChecking) return;
    isChecking = true;
    
    const url = `/Uob-partnerships-sustainability/uob-agreements/api/check_notifications.php?last_check=${lastCheckTime}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_new && data.notification) {
                ToastManager.show(
                    data.notification.title_ar,
                    data.notification.title_en,
                    data.notification.message_ar,
                    data.notification.message_en,
                    data.notification.type,
                    6000
                );
                
                if (data.notification.timestamp) {
                    lastCheckTime = data.notification.timestamp;
                }
                
                updateBellCount();
            }
            isChecking = false;
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
            isChecking = false;
        });
}

function updateBellCount() {
    fetch(`/Uob-partnerships-sustainability/uob-agreements/api/get_unread_count.php`)
        .then(response => response.json())
        .then(data => {
            if (data.count !== undefined) {
                const badge = document.querySelector('.badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => console.error('Error updating bell count:', error));
}

// Check every 30 seconds
setInterval(checkForNewNotifications, 30000);

// Initial check after 3 seconds
setTimeout(checkForNewNotifications, 3000);
</script>
</body>

</html>
