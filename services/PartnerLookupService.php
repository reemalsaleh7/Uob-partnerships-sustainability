<?php

declare(strict_types=1);

final class PartnerLookupService
{
    private const ENDPOINT = 'https://www.wikidata.org/w/api.php';
    private const USER_AGENT =
        'UOB-Partnerships-Directory-Lookup/1.0 (University agreement form)';

    public function lookup(string $organizationName): array
    {
        $name = trim($organizationName);
        if ($name === '') {
            throw new InvalidArgumentException(
                'Enter the partner organization name before searching'
            );
        }
        if ($this->length($name) > 255) {
            throw new InvalidArgumentException(
                'Partner organization name must not exceed 255 characters'
            );
        }

        $language = preg_match('/[\p{Arabic}]/u', $name) === 1
            ? 'ar'
            : 'en';
        $search = $this->request([
            'action' => 'wbsearchentities',
            'search' => $name,
            'language' => $language,
            'uselang' => $language,
            'type' => 'item',
            'limit' => 5,
            'format' => 'json',
            'formatversion' => 2,
        ]);
        $match = $this->bestSearchMatch(
            $name,
            is_array($search['search'] ?? null)
                ? $search['search']
                : []
        );
        $entityId = (string) ($match['id'] ?? '');
        if (!preg_match('/^Q[1-9][0-9]*$/', $entityId)) {
            throw new DomainException(
                'No reliable public record was found for that organization'
            );
        }

        $entityResponse = $this->request([
            'action' => 'wbgetentities',
            'ids' => $entityId,
            'props' => 'claims|descriptions|labels',
            'languages' => $language . '|en|ar',
            'languagefallback' => 1,
            'format' => 'json',
            'formatversion' => 2,
        ]);
        $entity = $entityResponse['entities'][$entityId] ?? null;
        if (!is_array($entity) || isset($entity['missing'])) {
            throw new DomainException(
                'The public organization record could not be read'
            );
        }

        $countryId = $this->claimEntityId($entity, 'P17');
        $country = $countryId === null
            ? null
            : $this->entityLabel($countryId, $language);
        $description = $this->localizedValue(
            $entity['descriptions'] ?? [],
            $language
        ) ?: trim((string) ($match['description'] ?? ''));
        $website = $this->claimString($entity, 'P856');

        return [
            'organization_name' => trim((string) (
                $this->localizedValue($entity['labels'] ?? [], $language)
                ?: ($match['label'] ?? $name)
            )),
            'partner_type' => $this->inferPartnerType(
                $entity,
                $description
            ),
            'country' => $country,
            'website' => $website,
            'profile' => $description !== ''
                ? ucfirst($description) . '.'
                : null,
            'source_label' => 'Wikidata',
            'source_url' => 'https://www.wikidata.org/wiki/' . $entityId,
        ];
    }

    private function bestSearchMatch(string $name, array $results): array
    {
        if ($results === []) {
            return [];
        }

        $needle = $this->normalize($name);
        foreach ($results as $result) {
            if (
                $this->normalize((string) ($result['label'] ?? ''))
                === $needle
            ) {
                return $result;
            }
        }

        $first = $results[0];
        $label = $this->normalize((string) ($first['label'] ?? ''));
        if (
            $label === ''
            || (
                !str_contains($label, $needle)
                && !str_contains($needle, $label)
            )
        ) {
            throw new DomainException(
                'No sufficiently close public organization record was found'
            );
        }

        return $first;
    }

    private function inferPartnerType(
        array $entity,
        string $description
    ): ?string {
        $instanceIds = $this->claimEntityIds($entity, 'P31');
        $known = [
            'ACADEMIC' => [
                'Q3918',
                'Q38723',
                'Q875538',
                'Q31855',
            ],
            'PUBLIC_GOVERNMENT' => [
                'Q327333',
                'Q2659904',
                'Q35798',
                'Q7188',
            ],
            'NON_PROFIT' => [
                'Q163740',
                'Q708676',
                'Q48204',
                'Q759524',
            ],
            'PRIVATE' => [
                'Q4830453',
                'Q783794',
                'Q6881511',
            ],
        ];
        foreach ($known as $type => $ids) {
            if (array_intersect($instanceIds, $ids) !== []) {
                return $type;
            }
        }

        $text = $this->normalize($description);
        $patterns = [
            'ACADEMIC' =>
                '/university|college|academic|research institute|جامعة|كلية|أكاديمي|معهد بحث/',
            'PUBLIC_GOVERNMENT' =>
                '/government|ministry|public authority|بلدية|حكوم|وزارة|هيئة عامة/',
            'NON_PROFIT' =>
                '/non.?profit|charit|foundation|ngo|غير ربحي|خيري|مؤسسة أهلية/',
            'PRIVATE' =>
                '/company|corporation|business|enterprise|شركة|مؤسسة تجارية/',
        ];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $type;
            }
        }

        return null;
    }

    private function entityLabel(string $entityId, string $language): ?string
    {
        $response = $this->request([
            'action' => 'wbgetentities',
            'ids' => $entityId,
            'props' => 'labels',
            'languages' => $language . '|en|ar',
            'languagefallback' => 1,
            'format' => 'json',
            'formatversion' => 2,
        ]);
        $entity = $response['entities'][$entityId] ?? null;

        return is_array($entity)
            ? $this->localizedValue($entity['labels'] ?? [], $language)
            : null;
    }

    private function localizedValue(array $values, string $language): ?string
    {
        foreach ([$language, 'en', 'ar'] as $candidate) {
            $value = trim((string) ($values[$candidate]['value'] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        foreach ($values as $value) {
            $text = trim((string) ($value['value'] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function claimEntityId(array $entity, string $property): ?string
    {
        return $this->claimEntityIds($entity, $property)[0] ?? null;
    }

    private function claimEntityIds(array $entity, string $property): array
    {
        $ids = [];
        foreach (($entity['claims'][$property] ?? []) as $claim) {
            $id = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if (is_string($id) && preg_match('/^Q[1-9][0-9]*$/', $id)) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function claimString(array $entity, string $property): ?string
    {
        foreach (($entity['claims'][$property] ?? []) as $claim) {
            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function request(array $parameters): array
    {
        $url = self::ENDPOINT . '?' . http_build_query(
            $parameters,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $body = $this->httpGet($url);
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || isset($decoded['error'])) {
            throw new RuntimeException(
                'The public partner directory returned an invalid response'
            );
        }

        return $decoded;
    }

    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException(
                    'The public partner lookup could not be started'
                );
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            if (!is_string($body) || $status < 200 || $status >= 300) {
                throw new RuntimeException(
                    $error !== ''
                        ? 'Public partner lookup failed: ' . $error
                        : 'The public partner lookup service is unavailable'
                );
            }

            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => false,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: ' . self::USER_AGENT,
                ]),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (!is_string($body)) {
            throw new RuntimeException(
                'Enable the PHP cURL extension or URL streams to use public partner lookup'
            );
        }

        return $body;
    }

    private function normalize(string $value): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($value), 'UTF-8')
            : strtolower(trim($value));

        return preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized)
            ?: $normalized;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
