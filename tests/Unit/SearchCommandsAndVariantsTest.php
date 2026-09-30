<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\CLI\CLI;
use Jengo\Search\Commands\SearchCommand;
use Jengo\Search\Commands\Variants\FlushVariant;
use Jengo\Search\Commands\Variants\ImportVariant;
use Jengo\Search\Commands\Variants\StatusVariant;
use Jengo\Search\Commands\Variants\SyncSettingsVariant;
use Jengo\Search\Facades\Search;
use Jengo\Search\Installers\SearchInstaller;
use PHPUnit\Framework\TestCase;

class SearchCommandsAndVariantsTest extends TestCase
{
    protected function tearDown(): void
    {
        Search::resetFake();
        parent::tearDown();
    }

    public function testImportVariantMetaAndExecution(): void
    {
        $variant = new ImportVariant();
        $this->assertSame('import', ImportVariant::name());
        $this->assertNotEmpty(ImportVariant::description());
        $this->assertArrayHasKey('model', $variant->arguments());
        $this->assertArrayHasKey('--chunk', $variant->options());

        // Fake search
        $fake = Search::fake();

        // Run with valid model
        ob_start();
        $variant->run([DummySearchableModel::class]);
        $out = ob_get_clean();

        $fake->assertIndexed('dummy_articles', 'a-1');
        $fake->assertIndexed('dummy_articles', 'a-2');

        // Run with empty model argument
        ob_start();
        $variant->run([]);
        $outEmpty = ob_get_clean();

        // Run with invalid non-existent class
        ob_start();
        $variant->run(['App\\NonExistentClass']);
        $outNonExistent = ob_get_clean();
    }

    public function testFlushVariantMetaAndExecution(): void
    {
        $variant = new FlushVariant();
        $this->assertSame('flush', FlushVariant::name());
        $this->assertNotEmpty(FlushVariant::description());
        $this->assertArrayHasKey('target', $variant->arguments());
        $this->assertIsArray($variant->options());

        $fake = Search::fake();

        ob_start();
        $variant->run(['posts']);
        ob_get_clean();

        $fake->assertFlushed('posts');

        // Run by model class
        ob_start();
        $variant->run([DummySearchableModel::class]);
        ob_get_clean();

        $fake->assertFlushed('dummy_articles');

        // Run with empty argument
        ob_start();
        $variant->run([]);
        ob_get_clean();
    }

    public function testSyncSettingsVariantMetaAndExecution(): void
    {
        $variant = new SyncSettingsVariant();
        $this->assertSame('sync-settings', SyncSettingsVariant::name());
        $this->assertNotEmpty(SyncSettingsVariant::description());
        $this->assertArrayHasKey('model', $variant->arguments());

        $fake = Search::fake();

        ob_start();
        $variant->run([DummySearchableModel::class]);
        ob_get_clean();

        // Run with empty argument
        ob_start();
        $variant->run([]);
        ob_get_clean();

        // Run with non-existent class
        ob_start();
        $variant->run(['Invalid\\Class']);
        ob_get_clean();
    }

    public function testStatusVariantMetaAndExecution(): void
    {
        $variant = new StatusVariant();
        $this->assertSame('status', StatusVariant::name());
        $this->assertNotEmpty(StatusVariant::description());
        $this->assertArrayHasKey('--driver', $variant->options());

        Search::fake();

        ob_start();
        $variant->run([]);
        $out = ob_get_clean();
        $this->assertIsString($out);
    }

    public function testSearchInstaller(): void
    {
        $installer = new SearchInstaller();
        $this->assertSame('search', SearchInstaller::name());
        $this->assertNotEmpty(SearchInstaller::description());
        $this->assertNotEmpty(SearchInstaller::reasonForSkipping());
        $this->assertIsBool($installer->shouldRun());
    }
}
