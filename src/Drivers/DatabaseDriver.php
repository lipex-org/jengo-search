<?php

declare(strict_types=1);

namespace Jengo\Search\Drivers;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Jengo\Search\Entities\SearchResult;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;

class DatabaseDriver extends AbstractSearchDriver
{
    protected ?BaseConnection $db = null;

    public function __construct(array $config = [], string $prefix = '')
    {
        parent::__construct($config, $prefix);
    }

    protected function getDb(): BaseConnection
    {
        if ($this->db === null) {
            $connectionName = $this->config['connection'] ?? 'default';
            $this->db = Database::connect($connectionName);
        }

        return $this->db;
    }

    public function search(string $index, string $query, array $options = []): SearchResultsCollection
    {
        $db = $this->getDb();
        $table = $this->unprefixIndex($index);

        if (!$db->tableExists($table)) {
            return new SearchResultsCollection(
                items: [],
                total: 0,
                page: (int) ($options['page'] ?? 1),
                perPage: (int) ($options['perPage'] ?? 20)
            );
        }

        $builder = $db->table($table);

        // Searchable fields matching
        $searchable = $options['searchable'] ?? [];
        if ($query !== '' && !empty($searchable)) {
            $builder->groupStart();
            foreach ($searchable as $i => $field) {
                if ($i === 0) {
                    $builder->like($field, $query);
                } else {
                    $builder->orLike($field, $query);
                }
            }
            $builder->groupEnd();
        }

        // Apply where filters
        if (!empty($options['wheres'])) {
            foreach ($options['wheres'] as $where) {
                $field = $where['field'];
                $operator = $where['operator'];
                $value = $where['value'];

                if ($operator === '=') {
                    $builder->where($field, $value);
                } else {
                    $builder->where("{$field} {$operator}", $value);
                }
            }
        }

        if (!empty($options['whereIns'])) {
            foreach ($options['whereIns'] as $whereIn) {
                $builder->whereIn($whereIn['field'], $whereIn['values']);
            }
        }

        // Count total
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        // Sort
        if (!empty($options['sort'])) {
            foreach ((array) $options['sort'] as $sortRule) {
                if (is_string($sortRule)) {
                    $parts = explode(':', $sortRule);
                    $builder->orderBy($parts[0], $parts[1] ?? 'asc');
                }
            }
        }

        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['perPage'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $rows = $builder->limit($perPage, $offset)->get()->getResultArray();

        $items = [];
        foreach ($rows as $row) {
            $items[] = new SearchResult($row);
        }

        return new SearchResultsCollection(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            facets: [],
            executionTimeMs: 0.0,
            raw: $rows
        );
    }

    public function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        // Database driver uses standard model/table persistence directly
        return ['status' => 'success', 'count' => count($documents)];
    }

    public function deleteDocuments(string $index, array $ids): array
    {
        return ['status' => 'success', 'count' => count($ids)];
    }

    public function flush(string $index): bool
    {
        return true;
    }

    public function syncSettings(string $index, IndexSettings $settings): bool
    {
        return true;
    }

    public function status(): array
    {
        return ['driver' => 'database', 'status' => 'ok'];
    }

    private function unprefixIndex(string $index): string
    {
        if ($this->prefix !== '' && str_starts_with($index, $this->prefix)) {
            return substr($index, strlen($this->prefix));
        }

        return $index;
    }
}
