<?php

declare(strict_types=1);

namespace Jengo\Search\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Jengo\Search\Contracts\SearchableInterface;

class SyncSettingsVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'sync-settings';
    }

    public static function description(): string
    {
        return 'Sync index schema and settings (filterable, sortable, searchable fields) to the search engine.';
    }

    public function arguments(): array
    {
        return [
            'model' => 'The searchable model class name (e.g. App\\Models\\ArticleModel)',
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function run(array $params): void
    {
        $modelClass = $params[0] ?? null;

        if (!$modelClass) {
            CLI::error('Please provide a model class name.');
            return;
        }

        $modelClass = str_replace('/', '\\', (string) $modelClass);

        if (!class_exists($modelClass)) {
            CLI::error("Model class [{$modelClass}] not found.");
            return;
        }

        $model = new $modelClass();

        if (!$model instanceof SearchableInterface && !method_exists($model, 'indexSettings')) {
            CLI::error("Model [{$modelClass}] does not implement SearchableInterface or indexSettings().");
            return;
        }

        $indexName = $model->searchableAs();
        $settings = $model->indexSettings();

        CLI::write("Syncing index settings for [{$indexName}]...", 'yellow');

        $manager = Services::search();
        $success = $manager->driver()->syncSettings($indexName, $settings);

        if ($success) {
            CLI::write("Index settings synced successfully for [{$indexName}].", 'green');
        } else {
            CLI::error("Failed to sync index settings for [{$indexName}].");
        }
    }
}
