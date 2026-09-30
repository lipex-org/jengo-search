# Jengo Search

A unified, multi-driver full-text search engine package for CodeIgniter 4 and the Jengo ecosystem. Supports **Meilisearch**, **Typesense**, and **Database (SQL/SQLite)** drivers with an expressive query builder, attribute-driven schema definitions, automatic model lifecycle hooks, and zero external SDK dependencies.

Documentation: https://lipex-org.github.io/jengophp.com/packages/search

---

## Installation

```bash
composer require jengo/search
php spark jengo:install search
```

---

## Quick Example

```php
use App\Models\ProductModel;
use Jengo\Search\Facades\Search;

// Execute a full-text search with facets and highlighting
$results = Search::query(ProductModel::class, 'oled laptop')
    ->where('category', 'Laptops')
    ->where('in_stock', true)
    ->highlight(['title', 'description'])
    ->facets(['category', 'brand'])
    ->paginate(12, 1)
    ->get();
```

---

## Documentation

For comprehensive guides, driver configuration, model lifecycle hooks, Inertia.js integration, and CLI commands, visit the official documentation at https://lipex-org.github.io/jengophp.com/packages/search.

---

## License

Released under the [MIT License](LICENSE).
