<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$currentAdminPage = basename($_SERVER['PHP_SELF']);

$adminLinks = [
  [
    'title' => 'لوحة الأدمن',
    'href'  => 'dashboard.php',
    'match' => ['dashboard.php']
  ],
  [
    'title' => 'مراجعة المبادرات',
    'href'  => 'review-initiatives.php',
    'match' => ['review-initiatives.php']
  ],
  [
    'title' => 'إدارة الاتفاقيات',
    'href'  => 'agreements.php',
    'match' => ['agreements.php', 'add-agreement.php', 'edit-agreement.php']
  ],
  [
    'title' => 'إدارة المبادرات',
    'href'  => 'initiatives.php',
    'match' => ['initiatives.php', 'add-initiative.php', 'edit-initiative.php']
  ],
  [
    'title' => 'المستخدمون',
    'href'  => 'users.php',
    'match' => ['users.php']
  ],
];
?>

<style>
  .admin-topbar-wrap{
    margin: 18px 0 22px;
  }

  .admin-topbar{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
    justify-content:flex-start;
    background: linear-gradient(135deg, #0b1f3a 0%, #17345c 100%);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 20px;
    padding: 14px;
    box-shadow: 0 14px 30px rgba(11,31,58,.12);
  }

  .admin-topbar-title{
    color:#fff;
    font-weight:800;
    font-size:1rem;
    margin-inline-end:10px;
    padding-inline:8px;
    white-space:nowrap;
  }

  .admin-topbar-links{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
  }

  .admin-topbar-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    padding:10px 18px;
    border-radius:14px;
    text-decoration:none;
    font-weight:700;
    background: rgba(255,255,255,.08);
    color:#fff;
    border:1px solid rgba(255,255,255,.12);
    transition: all .25s ease;
  }

  .admin-topbar-link:hover{
    background:#fff;
    color:#0b1f3a;
    transform: translateY(-2px);
  }

  .admin-topbar-link.active{
    background: linear-gradient(135deg, #c9a227 0%, #e6c35a 100%);
    color:#0b1f3a;
    border-color: transparent;
    box-shadow: 0 10px 22px rgba(201,162,39,.28);
  }

  @media (max-width: 768px){
    .admin-topbar{
      flex-direction:column;
      align-items:stretch;
    }

    .admin-topbar-title{
      margin:0 0 6px 0;
      padding:0 4px;
    }

    .admin-topbar-links{
      width:100%;
    }

    .admin-topbar-link{
      flex:1 1 calc(50% - 10px);
      text-align:center;
    }
  }
</style>

<div class="admin-topbar-wrap">
  <div class="admin-topbar">
    <div class="admin-topbar-title">تنقل الأدمن</div>

    <div class="admin-topbar-links">
      <?php foreach ($adminLinks as $link): ?>
        <?php $isActive = in_array($currentAdminPage, $link['match'], true); ?>
        <a
          class="admin-topbar-link <?= $isActive ? 'active' : '' ?>"
          href="<?= h($link['href']) ?>"
        >
          <?= h($link['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
