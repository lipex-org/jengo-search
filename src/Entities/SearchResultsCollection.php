<?php

declare(strict_types=1);

namespace Jengo\Search\Entities;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

class SearchResultsCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @param array<int, SearchResult|object> $items
     * @param int $total Total matched documents.
     * @param int $page Current page number.
     * @param int $perPage Items per page.
     * @param array<string, mixed> $facets Faceted distribution data.
     * @param float $executionTimeMs Execution duration in milliseconds.
     * @param array<string, mixed> $raw Raw search engine response.
     */
    public function __construct(
        public array $items = [],
        public int $total = 0,
        public int $page = 1,
        public int $perPage = 20,
        public array $facets = [],
        public float $executionTimeMs = 0.0,
        public array $raw = []
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function lastPage(): int
    {
        return (int) ceil($this->total / max(1, $this->perPage));
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->lastPage();
    }

    public function facets(): array
    {
        return $this->facets;
    }

    public function executionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function pluck(string $key): array
    {
        return array_map(function ($item) use ($key) {
            if ($item instanceof SearchResult) {
                return $item->get($key);
            }
            if (is_array($item)) {
                return $item[$key] ?? null;
            }
            if (is_object($item)) {
                return $item->{$key} ?? null;
            }
            return null;
        }, $this->items);
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof SearchResult) {
                return $item->toArray();
            }
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return (array) $item;
        }, $this->items);
    }

    public function jsonSerialize(): array
    {
        return [
            'data'              => $this->toArray(),
            'total'             => $this->total,
            'page'              => $this->page,
            'per_page'          => $this->perPage,
            'last_page'         => $this->lastPage(),
            'facets'            => $this->facets,
            'execution_time_ms' => $this->executionTimeMs,
        ];
    }
}
