<?php

declare(strict_types=1);

namespace Jengo\Search\Testing\Concerns;

use PHPUnit\Framework\Assert;

trait SearchTestAssertionsTrait
{
    /**
     * Assert that a document with the given ID exists in the specified index.
     */
    public function assertIndexed(string $index, string|int $id, ?callable $callback = null): void
    {
        Assert::assertArrayHasKey(
            (string) $id,
            $this->indexes[$index] ?? [],
            "Expected document with ID [{$id}] to be indexed in [{$index}], but it was not found."
        );

        if ($callback !== null) {
            $doc = $this->indexes[$index][(string) $id];
            Assert::assertTrue(
                (bool) $callback($doc),
                "Document with ID [{$id}] in index [{$index}] failed the assertion callback."
            );
        }
    }

    /**
     * Assert that a document with the given ID does NOT exist in the specified index.
     */
    public function assertNotIndexed(string $index, string|int $id): void
    {
        Assert::assertArrayNotHasKey(
            (string) $id,
            $this->indexes[$index] ?? [],
            "Expected document with ID [{$id}] not to be indexed in [{$index}], but it was found."
        );
    }

    /**
     * Assert the total count of documents in the specified index.
     */
    public function assertIndexCount(string $index, int $expectedCount): void
    {
        $actualCount = count($this->indexes[$index] ?? []);
        Assert::assertSame(
            $expectedCount,
            $actualCount,
            "Expected index [{$index}] to have [{$expectedCount}] documents, but found [{$actualCount}]."
        );
    }

    /**
     * Assert that an index was flushed.
     */
    public function assertFlushed(string $index): void
    {
        Assert::assertTrue(
            !empty($this->flushedIndexes[$index]),
            "Expected index [{$index}] to have been flushed, but it was not."
        );
    }

    /**
     * Assert that nothing has been indexed across any index.
     */
    public function assertNothingIndexed(): void
    {
        $total = 0;
        foreach ($this->indexes as $docs) {
            $total += count($docs);
        }

        Assert::assertSame(0, $total, "Expected nothing to be indexed, but found [{$total}] documents.");
    }
}
