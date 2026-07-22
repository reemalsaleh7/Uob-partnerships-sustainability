<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$currentAdminPage = basename($_SERVER['PHP_SELF']);
$agreementAdminUrl = defined('AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN')
  && AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN
    ? '../workspace/agreements.php'
    : 'agreements.php';
$agreementWorkflowUrl = defined('AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN')
  && AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN
    ? '../workspace/workflow-inbox.php'
    : 'review-agreements.php';

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
    'href'  => $agreementAdminUrl,
    'match' => ['agreements.php', 'add-agreement.php', 'edit-agreement.php']
  ],
  [
    'title' => 'مهام الاتفاقيات',
    'href'  => $agreementWorkflowUrl,
    'match' => []
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
    background:#fff;
    border:1px solid #e6ebf2;
    border-radius:18px;
    padding: 14px;
    box-shadow:0 12px 30px rgba(2,8,23,.07);
  }

  .admin-topbar-title{
    color:#0b1f3a;
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
    background:#f8fafc;
    color:#0b1f3a;
    border:1px solid #e6ebf2;
    transition: all .25s ease;
  }

  .admin-topbar-link:hover{
    background:#f2f5fb;
    color:#0b1f3a;
    transform: translateY(-2px);
  }

  .admin-topbar-link.active{
    background: linear-gradient(135deg, #c9a227 0%, #e6c35a 100%);
    color:#0b1f3a;
    border-color: transparent;
    box-shadow:0 8px 18px rgba(201,162,39,.22);
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
