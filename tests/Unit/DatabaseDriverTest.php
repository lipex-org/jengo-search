<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use Jengo\Search\Drivers\DatabaseDriver;
use Jengo\Search\Support\IndexSettings;
use PHPUnit\Framework\TestCase;

class DatabaseDriverTest extends TestCase
{
    public function testDatabaseDriverOperationsAndFallback(): void
    {
        $driver = new DatabaseDriver(['connection' => 'default'], 'jengo_');

        $this->assertSame(['status' => 'success', 'count' => 2], $driver->updateDocuments('articles', [['id' => 1], ['id' => 2]]));
        $this->assertSame(['status' => 'success', 'count' => 1], $driver->deleteDocuments('articles', [1]));
        $this->assertTrue($driver->flush('articles'));
        $this->assertTrue($driver->syncSettings('articles', new IndexSettings()));
        $this->assertSame(['driver' => 'database', 'status' => 'ok'], $driver->status());
    }

    public function testDatabaseDriverSearchTableNotExists(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('tableExists')->willReturn(false);

        $driver = new DatabaseDriver([], 'jengo_');
        $driver->setDb($db);

        $results = $driver->search('jengo_missing_table', 'search query');
        $this->assertSame(0, $results->total());
        $this->assertCount(0, $results);
    }

    public function testDatabaseDriverSearchMockedQuery(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->method('tableExists')->willReturn(true);

        $resultMock = $this->createMock(ResultInterface::class);
        $resultMock->method('getResultArray')->willReturn([
            ['id' => 1, 'title' => 'Article One', 'views' => 100],
        ]);

        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('groupStart')->willReturnSelf();
        $builder->method('groupEnd')->willReturnSelf();
        $builder->method('like')->willReturnSelf();
        $builder->method('orLike')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('whereIn')->willReturnSelf();
        $builder->method('orderBy')->willReturnSelf();
        $builder->method('limit')->willReturnSelf();
        $builder->method('countAllResults')->willReturn(1);
        $builder->method('get')->willReturn($resultMock);

        $db->method('table')->willReturn($builder);

        $driver = new DatabaseDriver([], 'jengo_');
        $driver->setDb($db);

        $results = $driver->search('jengo_articles', 'Article', [
            'searchable' => ['title', 'content'],
            'wheres'     => [
                ['field' => 'status', 'operator' => '=', 'value' => 'published'],
                ['field' => 'views', 'operator' => '>', 'value' => 50],
            ],
            'whereIns'   => [
                ['field' => 'category_id', 'values' => [1, 2]],
            ],
            'sort'       => ['views:desc'],
            'page'       => 1,
            'perPage'    => 10,
        ]);

        $this->assertSame(1, $results->total());
        $this->assertCount(1, $results);
        $this->assertSame('Article One', $results->first()->title);
    }
}
