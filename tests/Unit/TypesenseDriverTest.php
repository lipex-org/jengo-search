<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use Jengo\Search\Drivers\TypesenseDriver;
use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Support\IndexSettings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TypesenseDriverTest extends TestCase
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
        $driver = new TypesenseDriver([
            'nodes' => [
                [
                    'protocol' => 'https',
                    'host'     => 'typesense.internal',
                    'port'     => '443',
                ],
            ],
            'apiKey'  => 'xyz123',
            'timeout' => 4.0,
        ], 'prefix_');

        $mockResponse = [
            'found' => 1,
            'search_time_ms' => 0.8,
            'facet_counts' => [
                [
                    'field_name' => 'tags',
                    'counts' => [
                        ['value' => 'php', 'count' => 1],
                    ],
                ],
            ],
            'hits' => [
                [
                    'document' => [
                        'id' => '10',
                        'title' => 'Typesense Search',
                    ],
                    'highlights' => [
                        [
                            'field' => 'title',
                            'snippet' => '<mark>Typesense</mark> Search',
                        ],
                    ],
                ],
            ],
        ];

        $driver->setHttpClient($this->createMockClient(200, $mockResponse));

        $results = $driver->search('products', 'Typesense', [
            'page'             => 1,
            'perPage'          => 20,
            'filter'           => 'price:>100',
            'sort'             => ['price:desc'],
            'facets'           => ['tags'],
            'highlight'        => ['title'],
        ]);

        $this->assertSame(1, $results->total());
        $this->assertCount(1, $results);
        $this->assertSame(0.8, $results->executionTimeMs());
        $this->assertSame(['tags' => ['php' => 1]], $results->facets());

        $first = $results->first();
        $this->assertInstanceOf(SearchResult::class, $first);
        $this->assertSame('Typesense Search', $first->title);
        $this->assertSame('<mark>Typesense</mark> Search', $first->getHighlight('title'));
    }

    public function testUpdateDocumentsEmptyAndSuccess(): void
    {
        $driver = new TypesenseDriver();
        $this->assertSame(['status' => 'empty'], $driver->updateDocuments('products', []));

        $driver->setHttpClient($this->createMockClient(200, '{"success": true}'));
        $res = $driver->updateDocuments('products', [
            ['id' => 1, 'title' => 'Product 1'],
        ]);

        $this->assertSame('success', $res['status']);
    }

    public function testDeleteDocumentsEmptyAndSuccess(): void
    {
        $driver = new TypesenseDriver();
        $this->assertSame(['status' => 'empty'], $driver->deleteDocuments('products', []));

        $driver->setHttpClient($this->createMockClient(200, ['num_deleted' => 2]));
        $res = $driver->deleteDocuments('products', [1, 2]);
        $this->assertSame(2, $res['num_deleted']);
    }

    public function testFlushSuccess(): void
    {
        $driver = new TypesenseDriver();
        $driver->setHttpClient($this->createMockClient(200, ['num_deleted' => 5]));
        $this->assertTrue($driver->flush('products'));
    }

    public function testSyncSettingsCollectionExistsAndCreated(): void
    {
        $driver = new TypesenseDriver();
        // Collection already exists (200)
        $driver->setHttpClient($this->createMockClient(200, ['name' => 'products']));
        $this->assertTrue($driver->syncSettings('products', new IndexSettings()));
    }

    public function testStatusSuccessAndError(): void
    {
        $driver = new TypesenseDriver();
        $driver->setHttpClient($this->createMockClient(200, ['ok' => true]));

        $status = $driver->status();
        $this->assertSame('typesense', $status['driver']);
        $this->assertSame('ok', $status['status']);

        $driver->setHttpClient($this->createMockClient(500, ['message' => 'Unavailable']));
        $errStatus = $driver->status();
        $this->assertSame('error', $errStatus['status']);
    }

    public function testHttpErrorThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Collection not found');

        $driver = new TypesenseDriver();
        $driver->setHttpClient($this->createMockClient(404, ['message' => 'Collection not found']));

        $driver->search('non_existing', 'test');
    }
}
