<?php

declare(strict_types=1);

namespace Jengo\Search\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SearchIndex
{
    /**
     * @param string|null $name Name of the search index. Defaults to table or class basename if omitted.
     * @param array<string> $searchableAttributes Attributes scanned during full-text matching.
     * @param array<string> $filterableAttributes Attributes available for exact or range filtering.
     * @param array<string> $sortableAttributes Attributes allowed in order/sort queries.
     * @param array<string> $rankingRules Custom engine ranking criteria.
     * @param string $primaryKey Primary key attribute name.
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly array $searchableAttributes = [],
        public readonly array $filterableAttributes = [],
        public readonly array $sortableAttributes = [],
        public readonly array $rankingRules = [],
        public readonly string $primaryKey = 'id'
    ) {
    }
}
