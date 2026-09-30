<?php

declare(strict_types=1);

namespace Jengo\Search\Contracts;

use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;

interface SearchDriverInterface
{
    /**
     * Execute a search query against an index.
     *
     * @param string $index
     * @param string $query
     * @param array<string, mixed> $options
     */
    public function search(string $index, string $query, array $options = []): SearchResultsCollection;

    /**
     * Index or update a batch of documents.
     *
     * @param string $index
     * @param array<int, array<string, mixed>> $documents
     * @param string $primaryKey
     * @return array<string, mixed>
     */
    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array;

    /**
     * Delete documents by IDs from an index.
     *
     * @param string $index
     * @param array<int, int|string> $ids
     * @return array<string, mixed>
     */
    public function deleteDocuments(string $index, array $ids): array;

    /**
     * Clear all documents from an index.
     */
    public function flush(string $index): bool;

    /**
     * Sync index configuration and searchable/filterable/sortable settings.
     */
    public function syncSettings(string $index, IndexSettings $settings): bool;

    /**
     * Check search engine connectivity and status.
     *
     * @return array<string, mixed>
     */
    public function status(): array;
}
