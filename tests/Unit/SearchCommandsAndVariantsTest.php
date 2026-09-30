<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
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
    private MockInputOutput $io;

    public function setUp(): void
    {
        parent::setUp();

        $this->io = new MockInputOutput();

        CLI::setInputOutput($this->io);
    }
    protected function tearDown(): void
    {
        Search::resetFake();
        parent::tearDown();

        CLI::resetInputOutput();
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
        $variant->run([DummySearchableModel::class]);
        $out = $this->io->getOutput();
        $this->assertStringContainsString(DummySearchableModel::class, $out);
        $this->assertStringContainsString('Successfully imported records into search index', $out);

        $fake->assertIndexed('dummy_articles', 'a-1');
        $fake->assertIndexed('dummy_articles', 'a-2');

        // Run with empty model argument
        $variant->run([]);
        $outEmpty = $this->io->getOutput();
        $this->assertStringContainsString('Please provide a model class name to import', $outEmpty);

        $variant->run(['App\\NonExistentClass']);
        $nonExistent = $this->io->getOutput();
        $this->assertStringContainsString('Model class [App\NonExistentClass] not found', $nonExistent);
    }

    public function testFlushVariantMetaAndExecution(): void
    {
        $variant = new FlushVariant();
        $this->assertSame('flush', FlushVariant::name());
        $this->assertNotEmpty(FlushVariant::description());
        $this->assertArrayHasKey('target', $variant->arguments());
        $this->assertIsArray($variant->options());

        $fake = Search::fake();

        $variant->run(['posts']);
        $out = $this->io->getOutput();
        $this->assertStringContainsString('Flushing search index [posts]', $out);
        $this->assertStringContainsString('flushed successfully', $out);
        $fake->assertFlushed('posts');

        // Run by model class
        $variant->run([DummySearchableModel::class]);
        $outModel = $this->io->getOutput();
        $this->assertStringContainsString('Flushing search index [dummy_articles]', $outModel);
        $this->assertStringContainsString('flushed successfully', $outModel);
        $fake->assertFlushed('dummy_articles');

        // Run with empty argument
        $variant->run([]);
        $outEmpty = $this->io->getOutput();
        $this->assertStringContainsString('Please provide an index name or model class name to flush', $outEmpty);
    }

    public function testSyncSettingsVariantMetaAndExecution(): void
    {
        $variant = new SyncSettingsVariant();
        $this->assertSame('sync-settings', SyncSettingsVariant::name());
        $this->assertNotEmpty(SyncSettingsVariant::description());
        $this->assertArrayHasKey('model', $variant->arguments());

        $fake = Search::fake();

        $variant->run([DummySearchableModel::class]);
        $out = $this->io->getOutput();
        $this->assertStringContainsString('Syncing index settings for [dummy_articles]', $out);
        $this->assertStringContainsString('Index settings synced successfully', $out);

        // Run with empty argument
        $variant->run([]);
        $outEmpty = $this->io->getOutput();
        $this->assertStringContainsString('Please provide a model class name', $outEmpty);

        // Run with non-existent class
        $variant->run(['Invalid\\Class']);
        $outNonExistent = $this->io->getOutput();
        $this->assertStringContainsString('Model class [Invalid\Class] not found', $outNonExistent);
    }

    public function testStatusVariantMetaAndExecution(): void
    {
        $variant = new StatusVariant();
        $this->assertSame('status', StatusVariant::name());
        $this->assertNotEmpty(StatusVariant::description());
        $this->assertArrayHasKey('--driver', $variant->options());

        Search::fake();

        $variant->run([]);
        $out = $this->io->getOutput();
        $this->assertStringContainsString('Checking search driver health', $out);
        $this->assertStringContainsString('fake', $out);
        $this->assertStringContainsString('ok', $out);
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
