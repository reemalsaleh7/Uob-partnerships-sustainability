<?php
// includes/functions.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stripBom(string $text): string {
    if (substr($text, 0, 3) === "\xEF\xBB\xBF") return substr($text, 3);
    return $text;
}

function normalizeUtf8(string $text): string {
    $text = stripBom($text);

    if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    if (function_exists('iconv')) {
        $tryEnc = ['CP1256', 'windows-1256', 'ISO-8859-6'];
        foreach ($tryEnc as $enc) {
            $converted = @iconv($enc, 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '' && 
                (function_exists('mb_check_encoding') ? mb_check_encoding($converted, 'UTF-8') : true)) {
                return $converted;
            }
        }
    }

    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-6');
        if (is_string($converted) && $converted !== '') return $converted;
        $converted = @mb_convert_encoding($text, 'UTF-8', 'CP1256');
        if (is_string($converted) && $converted !== '') return $converted;
    }

    return $text;
}

function readCsvRows(string $path, string $delimiter = ','): array {
    if (!file_exists($path)) return [];
    $rows = [];

    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $raw = normalizeUtf8($raw);

    $tmp = fopen('php://temp', 'r+');
    if (!$tmp) return [];

    fwrite($tmp, $raw);
    rewind($tmp);

    while (($data = fgetcsv($tmp, 0, $delimiter)) !== false) {
        $data = array_map(function ($v) {
            $v = $v ?? '';
            $v = normalizeUtf8((string)$v);
            return trim($v);
        }, $data);

        $allEmpty = true;
        foreach ($data as $cell) {
            if ($cell !== '') {
                $allEmpty = false;
                break;
            }
        }

        if ($allEmpty) continue;
        $rows[] = $data;
    }

    fclose($tmp);
    return $rows;
}

/**
 * ترجع الاتفاقيات
 * - افتراضيًا: فقط المعتمد admin_status = معتمد
 * - إذا مررنا false: ترجع الكل للأدمن
 */
function readAgreements(bool $onlyApproved = true): array {
    if (!defined('AGREEMENTS_CSV') || !file_exists(AGREEMENTS_CSV)) return [];
    
    $rows = readCsvRows(AGREEMENTS_CSV);
    if (!$rows) return [];

    $headerIndex = -1;
    for ($i = 0; $i < count($rows); $i++) {
        $first = strtolower(trim((string)($rows[$i][0] ?? '')));

        // ✅ حل مشكلة BOM
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);

      if (str_contains($first, 'agreement_code')) {
            $headerIndex = $i;
            break;
        }
    }

    if ($headerIndex === -1) return [];

    $header = $rows[$headerIndex];
    $out = [];

    for ($i = $headerIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $codeCell = trim((string)($row[0] ?? ''));
        if ($codeCell === '') continue;

        $assoc = [];
        foreach ($header as $idx => $key) {
            $assoc[$key] = trim((string)($row[$idx] ?? ''));
        }

        if ($onlyApproved) {
    $adminStatus = trim((string)($assoc['admin_status'] ?? ''));

    if ($adminStatus === '' || $adminStatus === 'معتمد') {
        // خليها تمر
    } else {
        continue;
    }
}

        $code = trim((string)($assoc['agreement_code'] ?? ''));
        if ($code !== '') {
            $out[$code] = $assoc;
        }
    }

    return $out;
}

/**
 * ترجع جميع المبادرات
 * - افتراضيًا: فقط المعتمد status = معتمد
 * - إذا مررنا false: ترجع الكل للأدمن
 */
