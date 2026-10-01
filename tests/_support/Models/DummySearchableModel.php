<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use Jengo\Search\Attributes\SearchIndex;
use Jengo\Search\Contracts\SearchableInterface;
use Jengo\Search\Traits\Searchable;

#[SearchIndex(name: 'dummy_articles', primaryKey: 'uuid')]
class DummySearchableModel implements SearchableInterface
{
    use Searchable;

    public string $table = 'articles';
    public string $primaryKey = 'uuid';

    public array $mockDb = [
        ['uuid' => 'a-1', 'title' => 'Article One', 'body' => 'Text 1'],
        ['uuid' => 'a-2', 'title' => 'Article Two', 'body' => 'Text 2'],
    ];

    public function findAll(): array
    {
        return $this->mockDb;
    }

    public function find($id = null)
    {
        foreach ($this->mockDb as $row) {
            if ($row['uuid'] === $id) {
                return $row;
            }
        }
        return null;
    }
}
