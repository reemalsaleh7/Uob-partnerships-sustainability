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

        $text = $this->extractDocxText($temporaryPath);
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
            'contacts' => $this->extractContacts($paragraphs),
            'message' => sprintf(
                'Text was extracted in %s and kept in its original language. Review every suggested clause before saving.',
                $language === 'ar' ? 'Arabic' : 'English'
            ),
        ];
    }

    private function extractDocxText(string $path): string
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
            return '';
        }

        $paragraphs = [];
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
                        $parts = [];
                        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $textNode) {
                            $parts[] = $textNode->textContent;
                        }
                        $value = trim(implode('', $parts));
                        if ($value !== '') {
                            $paragraphs[] = $value;
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

        return function_exists('mb_substr')
            ? mb_substr($text, 0, self::MAX_EXTRACTED_CHARACTERS, 'UTF-8')
            : substr($text, 0, self::MAX_EXTRACTED_CHARACTERS);
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
        $articleHeading =
            '/(?:\barticle\s*(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven)\b|المادة\s*(?:\d+|الأولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة|الثامنة|التاسعة|العاشرة|الحادية\s+عشرة))/iu';
        $patterns = [
            'collaboration_areas' =>
                '/(?:\barticle\s*(?:1|one)\b|fields?\s+of\s+cooperation|areas?\s+of\s+cooperation|المادة\s*(?:1|الأولى)|مجالات?\s+التعاون)/iu',
            'implementation_methods' =>
                '/(?:\barticle\s*(?:2|two)\b|implementation\s+methods?|means?\s+of\s+implementation|المادة\s*(?:2|الثانية)|أساليب?\s+التنفيذ|وسائل?\s+التنفيذ)/iu',
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
                $section = $this->articleSection(
                    $paragraphs,
                    $pattern,
                    $articleHeading
                );
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

    private function articleSection(
        array $paragraphs,
        string $targetHeading,
        string $anyArticleHeading
    ): string {
        $capturing = false;
        $section = [];

        foreach ($paragraphs as $paragraph) {
            $isTarget = preg_match($targetHeading, $paragraph) === 1;
            $isArticle = preg_match($anyArticleHeading, $paragraph) === 1;
            if (!$capturing && $isTarget) {
                $capturing = true;
                $section[] = $paragraph;
                continue;
            }
            if ($capturing && $isArticle && !$isTarget) {
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

    private function extractContacts(array $paragraphs): array
    {
        $contacts = [];
        $rolePatterns = [
            'COORDINATOR' =>
                '/\bco-?ordinator\b|\bfocal\s+point\b|منسق|نقطة\s+اتصال/iu',
            'SIGNATORY' =>
                '/\bsignator(?:y|ies)\b|\bauthori[sz]ed\s+signer\b|المفوض\s+بالتوقيع|المخول\s+بالتوقيع|التوقيع|الموقعون?/iu',
        ];

        foreach ($paragraphs as $index => $paragraph) {
            foreach ($rolePatterns as $role => $rolePattern) {
                if (preg_match($rolePattern, $paragraph) !== 1) {
                    continue;
                }

                $context = array_slice(
                    $paragraphs,
                    max(0, $index - 2),
                    7
                );
                $contextText = implode("\n", $context);
                $partyType = preg_match(
                    '/University\s+of\s+Bahrain|\bUOB\b|جامعة\s+البحرين/iu',
                    $contextText
                ) === 1 ? 'UOB' : 'PARTNER';
                $contact = [
                    'party_type' => $partyType,
                    'contact_role' => $role,
                    'full_name' => $this->labeledValue(
                        $context,
                        '/(?:full\s+name|name|الاسم\s+الكامل|الاسم)\s*[:\-]\s*(.+)$/iu'
                    ),
                    'job_title' => $this->labeledValue(
                        $context,
                        '/(?:job\s+title|title|position|designation|المسمى\s+الوظيفي|المنصب)\s*[:\-]\s*(.+)$/iu'
                    ),
                    'email' => '',
                    'phone' => '',
                ];

                if (preg_match(
                    '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
                    $contextText,
                    $email
                )) {
                    $contact['email'] = $email[0];
                }
                if (preg_match(
                    '/(?:\+\d{1,3}[\s.-]?)?(?:\(?\d{2,4}\)?[\s.-]?){2,5}\d{2,4}/u',
                    $contextText,
                    $phone
                )) {
                    $contact['phone'] = trim($phone[0]);
                }

                if ($contact['full_name'] === '') {
                    $contact['full_name'] = $this->roleValue(
                        $paragraph,
                        $rolePattern
                    );
                }

                $key = $partyType . ':' . $role;
                if (
                    !isset($contacts[$key])
                    && array_filter(
                        array_slice($contact, 2),
                        static fn (string $value): bool =>
                            trim($value) !== ''
                    ) !== []
                ) {
                    $contacts[$key] = $contact;
                }
            }
        }

        return array_values($contacts);
    }

    private function labeledValue(array $paragraphs, string $pattern): string
    {
        foreach ($paragraphs as $paragraph) {
            if (preg_match($pattern, $paragraph, $match) === 1) {
                return trim($match[1]);
            }
        }

        return '';
    }

    private function roleValue(string $paragraph, string $rolePattern): string
    {
        $value = preg_replace($rolePattern, '', $paragraph, 1);
        $value = trim((string) $value, " \t\n\r\0\x0B:-|");

        return $this->length($value) <= 255 ? $value : '';
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
