<?php

declare(strict_types=1);

namespace Jengo\Search\Support;

class IndexSettings
{
    /**
     * @param array<string> $searchableAttributes
     * @param array<string> $filterableAttributes
     * @param array<string> $sortableAttributes
     * @param array<string> $rankingRules
     * @param array<string, array<string>> $synonyms
     */
    public function __construct(
        public array $searchableAttributes = [],
        public array $filterableAttributes = [],
        public array $sortableAttributes = [],
        public array $rankingRules = [],
        public string $primaryKey = 'id',
        public ?string $name = null,
        public ?string $distinctField = null,
        public array $synonyms = []
    ) {
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'searchableFields' => $this->searchableAttributes,
            'filterableFields' => $this->filterableAttributes,
            'sortableFields'   => $this->sortableAttributes,
            default            => null,
        };
    }

    public function toArray(): array
    {
        return [
            'name'                 => $this->name,
            'searchableAttributes' => $this->searchableAttributes,
            'searchableFields'     => $this->searchableAttributes,
            'filterableAttributes' => $this->filterableAttributes,
            'filterableFields'     => $this->filterableAttributes,
            'sortableAttributes'   => $this->sortableAttributes,
            'sortableFields'       => $this->sortableAttributes,
            'rankingRules'         => $this->rankingRules,
            'primaryKey'           => $this->primaryKey,
            'distinctField'        => $this->distinctField,
            'synonyms'             => $this->synonyms,
        ];
    }
}
