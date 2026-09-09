<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Data\SearchRequest;
use App\Data\SearchResultSet;

interface SearchProviderInterface
{
    public function search(SearchRequest $request): SearchResultSet;
}
