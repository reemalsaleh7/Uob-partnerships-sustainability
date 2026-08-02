<?php

declare(strict_types=1);

const WORKSPACE_LANGUAGE_COOKIE = 'uob_workspace_language';
const WORKSPACE_SUPPORTED_LANGUAGES = ['en', 'ar'];

function workspaceResolveLanguage(): string
{
    static $language = null;

    if (is_string($language)) {
        return $language;
    }

    $requested = strtolower(trim((string) ($_GET['lang'] ?? '')));

    if (in_array($requested, WORKSPACE_SUPPORTED_LANGUAGES, true)) {
        setcookie(
            WORKSPACE_LANGUAGE_COOKIE,
            $requested,
            [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );
        $_COOKIE[WORKSPACE_LANGUAGE_COOKIE] = $requested;
        $language = $requested;

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $parts = parse_url($uri);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['lang']);

        $target = (string) ($parts['path'] ?? '');
        if ($query !== []) {
            $target .= '?' . http_build_query($query);
        }

        if (!headers_sent()) {
            header(
                'Location: ' . ($target !== '' ? $target : './'),
                true,
                303
            );
            exit;
        }

        return $language;
    }

    $stored = strtolower(trim((string) (
        $_COOKIE[WORKSPACE_LANGUAGE_COOKIE] ?? 'en'
    )));

    $language = in_array(
        $stored,
        WORKSPACE_SUPPORTED_LANGUAGES,
        true
    )
        ? $stored
        : 'en';

    return $language;
}

function workspaceLanguageDirection(
    ?string $language = null
): string {
    return ($language ?? workspaceResolveLanguage()) === 'ar'
        ? 'rtl'
        : 'ltr';
}

function workspaceLanguageUrl(string $language): string
{
    $language = in_array(
        $language,
        WORKSPACE_SUPPORTED_LANGUAGES,
        true
    )
        ? $language
        : 'en';

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $parts = parse_url($uri);
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $query['lang'] = $language;

    $path = (string) ($parts['path'] ?? '');

    return ($path !== '' ? $path : './')
        . '?'
        . http_build_query($query);
}

function workspaceLanguagePack(
    ?string $language = null
): array {
    static $packs = [];

    $language = $language ?? workspaceResolveLanguage();

    if (isset($packs[$language])) {
        return $packs[$language];
    }

    $path = dirname(__DIR__)
        . '/lang/'
        . $language
        . '.php';
    $pack = is_file($path) ? require $path : [];

    $packs[$language] = [
        'translations' => is_array(
            $pack['translations'] ?? null
        )
            ? $pack['translations']
            : [],
        'patterns' => is_array($pack['patterns'] ?? null)
            ? $pack['patterns']
            : [],
    ];

    return $packs[$language];
}

function workspaceNormalizeTranslationText(
    string $text
): string {
    return trim(
        preg_replace('~\s+~u', ' ', $text) ?? $text
    );
}

function workspaceTranslateDateTokens(
    string $text
): string {
    static $tokens = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر',
        'Jan' => 'يناير',
        'Feb' => 'فبراير',
        'Mar' => 'مارس',
        'Apr' => 'أبريل',
        'Jun' => 'يونيو',
        'Jul' => 'يوليو',
        'Aug' => 'أغسطس',
        'Sep' => 'سبتمبر',
        'Sept' => 'سبتمبر',
        'Oct' => 'أكتوبر',
        'Nov' => 'نوفمبر',
        'Dec' => 'ديسمبر',
        'Sunday' => 'الأحد',
        'Monday' => 'الاثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
        'AM' => 'ص',
        'PM' => 'م',
    ];

    foreach ($tokens as $source => $replacement) {
        $text = preg_replace(
            '~(?<![\p{L}])'
                . preg_quote($source, '~')
                . '(?![\p{L}])~iu',
            $replacement,
            $text
        ) ?? $text;
    }

    return $text;
}

function workspaceApplyTranslationPatterns(
    string $text,
    array $patterns
): string {
    foreach ($patterns as $pattern) {
        $source = (string) ($pattern['source'] ?? '');

        if ($source === '') {
            continue;
        }

        $replacement = (string) (
            $pattern['replacement'] ?? ''
        );
        $count = 0;
        $result = preg_replace(
            '~' . str_replace('~', '\~', $source) . '~u',
            $replacement,
            $text,
            1,
            $count
        );

        if ($count > 0 && is_string($result)) {
            return $result;
        }
    }

    return $text;
}

