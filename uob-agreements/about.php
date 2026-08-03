<?php
$pageTitle = 'About';
$pageSubtitle = '';
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

$isArabic = ($lang === 'ar');

$content = [
  'ar' => [
    'title' => 'عن البوابة',
    'subtitle' => 'بوابة الاستدامة والشراكات – جامعة البحرين',
    'description' => 'بوابة الاستدامة والشراكات في جامعة البحرين هي منصة رسمية ورقمية متكاملة لعرض وإدارة شراكات واتفاقيات ومبادرات الجامعة المرتبطة بالاستدامة، حيث تتيح الوصول إلى بيانات الاتفاقيات واستعراض تفاصيلها، وتعرض مبادرات جامعة البحرين وتربطها بالاتفاقيات وأهداف التنمية المستدامة والتصنيفات العالمية، كما توضح مساهمة الجامعة في تحقيق أهداف التنمية المستدامة من خلال المبادرات والاتفاقيات والأنشطة الداعمة لكل هدف؛ بما يسهم في تنظيم البيانات، وتعزيز الشفافية، وتسهيل الاستعراض والتحليل والوصول إلى المعلومات للطلبة والجهات المعنية.',
  ],
  'en' => [
    'title' => 'About the Portal',
    'subtitle' => 'Sustainability and Partnerships Portal – University of Bahrain',
    'description' => 'The University of Bahrain Sustainability and Partnerships Portal is an official, integrated digital platform for presenting and managing the University’s sustainability-related partnerships, agreements, and initiatives. It provides access to agreement data and details, presents University initiatives and links them with agreements, the Sustainable Development Goals, and global rankings, and demonstrates the University’s contribution to the SDGs through the initiatives, agreements, and activities supporting each goal—helping organize data, enhance transparency, and make information easier to browse, analyze, and access for students and relevant stakeholders.',
  ],
];

$copy = $content[$isArabic ? 'ar' : 'en'];
?>

<style>
:root {
  --about-navy: #073b66;
  --about-blue: #0d6685;
  --about-gold: #b89536;
  --about-ink: #415466;
  --about-surface: #f5f8fa;
  --about-sticky-offset: 72px;
}

/* يبقى شريط التنقل ظاهرًا مثل الحركة المرجعية */
.uob-navbar {
  position: sticky !important;
  top: 0;
  z-index: 1030;
}

.about-page {
  position: relative;
  overflow: visible;
  background: var(--about-surface);
}

.about-hero {
  position: absolute;
  inset: 0;
  background: url('assets/image/hero/UoB_PSSO-1024x576.jpg') center 48% / cover no-repeat;
  transform: scale(1.015);
  animation: aboutHeroZoom 8s ease-out both;
}

.about-hero-section {
  position: sticky;
  top: var(--about-sticky-offset);
  z-index: 0;
  display: flex;
  min-height: 340px;
  align-items: flex-end;
  overflow: hidden;
  background: var(--about-navy);
  will-change: transform;
}

.about-hero-section::before {
  content: '';
  position: absolute;
  z-index: 1;
  inset: 0;
  background:
    linear-gradient(90deg, rgba(4, 37, 69, .94) 0%, rgba(4, 45, 78, .72) 44%, rgba(4, 45, 78, .22) 100%),
    linear-gradient(0deg, rgba(3, 31, 59, .62) 0%, transparent 58%);
}

[dir='rtl'] .about-hero-section::before {
  background:
    linear-gradient(270deg, rgba(4, 37, 69, .94) 0%, rgba(4, 45, 78, .72) 44%, rgba(4, 45, 78, .22) 100%),
    linear-gradient(0deg, rgba(3, 31, 59, .62) 0%, transparent 58%);
}

