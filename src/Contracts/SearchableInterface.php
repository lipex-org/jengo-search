<?php

declare(strict_types=1);

namespace Jengo\Search\Contracts;

use Jengo\Search\Support\IndexSettings;

interface SearchableInterface
{
    /**
     * Get the name of the search index for this model or entity.
     */
    public function getSearchIndexName(): string;

    /**
     * Get the search index settings (searchable, filterable, sortable attributes).
     */
    public static function getSearchIndexSettings(): IndexSettings;

    /**
     * Transform the entity/model into a searchable array representation.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array;

    /**
     * Get the search key value (e.g. integer or obfuscated ID).
     */
    public function getSearchKey(): int|string;

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool;
}
