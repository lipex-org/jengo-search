<?php

declare(strict_types=1);

namespace Jengo\Search\Drivers;

use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;

class NullDriver extends AbstractSearchDriver
{
    public function search(string $index, string $query, array $options = []): SearchResultsCollection
    {
        return new SearchResultsCollection(
            items: [],
            total: 0,
            page: (int) ($options['page'] ?? 1),
            perPage: (int) ($options['perPage'] ?? 20),
            facets: [],
            executionTimeMs: 0.0,
            raw: []
        );
    }

    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        return ['status' => 'success', 'count' => count($documents)];
    }

    public function deleteDocuments(string $index, array $ids): array
    {
        return ['status' => 'success', 'count' => count($ids)];
    }

    public function flush(string $index): bool
    {
        return true;
    }

    public function syncSettings(string $index, IndexSettings $settings): bool
    {
        return true;
    }

    public function status(): array
    {
        return ['driver' => 'null', 'status' => 'ok'];
    }
}