function loadAllInitiatives(bool $onlyApproved = true): array {
    if (!defined('INITIATIVES_MASTER') || !file_exists(INITIATIVES_MASTER)) return [];

    $rows = readCsvRows(INITIATIVES_MASTER);
    if (count($rows) <= 1) return [];

    $header = $rows[0];
    $all = [];

    $agreements = readAgreements(false);

    for ($i = 1; $i < count($rows); $i++) {
        $r = $rows[$i];

        $assoc = [];
        foreach ($header as $idx => $key) {
            $assoc[$key] = trim((string)($r[$idx] ?? ''));
        }

       if ($onlyApproved) {
           $status = trim((string)($assoc['status'] ?? ''));

          $approvedStatuses = ['معتمد', 'approved', 'Approved'];

          if (!in_array($status, $approvedStatuses, true)) {
             continue;
           }
       }  

        $code = trim((string)($assoc['agreement_code'] ?? ''));

        if ($code !== '' && isset($agreements[$code])) {
            $assoc['_agreement'] = $agreements[$code];
            $assoc['_agreement_name'] = $agreements[$code]['agreement_name'] ?? '';
        } else {
            $assoc['_agreement'] = null;
            $assoc['_agreement_name'] = '';
        }

        $assoc['_id'] = trim((string)($assoc['id'] ?? '')) ?: ('INIT-' . $i);
        $assoc['_source_file'] = 'initiatives_master.csv';

        $all[] = $assoc;
    }

    return $all;
}

/**
 * تحديث حالة الاتفاقية من الأدمن
 * admin_status = معتمد / مرفوض / قيد المراجعة
 */
function updateAgreementAdminStatus(string $agreementCode, string $newStatus, string $note = ''): bool {
    if (!defined('AGREEMENTS_CSV') || !file_exists(AGREEMENTS_CSV)) return false;
    
    $rows = readCsvRows(AGREEMENTS_CSV);
    if (!$rows || count($rows) < 2) return false;

    $header = $rows[0];

    // التأكد من وجود الأعمدة الإدارية
    $requiredCols = ['admin_status', 'notes_vppd', 'submitted_by', 'submitted_at'];
    foreach ($requiredCols as $col) {
        if (!in_array($col, $header, true)) {
            $header[] = $col;
            for ($i = 1; $i < count($rows); $i++) {
                $rows[$i][] = '';
            }
        }
    }

    $codeIndex = array_search('agreement_code', $header, true);
    $statusIndex = array_search('admin_status', $header, true);
    $noteIndex = array_search('notes_vppd', $header, true);

    if ($codeIndex === false || $statusIndex === false || $noteIndex === false) {
        return false;
    }

    $changed = false;
    for ($i = 1; $i < count($rows); $i++) {
        $rowCode = trim((string)($rows[$i][$codeIndex] ?? ''));
        if ($rowCode === $agreementCode) {
            $rows[$i][$statusIndex] = $newStatus;
            $rows[$i][$noteIndex] = $note;
            $changed = true;
            break;
        }
    }

    if (!$changed) return false;

    $fp = fopen(AGREEMENTS_CSV, 'w');
    if (!$fp) return false;

    fputcsv($fp, $header);
    for ($i = 1; $i < count($rows); $i++) {
        fputcsv($fp, $rows[$i]);
    }
    fclose($fp);

    return true;
}

/**
 * تحديث حالة المبادرة من الأدمن
 * status = معتمد / مرفوض / قيد المراجعة
 */
function updateInitiativeAdminStatus(string $id, string $newStatus, string $note = ''): bool {
    if (!defined('INITIATIVES_MASTER') || !file_exists(INITIATIVES_MASTER)) return false;

    $rows = readCsvRows(INITIATIVES_MASTER);
    if (count($rows) < 2) return false;

    $header = $rows[0];

    // البحث عن أعمدة id, status, notes_vppd
    $idIndex = array_search('id', $header, true);
    if ($idIndex === false) return false;

    // التأكد من وجود عمود status
    if (!in_array('status', $header, true)) {
        $header[] = 'status';
        for ($i = 1; $i < count($rows); $i++) {
            $rows[$i][] = '';
        }
    }

    // التأكد من وجود عمود notes_vppd
    if (!in_array('notes_vppd', $header, true)) {
        $header[] = 'notes_vppd';
        for ($i = 1; $i < count($rows); $i++) {
            $rows[$i][] = '';
        }
    }

    $statusIndex = array_search('status', $header, true);
    $noteIndex = array_search('notes_vppd', $header, true);

    if ($statusIndex === false || $noteIndex === false) return false;

    $changed = false;
    for ($i = 1; $i < count($rows); $i++) {
        $rowId = trim((string)($rows[$i][$idIndex] ?? ''));
        if ($rowId === $id) {
            $rows[$i][$statusIndex] = $newStatus;
            $rows[$i][$noteIndex] = $note;
            $changed = true;
            break;
        }
    }

    if (!$changed) return false;

    $fp = fopen(INITIATIVES_MASTER, 'w');
    if (!$fp) return false;

    fputcsv($fp, $header);
    for ($i = 1; $i < count($rows); $i++) {
        fputcsv($fp, $rows[$i]);
    }
    fclose($fp);

    return true;
}

