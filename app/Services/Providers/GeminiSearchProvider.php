<?php
declare(strict_types=1);

namespace App\Services\Providers;

use App\Contracts\HttpClientInterface;
use App\Contracts\SearchProviderInterface;
use App\Data\SearchCandidate;
use App\Data\SearchRequest;
use App\Data\SearchResultSet;
use App\Services\Collection\LiveQueryPlanner;
use App\Services\Http\NativeHttpClient;

final class GeminiSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $endpoint,
        private int $timeoutSeconds = 10,
        private ?HttpClientInterface $http = null
    ) {
        $this->http ??= new NativeHttpClient();
    }

    public function search(SearchRequest $request): SearchResultSet
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('MISSING_API_KEY');
        }

        $url = $this->endpoint . '/' . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($this->apiKey);
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $this->prompt($request)]],
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = $this->http->postJson($url, $payload, [], $this->timeoutSeconds);
        $status = (int)($response['status'] ?? 0);
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('AUTHENTICATION_FAILURE');
        }
        if ($status === 429) {
            throw new \RuntimeException('RATE_LIMITED');
        }
        if ($status >= 500) {
            throw new \RuntimeException('PROVIDER_UNAVAILABLE');
        }

        return new SearchResultSet('gemini', $this->candidatesFromResponse((string)$response['body'], $request));
    }

    /**
     * @return SearchCandidate[]
     */
    public function candidatesFromResponse(string $body, SearchRequest $request): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('MALFORMED_RESPONSE');
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text)) {
            throw new \RuntimeException('MALFORMED_RESPONSE');
        }

        $rows = json_decode($text, true);
        if (!is_array($rows)) {
            throw new \RuntimeException('MALFORMED_RESPONSE');
        }

        $now = date('Y-m-d H:i:s');
        $candidates = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['title'])) {
                continue;
            }
            $url = isset($row['url']) && $row['url'] !== '' ? (string)$row['url'] : null;
            $candidates[] = new SearchCandidate(
                (string)($row['source'] ?? ($url ? parse_url($url, PHP_URL_HOST) : 'gemini_search')),
                (string)$row['title'],
                isset($row['price_text']) && $row['price_text'] !== '' ? (string)$row['price_text'] : null,
                $url,
                isset($row['observed_at']) && $row['observed_at'] !== '' ? (string)$row['observed_at'] : $now,
                (string)($row['evidence_level'] ?? 'C'),
                isset($row['snippet']) ? (string)$row['snippet'] : null,
                'gemini',
                $this->model,
                $request->query,
                $now,
                ['provider_response_type' => 'gemini_generate_content', 'query_version' => LiveQueryPlanner::VERSION]
            );
            if (count($candidates) >= $request->limit) {
                break;
            }
        }

        return $candidates;
    }

    private function prompt(SearchRequest $request): string
    {
        return "Return JSON array only. Find used Thai market listing candidates for: {$request->query}. Include title, snippet, url, source, price_text when explicit, observed_at when known, evidence_level C or D. Do not include seller personal contact information.";
    }
}
