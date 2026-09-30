<?php

declare(strict_types=1);

namespace Jengo\Search\Support;

use Config\Services;
use InvalidArgumentException;
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
        $this->drivers[$name] = $callback($this->config);
        return $this;
    }

    public function getConfig(): SearchConfig
    {
        return $this->config;
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
