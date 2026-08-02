<?php

/**
 * This file is part of the Phalcon Quill.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Quill\Tests\Unit\Cli;

use Phalcon\Quill\Cli\GenerateCommand;
use Phalcon\Quill\Config;
use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Formatter\MarkdownFormatter;
use Phalcon\Quill\Reader\ReaderFactory;
use PHPUnit\Framework\TestCase;

use function chmod;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function glob;
use function is_dir;
use function is_file;
use function rmdir;
use function unlink;

final class GenerateCommandTest extends TestCase
{
    private string $outputDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = dirname(__DIR__, 2) . '/_output/generate';

        // Mutation runs share this directory and can leave it behind, so the
        // test starts from nothing rather than from whatever survived.
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();

        parent::tearDown();
    }

    public function testAFilteredRunPrunesNothing(): void
    {
        $this->command()->execute($this->config());

        $kept = $this->outputDir . '/phalcon_sample.md';
        $this->assertFileExists($kept);

        // Filtered runs are deliberately partial, so untouched pages stay.
        $this->command()->execute($this->config(), 'nothingmatches');

        $this->assertFileExists($kept);
    }

    public function testAnUnwritableDestinationFailsLoudly(): void
    {
        $this->command()->execute($this->config());

        // Simulate what a root-owned output directory does to a non-root run.
        $page = $this->outputDir . '/phalcon_sample.md';
        chmod($page, 0444);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessage('Could not write');

        try {
            $this->command()->execute($this->config());
        } finally {
            chmod($page, 0644);
        }
    }

    public function testCreatesTheOutputDirectoryWhenAbsent(): void
    {
        $this->assertFalse(file_exists($this->outputDir));

        $this->command()->execute($this->config());

        $this->assertDirectoryExists($this->outputDir);
    }

    public function testFilterRestrictsWhichPagesAreWritten(): void
    {
        $this->assertSame(0, $this->command()->execute($this->config(), 'nothingmatches'));

        // The index is always written; no page files survive the filter.
        $this->assertFileExists($this->outputDir . '/index.md');
        $this->assertSame([], glob($this->outputDir . '/phalcon_*.md') ?: []);
    }

    public function testOtherFileTypesAreLeftAlone(): void
    {
        $this->command()->execute($this->config());

        $foreign = $this->outputDir . '/notes.txt';
        file_put_contents($foreign, 'not ours');

        $this->command()->execute($this->config());

        $this->assertFileExists($foreign);
        unlink($foreign);
    }

    public function testStaleDocumentsArePruned(): void
    {
        $this->command()->execute($this->config());

        // A page whose source namespace has since been deleted.
        $orphan = $this->outputDir . '/phalcon_gone.md';
        file_put_contents($orphan, 'stale');

        $this->command()->execute($this->config());

        $this->assertFileDoesNotExist($orphan);
        $this->assertFileExists($this->outputDir . '/phalcon_sample.md');
    }

    public function testWritesTheIndexAndEveryPage(): void
    {
        $this->assertSame(0, $this->command()->execute($this->config()));

        $this->assertFileExists($this->outputDir . '/index.md');
        $this->assertFileExists($this->outputDir . '/phalcon_sample.md');

        $this->assertStringContainsString(
            '- [Phalcon Sample](phalcon_sample.md)',
            (string) file_get_contents($this->outputDir . '/index.md')
        );
    }

    private function clean(): void
    {
        foreach (glob($this->outputDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }
    }

    private function command(): GenerateCommand
    {
        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        return new GenerateCommand(new ReaderFactory(), new MarkdownFormatter(), $stdout);
    }

    private function config(): Config
    {
        return new Config(
            'zephir',
            dirname(__DIR__, 2) . '/Fixtures/zep',
            $this->outputDir,
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep',
            'Phalcon'
        );
    }
}
