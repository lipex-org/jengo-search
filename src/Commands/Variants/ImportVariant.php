<?php

declare(strict_types=1);

namespace Jengo\Search\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Jengo\Search\Contracts\SearchableInterface;

class ImportVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'import';
    }

    public static function description(): string
    {
        return 'Import model records into the search index.';
    }

    public function arguments(): array
    {
        return [
            'model' => 'The fully qualified model class name (e.g. App\\Models\\ArticleModel)',
        ];
    }

    public function options(): array
    {
        return [
            '--chunk' => 'The number of records to process at a time (default: 500)',
        ];
    }

    public function run(array $params): void
    {
        $modelClass = $params[0] ?? CLI::getOption('model') ?? null;

        if (!$modelClass) {
            CLI::error('Please provide a model class name to import.');
            return;
        }

        // Normalize slashes
        $modelClass = str_replace('/', '\\', (string) $modelClass);

        if (!class_exists($modelClass)) {
            CLI::error("Model class [{$modelClass}] not found.");
            return;
        }

        $model = new $modelClass();

        if (!$model instanceof SearchableInterface && !method_exists($model, 'makeAllSearchable')) {
            CLI::error("Model [{$modelClass}] does not use the Searchable trait or implement SearchableInterface.");
            return;
        }

        $chunk = (int) (CLI::getOption('chunk') ?? 500);
        if ($chunk <= 0) {
            $chunk = 500;
        }

        CLI::write("Importing records from [{$modelClass}] into search index (chunk: {$chunk})...", 'yellow');

        $model->makeAllSearchable($chunk);

        CLI::write("Successfully imported records into search index.", 'green');
    }
}
