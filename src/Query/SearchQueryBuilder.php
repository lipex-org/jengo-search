<?php

declare(strict_types=1);

namespace Jengo\Search\Query;

use Jengo\Search\Contracts\SearchDriverInterface;
use Jengo\Search\Contracts\SearchableInterface;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Support\IndexSettings;
use ReflectionClass;

class SearchQueryBuilder
{
    protected string $index;
    protected ?string $customFilter = null;
    protected array $wheres = [];
    protected array $whereIns = [];
    protected array $sorts = [];
    protected array $facets = [];
    protected array $highlights = [];
    protected int $page = 1;
    protected int $perPage = 20;
    protected ?IndexSettings $settings = null;

    /**
     * @param SearchDriverInterface $driver
     * @param class-string|string $modelOrClass
     * @param string $query
     */
    public function __construct(
        protected SearchDriverInterface $driver,
        protected string $modelOrClass,
        protected string $query = ''
    ) {
        $this->resolveIndexAndSettings();
    }

    public function filter(string $rawFilter): self
    {
        $this->customFilter = $rawFilter;
        return $this;
    }

    public function where(string $field, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = (string) $operatorOrValue;
        }

        if (is_array($value)) {
            return $this->whereIn($field, $value);
        }

        $this->wheres[] = [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $value,
        ];

        return $this;
    }

    public function whereIn(string $field, array $values): self
    {
        $this->whereIns[] = [
            'field'  => $field,
            'values' => $values,
        ];

        return $this;
    }

    public function sortBy(string $field, string $direction = 'asc'): self
    {
        if (str_contains($field, ':')) {
            [$field, $direction] = explode(':', $field, 2);
        }

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->sorts[] = "{$field}:{$direction}";

        return $this;
    }

    public function paginate(int $perPage = 20, int $page = 1): self
    {
        $this->perPage = max(1, $perPage);
        $this->page = max(1, $page);

        return $this;
    }

    public function page(int $page): self
    {
        $this->page = max(1, $page);
        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = max(1, $perPage);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->perPage = max(1, $limit);
        return $this;
    }

    public function highlight(array $fields): self
    {
        $this->highlights = $fields;
        return $this;
    }

    public function facets(array $fields): self
    {
        $this->facets = $fields;
        return $this;
    }

    public function get(): SearchResultsCollection
    {
        $options = [
            'page'       => $this->page,
            'perPage'    => $this->perPage,
            'sort'       => $this->sorts,
            'facets'     => $this->facets,
            'highlight'  => $this->highlights,
            'wheres'     => $this->wheres,
            'whereIns'   => $this->whereIns,
            'searchable' => $this->settings?->searchableAttributes ?? [],
        ];

        // Format filter string for Meilisearch / Typesense
        $options['filter'] = $this->buildFilterString();

        return $this->driver->search($this->index, $this->query, $options);
    }

    public function first(): mixed
    {
        $results = $this->paginate(1, 1)->get();
        return $results->first();
    }

    public function raw(): array
    {
        return $this->get()->raw();
    }

    protected function buildFilterString(): string
    {
        if ($this->customFilter !== null) {
            return $this->customFilter;
        }

        $filters = [];

        foreach ($this->wheres as $where) {
            $field = $where['field'];
            $op = $where['operator'];
            $val = $where['value'];

            if (is_string($val)) {
                $filters[] = "{$field} {$op} '{$val}'";
            } elseif (is_bool($val)) {
                $boolStr = $val ? 'true' : 'false';
                $filters[] = "{$field} {$op} {$boolStr}";
            } else {
                $filters[] = "{$field} {$op} {$val}";
            }
        }

        foreach ($this->whereIns as $whereIn) {
            $field = $whereIn['field'];
            $vals = array_map(function ($v) {
                return is_string($v) ? "'{$v}'" : (string) $v;
            }, $whereIn['values']);
            $joined = implode(', ', $vals);
            $filters[] = "{$field} IN [{$joined}]";
        }

        return implode(' AND ', $filters);
    }

    protected function resolveIndexAndSettings(): void
    {
        if (class_exists($this->modelOrClass)) {
            $reflection = new ReflectionClass($this->modelOrClass);

            // Check SearchIndex attribute
            $attrs = $reflection->getAttributes(\Jengo\Search\Attributes\SearchIndex::class);
            if (!empty($attrs)) {
                /** @var \Jengo\Search\Attributes\SearchIndex $attrInstance */
                $attrInstance = $attrs[0]->newInstance();
                $this->index = $attrInstance->name ?? $this->inferIndexName($this->modelOrClass);
                $this->settings = new IndexSettings(
                    searchableAttributes: $attrInstance->searchableAttributes,
                    filterableAttributes: $attrInstance->filterableAttributes,
                    sortableAttributes: $attrInstance->sortableAttributes,
                    rankingRules: $attrInstance->rankingRules,
                    primaryKey: $attrInstance->primaryKey
                );
                return;
            }

            if ($reflection->implementsInterface(SearchableInterface::class)) {
                $this->index = $this->modelOrClass::getSearchIndexSettings()->toArray()['name'] ?? $this->inferIndexName($this->modelOrClass);
                $this->settings = $this->modelOrClass::getSearchIndexSettings();
                return;
            }
        }

        $this->index = $this->inferIndexName($this->modelOrClass);
        $this->settings = new IndexSettings();
    }

    private function inferIndexName(string $name): string
    {
        $base = class_basename($name);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
    }
}
