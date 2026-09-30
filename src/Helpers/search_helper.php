<?php

declare(strict_types=1);

use Jengo\Search\Facades\Search;
use Jengo\Search\Query\SearchQueryBuilder;
use Jengo\Search\Support\SearchManager;

if (!function_exists('search')) {
    /**
     * Fluent helper to initiate a search query or retrieve SearchManager.
     *
     * @param class-string|string|null $modelOrClass
     * @param string $query
     * @return SearchQueryBuilder|SearchManager
     */
    function search(?string $modelOrClass = null, string $query = ''): SearchQueryBuilder|SearchManager
    {
        if ($modelOrClass === null) {
            return Search::getManager();
        }

        return Search::query($modelOrClass, $query);
    }
}
