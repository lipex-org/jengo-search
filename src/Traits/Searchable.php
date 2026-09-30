<?php

declare(strict_types=1);

namespace Jengo\Search\Traits;

use Jengo\Search\Attributes\SearchIndex;
use Jengo\Search\Facades\Search;
use Jengo\Search\Support\IndexSettings;
use ReflectionClass;

trait Searchable
{
    /**
     * Get search index name.
     */
    public function getSearchIndexName(): string
    {
        $reflection = new ReflectionClass(static::class);
        $attrs = $reflection->getAttributes(SearchIndex::class);
        if (!empty($attrs)) {
            $instance = $attrs[0]->newInstance();
            if (!empty($instance->name)) {
                return $instance->name;
            }
        }

        return $this->inferSearchIndexName();
    }

    public function searchableAs(): string
    {
        return $this->getSearchIndexName();
    }

    public function searchableKeyName(): string
    {
        return static::getSearchIndexSettings()->primaryKey;
    }

    public function indexSettings(): IndexSettings
    {
        return static::getSearchIndexSettings();
    }

    public function searchable(array|object|null $record = null): void
    {
        if ($record !== null) {
            $docs = is_array($record) && isset($record[0]) && is_array($record[0]) ? $record : [(array) $record];
            Search::updateDocuments($this->searchableAs(), $docs, $this->searchableKeyName());
            return;
        }

        $this->searchIndex();
    }

    public function unsearchable(array $ids = []): void
    {
        if (!empty($ids)) {
            Search::deleteDocuments($this->searchableAs(), $ids);
            return;
        }

        $this->searchUnindex();
    }

    public function makeAllSearchable(int $chunk = 500): void
    {
        $transformRow = function ($row) {
            if (is_object($row) && method_exists($row, 'toSearchableArray')) {
                return $row->toSearchableArray();
            }

            if (is_array($row)) {
                if (method_exists($this, 'toSearchableArray')) {
                    $clone = clone $this;
                    if (property_exists($clone, 'attributes')) {
                        $clone->attributes = $row;
                    }
                    foreach ($row as $k => $v) {
                        $clone->{$k} = $v;
                    }
                    return $clone->toSearchableArray();
                }
                return $row;
            }

            return (array) $row;
        };

        if (method_exists($this, 'chunk')) {
            $buffer = [];
            $this->chunk($chunk, function ($rowOrRows) use (&$buffer, $transformRow, $chunk) {
                // CI4 Model::chunk passes individual rows, whereas custom chunkers may pass array of rows
                if (is_array($rowOrRows) && !empty($rowOrRows) && (is_array(reset($rowOrRows)) || is_object(reset($rowOrRows)))) {
                    $docs = array_map($transformRow, $rowOrRows);
                    Search::updateDocuments($this->searchableAs(), $docs, $this->searchableKeyName());
                } else {
                    $buffer[] = $transformRow($rowOrRows);
                    if (count($buffer) >= $chunk) {
                        Search::updateDocuments($this->searchableAs(), $buffer, $this->searchableKeyName());
                        $buffer = [];
                    }
                }
            });

            if (!empty($buffer)) {
                Search::updateDocuments($this->searchableAs(), $buffer, $this->searchableKeyName());
            }
            return;
        }

        if (method_exists($this, 'findAll')) {
            $results = $this->findAll();
            $docs = array_map($transformRow, $results);
            Search::updateDocuments($this->searchableAs(), $docs, $this->searchableKeyName());
        }
    }

    /**
     * Get index settings from attribute or defaults.
     */
    public static function getSearchIndexSettings(): IndexSettings
    {
        $reflection = new ReflectionClass(static::class);
        $attrs = $reflection->getAttributes(SearchIndex::class);

        if (!empty($attrs)) {
            /** @var SearchIndex $instance */
            $instance = $attrs[0]->newInstance();
            return new IndexSettings(
                searchableAttributes: $instance->searchableAttributes,
                filterableAttributes: $instance->filterableAttributes,
                sortableAttributes: $instance->sortableAttributes,
                rankingRules: $instance->rankingRules,
                primaryKey: $instance->primaryKey,
                distinctField: $instance->distinctField,
                synonyms: $instance->synonyms
            );
        }

        return new IndexSettings();
    }

    /**
     * Transform the model into a searchable array.
     */
    public function toSearchableArray(): array
    {
        if (method_exists($this, 'toArray')) {
            return $this->toArray();
        }

        return get_object_vars($this);
    }

    /**
     * Get the search key (primary ID).
     */
    public function getSearchKey(): int|string
    {
        if (isset($this->id)) {
            return $this->id;
        }

        return 0;
    }

    /**
     * Determine if this instance should be indexed.
     */
    public function shouldBeSearchable(): bool
    {
        return true;
    }

    /**
     * Synchronize the current model instance to the search index.
     */
    public function searchIndex(): void
    {
        if (!$this->shouldBeSearchable()) {
            return;
        }

        $index = $this->getSearchIndexName();
        $payload = $this->toSearchableArray();

        Search::updateDocuments($index, [$payload], static::getSearchIndexSettings()->primaryKey);
    }

    /**
     * Remove the current model instance from the search index.
     */
    public function searchUnindex(): void
    {
        $index = $this->getSearchIndexName();
        $key = $this->getSearchKey();

        Search::deleteDocuments($index, [$key]);
    }

    /**
     * Initialize automatic search model event hooks.
     * CodeIgniter 4 Model automatically calls initialize() during __construct().
     */
    protected function initialize(): void
    {
        if (is_callable('parent::initialize')) {
            parent::initialize();
        }

        if (property_exists($this, 'afterInsert') && !in_array('afterInsertSearchable', $this->afterInsert, true)) {
            $this->afterInsert[] = 'afterInsertSearchable';
        }

        if (property_exists($this, 'afterUpdate') && !in_array('afterUpdateSearchable', $this->afterUpdate, true)) {
            $this->afterUpdate[] = 'afterUpdateSearchable';
        }

        if (property_exists($this, 'afterDelete') && !in_array('afterDeleteSearchable', $this->afterDelete, true)) {
            $this->afterDelete[] = 'afterDeleteSearchable';
        }
    }

    /**
     * CI4 Model afterInsert callback to sync newly created records.
     */
    protected function afterInsertSearchable(array $data): array
    {
        if (empty($data['result']) || empty($data['id'])) {
            return $data;
        }

        $id = $data['id'];
        $record = method_exists($this, 'find') ? $this->find($id) : ($data['data'] ?? null);

        if ($record !== null) {
            $this->searchable($record);
        }

        return $data;
    }

    /**
     * CI4 Model afterUpdate callback to sync updated records.
     */
    protected function afterUpdateSearchable(array $data): array
    {
        if (empty($data['result']) || empty($data['id'])) {
            return $data;
        }

        $ids = is_array($data['id']) ? $data['id'] : [$data['id']];
        foreach ($ids as $id) {
            $record = method_exists($this, 'find') ? $this->find($id) : ($data['data'] ?? null);
            if ($record !== null) {
                $this->searchable($record);
            }
        }

        return $data;
    }

    /**
     * CI4 Model afterDelete callback to remove deleted records from search index.
     */
    protected function afterDeleteSearchable(array $data): array
    {
        if (empty($data['result']) || empty($data['id'])) {
            return $data;
        }

        $ids = is_array($data['id']) ? $data['id'] : [$data['id']];
        $this->unsearchable($ids);

        return $data;
    }

    protected function inferSearchIndexName(): string
    {
        $base = class_basename(static::class);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
    }
}
