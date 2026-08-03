<?php
declare(strict_types=1);

function sdgNormalizeText(string $text): string {
    $text = trim(strtr($text, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function sdgGoalNames(): array {
    return [
        1=>['no poverty','القضاء على الفقر'], 2=>['zero hunger','القضاء على الجوع'],
        3=>['good health and well-being','good health and wellbeing','الصحة الجيدة والرفاه'],
        4=>['quality education','التعليم الجيد'], 5=>['gender equality','المساواة بين الجنسين'],
        6=>['clean water and sanitation','المياه النظيفة والصرف الصحي'],
        7=>['affordable and clean energy','طاقة نظيفة وبأسعار معقولة'],
        8=>['decent work and economic growth','العمل اللائق ونمو الاقتصاد','العمل اللائق والنمو الاقتصادي'],
        9=>['industry, innovation and infrastructure','industry innovation and infrastructure','الصناعة والابتكار والبنية التحتية'],
        10=>['reduced inequalities','الحد من أوجه عدم المساواة'],
        11=>['sustainable cities and communities','مدن ومجتمعات مستدامة'],
        12=>['responsible consumption and production','الاستهلاك والإنتاج المسؤولان'],
        13=>['climate action','العمل المناخي'], 14=>['life below water','الحياة تحت الماء'],
        15=>['life on land','الحياة في البر'],
        16=>['peace, justice and strong institutions','peace justice and strong institutions','السلام والعدل والمؤسسات القوية'],
        17=>['partnerships for the goals','عقد الشراكات لتحقيق الأهداف'],
    ];
}

function sdgNumbersFromText(string $text): array {
    $text = sdgNormalizeText($text);
    if ($text === '') return [];
    $found = [];
    if (preg_match_all('/(?:sdg|goal|الهدف|هدف)\s*[-:#]?\s*(1[0-7]|[1-9])(?!\d)/u', $text, $m)) {
        foreach ($m[1] as $n) $found[(int)$n] = true;
    }
    if (preg_match('/^\s*(1[0-7]|[1-9])\s*$/u', $text, $m)) $found[(int)$m[1]] = true;
    foreach (sdgGoalNames() as $number => $names) {
        foreach ($names as $name) {
            if (strpos($text, sdgNormalizeText($name)) !== false) { $found[$number] = true; break; }
        }
    }
    ksort($found);
    return array_keys($found);
}

function sdgHeaderIsRelevant(string $header): bool {
    $h = preg_replace('/[\s_-]+/u', '', sdgNormalizeText($header));
    return strpos($h, 'sdg') !== false || strpos($h, 'goal') !== false || strpos($h, 'هدف') !== false || strpos($h, 'اهداف') !== false || strpos($h, 'أهداف') !== false;
}

function sdgNumbersFromRow(array $row): array {
    $found = [];
    foreach ($row as $key => $value) {
        if (!is_string($key) || !sdgHeaderIsRelevant($key) || is_array($value)) continue;
        foreach (sdgNumbersFromText((string)$value) as $n) $found[$n] = true;
    }
    ksort($found);
    return array_keys($found);
}

function sdgInitiativeIsVisible(array $row, bool $isAdmin): bool {
    if ($isAdmin) return true;
    $status = trim((string)($row['status'] ?? $row['الحالة الإدارية'] ?? ''));
    return in_array(sdgNormalizeText($status), ['', 'معتمد', 'approved'], true);
}

function sdgAgreementIsVisible(array $row, bool $isAdmin): bool {
    if ($isAdmin) return true;
    $status = trim((string)($row['admin_status'] ?? $row['الحالة الإدارية'] ?? ''));
    return in_array(sdgNormalizeText($status), ['', 'معتمد', 'approved'], true);
}

function sdgLoadPortalRows(bool $isAdmin): array {
    $initiatives = function_exists('loadAllInitiatives') ? loadAllInitiatives(false) : [];
    $agreements = function_exists('readAgreements') ? readAgreements(false) : [];
    $initiatives = array_values(array_filter($initiatives, fn($row) => sdgInitiativeIsVisible($row, $isAdmin)));
    $agreements = array_values(array_filter($agreements, fn($row) => sdgAgreementIsVisible($row, $isAdmin)));
    return [$initiatives, $agreements];
}
