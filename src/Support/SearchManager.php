<?php

declare(strict_types=1);

namespace Jengo\Search\Support;

use InvalidArgumentException;
use Jengo\Base\Container\Traits\HasContainer;
use Jengo\Queues\Facades\Queue;
use Jengo\Search\Config\Search as SearchConfig;
use Jengo\Search\Contracts\SearchDriverInterface;
use Jengo\Search\Drivers\DatabaseDriver;
use Jengo\Search\Drivers\MeilisearchDriver;
use Jengo\Search\Drivers\NullDriver;
use Jengo\Search\Drivers\TypesenseDriver;
use Jengo\Search\Query\SearchQueryBuilder;
use Jengo\Search\Testing\SearchFake;

class SearchManager
{
    use HasContainer;

    protected SearchConfig $config;
    protected array $drivers = [];
    protected ?SearchFake $fake = null;

    public function __construct(?SearchConfig $config = null)
    {
        $this->config = $config ?? config('Search') ?? new SearchConfig();
    }

    public function driver(?string $name = null): SearchDriverInterface
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        $name = $name ?? $this->config->default;

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = $this->resolveDriver($name);
    }

    public function query(string $modelOrClass, string $query = ''): SearchQueryBuilder
    {
        return new SearchQueryBuilder($this->driver(), $modelOrClass, $query);
    }

    public function fake(): SearchFake
    {
        return $this->fake = new SearchFake();
    }

    public function isFaking(): bool
    {
        return $this->fake !== null;
    }

    public function reset(): void
    {
        $this->fake = null;
        $this->drivers = [];
    }

    public function resetFake(): void
    {
        $this->fake = null;
    }

    public function extend(string $name, callable $callback): self
    {
        $this->drivers[$name] = $this->call($callback, ['config' => $this->config]);
        return $this;
    }

    public function getConfig(): SearchConfig
    {
        return $this->config;
    }

    public function shouldQueue(): bool
    {
        return (bool) ($this->config->queue ?? false);
    }

    public function dispatchUpdate(string $index, array $documents, string $primaryKey = 'id'): array
    {
        if ($this->fake !== null) {
            return $this->fake->updateDocuments($index, $documents, $primaryKey);
        }

        if ($this->shouldQueue()) {
            Queue::defer(function () use ($index, $documents, $primaryKey) {
                $this->driver()->updateDocuments($index, $documents, $primaryKey);
            });

            return ['status' => 'queued', 'count' => count($documents)];
        }

        return $this->driver()->updateDocuments($index, $documents, $primaryKey);
    }

    public function dispatchDelete(string $index, array $ids): array
    {
        if ($this->fake !== null) {
            return $this->fake->deleteDocuments($index, $ids);
        }

        if ($this->shouldQueue()) {
            Queue::defer(function () use ($index, $ids) {
                $this->driver()->deleteDocuments($index, $ids);
            });

            return ['status' => 'queued', 'count' => count($ids)];
        }

        return $this->driver()->deleteDocuments($index, $ids);
    }

    protected function resolveDriver(string $name): SearchDriverInterface
    {
        $driverConfigs = $this->config->drivers;
        $driverConfig = $driverConfigs[$name] ?? [];
        $prefix = $this->config->prefix;

        return match ($name) {
            'meilisearch' => new MeilisearchDriver($driverConfig, $prefix),
            'typesense'   => new TypesenseDriver($driverConfig, $prefix),
            'database'    => new DatabaseDriver($driverConfig, $prefix),
            'null'        => new NullDriver($driverConfig, $prefix),
            default       => throw new InvalidArgumentException("Search driver [{$name}] is not supported."),
        };
    }
}