.about-hero-section::after {
  content: '';
  position: absolute;
  z-index: 2;
  right: 0;
  bottom: 0;
  left: 0;
  height: 5px;
  background: linear-gradient(90deg, #8e6a1e, #d8bd68, #8e6a1e);
}

.about-hero-content {
  position: relative;
  z-index: 3;
  max-width: 760px;
  padding: 66px 0 58px;
  animation: aboutRise .7s ease both;
}

.about-eyebrow {
  display: flex;
  margin-bottom: 12px;
  align-items: center;
  gap: 11px;
  color: #ead48d;
  font-family: 'Cairo', sans-serif;
  font-size: 13px;
  line-height: 1.7;
  font-weight: 700;
}

.about-eyebrow::before {
  content: '';
  width: 35px;
  height: 2px;
  flex: 0 0 35px;
  background: #d4b95f;
}

.about-hero-content h1 {
  margin: 0;
  color: #fff;
  font-family: 'Cairo', sans-serif;
  font-size: clamp(30px, 4vw, 44px);
  line-height: 1.35;
  font-weight: 800;
  letter-spacing: -.015em;
  text-shadow: 0 4px 18px rgba(0, 0, 0, .2);
}

.about-content-section {
  position: relative;
  z-index: 2;
  min-height: 420px;
  padding: 0 0 74px;
  overflow: hidden;
  background:
    radial-gradient(circle at 12% 85%, rgba(184, 149, 54, .09) 0 105px, transparent 106px),
    var(--about-surface);
  box-shadow: 0 -12px 32px rgba(3, 31, 59, .08);
}

.about-content-section::after {
  content: '';
  position: absolute;
  right: -130px;
  bottom: -180px;
  width: 370px;
  height: 370px;
  border: 1px solid rgba(7, 59, 102, .08);
  border-radius: 50%;
  pointer-events: none;
}

.about-text-wrap {
  position: relative;
  z-index: 1;
  display: flow-root;
}

.about-text-panel {
  position: relative;
  margin-top: -42px;
  padding: 42px clamp(28px, 5vw, 62px) 44px;
  overflow: hidden;
  border: 1px solid rgba(7, 59, 102, .1);
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 22px 55px rgba(7, 59, 102, .11);
  animation: aboutRise .7s ease .12s both;
}

.about-text-panel::before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  inset-inline-start: 0;
  width: 6px;
  background: linear-gradient(180deg, var(--about-gold), var(--about-navy));
}

.about-text-panel::after {
  content: '';
  position: absolute;
  top: -38px;
  inset-inline-end: -28px;
  width: 125px;
  height: 125px;
  border: 22px solid rgba(184, 149, 54, .07);
  border-radius: 50%;
}

.about-text-panel p {
  position: relative;
  z-index: 1;
  max-width: 940px;
  margin: 0;
  color: var(--about-ink);
  font-family: 'Cairo', sans-serif;
  font-size: clamp(14px, 1.2vw, 16px);
  line-height: 2.15;
  font-weight: 400;
  letter-spacing: .005em;
}

[dir='rtl'] .about-text-panel p {
  text-align: justify;
  text-justify: inter-word;
}

@keyframes aboutRise {
  from {
    opacity: 0;
    transform: translateY(24px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes aboutHeroZoom {
  from { transform: scale(1.05); }
  to { transform: scale(1.015); }
}

@media (prefers-reduced-motion: reduce) {
  .about-hero,
  .about-hero-content,
  .about-text-panel {
    animation: none;
  }
}

@media (max-width: 768px) {
  .about-hero-section {
    min-height: 285px;
  }

  .about-hero {
    background-position: 58% center;
  }

  .about-hero-content {
    padding: 56px 0 48px;
  }

  .about-eyebrow {
    font-size: 12px;
  }

  .about-content-section {
    padding-bottom: 50px;
  }

  .about-text-panel {
    margin-top: -28px;
    padding: 33px 24px 35px 29px;
    border-radius: 15px;
  }

  .about-text-panel p {
    font-size: 14px;
    line-height: 1.95;
  }

  [dir='rtl'] .about-text-panel p {
    text-align: start;
  }
}
</style>

<main class="about-page">
  <section class="about-hero-section">
    <div class="about-hero" aria-hidden="true"></div>
    <div class="container">
      <div class="about-hero-content">
        <span class="about-eyebrow"><?= h($copy['subtitle']) ?></span>
        <h1><?= h($copy['title']) ?></h1>
      </div>
    </div>
  </section>

  <section class="about-content-section">
    <div class="container about-text-wrap">
      <article class="about-text-panel">
        <p><?= h($copy['description']) ?></p>
      </article>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>