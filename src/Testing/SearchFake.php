<?php

declare(strict_types=1);

namespace Jengo\Search\Testing;

use Jengo\Search\Contracts\SearchDriverInterface;
use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;
use Jengo\Search\Testing\Concerns\SearchTestAssertionsTrait;

class SearchFake implements SearchDriverInterface
{
    use SearchTestAssertionsTrait;

    /**
     * In-memory index store: [indexName => [docId => documentArray]]
     */
    public array $indexes = [];

    /**
     * History of queries executed: [['index' => ..., 'query' => ..., 'options' => ...]]
     */
    public array $queries = [];

    /**
     * Flushed indexes.
     */
    public array $flushedIndexes = [];

    public function search(string $index, string $query, array $options = []): SearchResultsCollection
    {
        $this->queries[] = [
            'index'   => $index,
            'query'   => $query,
            'options' => $options,
        ];

        $docs = array_values($this->indexes[$index] ?? []);

        // Basic in-memory matching if query provided
        if ($query !== '' && $query !== '*') {
            $matched = [];
            foreach ($docs as $doc) {
                $found = false;
                foreach ($doc as $v) {
                    if (is_scalar($v) && stripos((string) $v, $query) !== false) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $matched[] = $doc;
                }
            }
            $docs = $matched;
        }

        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['perPage'] ?? 20);
        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($docs, $offset, $perPage);

        $items = [];
        foreach ($sliced as $doc) {
            $items[] = new SearchResult($doc);
        }

        return new SearchResultsCollection(
            items: $items,
            total: count($docs),
            page: $page,
            perPage: $perPage,
            facets: [],
            executionTimeMs: 0.1,
            raw: $sliced
        );
    }

    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        if (!isset($this->indexes[$index])) {
            $this->indexes[$index] = [];
        }

        foreach ($documents as $doc) {
            $id = $doc[$primaryKey] ?? count($this->indexes[$index]) + 1;
            $this->indexes[$index][(string) $id] = $doc;
        }

        return ['status' => 'success', 'count' => count($documents)];
    }

    public function deleteDocuments(string $index, array $ids): array
    {
        if (!isset($this->indexes[$index])) {
            return ['status' => 'success', 'count' => 0];
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if (isset($this->indexes[$index][(string) $id])) {
                unset($this->indexes[$index][(string) $id]);
                $deleted++;
            }
        }

        return ['status' => 'success', 'count' => $deleted];
    }

    public function flush(string $index): bool
    {
        $this->indexes[$index] = [];
        $this->flushedIndexes[$index] = true;

        return true;
    }

    public function syncSettings(string $index, IndexSettings $settings): bool
    {
        return true;
    }

    public function status(): array
    {
        return ['driver' => 'fake', 'status' => 'ok', 'indexes' => count($this->indexes)];
    }
}
