<?php

declare(strict_types=1);

namespace Jengo\Search\Drivers;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Jengo\Search\Contracts\SearchDriverInterface;

abstract class AbstractSearchDriver implements SearchDriverInterface
{
    /**
     * @param array<string, mixed> $config
     * @param string $prefix
     */
    public function __construct(
        protected array $config = [],
        protected string $prefix = ''
    ) {
    }

    public function qualifyIndex(string $index): string
    {
        if ($this->prefix !== '' && !str_starts_with($index, $this->prefix)) {
            return $this->prefix . $index;
        }

        return $index;
    }

    protected ?CURLRequest $httpClient = null;

    public function setHttpClient(?CURLRequest $client): self
    {
        $this->httpClient = $client;
        return $this;
    }

    protected function getHttpClient(array $options = []): CURLRequest
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        return Services::curlrequest($options);
    }
}
