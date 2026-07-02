<?php
$pageTitle = "التصنيفات والمتطلبات";
$pageSubtitle = "دليل عملي سريع لمتطلبات التصنيفات وكيف تكتب خبر/مبادرة مؤهلة.";
$breadcrumb = [
  ['label' => 'التصنيفات والمتطلبات', 'href' => 'rankings.php', 'active' => true],
];

// نخفي شريط العنوان الافتراضي عشان نستخدم Hero
$hidePageHeader = true;
// نخلي الصفحة Full width (بدون container من الهيدر) إذا مشروعك يستخدم هالـ flag
$mainContainer = false;

require_once __DIR__ . '/header.php';

/* صور الصفحة (عدّلها حسب الموجود عندك داخل assets/image) */
$imgHero = 'assets/image/ranking4.png';
$imgAbout = 'assets/image/ranking.png';
$imgTHE = 'assets/image/ranking1.png';
$imgQS  = 'assets/image/ranking2.png';
$imgGM  = 'assets/image/ranking3.png';
?>

<style>
  /* ===== Rankings Page (Local) ===== */
  .rk-wrap{ padding: 24px 0 80px; }
  .rk-container{ width:min(1180px, calc(100% - 40px)); margin:0 auto; }

  .rk-hero{
    position:relative;
    border-radius: 26px;
    overflow:hidden;
    min-height: 420px;
    box-shadow: var(--shadow, 0 18px 44px rgba(2,8,23,.10));
    background: #0b1f3a;
    margin: 22px auto 26px;
  }
  .rk-hero-bg{
    position:absolute; inset:0;
    background-image: url("<?= h($imgHero) ?>");
    background-size: cover;
    background-position: center;
    filter: saturate(1.05) contrast(1.05);
    transform: scale(1.03);
  }
  .rk-hero::after{
    content:"";
    position:absolute; inset:0;
    background: linear-gradient(90deg, rgba(11,31,58,.90) 0%, rgba(11,31,58,.62) 48%, rgba(11,31,58,.20) 100%);
  }
  .rk-hero-inner{
    position:relative;
    z-index:2;
    padding: 44px 38px;
    display:grid;
    gap: 18px;
    max-width: 680px;
  }
  .rk-eyebrow{
    display:inline-flex;
    gap:10px;
    align-items:center;
    font-weight:800;
    letter-spacing:.5px;
    color: rgba(255,255,255,.92);
  }
  .rk-dot{
    width:10px; height:10px;
    border-radius:999px;
    background: var(--uob-gold, #c9a227);
    box-shadow: 0 0 0 6px rgba(201,162,39,.18);
  }
  .rk-title{
    color:#fff;
    font-size: clamp(30px, 4vw, 46px);
    font-weight: 900;
    line-height: 1.06;
    margin:0;
  }
  .rk-sub{
    color: rgba(255,255,255,.86);
    font-size: 15.5px;
    line-height: 1.8;
    margin: 0;
    max-width: 56ch;
  }
  .rk-hero-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top: 6px;
  }
  .rk-btn{
    display:inline-flex; align-items:center; justify-content:center;
    border-radius: 14px;
    padding: 10px 14px;
    font-weight:800;
    border: 1px solid rgba(255,255,255,.18);
    text-decoration:none;
    transition: .18s ease;
    backdrop-filter: blur(6px);
  }
  .rk-btn-primary{
    background: var(--uob-gold, #c9a227);
    color: #0b1f3a;
    border-color: rgba(201,162,39,.55);
  }
  .rk-btn-primary:hover{ transform: translateY(-1px); filter: brightness(1.02); }
  .rk-btn-ghost{
    background: rgba(255,255,255,.12);
    color:#fff;
  }
  .rk-btn-ghost:hover{ background: rgba(255,255,255,.18); transform: translateY(-1px); }

  .rk-pills{
    display:flex; flex-wrap:wrap; gap:10px;
    margin-top: 10px;
  }
  .rk-pill{
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.18);
    color: rgba(255,255,255,.92);
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
  }

  .rk-section{
    padding: 28px 0;
  }
  .rk-kicker{
    color: var(--uob-navy, #0b1f3a);
    font-weight:900;
    letter-spacing:.3px;
    margin:0 0 8px;
  }
  .rk-h2{
    margin:0 0 12px;
    font-weight: 950;
    color: var(--text, #0f172a);
    font-size: clamp(26px, 3.2vw, 40px);
    line-height: 1.15;
  }
  .rk-lead{
    color: var(--muted, #64748b);
    line-height: 1.9;
    margin:0;
    max-width: 72ch;
  }

  .rk-split{
    display:grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 22px;
    align-items:center;
  }
  .rk-split.reverse{ grid-template-columns: .9fr 1.1fr; }
  @media (max-width: 980px){
    .rk-split, .rk-split.reverse{ grid-template-columns: 1fr; }
  }

  .rk-photo{
    border-radius: 22px;
    overflow:hidden;
    box-shadow: var(--shadow-sm, 0 10px 26px rgba(2,8,23,.07));
    background: #fff;
  }
  .rk-photo img{
    width:100%; height: 100%;
    display:block;
    object-fit: cover;
  }

  .rk-card{
    background: var(--card, #fff);
    border: 1px solid var(--border, #e6ebf2);
    border-radius: 22px;
    box-shadow: var(--shadow-sm, 0 10px 26px rgba(2,8,23,.07));
    padding: 18px 18px;
  }
  .rk-mini{
    display:flex; gap:10px; flex-wrap:wrap;
    margin-bottom: 10px;
  }
  .rk-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding: 7px 12px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 13px;
    background: rgba(11,31,58,.06);
    color: var(--uob-navy, #0b1f3a);
    border: 1px solid rgba(11,31,58,.10);
  }
  .rk-badge .b{
    width:9px; height:9px; border-radius:999px;
    background: var(--uob-gold, #c9a227);
  }

  .rk-list{
    margin: 10px 0 0;
    padding: 0 18px 0 0;
    color: var(--muted, #64748b);
    line-height: 1.9;
  }

  .rk-steps{
    margin-top: 14px;
    border-radius: 22px;
    border: 1px solid var(--border, #e6ebf2);
    background: #fff;
    box-shadow: var(--shadow-sm, 0 10px 26px rgba(2,8,23,.07));
    overflow:hidden;
  }
  .rk-step{
    display:flex;
    gap: 14px;
    padding: 14px 16px;
    border-top: 1px solid var(--border, #e6ebf2);
  }
  .rk-step:first-child{ border-top:0; }
  .rk-step-num{
    width: 34px; height: 34px;
    border-radius: 12px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(201,162,39,.16);
    color: var(--uob-navy, #0b1f3a);
    font-weight: 950;
    flex: 0 0 auto;
  }
  .rk-step-title{ margin:0; font-weight: 950; color: var(--text, #0f172a); }
  .rk-step-desc{ margin: 2px 0 0; color: var(--muted, #64748b); line-height: 1.8; font-size: 14px; }

  .rk-cta{
    margin-top: 26px;
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(11,31,58,1) 0%, rgba(16,42,76,1) 60%, rgba(11,31,58,1) 100%);
    color:#fff;
    padding: 26px 22px;
    box-shadow: var(--shadow, 0 18px 44px rgba(2,8,23,.10));
    overflow:hidden;
    position:relative;
  }
  .rk-cta::after{
    content:"";
    position:absolute; inset:-80px;
    background: radial-gradient(circle at 30% 30%, rgba(201,162,39,.22), transparent 55%);
  }
  .rk-cta-inner{ position:relative; z-index:2; display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
  .rk-cta h3{ margin:0; font-weight:950; }
  .rk-cta p{ margin:6px 0 0; color: rgba(255,255,255,.86); line-height:1.8; max-width: 70ch; }

  /* Bootstrap accordion tweaks (optional) */
  .accordion .accordion-item{
    border: 1px solid var(--border, #e6ebf2);
    border-radius: 16px;
    overflow:hidden;
    margin-bottom: 10px;
  }
  .accordion-button{
    font-weight: 900;
    color: var(--uob-navy, #0b1f3a);
  }
</style>

<div class="rk-wrap">
  <div class="rk-container">

    <!-- HERO -->
    <section class="rk-hero" data-aos="fade-up">
      <div class="rk-hero-bg"></div>
      <div class="rk-hero-inner">
        <div class="rk-eyebrow"><span class="rk-dot"></span> UOB Rankings & Requirements</div>
        <h1 class="rk-title"><?= h($pageTitle) ?></h1>
        <p class="rk-sub"><?= h($pageSubtitle) ?></p>

        <div class="rk-pills">
          <span class="rk-pill">THE Impact</span>
          <span class="rk-pill">QS</span>
          <span class="rk-pill">GreenMetric</span>
          <span class="rk-pill">Youth Support</span>
        </div>

        <div class="rk-hero-actions">
          <a class="rk-btn rk-btn-primary" href="admin/add-initiative.php">قدّم مبادرة الآن</a>
          <a class="rk-btn rk-btn-ghost" href="#the">ابدأ بـ THE</a>
          <a class="rk-btn rk-btn-ghost" href="#qs">QS</a>
          <a class="rk-btn rk-btn-ghost" href="#greenmetric">GreenMetric</a>
          <a class="rk-btn rk-btn-ghost" href="#youth">داعم الشباب</a>
        </div>
      </div>
    </section>

    <!-- ABOUT -->
    <section class="rk-section" id="about">
      <div class="rk-split" data-aos="fade-up">
        <div class="rk-photo">
          <img src="<?= h($imgAbout) ?>" alt="UOB Rankings" loading="lazy">
        </div>

        <div>
          <div class="rk-kicker">About</div>
          <h2 class="rk-h2">About UOB Rankings & Requirements</h2>
          <p class="rk-lead">
            هذه الصفحة مصممة كدليل عملي للموظفين: كيف تكتب خبر/مبادرة “تؤثر فعليًا” وتكون قابلة للاحتساب في التصنيفات.
            ركّزنا على الوضوح: نقاط قبول/رفض، صيغة خبر جاهزة، وخطوات تجهيز الأدلة قبل الإرسال.
          </p>

          <div class="rk-steps" data-aos="fade-up" data-aos-delay="80">
            <div class="rk-step">
              <div class="rk-step-num">1</div>
              <div>
                <p class="rk-step-title">ابدأ بالأثر</p>
                <p class="rk-step-desc">لا تكتفي بوصف النشاط — وضّح “ماذا تغيّر؟” ومن المستفيد بالأرقام.</p>
              </div>
            </div>
            <div class="rk-step">
              <div class="rk-step-num">2</div>
              <div>
                <p class="rk-step-title">وثّق الأدلة</p>
                <p class="rk-step-desc">روابط دائمة + تقارير/سياسات منشورة + شريك منفّذ + نتائج قابلة للقياس.</p>
              </div>
            </div>
            <div class="rk-step">
              <div class="rk-step-num">3</div>
              <div>
                <p class="rk-step-title">اربطها بالتصنيف الصحيح</p>
                <p class="rk-step-desc">اختاري التصنيف الأنسب (THE/QS/GreenMetric/Youth) ثم قدّمي المبادرة.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- THE -->
    <section class="rk-section" id="the">
      <div class="rk-split reverse" data-aos="fade-up">
        <div>
          <div class="rk-kicker">THE • Impact</div>
          <h2 class="rk-h2">THE Impact Rankings</h2>

          <div class="rk-card mt-3">
            <div class="rk-mini">
              <span class="rk-badge"><span class="b"></span> يقبل</span>
              <span class="rk-badge"><span class="b"></span> لا يقبل</span>
              <span class="rk-badge"><span class="b"></span> صيغة خبر</span>
            </div>

            <p class="rk-lead" style="margin-bottom:10px;">
              يشترط أثر خارجي + أرقام + سياسة منشورة + شراكة منفذة + رابط دائم.
            </p>

            <div class="accordion" id="accTHE">
              <div class="accordion-item">
                <h2 class="accordion-header" id="the-ok-h">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#the-ok" aria-expanded="true" aria-controls="the-ok">
                    ✅ يقبل
                  </button>
                </h2>
                <div id="the-ok" class="accordion-collapse collapse show" aria-labelledby="the-ok-h" data-bs-parent="#accTHE">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>أثر خارجي حقيقي</li>
                      <li>أرقام واضحة (عدد مستفيدين/نسبة/تحسن/خفض…)</li>
                      <li>سياسة منشورة أو وثيقة رسمية</li>
                      <li>شراكة منفذة (مش مو بس توقيع)</li>
                      <li>رابط دائم (URL) للأدلة</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="the-no-h">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#the-no" aria-expanded="false" aria-controls="the-no">
                    ❌ لا يقبل
                  </button>
                </h2>
                <div id="the-no" class="accordion-collapse collapse" aria-labelledby="the-no-h" data-bs-parent="#accTHE">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>نشاط داخلي فقط بدون أثر على المجتمع</li>
                      <li>صور بدون رابط/مصدر دائم</li>
                      <li>MoU بدون تنفيذ/نتائج</li>
                      <li>خبر بدون أرقام أو نتيجة قابلة للقياس</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="the-format-h">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#the-format" aria-expanded="false" aria-controls="the-format">
                    📝 صيغة الخبر المقبولة (قالب سريع)
                  </button>
                </h2>
                <div id="the-format" class="accordion-collapse collapse" aria-labelledby="the-format-h" data-bs-parent="#accTHE">
                  <div class="accordion-body">
                    <div class="rk-card" style="padding:14px;">
                      <div style="font-weight:950; color:var(--uob-navy,#0b1f3a); margin-bottom:6px;">اكتب الخبر بهذه الصيغة:</div>
                      <div style="color:var(--muted,#64748b); line-height:1.9;">
                        المشكلة المجتمعية + دور الجامعة + الفئة المستهدفة + عدد المشاركين/المستفيدين + الشريك + نتيجة قابلة للقياس + ذكر SDG
                      </div>
                    </div>
                    <a class="btn btn-primary btn-sm mt-3" href="admin/add-initiative.php">قدّم مبادرة</a>
                  </div>
                </div>
              </div>
            </div><!-- /accordion -->
          </div><!-- /card -->
        </div>

        <div class="rk-photo">
          <img src="<?= h($imgTHE) ?>" alt="THE Impact" loading="lazy">
        </div>
      </div>
    </section>

    <!-- QS -->
    <section class="rk-section" id="qs">
      <div class="rk-split" data-aos="fade-up">
        <div class="rk-photo">
          <img src="<?= h($imgQS) ?>" alt="QS" loading="lazy">
        </div>

        <div>
          <div class="rk-kicker">QS • World / Sustainability</div>
          <h2 class="rk-h2">QS (World / Sustainability)</h2>

          <div class="rk-card mt-3">
            <p class="rk-lead" style="margin-bottom:10px;">
              يركز على السمعة، البحث، التوظيف، والتعاون الدولي (ومحور الاستدامة في QS Sustainability).
            </p>

            <div class="accordion" id="accQS">
              <div class="accordion-item">
                <h2 class="accordion-header" id="qs-ok-h">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#qs-ok" aria-expanded="true" aria-controls="qs-ok">
                    ✅ الخبر المقبول
                  </button>
                </h2>
                <div id="qs-ok" class="accordion-collapse collapse show" aria-labelledby="qs-ok-h" data-bs-parent="#accQS">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>إنجاز بحثي قوي (ورقة/استشهادات/تمويل)</li>
                      <li>تعاون دولي (مشروع مشترك/زيارة/مخرجات)</li>
                      <li>تصنيف/جائزة/اعتماد عالمي موثّق</li>
                      <li>شراكة صناعية بنتائج</li>
                      <li>أرقام توظيف/نتائج قابلة للقياس</li>
                    </ul>
                    <a class="btn btn-primary btn-sm mt-3" href="initiatives.php?ranking=QS">استعرض مبادرات QS</a>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="qs-no-h">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#qs-no" aria-expanded="false" aria-controls="qs-no">
                    ⚠️ لا يركز كثيرًا على
                  </button>
                </h2>
                <div id="qs-no" class="accordion-collapse collapse" aria-labelledby="qs-no-h" data-bs-parent="#accQS">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>نشاط توعوي بسيط بدون مخرجات</li>
                      <li>فعالية داخلية صغيرة بدون نتائج موثقة</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div><!-- /accordion -->
          </div>
        </div>
      </div>
    </section>

    <!-- GreenMetric -->
    <section class="rk-section" id="greenmetric">
      <div class="rk-split reverse" data-aos="fade-up">
        <div>
          <div class="rk-kicker">GreenMetric • Sustainability</div>
          <h2 class="rk-h2">GreenMetric</h2>

          <div class="rk-card mt-3">
            <p class="rk-lead" style="margin-bottom:10px;">
              يركز على الطاقة، المياه، النفايات، النقل، والمساحات الخضراء (بأدلة تشغيلية داخل الحرم).
            </p>

            <div class="accordion" id="accGM">
              <div class="accordion-item">
                <h2 class="accordion-header" id="gm-ok-h">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#gm-ok" aria-expanded="true" aria-controls="gm-ok">
                    ✅ يقبل
                  </button>
                </h2>
                <div id="gm-ok" class="accordion-collapse collapse show" aria-labelledby="gm-ok-h" data-bs-parent="#accGM">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>مشروع فعلي داخل الحرم (تنفيذ واضح)</li>
                      <li>أرقام استهلاك/خفض (kWh/m³/طن…)</li>
                      <li>تقارير قياس/متابعة</li>
                      <li>بنية تحتية مستدامة موثقة</li>
                    </ul>
                    <a class="btn btn-primary btn-sm mt-3" href="initiatives.php?ranking=GreenMetric">استعرض مبادرات GreenMetric</a>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="gm-no-h">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#gm-no" aria-expanded="false" aria-controls="gm-no">
                    ❌ لا يقبل
                  </button>
                </h2>
                <div id="gm-no" class="accordion-collapse collapse" aria-labelledby="gm-no-h" data-bs-parent="#accGM">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>ورشة توعوية بدون تطبيق/قياس</li>
                      <li>صور فقط بدون بيانات تشغيلية</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div><!-- /accordion -->
          </div>
        </div>

        <div class="rk-photo">
          <img src="<?= h($imgGM) ?>" alt="GreenMetric" loading="lazy">
        </div>
      </div>
    </section>

    <!-- Youth Support -->
    <section class="rk-section" id="youth">
      <div class="rk-split" data-aos="fade-up">
        <div class="rk-photo">
          <!-- إذا ما عندك صورة خاصة لداعم الشباب خليه نفس about أو أي صورة -->
          <img src="<?= h($imgAbout) ?>" alt="Youth Support" loading="lazy">
        </div>

        <div>
          <div class="rk-kicker">Youth Support • Ministry</div>
          <h2 class="rk-h2">مبادرة داعم الشباب</h2>

          <div class="rk-card mt-3">
            <p class="rk-lead" style="margin-bottom:10px;">
              يركز على تمكين الشباب + عدد المستفيدين + مهارات قيادية/توظيفية + شراكة مع الوزارة.
            </p>

            <div class="accordion" id="accYouth">
              <div class="accordion-item">
                <h2 class="accordion-header" id="y-ok-h">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#y-ok" aria-expanded="true" aria-controls="y-ok">
                    ✅ يجب أن يحتوي الخبر على
                  </button>
                </h2>
                <div id="y-ok" class="accordion-collapse collapse show" aria-labelledby="y-ok-h" data-bs-parent="#accYouth">
                  <div class="accordion-body">
                    <ul class="rk-list">
                      <li>عدد الشباب المستفيدين</li>
                      <li>الفئة العمرية</li>
                      <li>نوع التمكين (مهارات/توظيف/قيادة…)</li>
                      <li>أثر مباشر عليهم + دليل/رابط</li>
                    </ul>

                    <a class="btn btn-primary btn-sm mt-3" href="admin/add-initiative.php">قدّم مبادرة</a>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="y-tip-h">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#y-tip" aria-expanded="false" aria-controls="y-tip">
                    💡 نصيحة سريعة
                  </button>
                </h2>
                <div id="y-tip" class="accordion-collapse collapse" aria-labelledby="y-tip-h" data-bs-parent="#accYouth">
                  <div class="accordion-body">
                    <div class="rk-card" style="padding:14px;">
                      <div style="font-weight:950; color:var(--uob-navy,#0b1f3a); margin-bottom:6px;">
                        اذكري “قبل/بعد”
                      </div>
                      <div style="color:var(--muted,#64748b); line-height:1.9;">
                        مثال: قبل البرنامج نسبة توظيف X% وبعده X%، أو عدد شهادات/فرص تدريب/ساعات إرشاد… مع رابط.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div><!-- /accordion -->
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="rk-cta" data-aos="zoom-in">
      <div class="rk-cta-inner">
        <div>
          <h3>جاهزة؟ قدّمي مبادرتك بالشكل الصحيح</h3>
          <p>
            جهّزي: (الأثر + الأرقام + الشريك + الأدلة/الروابط) ثم ارفعي المبادرة.
            إذا تبين، أقدر بعدين أسويلك “قالب نموذج” داخل صفحة الإضافة نفسه (Form) عشان ما تنسين أي حقل.
          </p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <a class="rk-btn rk-btn-primary" href="admin/add-initiative.php">قدّم مبادرة</a>
          <a class="rk-btn rk-btn-ghost" href="#about">ارجع للأعلى</a>
        </div>
      </div>
    </section>

  </div><!-- /rk-container -->
</div><!-- /rk-wrap -->

<?php require_once __DIR__ . '/footer.php'; ?>