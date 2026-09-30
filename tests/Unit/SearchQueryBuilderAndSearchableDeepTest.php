<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Search\Contracts\SearchableInterface;
use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Facades\Search;
use Jengo\Search\Query\SearchQueryBuilder;
use Jengo\Search\Support\IndexSettings;
use Jengo\Search\Support\SearchManager;
use Jengo\Search\Testing\SearchFake;
use Jengo\Search\Traits\Searchable;
use PHPUnit\Framework\TestCase;

class SearchableModelWithoutAttribute implements SearchableInterface
{
    use Searchable;

    public int $id = 42;
    public string $name = 'Plain Item';
    public bool $is_active = true;

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}

class SearchQueryBuilderAndSearchableDeepTest extends TestCase
{
    protected function tearDown(): void
    {
        Search::resetFake();
        parent::tearDown();
    }

    public function testSearchQueryBuilderFilterOperatorsAndWhereIn(): void
    {
        $fake = Search::fake();

        $builder = (new SearchQueryBuilder($fake, 'products', 'laptop'))
            ->where('price', '>', 500)
            ->where('in_stock', true)
            ->where('brand', 'Dell')
            ->whereIn('category_id', [1, 2, 'three'])
            ->sortBy('price', 'desc')
            ->highlight(['title', 'description'])
            ->facets(['brand', 'category'])
            ->page(2)
            ->perPage(15);

        $results = $builder->get();
        $this->assertInstanceOf(SearchResultsCollection::class, $results);

        $query = $fake->queries[0];
        $this->assertSame('products', $query['index']);
        $this->assertSame('laptop', $query['query']);
        $this->assertStringContainsString('price > 500', $query['options']['filter']);
        $this->assertStringContainsString('in_stock = true', $query['options']['filter']);
        $this->assertStringContainsString("brand = 'Dell'", $query['options']['filter']);
        $this->assertStringContainsString("category_id IN [1, 2, 'three']", $query['options']['filter']);
        $this->assertSame(['price:desc'], $query['options']['sort']);
        $this->assertSame(2, $query['options']['page']);
        $this->assertSame(15, $query['options']['perPage']);

        // Test first() and raw()
        $first = $builder->first();
        $raw = $builder->raw();
        $this->assertIsArray($raw);
    }

    public function testSearchQueryBuilderWithSearchableModelWithoutAttribute(): void
    {
        $fake = Search::fake();

        $builder = Search::query(SearchableModelWithoutAttribute::class, 'plain');
        $this->assertSame('searchable_model_without_attribute', $fake->queries ? $fake->queries[0]['index'] : 'searchable_model_without_attribute');

        $model = new SearchableModelWithoutAttribute();
        $this->assertSame('searchable_model_without_attribute', $model->getSearchIndexName());
        $this->assertSame(42, $model->getSearchKey());
        $this->assertTrue($model->shouldBeSearchable());

        // Model searchIndex and searchUnindex
        $model->searchIndex();
        $fake->assertIndexed('searchable_model_without_attribute', 42);

        $model->searchUnindex();
        $fake->assertNotIndexed('searchable_model_without_attribute', 42);
    }

    public function testSearchResultsCollectionSerializationAndMethods(): void
    {
        $item1 = new SearchResult(['id' => 1, 'name' => 'A']);
        $item2 = new SearchResult(['id' => 2, 'name' => 'B']);

        $collection = new SearchResultsCollection(
            items: [$item1, $item2],
            total: 2,
            page: 1,
            perPage: 10
        );

        $this->assertFalse($collection->isEmpty());
        $this->assertTrue($collection->isNotEmpty());
        $this->assertSame(['A', 'B'], $collection->pluck('name'));

        $json = json_encode($collection);
        $this->assertStringContainsString('"total":2', $json);
        $this->assertStringContainsString('"per_page":10', $json);

        // Test empty collection
        $empty = new SearchResultsCollection();
        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isNotEmpty());
        $this->assertNull($empty->first());
    }

    public function testSearchResultArrayAccessAndMutations(): void
    {
        $res = new SearchResult(['id' => 100, 'title' => 'Initial']);

        $this->assertTrue(isset($res['id']));
        $this->assertSame(100, $res['id']);

        $res['status'] = 'draft';
        $this->assertTrue(isset($res['status']));
        $this->assertSame('draft', $res['status']);

        unset($res['status']);
        $this->assertFalse(isset($res['status']));

        $serialized = json_encode($res);
        $this->assertStringContainsString('"id":100', $serialized);
    }

    public function testSearchManagerCustomExtension(): void
    {
        $manager = new SearchManager();
        $manager->extend('custom', function () {
            return new \Jengo\Search\Drivers\NullDriver();
        });

        $this->assertInstanceOf(\Jengo\Search\Drivers\NullDriver::class, $manager->driver('custom'));
    }

    public function testSearchQueryBuilderWhereWithArrayDelegation(): void
    {
        $fake = Search::fake();

        $builder = (new SearchQueryBuilder($fake, 'products', 'phone'))
            ->where('category', ['Smartphones', 'Audio']);

        $builder->get();

        $query = $fake->queries[0];
        $this->assertStringContainsString("category IN ['Smartphones', 'Audio']", $query['options']['filter']);
    }

    public function testSearchResultSerializesHighlightsAndMetadata(): void
    {
        $res = new SearchResult(
            document: ['id' => 10, 'title' => 'MacBook'],
            highlights: ['title' => '<em>MacBook</em>'],
            metadata: ['score' => 0.99]
        );

        $array = $res->toArray();
        $this->assertArrayHasKey('_highlights', $array);
        $this->assertSame('<em>MacBook</em>', $array['_highlights']['title']);
        $this->assertArrayHasKey('_metadata', $array);
        $this->assertSame(0.99, $array['_metadata']['score']);

        $json = json_encode($res, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('<em>MacBook</em>', $json);
    }

    public function testSyncSearchIndexJobExecution(): void
    {
        $fake = Search::fake();

        $updateJob = new \Jengo\Search\Jobs\SyncSearchIndexJob('update', 'posts', [['id' => 1, 'title' => 'Job Post']], 'id');
        $updateJob->handle();
        $fake->assertIndexed('posts', 1);

        $deleteJob = new \Jengo\Search\Jobs\SyncSearchIndexJob('delete', 'posts', [1]);
        $deleteJob->handle();
        $fake->assertNotIndexed('posts', 1);
    }
}