function workspaceTranslateTextInternal(
    string $text,
    int $depth = 0
): string {
    if (
        workspaceResolveLanguage() !== 'ar'
        || $text === ''
    ) {
        return $text;
    }

    $original = workspaceNormalizeTranslationText($text);

    if ($original === '') {
        return $text;
    }

    $pack = workspaceLanguagePack('ar');
    $translations = $pack['translations'];

    if (array_key_exists($original, $translations)) {
        return (string) $translations[$original];
    }

    $patternResult = workspaceApplyTranslationPatterns(
        $original,
        $pack['patterns']
    );

    if ($patternResult !== $original) {
        return workspaceTranslateDateTokens($patternResult);
    }

    if (
        preg_match(
            '~^[A-Z][A-Z0-9_ ]{1,100}$~',
            $original
        ) === 1
    ) {
        $humanized = str_replace('_', ' ', $original);

        if (array_key_exists($humanized, $translations)) {
            return (string) $translations[$humanized];
        }
    }

    if ($depth < 3) {
        foreach ([' · ', ' → '] as $delimiter) {
            if (!str_contains($original, $delimiter)) {
                continue;
            }

            $parts = explode($delimiter, $original);
            $translatedParts = array_map(
                static fn (string $part): string =>
                    workspaceTranslateTextInternal(
                        $part,
                        $depth + 1
                    ),
                $parts
            );

            if ($translatedParts !== $parts) {
                return implode($delimiter, $translatedParts);
            }
        }

        if (
            preg_match(
                '~^([^:]{1,90}):\s+(.+)$~u',
                $original,
                $matches
            ) === 1
        ) {
            $prefix = workspaceTranslateTextInternal(
                (string) $matches[1],
                $depth + 1
            );

            if ($prefix !== $matches[1]) {
                return $prefix
                    . ': '
                    . workspaceTranslateDateTokens(
                        (string) $matches[2]
                    );
            }
        }
    }

    return workspaceTranslateDateTokens($original);
}

function workspaceTranslateText(string $text): string
{
    return workspaceTranslateTextInternal($text);
}

function workspaceT(string $text): string
{
    return workspaceTranslateText($text);
}

function workspaceTranslateHtml(string $html): string
{
    if (
        workspaceResolveLanguage() !== 'ar'
        || trim($html) === ''
    ) {
        return $html;
    }

    $parts = preg_split(
        '~(<script\b[^>]*>.*?</script>'
            . '|<style\b[^>]*>.*?</style>)~isu',
        $html,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );

    if (!is_array($parts)) {
        return $html;
    }

    foreach ($parts as $index => $part) {
        if ($index % 2 === 1) {
            continue;
        }

        $part = preg_replace_callback(
            '~\b('
                . 'placeholder'
                . '|title'
                . '|aria-label'
                . '|aria-description'
                . '|alt'
                . '|data-bs-title'
                . '|data-bs-original-title'
                . ')=(["\'])(.*?)\2~isu',
            static function (array $matches): string {
                $decoded = html_entity_decode(
                    (string) $matches[3],
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
                $translated = workspaceTranslateText(
                    $decoded
                );
                $escaped = htmlspecialchars(
                    $translated,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );

                return $matches[1]
                    . '='
                    . $matches[2]
                    . $escaped
                    . $matches[2];
            },
            $part
        ) ?? $part;

        $part = preg_replace_callback(
            '~>([^<]+)<~su',
            static function (array $matches): string {
                $raw = (string) $matches[1];

                if (trim($raw) === '') {
                    return $matches[0];
                }

                preg_match('~^\s*~u', $raw, $leadingMatch);
                preg_match('~\s*$~u', $raw, $trailingMatch);
                $leading = (string) (
                    $leadingMatch[0] ?? ''
                );
                $trailing = (string) (
                    $trailingMatch[0] ?? ''
                );
                $coreLength = strlen($raw)
                    - strlen($leading)
                    - strlen($trailing);
                $core = $coreLength > 0
                    ? substr(
                        $raw,
                        strlen($leading),
                        $coreLength
                    )
                    : trim($raw);
                $decoded = html_entity_decode(
                    preg_replace(
                        '~\s+~u',
                        ' ',
                        $core
                    ) ?? $core,
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
                $normalized = trim($decoded);
                $translated = workspaceTranslateText(
                    $normalized
                );

                if ($translated === $normalized) {
                    return $matches[0];
                }

                $escaped = htmlspecialchars(
                    $translated,
                    ENT_NOQUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );

                return '>'
                    . $leading
                    . $escaped
                    . $trailing
                    . '<';
            },
            $part
        ) ?? $part;

        $parts[$index] = $part;
    }

    return implode('', $parts);
}

function workspaceStartTranslationBuffer(): void
{
    if (workspaceResolveLanguage() !== 'ar') {
        return;
    }

    if (!empty(
        $GLOBALS['workspace_i18n_buffer_started']
    )) {
        return;
    }

    $GLOBALS['workspace_i18n_buffer_started'] = true;
    ob_start('workspaceTranslateHtml');
}

function workspaceEndTranslationBuffer(): void
{
    if (empty(
        $GLOBALS['workspace_i18n_buffer_started']
    )) {
        return;
    }

    $GLOBALS['workspace_i18n_buffer_started'] = false;

    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function workspaceClientI18nConfig(): array
{
    $language = workspaceResolveLanguage();
    $pack = workspaceLanguagePack($language);

    return [
        'language' => $language,
        'direction' => workspaceLanguageDirection($language),
        'translations' => $pack['translations'],
        'patterns' => $pack['patterns'],
    ];
}
