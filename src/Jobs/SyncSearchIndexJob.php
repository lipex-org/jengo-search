<?php

declare(strict_types=1);

namespace Jengo\Search\Jobs;

use Jengo\Search\Facades\Search;

class SyncSearchIndexJob
{
    /**
     * @param string $action 'update' or 'delete'
     * @param string $index Index name
     * @param array $payload Documents list (for update) or IDs list (for delete)
     * @param string $primaryKey Primary key attribute name
     */
    public function __construct(
        public string $action,
        public string $index,
        public array $payload,
        public string $primaryKey = 'id'
    ) {
    }

    /**
     * Execute the background job.
     */
    public function handle(): void
    {
        if ($this->action === 'delete') {
            Search::deleteDocuments($this->index, $this->payload);
        } else {
            Search::updateDocuments($this->index, $this->payload, $this->primaryKey);
        }
    }
}
