<?php

declare(strict_types=1);

namespace Jengo\Search\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;

class StatusVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'status';
    }

    public static function description(): string
    {
        return 'Check the connectivity and status of the configured search driver.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--driver' => 'Specific search driver to check (defaults to default driver)',
        ];
    }

    public function run(array $params): void
    {
        $driverName = CLI::getOption('driver');
        $manager = Services::search();
        $driver = $driverName ? $manager->driver((string) $driverName) : $manager->driver();

        CLI::write("Checking search driver health...", 'yellow');

        $status = $driver->status();

        CLI::table(
            array_map(
                static fn ($k, $v) => [$k, is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)],
                array_keys($status),
                array_values($status)
            ),
            ['Metric / Setting', 'Value']
        );
    }
}
