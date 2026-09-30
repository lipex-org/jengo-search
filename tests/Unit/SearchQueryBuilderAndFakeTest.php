<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Search\Facades\Search;
use Jengo\Search\Query\SearchQueryBuilder;
use PHPUnit\Framework\TestCase;

class SearchQueryBuilderAndFakeTest extends TestCase
{
    protected function tearDown(): void
    {
        Search::resetFake();
        parent::tearDown();
    }

    public function testSearchFakeIndexingAndAssertions(): void
    {
        $fake = Search::fake();

        $fake->updateDocuments('posts', [
            ['id' => 1, 'title' => 'First Post', 'category' => 'news'],
            ['id' => 2, 'title' => 'Second Post', 'category' => 'tech'],
        ]);

        $fake->assertIndexed('posts', 1);
        $fake->assertIndexed('posts', 2, fn($doc) => $doc['category'] === 'tech');
        $fake->assertNotIndexed('posts', 99);
        $fake->assertIndexCount('posts', 2);

        $fake->deleteDocuments('posts', [1]);
        $fake->assertNotIndexed('posts', 1);
        $fake->assertIndexCount('posts', 1);

        $fake->flush('posts');
        $fake->assertFlushed('posts');
        $fake->assertIndexCount('posts', 0);
        $fake->assertNothingIndexed();
    }

    public function testSearchQueryBuilderFluentInterface(): void
    {
        $fake = Search::fake();

        $fake->updateDocuments('articles', [
            ['id' => 10, 'title' => 'PHP 8.5 Performance', 'author_id' => 5],
            ['id' => 20, 'title' => 'Typesense vs Meilisearch', 'author_id' => 5],
        ]);

        $builder = Search::query('articles', 'Performance')
            ->filter('author_id = 5')
            ->sortBy('created_at:desc')
            ->facets(['author_id'])
            ->highlight(['title'])
            ->limit(10)
            ->page(1);

        $results = $builder->get();

        $this->assertCount(1, $results);
        $this->assertSame(10, $results->first()->id);
        $this->assertSame('PHP 8.5 Performance', $results->first()->title);

        $this->assertCount(1, $fake->queries);
        $this->assertSame('articles', $fake->queries[0]['index']);
        $this->assertSame('Performance', $fake->queries[0]['query']);
        $this->assertSame('author_id = 5', $fake->queries[0]['options']['filter']);
        $this->assertSame(['created_at:desc'], $fake->queries[0]['options']['sort']);
    }

    public function testSearchHelperFunction(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Helpers/search_helper.php';

        Search::fake();

        $builder = search('test', 'products');
        $this->assertInstanceOf(SearchQueryBuilder::class, $builder);

        $manager = search();
        $this->assertInstanceOf(\Jengo\Search\Support\SearchManager::class, $manager);
    }
}
