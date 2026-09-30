<?php

declare(strict_types=1);

namespace Jengo\Search\Config;

use CodeIgniter\Config\BaseConfig;

class Search extends BaseConfig
{
    /**
     * Default search driver to use.
     * Supported: 'meilisearch', 'typesense', 'database', 'null'
     */
    public string $default = 'meilisearch';

    /**
     * Global prefix for all index names.
     */
    public string $prefix = 'jengo_';

    /**
     * Whether to dispatch indexing operations to background queue.
     */
    public bool $queue = false;

    /**
     * Driver configurations.
     */
    public array $drivers = [
        'meilisearch' => [
            'host'    => 'http://127.0.0.1:7700',
            'apiKey'  => '',
            'timeout' => 5,
        ],
        'typesense' => [
            'nodes' => [
                [
                    'host'     => '127.0.0.1',
                    'port'     => '8108',
                    'protocol' => 'http',
                ],
            ],
            'apiKey'  => '',
            'timeout' => 5,
        ],
        'database' => [
            'connection' => 'default',
        ],
        'null' => [],
    ];
}