function computeReadyScore(array $it): array {
    $bool = function (string $v): bool {
        $v = strtolower(trim($v));
        return in_array($v, ['نعم', 'yes', 'true', '1'], true);
    };

    $published = $bool((string)($it['published'] ?? ''));
    $news = trim((string)($it['news_link'] ?? '')) !== '';
    $evidence = trim((string)($it['images_link'] ?? '')) !== '';
    $numbers = trim((string)($it['beneficiaries'] ?? '')) !== '';
    $outcome = trim((string)($it['outputs'] ?? '')) !== '';
    $sdg = trim((string)($it['sdg_primary'] ?? '')) !== '';

    $supportsQS = $bool((string)($it['supports_qs'] ?? ''));
    $supportsGM = $bool((string)($it['supports_greenmetric'] ?? ''));
    $supportsTHE = $bool((string)($it['supports_the'] ?? ''));

    $base = 0;
    $base += $sdg ? 15 : 0;
    $base += $numbers ? 15 : 0;
    $base += $outcome ? 15 : 0;
    $base += $published ? 10 : 0;
    $base += $news ? 10 : 0;
    $base += $evidence ? 10 : 0;

    $tags = [];
    if ($supportsTHE) $tags[] = 'THE';
    if ($supportsQS) $tags[] = 'QS';
    if ($supportsGM) $tags[] = 'GreenMetric';
    if (!$tags) $tags[] = '—';

    return [
        'tags' => $tags,
        'the' => min(100, $base + ($supportsTHE ? 25 : 0)),
        'qs' => min(100, $base + ($supportsQS ? 25 : 0)),
        'gm' => min(100, $base + ($supportsGM ? 25 : 0)),
    ];
}

function paginate(array $items, int $page, int $perPage): array {
    $total = count($items);
    $pages = (int)ceil($total / $perPage);
    $page = max(1, min($pages ?: 1, $page));
    $offset = ($page - 1) * $perPage;

    return [
        'items' => array_slice($items, $offset, $perPage),
        'total' => $total,
        'pages' => $pages ?: 1,
        'page' => $page,
        'perPage' => $perPage,
    ];
}

/**
 * إضافة مبادرة جديدة
 */
function addInitiative(array $data): bool {
    if (!defined('INITIATIVES_MASTER')) return false;
    
    $fields = [
        'id', 'agreement_code', 'initiative_number', 'entity', 'coordinator', 'title', 'type',
        'start_date', 'end_date', 'location', 'description', 'target_group', 'beneficiaries',
        'sdg_primary', 'sdg_secondary', 'supports_qs', 'supports_greenmetric', 'supports_the',
        'published', 'news_link', 'images_link', 'outputs', 'notes_entity', 'notes_vppd',
        'status', 'submitted_by', 'submitted_at'
    ];
    
    // إنشاء الملف إذا لم يكن موجوداً
    if (!file_exists(INITIATIVES_MASTER)) {
        $fp = fopen(INITIATIVES_MASTER, 'w');
        fputcsv($fp, $fields);
        fclose($fp);
    }
    
    // إضافة البيانات
    $fp = fopen(INITIATIVES_MASTER, 'a');
    $row = [];
    foreach ($fields as $f) {
        $row[] = $data[$f] ?? '';
    }
    fputcsv($fp, $row);
    fclose($fp);
    
    return true;
}

/**
 * إضافة اتفاقية جديدة
 */
