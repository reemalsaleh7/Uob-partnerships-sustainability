<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: ../login.php");
  exit;
}

$pageTitle = "مراجعة المبادرات";
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/../header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

/* Actions */
if (isset($_GET['approve'])) {
  updateInitiativeAdminStatus($_GET['approve'], 'معتمد');
  header("Location: review-initiatives.php?tab=approved&lang=" . urlencode($lang));
  exit;
}

if (isset($_GET['reject'])) {
  updateInitiativeAdminStatus($_GET['reject'], 'مرفوض');
  header("Location: review-initiatives.php?tab=rejected&lang=" . urlencode($lang));
  exit;
}

if (isset($_POST['note_id'])) {
  updateInitiativeAdminStatus(
    $_POST['note_id'],
    $_POST['note_status'],
    $_POST['note_text']
  );
}

$tab = $_GET['tab'] ?? 'new';
$allRaw = loadAllInitiatives(false);

$total = count($allRaw);
$newCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$notesCount = 0;

foreach ($allRaw as $it) {
  $status = trim($it['status'] ?? 'قيد المراجعة');
  $note = trim($it['notes_vppd'] ?? '');

  if ($status === 'قيد المراجعة') $newCount++;
  if ($status === 'معتمد') $approvedCount++;
  if ($status === 'مرفوض') $rejectedCount++;
  if ($note !== '') $notesCount++;
}

$all = array_values(array_filter($allRaw, function($it) use ($tab) {
  $status = trim($it['status'] ?? 'قيد المراجعة');
  $note = trim($it['notes_vppd'] ?? '');

  if ($tab === 'new') return $status === 'قيد المراجعة';
  if ($tab === 'approved') return $status === 'معتمد';
  if ($tab === 'rejected') return $status === 'مرفوض';
  if ($tab === 'notes') return $note !== '';

  return true;
}));

$entities = [];
$types = [];
$sdgs = [];

foreach ($allRaw as $it) {
  $entity = trim($it['entity'] ?? '');
  $type = trim($it['type'] ?? '');
  $sdg = trim($it['sdg_primary'] ?? ($it['sdgs'] ?? ''));

  if ($entity !== '') $entities[$entity] = true;
  if ($type !== '') $types[$type] = true;
  if ($sdg !== '') $sdgs[$sdg] = true;
}

$entities = array_keys($entities);
$types = array_keys($types);
$sdgs = array_keys($sdgs);

sort($entities);
sort($types);
sort($sdgs);

function initiativeStatusClass($status) {
  if ($status === 'معتمد') return 'approved';
  if ($status === 'مرفوض') return 'rejected';
  return 'pending';
}
?>

