<?php
declare(strict_types=1);

namespace App\Services\Providers;

use App\Contracts\SearchProviderInterface;
use App\Data\SearchCandidate;
use App\Data\SearchRequest;
use App\Data\SearchResultSet;

final class OfflineFileProvider implements SearchProviderInterface
{
    /**
     * @param SearchCandidate[] $candidates
     */
    public function __construct(private array $candidates)
    {
    }

    public function search(SearchRequest $request): SearchResultSet
    {
        return new SearchResultSet('offline_file_import', array_slice($this->candidates, 0, $request->limit));
    }
}
