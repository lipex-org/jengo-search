<?php

declare(strict_types=1);

namespace Jengo\Search\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Jengo\Search\Contracts\SearchableInterface;

class FlushVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'flush';
    }

    public static function description(): string
    {
        return 'Flush (clear all documents from) a search index.';
    }

    public function arguments(): array
    {
        return [
            'target' => 'The search index name or model class name',
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function run(array $params): void
    {
        $target = $params[0] ?? null;

        if (!$target) {
            CLI::error('Please provide an index name or model class name to flush.');
            return;
        }

        $target = str_replace('/', '\\', (string) $target);
        $indexName = $target;

        if (class_exists($target)) {
            $model = new $target();
            if ($model instanceof SearchableInterface || method_exists($model, 'searchableAs')) {
                $indexName = $model->searchableAs();
            }
        }

        CLI::write("Flushing search index [{$indexName}]...", 'yellow');

        $manager = Services::search();
        $success = $manager->driver()->flush($indexName);

        if ($success) {
            CLI::write("Search index [{$indexName}] has been flushed successfully.", 'green');
        } else {
            CLI::error("Failed to flush search index [{$indexName}].");
        }
    }
}
