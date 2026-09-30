<?php

declare(strict_types=1);

namespace Jengo\Search\Facades;

use Config\Services;
use Jengo\Search\Contracts\SearchDriverInterface;
use Jengo\Search\Entities\SearchResultsCollection;
use Jengo\Search\Query\SearchQueryBuilder;
use Jengo\Search\Support\SearchManager;
use Jengo\Search\Testing\SearchFake;

class Search
{
    protected static ?SearchManager $manager = null;

    public static function getManager(): SearchManager
    {
        if (static::$manager === null) {
            static::$manager = Services::search();
        }

        return static::$manager;
    }

    public static function driver(?string $name = null): SearchDriverInterface
    {
        return static::getManager()->driver($name);
    }

    public static function query(string $modelOrClass, string $query = ''): SearchQueryBuilder
    {
        return static::getManager()->query($modelOrClass, $query);
    }

    public static function search(string $index, string $query, array $options = []): SearchResultsCollection
    {
        return static::driver()->search($index, $query, $options);
    }

    public static function updateDocuments(string $index, array $documents, string $primaryKey = 'id'): array
    {
        return static::driver()->updateDocuments($index, $documents, $primaryKey);
    }

    public static function deleteDocuments(string $index, array $ids): array
    {
        return static::driver()->deleteDocuments($index, $ids);
    }

    public static function flush(string $index): bool
    {
        return static::driver()->flush($index);
    }

    public static function fake(): SearchFake
    {
        return static::getManager()->fake();
    }

    public static function reset(): void
    {
        static::getManager()->reset();
    }

    public static function resetFake(): void
    {
        static::getManager()->resetFake();
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::getManager()->{$method}(...$arguments);
    }
}