function addAgreement(array $data): bool {
    if (!defined('AGREEMENTS_CSV')) return false;
    
    $fields = [
        'agreement_code', 'agreement_name', 'agreement_type', 'partner_entity', 'entity_type',
        'country', 'start_date', 'end_date', 'auto_renew', 'owner_entity', 'status',
        'summary', 'goals', 'supports_sdg', 'sdg_goals'
    ];
    
    // إنشاء الملف إذا لم يكن موجوداً
    if (!file_exists(AGREEMENTS_CSV)) {
        $fp = fopen(AGREEMENTS_CSV, 'w');
        $header = array_merge($fields, ['admin_status', 'notes_vppd', 'submitted_by', 'submitted_at']);
        fputcsv($fp, $header);
        fclose($fp);
    }
    
    // إضافة البيانات
    $fp = fopen(AGREEMENTS_CSV, 'a');
    $row = [];
    foreach ($fields as $f) {
        $row[] = $data[$f] ?? '';
    }
    $row[] = $data['admin_status'] ?? 'معتمد'; // admin_status
    $row[] = $data['notes_vppd'] ?? '';             // notes_vppd
    $row[] = $data['submitted_by'] ?? '';
    $row[] = $data['submitted_at'] ?? date('Y-m-d H:i:s');
    fputcsv($fp, $row);
    fclose($fp);
    
    return true;
}


function notificationExistsByReference(string $type, string $referenceId): bool {
    $file = DATA_DIR . '/notifications.csv';
    if (!file_exists($file)) return false;

    $fp = fopen($file, 'r');
    if (!$fp) return false;

    $header = fgetcsv($fp);
    if (!$header) {
        fclose($fp);
        return false;
    }

    while (($row = fgetcsv($fp)) !== false) {
        $row = array_pad($row, count($header), '');
        $n = array_combine($header, $row);

        if (
            ($n['type'] ?? '') === $type &&
            ($n['reference_id'] ?? '') === $referenceId
        ) {
            fclose($fp);
            return true;
        }
    }

    fclose($fp);
    return false;
}

function addNotification(array $data): bool {
    $file = DATA_DIR . '/notifications.csv';

    $header = [
        'notification_id',
        'target_type',
        'target_college',
        'target_department',
        'target_email',
        'title',
        'message',
        'type',
        'reference_id',
        'status',
        'created_by',
        'created_at',
        'is_read'
    ];

    if (!file_exists($file) || filesize($file) === 0) {
        $fp = fopen($file, 'w');
        fputcsv($fp, $header);
        fclose($fp);
    }

    $type = $data['type'] ?? 'info';
    $referenceId = $data['reference_id'] ?? '';

    if ($referenceId !== '' && notificationExistsByReference($type, $referenceId)) {
        return true;
    }

    $row = [
        'NOT-' . date('YmdHis') . '-' . rand(100, 999),
        $data['target_type'] ?? 'all',
        $data['target_college'] ?? '',
        $data['target_department'] ?? '',
        $data['target_email'] ?? '',
        $data['title'] ?? '',
        $data['message'] ?? '',
        $type,
        $referenceId,
        'active',
        $data['created_by'] ?? '',
        date('Y-m-d H:i:s'),
        '0'
    ];

    $fp = fopen($file, 'a');
    fputcsv($fp, $row);
    fclose($fp);

    return true;
}

function markAllNotificationsAsRead(): void {
    $file = DATA_DIR . '/notifications.csv';
    if (!file_exists($file)) return;

    $rows = [];
    $fp = fopen($file, 'r');
    if (!$fp) return;

    $header = fgetcsv($fp);
    if (!$header) {
        fclose($fp);
        return;
    }

    while (($row = fgetcsv($fp)) !== false) {
        $row = array_pad($row, count($header), '');
        $n = array_combine($header, $row);
        $n['is_read'] = '1';
        $rows[] = $n;
    }

    fclose($fp);

    $fp = fopen($file, 'w');
    fputcsv($fp, $header);

    foreach ($rows as $n) {
        $line = [];
        foreach ($header as $h) {
            $line[] = $n[$h] ?? '';
        }
        fputcsv($fp, $line);
    }

    fclose($fp);
}
