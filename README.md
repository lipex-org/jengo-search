# Jengo Search

A unified, multi-driver full-text search engine package for CodeIgniter 4 and the Jengo ecosystem.

`jengo/search` provides an expressive, driver-agnostic query builder, PHP 8 attribute-driven index configurations, real-time model lifecycle synchronization, faceted navigation, keyword highlighting, and background queue support for Meilisearch, Typesense, and SQL databases without external vendor SDK dependencies.

---

## Features

- **Multi-Driver Engine Support**: Seamless switching between **Meilisearch**, **Typesense**, **Database (SQL/SQLite)**, and **Null / Testing Fake** drivers.
- **PHP 8 Attributes**: Configure searchable fields, filterable facets, sortable attributes, ranking rules, distinct keys, and synonyms directly on your models via `#[SearchIndex]`.
- **Automatic Model Lifecycle Synchronization**: Hooks into CodeIgniter 4 Model events (`afterInsert`, `afterUpdate`, `afterDelete`) to keep indices in real-time sync with database writes.
- **Background Queue Integration**: Asynchronous document indexing and purging via `jengo/queues` when background queueing is enabled.
- **Fluent Query Builder**: Chain filters, multi-value `where()` / `whereIn()`, faceted aggregations, keyword highlights, sorting, and pagination.
- **Inertia.js & Vue / React Ready**: Clean JSON serialization preserving matched highlighted tags (`<em>` or `<mark>`) and execution duration for reactive frontends.
- **Zero Heavy SDK Dependencies**: Built with native HTTP calls over cURL/Guzzle for optimal speed and minimal vendor footprint.
- **CLI Management**: Commands for bulk importing, flushing, syncing settings, and inspecting engine health.

---

## Installation

Install the package via Composer:

```bash
composer require jengo/search
```

Publish configuration files and commands:

```bash
php spark jengo:install search
```

This creates `app/Config/Search.php`.

---

## Configuration

Configure your default driver and credentials in `app/Config/Search.php`:

```php
<?php

namespace Config;

use Jengo\Search\Config\Search as BaseSearch;

class Search extends BaseSearch
{
    public string $default = 'meilisearch'; // 'meilisearch', 'typesense', 'database', 'null'
    public string $prefix = 'jengo_';
    public bool $queue = false; // Set true to dispatch indexing to background queues

    public array $drivers = [
        'meilisearch' => [
            'host'    => 'http://127.0.0.1:7700',
            'apiKey'  => 'your_master_key',
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
            'apiKey'  => 'your_api_key',
            'timeout' => 5,
        ],
        'database' => [
            'connection' => 'default',
        ],
        'null' => [],
    ];
}
```

Verify your search engine connection anytime via CLI:

```bash
php spark jengo:search status
```

---

## Defining Searchable Models

Add the `Searchable` trait and `#[SearchIndex]` attribute to any CodeIgniter 4 Model:

```php
<?php

namespace App\Models;

use CodeIgniter\Model;
use Jengo\Search\Attributes\SearchIndex;
use Jengo\Search\Contracts\SearchableInterface;
use Jengo\Search\Traits\Searchable;

#[SearchIndex(
    name: 'products',
    searchableAttributes: ['title', 'description', 'category', 'brand'],
    filterableAttributes: ['category', 'brand', 'in_stock', 'price', 'rating'],
    sortableAttributes: ['price', 'rating', 'created_at'],
    rankingRules: ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
    primaryKey: 'id',
    distinctField: 'category',
    synonyms: ['laptop' => ['notebook', 'macbook']]
)]
class ProductModel extends Model implements SearchableInterface
{
    use Searchable;

    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $allowedFields = ['title', 'description', 'category', 'brand', 'price', 'rating', 'in_stock'];

    /**
     * Optional: customize the payload indexed in search engines.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'          => (int) ($this->attributes['id'] ?? $this->id ?? 0),
            'title'       => (string) ($this->attributes['title'] ?? $this->title ?? ''),
            'description' => (string) ($this->attributes['description'] ?? $this->description ?? ''),
            'category'    => (string) ($this->attributes['category'] ?? $this->category ?? ''),
            'brand'       => (string) ($this->attributes['brand'] ?? $this->brand ?? ''),
            'price'       => (float) ($this->attributes['price'] ?? $this->price ?? 0.0),
            'rating'      => (float) ($this->attributes['rating'] ?? $this->rating ?? 0.0),
            'in_stock'    => (bool) ($this->attributes['in_stock'] ?? $this->in_stock ?? true),
        ];
    }
}
```

