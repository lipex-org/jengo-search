<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use Jengo\Search\Drivers\MeilisearchDriver;
use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Support\IndexSettings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MeilisearchDriverTest extends TestCase
{
    private function createMockClient(int $statusCode, array|string $body): CURLRequest
    {
        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn(is_array($body) ? json_encode($body) : $body);

        $client = $this->createMock(CURLRequest::class);
        $client->method('request')->willReturn($response);

        return $client;
    }

    public function testSearchSuccessWithFacetsAndHighlights(): void
    {
        $driver = new MeilisearchDriver([
            'host'    => 'http://127.0.0.1:7700',
            'apiKey'  => 'testKey',
            'timeout' => 3.0,
        ], 'prefix_');

        $mockResponse = [
            'hits' => [
                [
                    'id' => 1,
                    'title' => 'CodeIgniter Framework',
                    '_formatted' => [
                        'title' => '<em>CodeIgniter</em> Framework',
                    ],
                ],
            ],
            'estimatedTotalHits' => 1,
            'processingTimeMs' => 1.2,
            'facetDistribution' => [
                'category' => ['php' => 1],
            ],
        ];

        $driver->setHttpClient($this->createMockClient(200, $mockResponse));

        $results = $driver->search('articles', 'CodeIgniter', [
            'page'      => 1,
            'perPage'   => 10,
            'filter'    => 'status = "published"',
            'sort'      => ['created_at:desc'],
            'facets'    => ['category'],
            'highlight' => ['title'],
        ]);

        $this->assertSame(1, $results->total());
        $this->assertCount(1, $results);
        $this->assertSame(1.2, $results->executionTimeMs());
        $this->assertSame(['category' => ['php' => 1]], $results->facets());

        $first = $results->first();
        $this->assertInstanceOf(SearchResult::class, $first);
        $this->assertSame('CodeIgniter Framework', $first->title);
        $this->assertSame('<em>CodeIgniter</em> Framework', $first->getHighlight('title'));
    }

    public function testUpdateDocumentsEmpty(): void
    {
        $driver = new MeilisearchDriver();
        $res = $driver->updateDocuments('articles', []);
        $this->assertSame(['status' => 'empty'], $res);
    }

    public function testUpdateDocumentsSuccess(): void
    {
        $driver = new MeilisearchDriver();
        $driver->setHttpClient($this->createMockClient(202, ['taskUid' => 42, 'status' => 'enqueued']));

        $res = $driver->updateDocuments('articles', [
            ['id' => 1, 'title' => 'Doc 1'],
        ]);

        $this->assertSame(42, $res['taskUid']);
    }

    public function testDeleteDocumentsEmptyAndSuccess(): void
    {
        $driver = new MeilisearchDriver();
        $this->assertSame(['status' => 'empty'], $driver->deleteDocuments('articles', []));

        $driver->setHttpClient($this->createMockClient(202, ['taskUid' => 99]));
        $res = $driver->deleteDocuments('articles', [1, 2]);
        $this->assertSame(99, $res['taskUid']);
    }

    public function testFlush(): void
    {
        $driver = new MeilisearchDriver();
        $driver->setHttpClient($this->createMockClient(202, ['taskUid' => 101]));
        $this->assertTrue($driver->flush('articles'));
    }

    public function testSyncSettingsEmptyAndWithPayload(): void
    {
        $driver = new MeilisearchDriver();
        $this->assertTrue($driver->syncSettings('articles', new IndexSettings()));

        $settings = new IndexSettings(
            searchableAttributes: ['title', 'body'],
            filterableAttributes: ['status'],
            sortableAttributes: ['created_at'],
            rankingRules: ['words', 'typo']
        );

        $driver->setHttpClient($this->createMockClient(202, ['taskUid' => 105]));
        $this->assertTrue($driver->syncSettings('articles', $settings));
    }

    public function testStatusSuccessAndError(): void
    {
        $driver = new MeilisearchDriver(['host' => 'http://127.0.0.1:7700']);
        $driver->setHttpClient($this->createMockClient(200, ['status' => 'available']));

        $status = $driver->status();
        $this->assertSame('meilisearch', $status['driver']);
        $this->assertSame('available', $status['status']);

        // Error scenario
        $driver->setHttpClient($this->createMockClient(500, ['message' => 'Internal Server Error']));
        $errStatus = $driver->status();
        $this->assertSame('error', $errStatus['status']);
    }

    public function testHttpErrorThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Index not found');

        $driver = new MeilisearchDriver();
        $driver->setHttpClient($this->createMockClient(404, ['message' => 'Index not found']));

        $driver->search('non_existing', 'test');
    }
}
