<?php

/**
 * This file is part of the Phalcon Scribe.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Scribe\Tests\Unit\Cli;

use Phalcon\Scribe\Cli\GenerateCommand;
use Phalcon\Scribe\Config;
use Phalcon\Scribe\Formatter\MarkdownFormatter;
use Phalcon\Scribe\Reader\ReaderFactory;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_exists;
use function file_get_contents;
use function fopen;
use function glob;
use function is_dir;
use function rmdir;
use function unlink;

final class GenerateCommandTest extends TestCase
{
    private string $outputDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = dirname(__DIR__, 2) . '/_output/generate';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outputDir . '/*.md') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }

        parent::tearDown();
    }

    public function testFilterRestrictsWhichPagesAreWritten(): void
    {
        $this->assertSame(0, $this->command()->execute($this->config(), 'nothingmatches'));

        // The index is always written; no page files survive the filter.
        $this->assertFileExists($this->outputDir . '/index.md');
        $this->assertSame([], glob($this->outputDir . '/phalcon_*.md') ?: []);
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

    public function testCreatesTheOutputDirectoryWhenAbsent(): void
    {
        $this->assertFalse(file_exists($this->outputDir));

        $this->command()->execute($this->config());

        $this->assertDirectoryExists($this->outputDir);
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
            'zep'
        );
    }
}
