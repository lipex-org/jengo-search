<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;
use PHPUnit\Framework\TestCase;

class SearchEntitiesAndSettingsTest extends TestCase
{
    public function testIndexSettings(): void
    {
        $settings = new IndexSettings(
            searchableAttributes: ['title', 'content'],
            filterableAttributes: ['status', 'category_id'],
            sortableAttributes: ['created_at', 'views'],
            rankingRules: ['words', 'typo', 'proximity'],
            distinctField: 'user_id'
        );

        $this->assertSame(['title', 'content'], $settings->searchableFields);
        $this->assertSame(['status', 'category_id'], $settings->filterableFields);
        $this->assertSame(['created_at', 'views'], $settings->sortableFields);
        $this->assertSame(['words', 'typo', 'proximity'], $settings->rankingRules);
        $this->assertSame('user_id', $settings->distinctField);

        $array = $settings->toArray();
        $this->assertSame(['title', 'content'], $array['searchableFields']);
        $this->assertSame('user_id', $array['distinctField']);
    }

    public function testSearchResult(): void
    {
        $doc = [
            'id' => 123,
            'title' => 'Building Fast Search Engines',
            'content' => 'Full-text search in PHP with zero dependencies.',
            '_formatted' => [
                'title' => 'Building <em>Fast</em> Search Engines',
            ],
            '_highlights' => [
                'title' => ['snippet' => 'Building <mark>Fast</mark> Search Engines'],
            ],
        ];

        $result = new SearchResult($doc);

        $this->assertSame(123, $result->id);
        $this->assertSame('Building Fast Search Engines', $result->title);
        $this->assertSame('Building Fast Search Engines', $result->get('title'));
        $this->assertTrue(isset($result->title));
        $this->assertFalse(isset($result->non_existing));
        $this->assertNull($result->non_existing);
        $this->assertSame('Building <em>Fast</em> Search Engines', $result->getHighlight('title'));
        $this->assertSame($doc, $result->toArray());
        $this->assertSame($doc, $result->getRaw());
    }

    public function testSearchResultsCollection(): void
    {
        $items = [
            new SearchResult(['id' => 1, 'title' => 'First']),
            new SearchResult(['id' => 2, 'title' => 'Second']),
        ];

        $collection = new SearchResultsCollection(
            items: $items,
            total: 10,
            page: 2,
            perPage: 2,
            facets: ['categories' => ['tech' => 5]],
            executionTimeMs: 1.45,
            raw: ['dummy' => true]
        );

        $this->assertCount(2, $collection);
        $this->assertSame(10, $collection->total());
        $this->assertSame(2, $collection->page());
        $this->assertSame(2, $collection->perPage());
        $this->assertSame(5, $collection->lastPage());
        $this->assertTrue($collection->hasMorePages());
        $this->assertSame(1.45, $collection->executionTimeMs());
        $this->assertSame(['categories' => ['tech' => 5]], $collection->facets());
        $this->assertSame(['dummy' => true], $collection->raw());
        $this->assertSame($items, $collection->items());
        $this->assertSame(['First', 'Second'], $collection->pluck('title'));

        $mapped = $collection->map(fn($item) => $item->id * 10);
        $this->assertSame([10, 20], $mapped);

        // Iteration
        $iterated = [];
        foreach ($collection as $item) {
            $iterated[] = $item->id;
        }
        $this->assertSame([1, 2], $iterated);
    }
}
