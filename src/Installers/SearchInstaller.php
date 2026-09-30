<?php

declare(strict_types=1);

namespace Jengo\Search\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class SearchInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'search';
    }

    public static function description(): string
    {
        return 'Install search engine support (Meilisearch, Typesense, Database) and publish configuration';
    }

    public static function reasonForSkipping(): string
    {
        return 'Search configuration already published in app/Config/Search.php.';
    }

    public function shouldRun(): bool
    {
        return !file_exists(APPPATH . 'Config/Search.php');
    }

    public function install(): void
    {
        $this->addRun();

        $dest = APPPATH . 'Config/Search.php';
        if (file_exists($dest)) {
            CLI::write('Config/Search.php already exists, skipping.', 'yellow');
            return;
        }

        $source = __DIR__ . '/../Config/Search.php';
        $content = (string) file_get_contents($source);
        $content = str_replace(
            "namespace Jengo\\Search\\Config;\n\nuse CodeIgniter\\Config\\BaseConfig;",
            "namespace Config;\n\nuse Jengo\\Search\\Config\\Search as BaseSearch;",
            $content
        );
        $content = str_replace(
            "class Search extends BaseConfig",
            "class Search extends BaseSearch",
            $content
        );

        $this->writeFile($dest, $content);
        CLI::write('Published Config/Search.php successfully.', 'green');
    }
}
