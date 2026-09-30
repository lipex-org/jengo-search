<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Search\Attributes\SearchIndex;
use Jengo\Search\Config\Search as SearchConfig;
use Jengo\Search\Contracts\SearchableInterface;
use Jengo\Search\Drivers\DatabaseDriver;
use Jengo\Search\Drivers\MeilisearchDriver;
use Jengo\Search\Drivers\NullDriver;
use Jengo\Search\Drivers\TypesenseDriver;
use Jengo\Search\Facades\Search;
use Jengo\Search\Support\IndexSettings;
use Jengo\Search\Support\SearchManager;
use Jengo\Search\Traits\Searchable;
use PHPUnit\Framework\TestCase;

#[SearchIndex(name: 'dummy_articles', primaryKey: 'uuid')]
class DummySearchableModel implements SearchableInterface
{
    use Searchable;

    public string $table = 'articles';
    public string $primaryKey = 'uuid';

    public array $mockDb = [
        ['uuid' => 'a-1', 'title' => 'Article One', 'body' => 'Text 1'],
        ['uuid' => 'a-2', 'title' => 'Article Two', 'body' => 'Text 2'],
    ];

    public function findAll(): array
    {
        return $this->mockDb;
    }

    public function find($id = null)
    {
        foreach ($this->mockDb as $row) {
            if ($row['uuid'] === $id) {
                return $row;
            }
        }
        return null;
    }
}

class DriversAndSearchableTest extends TestCase
{
    protected function tearDown(): void
    {
        Search::resetFake();
        parent::tearDown();
    }

    public function testNullDriver(): void
    {
        $driver = new NullDriver();
        $res = $driver->search('dummy', 'query');

        $this->assertCount(0, $res);
        $this->assertSame(0, $res->total());
        $this->assertSame(['status' => 'success', 'count' => 0], $driver->updateDocuments('dummy', []));
        $this->assertSame(['status' => 'success', 'count' => 1], $driver->deleteDocuments('dummy', [1]));
        $this->assertTrue($driver->flush('dummy'));
        $this->assertTrue($driver->syncSettings('dummy', new IndexSettings()));
        $this->assertSame(['driver' => 'null', 'status' => 'ok'], $driver->status());
    }

    public function testSearchManagerDriverResolution(): void
    {
        $config = new SearchConfig();
        $config->default = 'null';

        $manager = new SearchManager($config);
        $this->assertInstanceOf(NullDriver::class, $manager->driver());
        $this->assertInstanceOf(NullDriver::class, $manager->driver('null'));
        $this->assertInstanceOf(MeilisearchDriver::class, $manager->driver('meilisearch'));
        $this->assertInstanceOf(TypesenseDriver::class, $manager->driver('typesense'));
        $this->assertInstanceOf(DatabaseDriver::class, $manager->driver('database'));
    }

    public function testSearchableTraitAndAttribute(): void
    {
        $fake = Search::fake();
        $model = new DummySearchableModel();

        $this->assertSame('dummy_articles', $model->searchableAs());
        $this->assertSame('uuid', $model->searchableKeyName());

        $model->searchable(['uuid' => 'a-1', 'title' => 'Article One', 'body' => 'Text 1']);
        $fake->assertIndexed('dummy_articles', 'a-1');

        $model->unsearchable(['a-1']);
        $fake->assertNotIndexed('dummy_articles', 'a-1');
    }
}
