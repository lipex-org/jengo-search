<?php

declare(strict_types=1);

namespace Jengo\Search\Drivers;

use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;
use RuntimeException;
use Throwable;

class TypesenseDriver extends AbstractSearchDriver
{
    protected string $host;
    protected string $apiKey;
    protected float $timeout;

    public function __construct(array $config = [], string $prefix = '')
    {
        parent::__construct($config, $prefix);

        $node = $config['nodes'][0] ?? [];
        $protocol = $node['protocol'] ?? 'http';
        $host = $node['host'] ?? '127.0.0.1';
        $port = $node['port'] ?? '8108';

        $this->host = "{$protocol}://{$host}:{$port}";
        $this->apiKey = (string) ($config['apiKey'] ?? '');
        $this->timeout = (float) ($config['timeout'] ?? 5.0);
    }

    public function search(string $index, string $query, array $options = []): SearchResultsCollection
    {
        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/collections/{$qualified}/documents/search";

        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['perPage'] ?? 20);

        $params = [
            'q'        => $query === '' ? '*' : $query,
            'query_by' => implode(',', $options['query_by'] ?? ['*']),
            'page'     => $page,
            'per_page' => $perPage,
        ];

        if (!empty($options['filter'])) {
            $params['filter_by'] = $options['filter'];
        }

        if (!empty($options['sort'])) {
            $params['sort_by'] = is_array($options['sort']) ? implode(',', $options['sort']) : $options['sort'];
        }

        if (!empty($options['facets'])) {
            $params['facet_by'] = implode(',', (array) $options['facets']);
        }

        if (!empty($options['highlight'])) {
            $params['highlight_fields'] = implode(',', (array) $options['highlight']);
        }

        $queryString = http_build_query($params);
        $res = $this->request('GET', "{$url}?{$queryString}");

        $hits = $res['hits'] ?? [];
        $total = (int) ($res['found'] ?? count($hits));
        $searchTimeMs = (float) ($res['search_time_ms'] ?? 0.0);

        $facets = [];
        if (!empty($res['facet_counts'])) {
            foreach ($res['facet_counts'] as $fc) {
                $fieldName = $fc['field_name'] ?? '';
                $counts = [];
                foreach ($fc['counts'] ?? [] as $c) {
                    $counts[$c['value']] = $c['count'];
                }
                if ($fieldName !== '') {
                    $facets[$fieldName] = $counts;
                }
            }
        }

        $items = [];
        foreach ($hits as $hit) {
            $doc = $hit['document'] ?? [];
            $highlights = [];
            if (!empty($hit['highlights'])) {
                foreach ($hit['highlights'] as $hl) {
                    $field = $hl['field'] ?? '';
                    $snippet = $hl['snippet'] ?? $hl['value'] ?? null;
                    if ($field && $snippet) {
                        $highlights[$field] = $snippet;
                    }
                }
            }
            $items[] = new SearchResult($doc, $highlights);
        }

        return new SearchResultsCollection(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            facets: $facets,
            executionTimeMs: $searchTimeMs,
            raw: $res
        );
    }

    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        if (empty($documents)) {
            return ['status' => 'empty'];
        }

        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/collections/{$qualified}/documents/import?action=upsert";

        // Typesense expects newline-delimited JSON for import
        $lines = [];
        foreach ($documents as $doc) {
            $lines[] = json_encode($doc);
        }
        $payload = implode("\n", $lines);

        $headers = [
            'Content-Type' => 'text/plain',
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ];

        $client = $this->getHttpClient([
            'timeout' => $this->timeout,
            'headers' => $headers,
        ]);

        try {
            $response = $client->request('POST', $url, ['body' => $payload]);
            $bodyContent = (string) $response->getBody();
            return ['status' => 'success', 'raw' => $bodyContent];
        } catch (Throwable $e) {
            throw new RuntimeException("Typesense document import error: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteDocuments(string $index, array $ids): array
    {
        if (empty($ids)) {
            return ['status' => 'empty'];
        }

        $qualified = $this->qualifyIndex($index);
        $filterBy = "id:[`" . implode('`,`', array_map('strval', $ids)) . "`]";
        $url = "{$this->host}/collections/{$qualified}/documents?filter_by=" . urlencode($filterBy);

        return $this->request('DELETE', $url);
    }

    public function flush(string $index): bool
    {
        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/collections/{$qualified}/documents?filter_by=id:!=__none__";

        try {
            $this->request('DELETE', $url);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function syncSettings(string $index, IndexSettings $settings): bool
    {
        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/collections";

        // Check if collection exists
        try {
            $this->request('GET', "{$this->host}/collections/{$qualified}");
            return true;
        } catch (Throwable) {
            // Create collection schema
            $fields = [
                ['name' => '.*', 'type' => 'auto'],
            ];

            $schema = [
                'name'   => $qualified,
                'fields' => $fields,
            ];

            try {
                $this->request('POST', $url, $schema);
                return true;
            } catch (Throwable $e) {
                throw new RuntimeException("Typesense collection creation error: " . $e->getMessage(), 0, $e);
            }
        }
    }

    public function status(): array
    {
        $url = "{$this->host}/health";
        try {
            $res = $this->request('GET', $url);
            return [
                'driver' => 'typesense',
                'status' => ($res['ok'] ?? false) ? 'ok' : 'unknown',
                'host'   => $this->host,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => 'typesense',
                'status' => 'error',
                'error'  => $e->getMessage(),
                'host'   => $this->host,
            ];
        }
    }

    protected function request(string $method, string $url, ?array $body = null): array
    {
        $headers = [
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json',
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ];

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
                $errorMsg = $data['message'] ?? "HTTP error {$statusCode} from Typesense";
                throw new RuntimeException($errorMsg, $statusCode);
            }

            return is_array($data) ? $data : [];
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException("Typesense connection error: " . $e->getMessage(), 0, $e);
        }
    }
}
