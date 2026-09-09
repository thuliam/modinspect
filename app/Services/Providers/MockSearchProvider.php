<?php
declare(strict_types=1);

namespace App\Services\Providers;

use App\Contracts\SearchProviderInterface;
use App\Data\SearchCandidate;
use App\Data\SearchRequest;
use App\Data\SearchResultSet;

final class MockSearchProvider implements SearchProviderInterface
{
    public function __construct(private string $fixturePath)
    {
    }

    public function search(SearchRequest $request): SearchResultSet
    {
        $rows = json_decode((string)file_get_contents($this->fixturePath), true, 512, JSON_THROW_ON_ERROR);
        $matches = [];
        $needle = $this->normalize($request->query);

        foreach ($rows as $row) {
            $haystack = $this->normalize((string)$row['title']);
            if ($needle !== '' && !str_contains($haystack, $needle) && !str_contains($needle, $haystack)) {
                continue;
            }

            $matches[] = new SearchCandidate(
                (string)$row['source'],
                (string)$row['title'],
                $row['price_text'] ?? null,
                $row['url'] ?? null,
                $row['captured_at'] ?? date('Y-m-d H:i:s'),
                $row['evidence_level'] ?? 'B'
            );

            if (count($matches) >= $request->limit) {
                break;
            }
        }

        return new SearchResultSet('mock', $matches);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9ก-๙]+/iu', '', mb_strtolower($value, 'UTF-8')) ?? '';
    }
}