Push your model schema settings to Meilisearch or Typesense:

```bash
php spark jengo:search sync-settings "App\Models\ProductModel"
```

Bulk import existing database records into the index:

```bash
php spark jengo:search import "App\Models\ProductModel" --chunk 500
```

---

## Querying

Execute searches using the `Search` facade or the `search()` helper:

```php
use App\Models\ProductModel;
use Jengo\Search\Facades\Search;

// Basic full-text query
$results = Search::query(ProductModel::class, 'oled laptop')
    ->where('category', 'Laptops')
    ->where('brand', ['Apple', 'Dell']) // Multi-value arrays delegate to whereIn automatically
    ->where('in_stock', true)
    ->where('price', '>=', 500)
    ->sortBy('price', 'desc')
    ->highlight(['title', 'description'])
    ->facets(['category', 'brand'])
    ->paginate(perPage: 12, page: 1)
    ->get();

// Iterating over matched results
foreach ($results as $item) {
    echo $item->title; // Original field value
    echo $item->getHighlight('title'); // Highlighted snippet (e.g. "Dell XPS 15 <mark>OLED</mark> Laptop")
}

// Accessing metadata and facets
$totalMatched = $results->total();
$timeTakenMs  = $results->executionTimeMs();
$facetCounts  = $results->facets();
```

---

## Inertia.js & Frontend Integration

Pass serialized search collections directly to Inertia.js:

```php
// app/Controllers/SearchController.php
public function index()
{
    $q = (string) ($this->request->getGet('q') ?? '');
    $page = (int) ($this->request->getGet('page') ?? 1);

    $results = Search::query(ProductModel::class, $q)
        ->highlight(['title', 'description'])
        ->facets(['category', 'brand'])
        ->paginate(12, $page)
        ->get();

    return Inertia::render('search/index', [
        'results' => $results->jsonSerialize(),
        'query'   => $q,
    ]);
}
```

Render highlighted matches in Vue 3:

```vue
<template>
  <div v-for="item in results.data" :key="item.id" class="product-card">
    <h3 v-html="item._highlights?.title || item.title"></h3>
    <p v-html="item._highlights?.description || item.description"></p>
    <span class="price">${{ item.price }}</span>
  </div>
</template>

<style>
/* Style both Meilisearch (em) and Typesense (mark) matches */
em, mark {
  font-style: normal;
  background-color: rgba(59, 130, 246, 0.2);
  color: #2563eb;
  font-weight: 700;
  border-radius: 0.25rem;
  padding: 0.1rem 0.25rem;
}
</style>
```

---

## Testing & Mocking

Test your search logic in-memory without connecting to live search clusters:

```php
use Jengo\Search\Facades\Search;

public function testProductSearch(): void
{
    $fake = Search::fake();

    Search::updateDocuments('products', [
        ['id' => 1, 'title' => 'MacBook Pro'],
    ]);

    $fake->assertIndexed('products', 1);

    Search::deleteDocuments('products', [1]);
    $fake->assertNotIndexed('products', 1);
}
```

---

## CLI Reference

| Command | Description |
| :--- | :--- |
| `php spark jengo:search status` | Inspect connectivity and driver health. |
| `php spark jengo:search sync-settings <Model>` | Push schema, attributes, and ranking settings to cluster. |
| `php spark jengo:search import <Model> [--chunk 500]` | Batch import model records into search index. |
| `php spark jengo:search flush <index\|Model>` | Purge all documents from the search index. |

---

## License

This package is open-source software licensed under the [MIT license](LICENSE).
