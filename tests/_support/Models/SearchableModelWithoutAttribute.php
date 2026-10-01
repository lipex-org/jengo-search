<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use Jengo\Search\Contracts\SearchableInterface;
use Jengo\Search\Traits\Searchable;

class SearchableModelWithoutAttribute implements SearchableInterface
{
    use Searchable;

    public int $id = 42;
    public string $name = 'Plain Item';
    public bool $is_active = true;

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