<style>
.admin-init-hero {
  background: #b89a68 !important;
  background-image:
    linear-gradient(135deg, #8f6f3f 0%, #b89a68 55%, #8f6f3f 100%) !important;
  color: #ffffff !important;
}
.admin-init-hero-inner{
  max-width:1220px;
  margin:auto;
  display:grid;
  grid-template-columns:1.1fr .9fr;
  gap:24px;
  align-items:center;
}
.admin-init-hero h1{
  margin:0;
  font-size:clamp(34px,4vw,56px);
  font-weight:950;
}
.admin-init-hero p{
  margin:14px 0 0;
  color:rgba(255,255,255,.86);
  font-weight:800;
  line-height:1.9;
}
.admin-init-stats{
  display:grid;
  grid-template-columns:repeat(2, 220px);
  gap:10px;
  justify-content:end;
}

.admin-init-stat{
  border:1px solid rgba(255,255,255,.16);
  background:rgba(255,255,255,.12);
  border-radius:18px;
  padding:12px 14px;
  cursor:pointer;
  min-height:105px;
}

.admin-init-stat span{
  display:block;
  color:rgba(255,255,255,.72);
  font-size:12px;
  font-weight:850;
}

.admin-init-stat strong{
  display:block;
  color:#fff;
  font-size:26px;
  font-weight:950;
  margin-top:4px;
}
.admin-init-shell{
  max-width:1220px;
  margin:-45px auto 48px;
  padding:0 20px;
  position:relative;
  z-index:5;
}
.admin-init-panel{
  background:#fff;
  border:1px solid rgba(230,235,242,.96);
  border-radius:28px;
  box-shadow:0 24px 60px rgba(2,8,23,.12);
  overflow:hidden;
}
.admin-init-head{
  padding:22px 24px;
  border-bottom:1px solid rgba(230,235,242,.95);
  background:linear-gradient(180deg,#fff,#f8fbff);
  display:flex;
  justify-content:space-between;
  gap:14px;
  flex-wrap:wrap;
}
.admin-init-title{
  color:var(--uob-navy);
  font-size:24px;
  font-weight:950;
  margin:0;
}
.admin-init-tabs{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  padding:18px 24px 0;
  background:#fbfdff;
}
.admin-init-tab{
  padding:10px 16px;
  border-radius:999px;
  background:#eef4ff;
  color:#0b1f3a;
  text-decoration:none;
  font-weight:950;
  border:1px solid #d9e6f7;
}
.admin-init-tab.active{
  background:#b89a68;
  color:#fff;
}
.admin-init-tab:hover{
  color:#b89a68;
}
.admin-init-tools{
  padding:18px 24px;
  border-bottom:1px solid rgba(230,235,242,.95);
  background:#fbfdff;
}
.admin-init-filter-grid{
  display:grid;
  grid-template-columns:2fr 1.2fr 1.2fr 1.2fr auto;
  gap:12px;
}
.admin-init-input{
  min-height:48px;
  border-radius:14px !important;
  border:1px solid #d9e3ef !important;
  background:#fff !important;
  font-weight:800;
}
.admin-init-clear{
  min-height:48px;
  border-radius:14px !important;
  font-weight:900;
}
.admin-init-table-wrap{
  width:100%;
  overflow:auto;
}
.admin-init-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  min-width:1100px;
}
.admin-init-table thead th{
  position:sticky;
  top:0;
background:#b89a68;
  color:#fff;
  padding:14px;
  font-size:13px;
  font-weight:950;
  white-space:nowrap;
}
.admin-init-table tbody td{
  padding:14px;
  border-bottom:1px solid #e8eef6;
  color:#0f172a;
  font-weight:800;
  vertical-align:middle;
}
.admin-init-table tbody tr:hover{
  background:#f8fbff;
}
.admin-title-cell{
  max-width:280px;
  color:var(--uob-navy);
  font-weight:950;
  line-height:1.55;
}
.admin-small{
  font-size:12px;
  color:#64748b;
  font-weight:800;
}
.status-pill{
  display:inline-flex;
  padding:6px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:950;
}
.status-pill.pending{
  background:#fff7ed;
  color:#9a3412;
  border:1px solid #fed7aa;
}
.status-pill.approved{
  background:#dcfce7;
  color:#166534;
  border:1px solid #bbf7d0;
}
.status-pill.rejected{
  background:#fee2e2;
  color:#991b1b;
  border:1px solid #fecaca;
}
.admin-actions{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.admin-btn{
  border:none;
  border-radius:12px;
  min-height:38px;
  padding:8px 12px;
  font-weight:950;
  font-size:13px;
  text-decoration:none;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
}
.btn-view{background:#eef4ff;color:#0b1f3a;}
.btn-approve{background:#16a34a;color:#fff;}
.btn-reject{background:#dc2626;color:#fff;}
.btn-edit{background:#0ea5e9;color:#fff;}
.admin-empty{
  padding:34px;
  text-align:center;
  color:#64748b;
  font-weight:950;
}
.req-hidden{display:none!important;}

.admin-modal{
  position:fixed;
  inset:0;
  z-index:9999;
  display:none;
}
.admin-modal.is-open{display:block;}
.admin-modal-backdrop{
  position:absolute;
  inset:0;
  background:rgba(2,8,23,.55);
}
.admin-modal-panel{
  position:relative;
  max-width:900px;
  max-height:86vh;
  overflow:auto;
  background:#fff;
  margin:6vh auto;
  border-radius:26px;
  box-shadow:0 30px 80px rgba(2,8,23,.28);
  padding:24px;
}
.admin-modal-head{
  display:flex;
  justify-content:space-between;
  gap:14px;
  border-bottom:1px solid #e8eef6;
  padding-bottom:16px;
  margin-bottom:16px;
}
.admin-modal-head h2{
  margin:0;
  color:var(--uob-navy);
  font-weight:950;
}
.admin-close{
  border:none;
  background:#eef4ff;
  color:#0b1f3a;
  width:42px;
  height:42px;
  border-radius:14px;
  font-weight:950;
}
.admin-detail-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}
.admin-detail-box{
  background:#fbfdff;
  border:1px solid #e8eef6;
  border-radius:16px;
  padding:12px 14px;
}
.admin-detail-box span{
  display:block;
  color:#64748b;
  font-size:12px;
  font-weight:850;
}
.admin-detail-box strong{
  display:block;
  color:#0f172a;
  font-weight:950;
}
.admin-desc{
  margin-top:12px;
  background:#fbfdff;
  border:1px solid #e8eef6;
  border-radius:16px;
  padding:14px;
  color:#334155;
  font-weight:850;
  line-height:1.9;
  white-space:pre-wrap;
}
.admin-note-form{
  margin-top:14px;
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.admin-note-form textarea{
  flex:1;
  min-width:260px;
  border-radius:14px;
  padding:10px;
  font-weight:800;
  border:1px solid #d9e3ef;
}

@media(max-width:992px){
  .admin-init-hero-inner{grid-template-columns:1fr;}
  .admin-init-filter-grid{grid-template-columns:1fr 1fr;}
}
@media(max-width:768px){
  .admin-init-filter-grid,.admin-detail-grid{grid-template-columns:1fr;}
  .admin-modal-panel{margin:3vh 12px;}
}
</style>

<section class="admin-init-hero">
  <div class="admin-init-hero-inner">
    <div>
      <h1><?= $isArabic ? 'مراجعة المبادرات' : 'Review Initiatives' ?></h1>
      <p>
        <?= $isArabic
          ? 'لوحة إدارية منظمة لمراجعة المبادرات، فلترتها، اعتمادها، رفضها، وإضافة الملاحظات بسهولة.'
          : 'A structured admin panel to review, filter, approve, reject, and comment on initiatives.'
        ?>
      </p>
    </div>

    <div class="admin-init-stats">
      <div class="admin-init-stat" data-tab-go="all"><span>كل المبادرات</span><strong><?= (int)$total ?></strong></div>
      <div class="admin-init-stat" data-tab-go="new"><span>قيد المراجعة</span><strong><?= (int)$newCount ?></strong></div>
      <div class="admin-init-stat" data-tab-go="approved"><span>المعتمدة</span><strong><?= (int)$approvedCount ?></strong></div>
      <div class="admin-init-stat" data-tab-go="rejected"><span>المرفوضة</span><strong><?= (int)$rejectedCount ?></strong></div>
    </div>
  </div>
</section>

<div class="admin-init-shell">
  <div class="admin-init-panel">

    <div class="admin-init-head">
      <div>
        <h2 class="admin-init-title">جدول المبادرات</h2>
        <div class="admin-small">
          <span id="visibleCount"><?= count($all) ?></span> مبادرة ظاهرة
        </div>
      </div>
    </div>

    <div class="admin-init-tabs">
      <a class="admin-init-tab <?= $tab==='new'?'active':'' ?>" href="?tab=new&lang=<?= h($lang) ?>">جديدة</a>
      <a class="admin-init-tab <?= $tab==='approved'?'active':'' ?>" href="?tab=approved&lang=<?= h($lang) ?>">المقبولة</a>
      <a class="admin-init-tab <?= $tab==='rejected'?'active':'' ?>" href="?tab=rejected&lang=<?= h($lang) ?>">المرفوضة</a>
      <a class="admin-init-tab <?= $tab==='notes'?'active':'' ?>" href="?tab=notes&lang=<?= h($lang) ?>">فيها ملاحظات</a>
      <a class="admin-init-tab <?= $tab==='all'?'active':'' ?>" href="?tab=all&lang=<?= h($lang) ?>"> الكل</a>
    </div>

    <div class="admin-init-tools">
      <div class="admin-init-filter-grid">
        <input id="searchInput" class="form-control admin-init-input" type="search" placeholder="بحث بالعنوان، الكود، الجهة، SDG...">

        <select id="entityFilter" class="form-select admin-init-input">
          <option value="">كل الجهات</option>
          <?php foreach ($entities as $e): ?>
            <option value="<?= h($e) ?>"><?= h($e) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="typeFilter" class="form-select admin-init-input">
          <option value="">كل الأنواع</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="sdgFilter" class="form-select admin-init-input">
          <option value="">كل SDG</option>
          <?php foreach ($sdgs as $s): ?>
            <option value="<?= h($s) ?>"><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>

        <button id="clearFilters" type="button" class="btn btn-outline-secondary admin-init-clear">مسح</button>
      </div>
    </div>

    <div class="admin-init-table-wrap">
      <table class="admin-init-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>المبادرة</th>
            <th>الجهة</th>
            <th>النوع</th>
            <th>SDG</th>
            <th>الحالة</th>
            <th>التاريخ</th>
            <th>إجراءات</th>
          </tr>
        </thead>

        <tbody>
          <?php if (!$all): ?>
            <tr><td colspan="8" class="admin-empty">لا توجد بيانات</td></tr>
          <?php endif; ?>

          <?php foreach ($all as $it): ?>
            <?php
              $id = trim($it['_id'] ?? $it['id'] ?? '');
              $title = trim($it['title'] ?? '');
              $entity = trim($it['entity'] ?? '');
              $type = trim($it['type'] ?? '');
              $sdg = trim($it['sdg_primary'] ?? ($it['sdgs'] ?? ''));
              $date = trim($it['start_date'] ?? '');
              $status = trim($it['status'] ?? 'قيد المراجعة');
              $statusClass = initiativeStatusClass($status);

              $searchText = mb_strtolower(
                $id.' '.$title.' '.$entity.' '.$type.' '.$sdg.' '.($it['agreement_code'] ?? '').' '.($it['_agreement_name'] ?? '')
              );
            ?>

            <tr class="initiative-row"
              data-search="<?= h($searchText) ?>"
              data-entity="<?= h($entity) ?>"
              data-type="<?= h($type) ?>"
              data-sdg="<?= h($sdg) ?>">
              <td>
                <strong><?= h($id ?: '—') ?></strong>
                <div class="admin-small"><?= h($it['agreement_code'] ?? '') ?></div>
              </td>

              <td>
                <div class="admin-title-cell"><?= h($title ?: '—') ?></div>
                <div class="admin-small"><?= h($it['_agreement_name'] ?? '') ?></div>
              </td>

              <td><?= h($entity ?: '—') ?></td>
              <td><?= h($type ?: '—') ?></td>
              <td><?= h($sdg ?: '—') ?></td>

              <td>
                <span class="status-pill <?= h($statusClass) ?>"><?= h($status) ?></span>
              </td>

              <td><?= h($date ?: '—') ?></td>

              <td>
                <div class="admin-actions">
                  <button type="button" class="admin-btn btn-view"
                    data-view='<?= h(json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
                    عرض
                  </button>

                  <a class="admin-btn btn-approve" href="?approve=<?= urlencode($id) ?>&tab=<?= h($tab) ?>&lang=<?= h($lang) ?>">قبول</a>
                  <a class="admin-btn btn-reject" href="?reject=<?= urlencode($id) ?>&tab=<?= h($tab) ?>&lang=<?= h($lang) ?>">رفض</a>
                  <a class="admin-btn btn-edit" href="edit-initiative.php?id=<?= urlencode($id) ?>">تعديل</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<div class="admin-modal" id="initiativeModal">
  <div class="admin-modal-backdrop" id="modalBackdrop"></div>

  <div class="admin-modal-panel">
    <div class="admin-modal-head">
      <div>
        <h2 id="modalTitle">—</h2>
        <div class="admin-small" id="modalId">—</div>
      </div>
      <button class="admin-close" id="modalClose" type="button">✕</button>
    </div>

    <div class="admin-detail-grid">
      <div class="admin-detail-box"><span>الجهة</span><strong id="modalEntity">—</strong></div>
      <div class="admin-detail-box"><span>المنسق</span><strong id="modalCoordinator">—</strong></div>
      <div class="admin-detail-box"><span>النوع</span><strong id="modalType">—</strong></div>
      <div class="admin-detail-box"><span>SDG</span><strong id="modalSdg">—</strong></div>
      <div class="admin-detail-box"><span>تاريخ التنفيذ</span><strong id="modalDate">—</strong></div>
      <div class="admin-detail-box"><span>الحالة</span><strong id="modalStatus">—</strong></div>
    </div>

    <div class="admin-desc" id="modalDescription">—</div>

    <form method="post" class="admin-note-form">
      <input type="hidden" name="note_id" id="modalNoteId">
      <input type="hidden" name="note_status" id="modalNoteStatus">
      <textarea name="note_text" id="modalNoteText" placeholder="اكتب ملاحظة الأدمن..."></textarea>
      <button class="btn btn-dark" type="submit">حفظ الملاحظة</button>
    </form>
  </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const entityFilter = document.getElementById('entityFilter');
const typeFilter = document.getElementById('typeFilter');
const sdgFilter = document.getElementById('sdgFilter');
const clearFilters = document.getElementById('clearFilters');
const visibleCount = document.getElementById('visibleCount');

function applyFilters(){
  const q = (searchInput.value || '').toLowerCase().trim();
  const entity = entityFilter.value;
  const type = typeFilter.value;
  const sdg = sdgFilter.value;

  let count = 0;

  document.querySelectorAll('.initiative-row').forEach(row => {
    const okSearch = !q || (row.dataset.search || '').includes(q);
    const okEntity = !entity || row.dataset.entity === entity;
    const okType = !type || row.dataset.type === type;
    const okSdg = !sdg || row.dataset.sdg === sdg;

    const show = okSearch && okEntity && okType && okSdg;
    row.classList.toggle('req-hidden', !show);
    if(show) count++;
  });

  visibleCount.textContent = count;
}

[searchInput, entityFilter, typeFilter, sdgFilter].forEach(el => {
  el.addEventListener('input', applyFilters);
  el.addEventListener('change', applyFilters);
});

clearFilters.addEventListener('click', () => {
  searchInput.value = '';
  entityFilter.value = '';
  typeFilter.value = '';
  sdgFilter.value = '';
  applyFilters();
});

document.querySelectorAll('[data-tab-go]').forEach(card => {
  card.addEventListener('click', () => {
    window.location.href = 'review-initiatives.php?tab=' + card.dataset.tabGo;
  });
});

const modal = document.getElementById('initiativeModal');
const modalBackdrop = document.getElementById('modalBackdrop');
const modalClose = document.getElementById('modalClose');

function setText(id, value){
  const el = document.getElementById(id);
  el.textContent = value && String(value).trim() !== '' ? value : '—';
}

document.querySelectorAll('.btn-view').forEach(btn => {
  btn.addEventListener('click', () => {
    const data = JSON.parse(btn.dataset.view || '{}');

    setText('modalTitle', data.title);
    setText('modalId', data._id || data.id);
    setText('modalEntity', data.entity);
    setText('modalCoordinator', data.coordinator);
    setText('modalType', data.type);
    setText('modalSdg', data.sdg_primary || data.sdgs);
    setText('modalDate', data.start_date);
    setText('modalStatus', data.status || 'قيد المراجعة');
    setText('modalDescription', data.description);

    document.getElementById('modalNoteId').value = data._id || data.id || '';
    document.getElementById('modalNoteStatus').value = data.status || 'قيد المراجعة';
    document.getElementById('modalNoteText').value = data.notes_vppd || '';

    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  });
});

function closeModal(){
  modal.classList.remove('is-open');
  document.body.style.overflow = '';
}

modalBackdrop.addEventListener('click', closeModal);
modalClose.addEventListener('click', closeModal);
document.addEventListener('keydown', e => {
  if(e.key === 'Escape') closeModal();
});

applyFilters();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>