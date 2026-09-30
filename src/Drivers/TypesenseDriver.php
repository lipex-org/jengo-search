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

        $queryBy = !empty($options['searchable']) ? (array) $options['searchable'] : ($options['query_by'] ?? ['*']);

        $params = [
            'q'        => $query === '' ? '*' : $query,
            'query_by' => implode(',', $queryBy),
            'page'     => $page,
            'per_page' => $perPage,
        ];

        if (!empty($options['filter'])) {
            $params['filter_by'] = $this->formatFilterForTypesense($options['filter']);
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

    protected function formatFilterForTypesense(string $filter): string
    {
        // Convert Meilisearch style "category = 'Laptops' AND in_stock = true" to Typesense "category:=Laptops && in_stock:=true"
        // Also handle "category IN ['a', 'b']" to "category:[`a`, `b`]"
        $filter = preg_replace_callback('/(\w+)\s+IN\s+\[(.*?)\]/i', function ($matches) {
            $field = $matches[1];
            $items = array_map(function ($val) {
                return trim($val, " '\"\t\n\r\0\x0B");
            }, explode(',', $matches[2]));
            return "{$field}:[`" . implode('`,`', $items) . "`]";
        }, $filter);

        $filter = preg_replace('/(\w+)\s*=\s*\'([^\']*)\'/', '$1:=$2', $filter);
        $filter = preg_replace('/(\w+)\s*=\s*(\w+)/', '$1:=$2', $filter);
        $filter = preg_replace('/(\w+)\s*>=\s*([0-9.]+)/', '$1:>=$2', $filter);
        $filter = preg_replace('/(\w+)\s*<=\s*([0-9.]+)/', '$1:<=$2', $filter);
        $filter = preg_replace('/(\w+)\s*>\s*([0-9.]+)/', '$1:>$2', $filter);
        $filter = preg_replace('/(\w+)\s*<\s*([0-9.]+)/', '$1:<$2', $filter);
        $filter = str_ireplace(' AND ', ' && ', $filter);
        $filter = str_ireplace(' OR ', ' || ', $filter);

        return $filter;
    }

    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        if (empty($documents)) {
            return ['status' => 'empty'];
        }

        $qualified = $this->qualifyIndex($index);
        $url = "{$this->host}/collections/{$qualified}/documents/import?action=upsert";

        // Typesense expects newline-delimited JSON for import with string or int IDs
        $lines = [];
        foreach ($documents as $doc) {
            if (isset($doc[$primaryKey])) {
                $doc[$primaryKey] = (string) $doc[$primaryKey];
            }
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

        // Build schema fields based on IndexSettings
        $fields = [];
        $facetFields = $settings->filterableAttributes;

        foreach ($settings->searchableAttributes as $field) {
            $fields[] = [
                'name'     => $field,
                'type'     => 'string',
                'facet'    => in_array($field, $facetFields, true),
                'optional' => true,
            ];
        }

        foreach ($settings->filterableAttributes as $field) {
            // If already added via searchable, skip
            if (in_array($field, $settings->searchableAttributes, true)) {
                continue;
            }
            $type = match ($field) {
                'in_stock', 'is_active' => 'bool',
                'price', 'rating'       => 'float',
                'category_id', 'created_at' => 'int64',
                default                 => 'auto',
            };

            $fields[] = [
                'name'     => $field,
                'type'     => $type,
                'facet'    => true,
                'optional' => true,
            ];
        }

        // Add wildcard auto fallback
        $fields[] = ['name' => '.*', 'type' => 'auto', 'optional' => true];

        $schema = [
            'name'   => $qualified,
            'fields' => $fields,
        ];

        // Check if collection exists
        try {
            $this->request('GET', "{$this->host}/collections/{$qualified}");
            // Update or patch if already exists
            return true;
        } catch (Throwable) {
            // Create collection
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
