<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

date_default_timezone_set('Asia/Bahrain');

define('APP_NAME', 'UOB Rankings & Impact Portal');
define('BASE_URL', ''); // خليها فاضية داخل XAMPP
define('OPENAI_API_KEY', '');
define('DATA_DIR', __DIR__ . '/../data');

define('AGREEMENTS_CSV', DATA_DIR . '/agreements.csv');

// Agreement administration now runs through the authenticated PostgreSQL
// workspace. Keep this as a rollout switch until the legacy Agreement pages
// have completed production acceptance testing.
define('AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN', true);

// ✅ فقط هذا نستخدمه للمبادرات
define('INITIATIVES_MASTER', DATA_DIR . '/initiatives_master.csv');

// رفع ملفات (اختياري)
define('UPLOAD_DIR', __DIR__ . '/../uploads');



if (!is_dir(UPLOAD_DIR)) {
  @mkdir(UPLOAD_DIR, 0777, true);
}
