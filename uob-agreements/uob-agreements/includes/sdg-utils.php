<?php

if (!function_exists('sdgNormalizeDigits')) {
    function sdgNormalizeDigits(string $text): string {
        return str_replace(
            ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
            ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'],
            $text
        );
    }
}

if (!function_exists('sdgNamesMap')) {
    function sdgNamesMap(): array {
        return [
            1 => ['No Poverty', 'القضاء على الفقر'],
            2 => ['Zero Hunger', 'القضاء على الجوع'],
            3 => ['Good Health and Well-being', 'Good Health and Wellbeing', 'الصحة الجيدة والرفاه'],
            4 => ['Quality Education', 'التعليم الجيد'],
            5 => ['Gender Equality', 'المساواة بين الجنسين'],
            6 => ['Clean Water and Sanitation', 'المياه النظيفة والصرف الصحي'],
            7 => ['Affordable and Clean Energy', 'طاقة نظيفة وبأسعار معقولة'],
            8 => ['Decent Work and Economic Growth', 'العمل اللائق ونمو الاقتصاد'],
            9 => ['Industry, Innovation and Infrastructure', 'Industry Innovation and Infrastructure', 'الصناعة والابتكار والبنية التحتية'],
            10 => ['Reduced Inequalities', 'الحد من أوجه عدم المساواة'],
            11 => ['Sustainable Cities and Communities', 'مدن ومجتمعات مستدامة'],
            12 => ['Responsible Consumption and Production', 'الاستهلاك والإنتاج المسؤولان'],
            13 => ['Climate Action', 'العمل المناخي'],
            14 => ['Life Below Water', 'الحياة تحت الماء'],
            15 => ['Life on Land', 'الحياة في البر'],
            16 => ['Peace, Justice and Strong Institutions', 'Peace Justice and Strong Institutions', 'السلام والعدل والمؤسسات القوية'],
            17 => ['Partnerships for the Goals', 'Partnership for the Goals', 'عقد الشراكات لتحقيق الأهداف'],
        ];
    }
}

if (!function_exists('sdgNumbersFromText')) {
    function sdgNumbersFromText(string $text): array {
        $text = sdgNormalizeDigits(trim($text));
        if ($text === '') return [];

        $found = [];
        if (preg_match_all('/(?:SDGs?|Goals?|الهدف|هدف)?\s*#?\s*(1[0-7]|[1-9])(?=\D|$)/iu', $text, $matches)) {
            foreach ($matches[1] as $number) $found[(int)$number] = true;
        }

        foreach (sdgNamesMap() as $number => $names) {
            foreach ($names as $name) {
                if (function_exists('mb_stripos') ? mb_stripos($text, $name, 0, 'UTF-8') !== false : stripos($text, $name) !== false) {
                    $found[$number] = true;
                    break;
                }
            }
        }

        ksort($found);
        return array_keys($found);
    }
}

if (!function_exists('sdgNormalizeHeader')) {
    function sdgNormalizeHeader(string $header): string {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        return function_exists('mb_strtolower') ? mb_strtolower($header, 'UTF-8') : strtolower($header);
    }
}

if (!function_exists('sdgNumbersFromRow')) {
    function sdgNumbersFromRow(array $row): array {
        $known = [
            'sdg', 'sdgs', 'sdg_primary', 'sdg_secondary', 'sdg_goals',
            'sdg الأساسي', 'sdg ثانوي', 'الأهداف المرتبطة', 'الاهداف المرتبطة',
            'أهداف التنمية المستدامة المرتبطة'
        ];
        $found = [];

        foreach ($row as $header => $value) {
            if (is_array($value) || is_object($value)) continue;
            $normalized = sdgNormalizeHeader((string)$header);
            $isSdgColumn = in_array($normalized, $known, true)
                || preg_match('/(^|[_\s-])sdgs?($|[_\s-])/iu', $normalized)
                || preg_match('/(?:هدف|أهداف).*(?:تنمية|مرتبط)/u', $normalized);
            if (!$isSdgColumn) continue;

            foreach (sdgNumbersFromText((string)$value) as $number) $found[$number] = true;
        }

        ksort($found);
        return array_keys($found);
    }
}

if (!function_exists('sdgInitiativeIsVisible')) {
    function sdgInitiativeIsVisible(array $row, bool $isAdmin): bool {
        if ($isAdmin) return true;
        $status = trim((string)($row['status'] ?? $row['الحالة الإدارية'] ?? ''));
        return $status === '' || $status === 'معتمد' || strtolower($status) === 'approved';
    }
}

if (!function_exists('sdgAgreementIsVisible')) {
    function sdgAgreementIsVisible(array $row, bool $isAdmin): bool {
        if ($isAdmin) return true;
        $status = trim((string)($row['admin_status'] ?? ''));
        return $status === '' || $status === 'معتمد' || strtolower($status) === 'approved';
    }
}
