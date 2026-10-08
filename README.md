<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/jengophp.com/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo Search</h1>

<p align="center">
  <strong>Pluggable full-text search engine for CodeIgniter 4 supporting Meilisearch, Algolia, and Database full-text search drivers.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/search"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/search/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/search/issues"><strong>Issues</strong></a>
</p>

---

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
