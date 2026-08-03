<?php

declare(strict_types=1);

final class AgreementClauseExtractionService
{
    private const MAX_FILE_SIZE_BYTES = 10485760;
    private const MAX_EXTRACTED_CHARACTERS = 120000;

    public function extract(array $uploadedFile): array
    {
        $error = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                $error === UPLOAD_ERR_NO_FILE
                    ? 'Choose an Agreement document to extract'
                    : 'The Agreement document upload failed'
            );
        }

        $temporaryPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException(
                'The uploaded Agreement document could not be verified'
            );
        }

        $fileName = basename(str_replace(
            '\\',
            '/',
            (string) ($uploadedFile['name'] ?? '')
        ));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension !== 'docx') {
            throw new InvalidArgumentException(
                'Automatic clause extraction requires a DOCX file'
            );
        }

        $size = filesize($temporaryPath);
        if (
            $size === false
            || $size <= 0
            || $size > self::MAX_FILE_SIZE_BYTES
        ) {
            throw new InvalidArgumentException(
                'The Agreement document must be larger than 0 bytes and no more than 10 MB'
            );
        }

        $this->validateDocxMediaTypeAndSignature($temporaryPath);

        $content = $this->extractDocxContent($temporaryPath);
        $text = $content['text'];
        if ($text === '') {
            throw new InvalidArgumentException(
                'No readable text was found in the DOCX document'
            );
        }

        $language = $this->detectLanguage($text);

        $paragraphs = $this->paragraphs($text);

        return [
            'extracted' => true,
            'language' => $language,
            'language_label' => $language === 'ar' ? 'Arabic' : 'English',
            'fields' => $this->classifyClauses($text, $paragraphs),
            'contacts' => $this->extractContacts(
                $paragraphs,
                $content['tables']
            ),
            'message' => sprintf(
                'Text was extracted in %s and kept in its original language. Review every suggested clause before saving.',
                $language === 'ar' ? 'Arabic' : 'English'
            ),
        ];
    }

    /**
     * Build a safe, readable preview of a DOCX that has already passed secure
     * Agreement-document storage and authorization checks.
     */
    public function previewStoredDocx(string $path): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(
                'The stored MOU document is not available for preview'
            );
        }

        $size = filesize($path);
        if (
            $size === false
            || $size <= 0
            || $size > self::MAX_FILE_SIZE_BYTES
        ) {
            throw new InvalidArgumentException(
                'The stored MOU document cannot be previewed securely'
            );
        }

        $this->validateDocxMediaTypeAndSignature($path);
        $content = $this->extractDocxContent($path);
        $text = trim((string) ($content['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException(
                'No readable text was found in the MOU document'
            );
        }

        $language = $this->detectLanguage($text);

        return [
            'preview_type' => 'TEXT',
            'language' => $language,
            'direction' => $language === 'ar' ? 'rtl' : 'ltr',
            'text' => $text,
        ];
    }

    private function validateDocxMediaTypeAndSignature(string $path): void
    {
        if (!class_exists('finfo')) {
            throw new RuntimeException(
                'The PHP Fileinfo extension is required for clause extraction'
            );
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowedMimeTypes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
        if (
            !is_string($mimeType)
            || !in_array(strtolower($mimeType), $allowedMimeTypes, true)
        ) {
            throw new InvalidArgumentException(
                'The uploaded content is not a valid DOCX file'
            );
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException(
                'The uploaded Agreement document could not be inspected'
            );
        }
        $signature = fread($handle, 4);
        fclose($handle);

        if (!str_starts_with((string) $signature, "PK\x03\x04")) {
            throw new InvalidArgumentException(
                'The uploaded content is not a valid DOCX file'
            );
        }
    }

    private function extractDocxText(string $path): string
    {
        return $this->extractDocxContent($path)['text'];
    }

    /**
     * Preserve table columns as structured data as well as producing the
     * ordinary paragraph text used by clause classification. Coordinator and
     * signature tables commonly place the two parties side by side; flattening
     * those cells into one paragraph stream mixes their names and email
     * addresses.
     *
     * @return array{text: string, tables: array<int, array<int, array<int, string>>>}
     */
    private function extractDocxContent(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'The PHP Zip extension is required for DOCX clause extraction'
            );
        }

        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new InvalidArgumentException('The DOCX file is invalid');
        }

        try {
            if (
                $archive->locateName('word/vbaProject.bin') !== false
                || $archive->locateName('[Content_Types].xml') === false
                || $archive->locateName('word/document.xml') === false
            ) {
                throw new InvalidArgumentException(
                    'The DOCX file is invalid or contains macros'
                );
            }

            $xml = $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
        }

        if (!is_string($xml) || $xml === '') {
            return ['text' => '', 'tables' => []];
        }

        $paragraphs = [];
        $tables = [];
        if (class_exists('DOMDocument')) {
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                if ($document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                    $xpath = new DOMXPath($document);
                    $xpath->registerNamespace(
                        'w',
                        'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                    );
                    foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
                        $value = $this->wordNodeText($xpath, $paragraph);
                        if ($value !== '') {
                            $paragraphs[] = $value;
                        }
                    }

                    foreach ($xpath->query('//w:tbl') ?: [] as $tableNode) {
                        $rows = [];
                        foreach (
                            $xpath->query('./w:tr', $tableNode) ?: []
                            as $rowNode
                        ) {
                            $cells = [];
                            foreach (
                                $xpath->query('./w:tc', $rowNode) ?: []
                                as $cellNode
                            ) {
                                $cellParagraphs = [];
                                foreach (
                                    $xpath->query('./w:p', $cellNode) ?: []
                                    as $cellParagraph
                                ) {
                                    $value = $this->wordNodeText(
                                        $xpath,
                                        $cellParagraph
                                    );
                                    if ($value !== '') {
                                        $cellParagraphs[] = $value;
                                    }
                                }
                                $cells[] = trim(implode("\n", $cellParagraphs));
                            }
                            if ($cells !== []) {
                                $rows[] = $cells;
                            }
                        }
                        if ($rows !== []) {
                            $tables[] = $rows;
                        }
                    }
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }

        if ($paragraphs === []) {
            $fallback = preg_replace(
                ['#</w:p>#i', '#<w:tab[^>]*/>#i', '#<w:br[^>]*/>#i'],
                ["\n", "\t", "\n"],
                $xml
            );
            $fallback = html_entity_decode(
                strip_tags((string) $fallback),
                ENT_QUOTES | ENT_XML1,
                'UTF-8'
            );
            $paragraphs = preg_split('/\R+/u', $fallback) ?: [];
        }

        $text = trim(implode("\n\n", array_values(array_filter(
            array_map(
                static fn (string $value): string =>
                    trim(preg_replace('/[ \t]+/u', ' ', $value) ?? $value),
                $paragraphs
            ),
            static fn (string $value): bool => $value !== ''
        ))));

        $text = function_exists('mb_substr')
            ? mb_substr($text, 0, self::MAX_EXTRACTED_CHARACTERS, 'UTF-8')
            : substr($text, 0, self::MAX_EXTRACTED_CHARACTERS);

        return [
            'text' => $text,
            'tables' => $tables,
        ];
    }

    private function wordNodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $parts = [];
        foreach (
            $xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $node)
                ?: []
            as $textNode
        ) {
            $parts[] = $textNode->localName === 't'
                ? $textNode->textContent
                : "\n";
        }

        return trim(preg_replace('/\n{2,}/u', "\n", implode('', $parts)) ?? '');
    }

    private function detectLanguage(string $text): string
    {
        preg_match_all('/[\p{Arabic}]/u', $text, $arabicMatches);
        preg_match_all('/[\p{L}]/u', $text, $letterMatches);
        $arabic = count($arabicMatches[0] ?? []);
        $letters = max(1, count($letterMatches[0] ?? []));

        return ($arabic / $letters) >= 0.2 ? 'ar' : 'en';
    }

    private function paragraphs(string $text): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $paragraph): string => trim($paragraph),
                preg_split('/\R{2,}/u', $text) ?: [$text]
            ),
            static fn (string $paragraph): bool => $paragraph !== ''
        ));
    }

    private function classifyClauses(string $text, array $paragraphs): array
    {
        $patterns = [
            'collaboration_areas' =>
                '/(?:fields?\s+of\s+cooperation|areas?\s+of\s+cooperation|مجالات?\s+التعاون)/iu',
            'implementation_methods' =>
                '/(?:implementation\s+methods?|means?\s+of\s+implementation|أساليب?\s+التنفيذ|وسائل?\s+التنفيذ)/iu',
            'monitoring_plan' => '/monitor|evaluat|annual report|progress report|متابع|تقييم|تقرير سنوي|تقارير دورية/iu',
            'confidentiality_terms' => '/confidential|non-disclosure|سرية|الإفصاح/iu',
            'intellectual_property_terms' => '/intellectual property|copyright|patent|ملكية فكرية|حقوق المؤلف|براءة/iu',
            'compliance_terms' => '/compliance|applicable law|regulation|legislation|امتثال|القوانين|اللوائح|التشريعات/iu',
            'relationship_disclaimer' => '/joint venture|employment|franchise|legal partnership|مشروع مشترك|علاقة عمل|امتياز|شراكة قانونية/iu',
            'amendment_terms' => '/amend|modif|variation|تعديل|تغيير/iu',
            'dispute_resolution_terms' => '/dispute|arbitration|jurisdiction|نزاع|تحكيم|اختصاص قضائي/iu',
            'other_terms' => '/terminat|expiry|notice period|إنهاء|فسخ|انقضاء|مدة الإشعار/iu',
        ];
        $fields = [];

        foreach ($patterns as $field => $pattern) {
            if (in_array(
                $field,
                ['collaboration_areas', 'implementation_methods'],
                true
            )) {
                $articleNumber =
                    $field === 'collaboration_areas' ? 1 : 2;
                $section = $this->numberedArticleSection(
                    $paragraphs,
                    $articleNumber
                );
                if ($section === '') {
                    $section = $this->topicalSection(
                        $paragraphs,
                        $pattern
                    );
                }
                if ($section !== '') {
                    $fields[$field] = $section;
                }
                continue;
            }

            $matches = array_values(array_filter(
                $paragraphs,
                static fn (string $paragraph): bool =>
                    preg_match($pattern, $paragraph) === 1
            ));
            if ($matches !== []) {
                $fields[$field] = trim(implode("\n\n", $matches));
            }
        }

        if ($fields === []) {
            $fields['other_terms'] = $text;
        }

        return $fields;
    }

    private function numberedArticleSection(
        array $paragraphs,
        int $targetNumber
    ): string {
        $capturing = false;
        $section = [];

        foreach ($paragraphs as $paragraph) {
            $articleNumber = $this->articleNumber($paragraph);
            if (!$capturing && $articleNumber === $targetNumber) {
                $capturing = true;
                $section[] = $paragraph;
                continue;
            }
            if (
                $capturing
                && $articleNumber !== null
                && $articleNumber !== $targetNumber
            ) {
                break;
            }
            if ($capturing) {
                $section[] = $paragraph;
                if (count($section) >= 20) {
                    break;
                }
            }
        }

        return trim(implode("\n\n", $section));
    }

    private function topicalSection(
        array $paragraphs,
        string $targetPattern
    ): string {
        foreach ($paragraphs as $index => $paragraph) {
            if (preg_match($targetPattern, $paragraph) !== 1) {
                continue;
            }

            $section = [$paragraph];
            for (
                $next = $index + 1;
                $next < count($paragraphs) && count($section) < 20;
                $next++
            ) {
                if ($this->articleNumber($paragraphs[$next]) !== null) {
                    break;
                }
                $section[] = $paragraphs[$next];
            }

            return trim(implode("\n\n", $section));
        }

        return '';
    }

    private function articleNumber(string $paragraph): ?int
    {
        if (
            preg_match(
                '/^\s*(?:article|المادة)\s*[\(\[]?\s*'
                . '(11|10|9|8|7|6|5|4|3|2|1|١١|١٠|٩|٨|٧|٦|٥|٤|٣|٢|١'
                . '|one|two|three|four|five|six|seven|eight|nine|ten|eleven'
                . '|الأولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة'
                . '|السابعة|الثامنة|التاسعة|العاشرة|الحادية\s+عشرة)'
                . '\s*[\)\]]?(?![\p{L}\p{N}])/iu',
                $paragraph,
                $match
            ) !== 1
        ) {
            return null;
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower(trim($match[1]), 'UTF-8')
            : strtolower(trim($match[1]));
        $numbers = [
            '1' => 1, '١' => 1, 'one' => 1, 'الأولى' => 1,
            '2' => 2, '٢' => 2, 'two' => 2, 'الثانية' => 2,
            '3' => 3, '٣' => 3, 'three' => 3, 'الثالثة' => 3,
            '4' => 4, '٤' => 4, 'four' => 4, 'الرابعة' => 4,
            '5' => 5, '٥' => 5, 'five' => 5, 'الخامسة' => 5,
            '6' => 6, '٦' => 6, 'six' => 6, 'السادسة' => 6,
            '7' => 7, '٧' => 7, 'seven' => 7, 'السابعة' => 7,
            '8' => 8, '٨' => 8, 'eight' => 8, 'الثامنة' => 8,
            '9' => 9, '٩' => 9, 'nine' => 9, 'التاسعة' => 9,
            '10' => 10, '١٠' => 10, 'ten' => 10, 'العاشرة' => 10,
            '11' => 11, '١١' => 11, 'eleven' => 11,
            'الحادية عشرة' => 11,
        ];

        return $numbers[$value] ?? null;
    }

    private function extractContacts(array $paragraphs, array $tables = []): array
    {
        $contacts = [];
        $tableRoles = [];
        foreach ($tables as $rows) {
            $role = $this->structuredContactTableRole(
                $this->structuredTableText($rows)
            );
            if ($role !== null) {
                $tableRoles[$role] = true;
            }
        }
        foreach ($this->extractContactsFromTables($tables) as $contact) {
            $this->storeContact($contacts, $contact);
        }

        $rolePatterns = [
            'COORDINATOR' =>
                '/\bco-?ordinator\b|\bfocal\s+point\b|منسق|نقطة\s+اتصال/iu',
            'SIGNATORY' =>
                '/\bsignator(?:y|ies)\b|\bauthori[sz]ed\s+signer\b'
                    . '|for\s+and\s+on\s+behalf\s+of'
                    . '|المفوض\s+بالتوقيع|المخول\s+بالتوقيع'
                    . '|الموقعون?|نيابة\s+عن/iu',
        ];

        $anyRolePattern = '/\bco-?ordinator\b|\bfocal\s+point\b'
            . '|\bsignator(?:y|ies)\b|\bauthori[sz]ed\s+signer\b'
            . '|for\s+and\s+on\s+behalf\s+of'
            . '|منسق|نقطة\s+اتصال|المفوض\s+بالتوقيع'
            . '|المخول\s+بالتوقيع|الموقعون?|نيابة\s+عن/iu';

        foreach ($paragraphs as $index => $paragraph) {
            foreach ($rolePatterns as $role => $rolePattern) {
                if (isset($tableRoles[$role])) {
                    continue;
                }
                if (preg_match($rolePattern, $paragraph) !== 1) {
                    continue;
                }

                $context = $this->contactContext(
                    $paragraphs,
                    $index,
                    $anyRolePattern
                );
                $partyContext = implode("\n", array_merge(
                    $this->precedingPartyContext(
                        $paragraphs,
                        $index,
                        $anyRolePattern
                    ),
                    $context
                ));
                $partyType = $this->contactPartyType(
                    $paragraph,
                    $partyContext,
                    array_values($contacts),
                    $role
                );
                $contact = [
                    'party_type' => $partyType,
                    'contact_role' => $role,
                    'full_name' => $this->labeledValue(
                        $context,
                        '(?:full\s+name|name|الاسم\s+الكامل|الاسم)'
                    ),
                    'job_title' => $this->labeledValue(
                        $context,
                        '(?:job\s+title|title|position|designation|capacity|المسمى\s+الوظيفي|المنصب|الصفة)'
                    ),
                    'email' => $this->emailValue($context),
                    'phone' => $this->phoneValue($context),
                ];

                if ($contact['full_name'] === '') {
                    $contact['full_name'] = $this->roleValue(
                        $paragraph,
                        $rolePattern
                    );
                }
                $this->storeContact($contacts, $contact);
            }
        }

        return array_values($contacts);
    }

    private function extractContactsFromTables(array $tables): array
    {
        $contacts = [];

        foreach ($tables as $rows) {
            $tableText = $this->structuredTableText($rows);
            $role = $this->structuredContactTableRole($tableText);
            if ($role === null) {
                continue;
            }

            $columnCount = 0;
            foreach ($rows as $row) {
                $columnCount = max($columnCount, count($row));
            }
            if ($columnCount < 1 || $columnCount > 4) {
                continue;
            }

            for ($column = 0; $column < $columnCount; $column++) {
                $columnParts = [];
                foreach ($rows as $row) {
                    $value = trim((string) ($row[$column] ?? ''));
                    if ($value !== '') {
                        $columnParts[] = $value;
                    }
                }
                $columnText = implode("\n", $columnParts);
                $context = $this->contactLines($columnText);
                if ($context === []) {
                    continue;
                }

                $partyType = $this->structuredPartyType(
                    $columnText,
                    $column,
                    $columnCount
                );
                $contact = [
                    'party_type' => $partyType,
                    'contact_role' => $role,
                    'full_name' => $this->labeledValue(
                        $context,
                        '(?:full\s+name|name|الاسم\s+الكامل|الاسم)'
                    ),
                    'job_title' => $this->labeledValue(
                        $context,
                        '(?:job\s+title|title|position|designation|capacity|المسمى\s+الوظيفي|المنصب|الصفة)'
                    ),
                    'email' => $this->emailValue($context),
                    'phone' => $this->phoneValue($context),
                ];

                if ($contact['full_name'] === '') {
                    $contact['full_name'] = $this->unlabeledNameValue($context);
                }
                if ($contact['job_title'] === '' && $role === 'SIGNATORY') {
                    $contact['job_title'] = $this->unlabeledTitleValue(
                        $context,
                        $contact['full_name']
                    );
                }

                $this->storeContact($contacts, $contact);
            }
        }

        return array_values($contacts);
    }

    private function structuredTableText(array $rows): string
    {
        $cells = [];
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $cells[] = (string) $cell;
            }
        }

        return implode("\n", $cells);
    }

    private function structuredContactTableRole(string $tableText): ?string
    {
        if (
            preg_match(
                '/e-?mail|phone|telephone|mobile|co-?ordinator|focal\s+point'
                    . '|البريد|الهاتف|الجوال|منسق|نقاط?\s+الاتصال/iu',
                $tableText
            ) === 1
        ) {
            return 'COORDINATOR';
        }

        if (
            preg_match(
                '/(?:position|designation|capacity|المنصب|الصفة)/iu',
                $tableText
            ) === 1
            && preg_match(
                '/University\s+of\s+Bahrain|جامعة\s+البحرين/iu',
                $tableText
            ) === 1
            && preg_match(
                '/partner|second\s+party|الطرف\s+الثاني|اسم\s+الجهة/iu',
                $tableText
            ) === 1
        ) {
            return 'SIGNATORY';
        }

        return null;
    }

    private function structuredPartyType(
        string $columnText,
        int $column,
        int $columnCount
    ): string {
        if (
            preg_match(
                '/University\s+of\s+Bahrain|\bUOB\b|جامعة\s+البحرين|الطرف\s+الأول/iu',
                $columnText
            ) === 1
        ) {
            return 'UOB';
        }
        if (
            preg_match(
                '/partner|second\s+party|الطرف\s+الثاني|الجهة\s+الشريكة|اسم\s+الجهة/iu',
                $columnText
            ) === 1
        ) {
            return 'PARTNER';
        }

        return $column === 0 || $columnCount === 1 ? 'UOB' : 'PARTNER';
    }

    private function contactLines(string $text): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $line): string => trim($line),
                preg_split('/\R+/u', $text) ?: []
            ),
            static fn (string $line): bool => $line !== ''
        ));
    }

    private function storeContact(array &$contacts, array $contact): void
    {
        $contact = $this->normalizeContact($contact);
        if (
            array_filter(
                array_slice($contact, 2),
                static fn (string $value): bool => trim($value) !== ''
            ) === []
        ) {
            return;
        }

        $key = $contact['party_type'] . ':' . $contact['contact_role'];
        if (!isset($contacts[$key])) {
            $contacts[$key] = $contact;
            return;
        }

        foreach (['full_name', 'job_title', 'email', 'phone'] as $field) {
            if ($contacts[$key][$field] === '' && $contact[$field] !== '') {
                $contacts[$key][$field] = $contact[$field];
            }
        }
    }

    private function normalizeContact(array $contact): array
    {
        foreach (['full_name', 'job_title', 'email', 'phone'] as $field) {
            $contact[$field] = trim((string) ($contact[$field] ?? ''));
        }

        if (!$this->isPlausiblePersonName($contact['full_name'])) {
            $contact['full_name'] = '';
        }
        if (
            $contact['full_name'] === ''
            && $this->honorificPersonName($contact['job_title'])
        ) {
            $contact['full_name'] = $contact['job_title'];
            $contact['job_title'] = '';
        }
        if (!$this->isPlausibleJobTitle($contact['job_title'])) {
            $contact['job_title'] = '';
        }
        if (
            $contact['email'] !== ''
            && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $contact['email'] = '';
        }

        return $contact;
    }

    private function isPlausiblePersonName(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || $this->length($value) > 100) {
            return false;
        }
        $words = preg_split('/\s+/u', $value) ?: [];
        if (count($words) > 10 || preg_match('/[@\d]|[!?؛;]/u', $value) === 1) {
            return false;
        }
        if (
            preg_match(
                '/^(?:name|full\s+name|job\s+title|title|position'
                    . '|designation|capacity|الاسم|الاسم\s+الكامل'
                    . '|المسمى\s+الوظيفي|المنصب|الصفة|[-_.…]+)$/iu',
                $value
            ) === 1
        ) {
            return false;
        }

        return preg_match(
            '/\b(?:appoint|represents?|implementation|agreement|memorandum|party|renewal)\b'
                . '|يعين|يمثله|لأغراض|متابعة|تنفيذ|مواد|مذكرة|الطرف(?:ان|ين)?'
                . '|ويجوز|إخطار|تغيير|الواردة|بياناته|تدخل|حيز|سارية'
                . '|تجدد|انتهائها|المادة/iu',
            $value
        ) !== 1;
    }

    private function isPlausibleJobTitle(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if ($this->length($value) > 120 || preg_match('/[@]|[!?؛;]/u', $value) === 1) {
            return false;
        }
        if (
            preg_match(
                '/^(?:job\s+title|title|position|designation|capacity'
                    . '|المسمى\s+الوظيفي|المنصب|الصفة|[-_.…]+)$/iu',
                $value
            ) === 1
        ) {
            return false;
        }

        return preg_match(
            '/\b(?:appoint|represents?|implementation|agreement|memorandum|party|renewal)\b'
                . '|يعين|يمثله|لأغراض|متابعة|تنفيذ|مواد|مذكرة|الطرف(?:ان|ين)?'
                . '|ويجوز|إخطار|تغيير|الواردة|بياناته|تدخل|حيز|سارية'
                . '|تجدد|انتهائها|المادة/iu',
            $value
        ) !== 1;
    }

    private function honorificPersonName(string $value): bool
    {
        if (
            preg_match(
                '/^(?:Prof(?:essor)?\.?|Dr\.?|Mr\.?|Mrs\.?|Ms\.?|Miss'
                    . '|الأستاذة?|أستاذة?'
                    . '|الدكتورة?|د\.|السيدة?|الشيخ)\s+(.+)$/iu',
                trim($value),
                $match
            ) !== 1
        ) {
            return false;
        }

        return preg_match(
            '/^(?:مساعد|مشارك|زائر|فخري|محاضر|مدير|رئيس|عميد|نائب'
                . '|منسق|مسؤول|أخصائي|مستشار)\b/iu',
            trim($match[1])
        ) !== 1;
    }

    private function unlabeledNameValue(array $paragraphs): string
    {
        foreach ($paragraphs as $paragraph) {
            $candidate = trim($paragraph);
            if (
                preg_match(
                    '/(?:University\s+of\s+Bahrain|جامعة\s+البحرين|partner'
                        . '|الطرف\s+(?:الأول|الثاني)|اسم\s+الجهة|e-?mail'
                        . '|phone|mobile|البريد|الهاتف|الجوال|المسمى'
                        . '|المنصب|الصفة)/iu',
                    $candidate
                ) === 1
                || preg_match('/[:@]/u', $candidate) === 1
                || !$this->isPlausiblePersonName($candidate)
            ) {
                continue;
            }

            if (
                $this->honorificPersonName($candidate)
                || preg_match(
                    '/^(?!رئيس\b|مدير\b|عميد\b|نائب\b|منسق\b|مسؤول\b)'
                        . '[\p{L}][\p{L}\'’.-]*(?:\s+[\p{L}][\p{L}\'’.-]*){1,6}$/u',
                    $candidate
                ) === 1
            ) {
                return $candidate;
            }
        }

        return '';
    }

    private function unlabeledTitleValue(
        array $paragraphs,
        string $fullName
    ): string {
        foreach ($paragraphs as $paragraph) {
            $candidate = trim($paragraph);
            if ($candidate === '' || $candidate === $fullName) {
                continue;
            }
            if (
                preg_match(
                    '/^(?:President|Vice\s+President|Director|Dean|Head'
                        . '|Manager|Chief|Professor|Chair|Coordinator'
                        . '|رئيس|نائب|مدير|عميد|رئيسة|مديرة|عميدة|منسق'
                        . '|مسؤول|أخصائي|مستشار)\b/iu',
                    $candidate
                ) === 1
                && $this->isPlausibleJobTitle($candidate)
            ) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Keep each coordinator/signatory's labels inside its own role block. This
     * prevents a nearby person's name from being reused as another person's
     * job title or contact value.
     */
    private function contactContext(
        array $paragraphs,
        int $roleIndex,
        string $anyRolePattern
    ): array {
        $context = [(string) ($paragraphs[$roleIndex] ?? '')];
        $limit = min(count($paragraphs), $roleIndex + 12);

        for ($index = $roleIndex + 1; $index < $limit; $index++) {
            $paragraph = (string) $paragraphs[$index];
            if (
                preg_match($anyRolePattern, $paragraph) === 1
                || $this->articleNumber($paragraph) !== null
                || preg_match(
                    '/^(?:ويجوز|تدخل\s+هذه\s+المذكرة|يجوز\s+لكل'
                        . '|this\s+(?:agreement|memorandum)\s+(?:enters|shall))\b/iu',
                    trim($paragraph)
                ) === 1
            ) {
                break;
            }
            $context[] = $paragraph;
        }

        return $context;
    }

    private function precedingPartyContext(
        array $paragraphs,
        int $roleIndex,
        string $anyRolePattern
    ): array {
        $context = [];
        $minimum = max(0, $roleIndex - 5);
        for ($index = $roleIndex - 1; $index >= $minimum; $index--) {
            $paragraph = (string) $paragraphs[$index];
            if (preg_match($anyRolePattern, $paragraph) === 1) {
                break;
            }
            array_unshift($context, $paragraph);
        }

        return $context;
    }

    private function contactPartyType(
        string $roleParagraph,
        string $context,
        array $contacts,
        string $role
    ): string {
        $uobPattern =
            '/University\s+of\s+Bahrain|\bUOB\b|جامعة\s+البحرين/iu';
        $partnerPattern =
            '/partner|second\s+party|الطرف\s+الثاني|الجهة\s+الشريكة/iu';

        if (preg_match($uobPattern, $roleParagraph) === 1) {
            return 'UOB';
        }
        if (preg_match($partnerPattern, $roleParagraph) === 1) {
            return 'PARTNER';
        }

        $hasUob = preg_match($uobPattern, $context) === 1;
        $hasPartner = preg_match($partnerPattern, $context) === 1;
        if ($hasUob && !$hasPartner) {
            return 'UOB';
        }
        if ($hasPartner && !$hasUob) {
            return 'PARTNER';
        }

        foreach ($contacts as $contact) {
            if (
                ($contact['contact_role'] ?? null) === $role
                && ($contact['party_type'] ?? null) === 'UOB'
            ) {
                return 'PARTNER';
            }
        }

        return 'UOB';
    }

    private function labeledValue(array $paragraphs, string $labels): string
    {
        $nextLabel = '(?:full\s+name|name|job\s+title|title|position'
            . '|designation|capacity|e-?mail(?:\s+address)?|phone'
            . '|telephone|tel\.?|mobile(?:\s+(?:number|no\.?))?'
            . '|الاسم\s+الكامل|الاسم|المسمى\s+الوظيفي|المنصب|الصفة'
            . '|البريد\s+الإلكتروني|البريد|الهاتف|رقم\s+الهاتف|الجوال)';
        $inline = '/(?:^|[|;،]\s*)' . $labels
            . '\s*[:\-]\s*(.+?)(?=[|;،]?\s+' . $nextLabel
            . '\s*[:\-]|\s*$)/iu';
        $standalone = '/^\s*' . $labels . '\s*[:\-]?\s*$/iu';
        foreach ($paragraphs as $index => $paragraph) {
            if (preg_match($inline, $paragraph, $match) === 1) {
                $value = trim($match[1]);
                if ($value !== '' && $this->length($value) <= 255) {
                    return $value;
                }
            }
            if (preg_match($standalone, $paragraph) === 1) {
                $value = trim((string) ($paragraphs[$index + 1] ?? ''));
                if (
                    $value !== ''
                    && $this->length($value) <= 255
                    && preg_match('/[:@]/u', $value) !== 1
                ) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function emailValue(array $paragraphs): string
    {
        $labeled = $this->labeledValue(
            $paragraphs,
            '(?:e-?mail(?:\s+address)?|البريد\s+الإلكتروني|البريد)'
        );
        $source = $labeled !== '' ? $labeled : implode("\n", $paragraphs);
        if (preg_match(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            $source,
            $match
        ) === 1) {
            return trim($match[0]);
        }

        return '';
    }

    private function phoneValue(array $paragraphs): string
    {
        $labeled = $this->labeledValue(
            $paragraphs,
            '(?:phone|telephone|tel\.?|mobile(?:\s+(?:number|no\.?))?'
                . '|الهاتف|رقم\s+الهاتف|الجوال)'
        );
        $sources = array_values(array_filter([
            $labeled,
            implode("\n", $paragraphs),
        ]));

        foreach ($sources as $source) {
            preg_match_all(
                '/(?<!\d)(?:\+?\d[\d\s().\-]{5,}\d)(?!\d)/u',
                $source,
                $matches
            );
            foreach ($matches[0] ?? [] as $candidate) {
                $candidate = trim((string) $candidate, " \t\n\r\0\x0B.,;:");
                $digits = preg_replace('/\D+/', '', $candidate) ?? '';
                if (
                    strlen($digits) >= 7
                    && strlen($digits) <= 15
                    && preg_match('/^\d{4}[\-\/.]\d{1,2}[\-\/.]\d{1,2}$/', $candidate) !== 1
                ) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function roleValue(string $paragraph, string $rolePattern): string
    {
        $value = preg_replace($rolePattern, '', $paragraph, 1);
        $value = trim((string) $value, " \t\n\r\0\x0B:-|");
        if (
            $value === ''
            || preg_match(
                '/^(?:name|full\s+name|title|job\s+title|position|designation|الاسم|المنصب|المسمى\s+الوظيفي)$/iu',
                $value
            ) === 1
        ) {
            return '';
        }

        return $this->isPlausiblePersonName($value) ? $value : '';
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
