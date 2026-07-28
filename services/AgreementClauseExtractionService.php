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
        if (!in_array($extension, ['docx', 'doc', 'pdf'], true)) {
            throw new InvalidArgumentException(
                'Clause extraction accepts PDF, DOC, or DOCX files'
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

        if ($extension !== 'docx') {
            return [
                'extracted' => false,
                'language' => null,
                'language_label' => null,
                'fields' => [],
                'message' => 'The file is ready to upload. Automatic clause extraction currently supports DOCX; PDF and DOC can still be reviewed as attached documents.',
            ];
        }

        $text = $this->extractDocxText($temporaryPath);
        if ($text === '') {
            throw new InvalidArgumentException(
                'No readable text was found in the DOCX document'
            );
        }

        $language = $this->detectLanguage($text);

        return [
            'extracted' => true,
            'language' => $language,
            'language_label' => $language === 'ar' ? 'Arabic' : 'English',
            'fields' => $this->classifyClauses($text),
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

    private function classifyClauses(string $text): array
    {
        $patterns = [
            'monitoring_plan' => '/monitor|evaluat|annual report|progress report|متابع|تقييم|تقرير سنوي|تقارير دورية/iu',
            'confidentiality_terms' => '/confidential|non-disclosure|سرية|الإفصاح/iu',
            'intellectual_property_terms' => '/intellectual property|copyright|patent|ملكية فكرية|حقوق المؤلف|براءة/iu',
            'compliance_terms' => '/compliance|applicable law|regulation|legislation|امتثال|القوانين|اللوائح|التشريعات/iu',
            'relationship_disclaimer' => '/joint venture|employment|franchise|legal partnership|مشروع مشترك|علاقة عمل|امتياز|شراكة قانونية/iu',
            'amendment_terms' => '/amend|modif|variation|تعديل|تغيير/iu',
            'dispute_resolution_terms' => '/dispute|arbitration|jurisdiction|نزاع|تحكيم|اختصاص قضائي/iu',
        ];
        $fields = [];
        $paragraphs = preg_split('/\R{2,}/u', $text) ?: [$text];

        foreach ($patterns as $field => $pattern) {
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
}
