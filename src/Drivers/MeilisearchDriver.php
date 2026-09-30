<?php

declare(strict_types=1);

namespace Jengo\Search\Drivers;

use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;
use RuntimeException;
use Throwable;

class MeilisearchDriver extends AbstractSearchDriver
{
    protected string $host;
    protected string $apiKey;
    protected float $timeout;

    public function __construct(array $config = [], string $prefix = '')
    {
        parent::__construct($config, $prefix);

        $this->host = rtrim($config['host'] ?? 'http://127.0.0.1:7700', '/');
        $this->apiKey = (string) ($config['apiKey'] ?? '');
        $this->timeout = (float) ($config['timeout'] ?? 5.0);
    }

    public function search(string $index, string $query, array $options = []): SearchResultsCollection
    {
        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/indexes/{$qualified}/search";

        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['perPage'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $body = [
            'q'      => $query,
            'offset' => $offset,
            'limit'  => $perPage,
        ];

        if (!empty($options['filter'])) {
            $body['filter'] = $options['filter'];
        }

        if (!empty($options['sort'])) {
            $body['sort'] = (array) $options['sort'];
        }

        if (!empty($options['facets'])) {
            $body['facets'] = (array) $options['facets'];
        }

        if (!empty($options['highlight'])) {
            $body['attributesToHighlight'] = (array) $options['highlight'];
        }

        $res = $this->request('POST', $url, $body);

        $hits = $res['hits'] ?? [];
        $total = $res['estimatedTotalHits'] ?? $res['totalHits'] ?? count($hits);
        $processingTime = (float) ($res['processingTimeMs'] ?? 0.0);
        $facetDistribution = $res['facetDistribution'] ?? [];

        $items = [];
        foreach ($hits as $hit) {
            $highlights = $hit['_formatted'] ?? [];
            unset($hit['_formatted'], $hit['_matchesPosition']);
            $items[] = new SearchResult($hit, $highlights);
        }

        return new SearchResultsCollection(
            items: $items,
            total: (int) $total,
            page: $page,
            perPage: $perPage,
            facets: $facetDistribution,
            executionTimeMs: $processingTime,
            raw: $res
        );
    }

    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        if (empty($documents)) {
            return ['status' => 'empty'];
        }

        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/indexes/{$qualified}/documents?primaryKey={$primaryKey}";

        return $this->request('POST', $url, $documents);
    }

    public function deleteDocuments(string $index, array $ids): array
    {
        if (empty($ids)) {
            return ['status' => 'empty'];
        }

        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/indexes/{$qualified}/documents/delete-batch";

        return $this->request('POST', $url, array_values($ids));
    }

    public function flush(string $index): bool
    {
        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/indexes/{$qualified}/documents";

        $res = $this->request('DELETE', $url);
        return isset($res['taskUid']);
    }

    public function syncSettings(string $index, IndexSettings $settings): bool
    {
        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/indexes/{$qualified}/settings";

        $payload = [];
        if (!empty($settings->searchableAttributes)) {
            $payload['searchableAttributes'] = $settings->searchableAttributes;
        }
        if (!empty($settings->filterableAttributes)) {
            $payload['filterableAttributes'] = $settings->filterableAttributes;
        }
        if (!empty($settings->sortableAttributes)) {
            $payload['sortableAttributes'] = $settings->sortableAttributes;
        }
        if (!empty($settings->rankingRules)) {
            $payload['rankingRules'] = $settings->rankingRules;
        }

        if (empty($payload)) {
            return true;
        }

        $res = $this->request('PATCH', $url, $payload);
        return isset($res['taskUid']);
    }

    public function status(): array
    {
        $url = "{$this->host}/health";
        try {
            $res = $this->request('GET', $url);
            return [
                'driver' => 'meilisearch',
                'status' => $res['status'] ?? 'unknown',
                'host'   => $this->host,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => 'meilisearch',
                'status' => 'error',
                'error'  => $e->getMessage(),
                'host'   => $this->host,
            ];
        }
    }

    protected function request(string $method, string $url, ?array $body = null): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $client = $this->getHttpClient([
            'timeout' => $this->timeout,
            'headers' => $headers,
        ]);

        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $bodyContent = (string) $response->getBody();
            $data = json_decode($bodyContent, true);

            if ($statusCode >= 400) {
                $errorMsg = $data['message'] ?? "HTTP error {$statusCode} from Meilisearch";
                throw new RuntimeException($errorMsg, $statusCode);
            }

            return is_array($data) ? $data : [];
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException("Meilisearch connection error: " . $e->getMessage(), 0, $e);
        }
    }
}
