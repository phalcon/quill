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
use Phalcon\Quill\Contracts\Formatter;
use Phalcon\Quill\Exceptions\NamespaceNotFound;
use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Formatter\JsonFormatter;
use Phalcon\Quill\Formatter\MarkdownFormatter;
use Phalcon\Quill\Reader\ReaderFactory;
use Phalcon\Quill\Selection;
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
use function rewind;
use function rmdir;
use function stream_get_contents;
use function strpos;
use function unlink;

final class GenerateCommandTest extends TestCase
{
    private string $outputDir = '';

    /** @var resource|null */
    private $stdout = null;

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
        $this->command()->execute($this->config(), Selection::none());

        $kept = $this->outputDir . '/phalcon_sample.md';
        $this->assertFileExists($kept);

        // Filtered runs are deliberately partial, so untouched pages stay.
        $this->command()->execute($this->config(), new Selection('nothingmatches'));

        $this->assertFileExists($kept);
    }

    /**
     * A formatter with no assets skips the whole step rather than creating an
     * empty directory for nothing.
     */
    public function testAFormatterWithoutAssetsWritesNone(): void
    {
        $assets = $this->outputDir . '/assets';

        $this->command(new JsonFormatter())->execute($this->config($assets), Selection::none());

        $this->assertDirectoryDoesNotExist($assets);
        $this->assertFileExists($this->outputDir . '/model.json');
    }

    /**
     * The assets directory is created when it is somewhere other than the one
     * the documents go in, which is the layout a documentation site wants.
     */
    public function testAnAbsentAssetsDirectoryIsCreated(): void
    {
        $assets = $this->outputDir . '/nested/assets/css';

        $this->command()->execute($this->config($assets), Selection::none());

        $this->assertFileExists($assets . '/' . MarkdownFormatter::STYLESHEET);
    }

    public function testANamespacedRunPrunesNothing(): void
    {
        $stale = $this->outputDir . '/phalcon_stale.md';
        $this->command()->execute($this->config(), Selection::none());
        file_put_contents($stale, 'stale');

        $this->command()->execute($this->config(), new Selection('', 'Phalcon\\Sample'));

        $this->assertFileExists($stale);
    }

    /**
     * A typo would otherwise produce a structurally valid document holding no
     * definitions, which reads as a successful run.
     */
    public function testANamespaceMatchingNothingFailsBeforeAnythingIsWritten(): void
    {
        $this->expectException(NamespaceNotFound::class);
        $this->expectExceptionMessageMatches('/Phalcon\\\\Nope/');

        try {
            $this->command()->execute($this->config(), new Selection('', 'Phalcon\\Nope'));
        } finally {
            $this->assertSame([], glob($this->outputDir . '/*.md') ?: []);
        }
    }

    /**
     * The position is load-bearing: format() can throw on a bad placeholder,
     * and a warning printed after the per-page lines is buried in a successful
     * run.
     */
    public function testAnUnrecognizedOverrideWarnsBeforeAnyPage(): void
    {
        $this->command()->execute(
            $this->config('', dirname(__DIR__, 2) . '/Fixtures/templates'),
            Selection::none()
        );

        $output = $this->emitted();

        $warningAt    = strpos($output, 'Warning:');
        $processingAt = strpos($output, 'Processing:');

        $this->assertNotFalse($warningAt);
        $this->assertNotFalse($processingAt);
        $this->assertLessThan($processingAt, $warningAt);
    }

    public function testAnUnwritableAssetFailsLoudly(): void
    {
        $assets = $this->outputDir . '/assets';
        mkdir($assets, 0777, true);

        $stylesheet = $assets . '/' . MarkdownFormatter::STYLESHEET;
        file_put_contents($stylesheet, '');
        chmod($stylesheet, 0444);

        try {
            $this->expectException(WriteFailed::class);
            $this->command()->execute($this->config($assets), Selection::none());
        } finally {
            chmod($stylesheet, 0644);
        }
    }

    public function testAnUnwritableDestinationFailsLoudly(): void
    {
        $this->command()->execute($this->config(), Selection::none());

        // Simulate what a root-owned output directory does to a non-root run.
        $page = $this->outputDir . '/phalcon_sample.md';
        chmod($page, 0444);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessage('Could not write');

        try {
            $this->command()->execute($this->config(), Selection::none());
        } finally {
            chmod($page, 0644);
        }
    }

    public function testCreatesTheOutputDirectoryWhenAbsent(): void
    {
        $this->assertFalse(file_exists($this->outputDir));

        $this->command()->execute($this->config(), Selection::none());

        $this->assertDirectoryExists($this->outputDir);
    }

    public function testFilterRestrictsWhichPagesAreWritten(): void
    {
        $this->assertSame(0, $this->command()->execute($this->config(), new Selection('nothingmatches')));

        // The index is always written; no page files survive the filter.
        $this->assertFileExists($this->outputDir . '/index.md');
        $this->assertSame([], glob($this->outputDir . '/phalcon_*.md') ?: []);
    }

    public function testOtherFileTypesAreLeftAlone(): void
    {
        $this->command()->execute($this->config(), Selection::none());

        $foreign = $this->outputDir . '/notes.txt';
        file_put_contents($foreign, 'not ours');

        $this->command()->execute($this->config(), Selection::none());

        $this->assertFileExists($foreign);
        unlink($foreign);
    }

    public function testStaleDocumentsArePruned(): void
    {
        $this->command()->execute($this->config(), Selection::none());

        // A page whose source namespace has since been deleted.
        $orphan = $this->outputDir . '/phalcon_gone.md';
        file_put_contents($orphan, 'stale');

        $this->command()->execute($this->config(), Selection::none());

        $this->assertFileDoesNotExist($orphan);
        $this->assertFileExists($this->outputDir . '/phalcon_sample.md');
    }

    public function testWritesTheIndexAndEveryPage(): void
    {
        $this->assertSame(0, $this->command()->execute($this->config(), Selection::none()));

        $this->assertFileExists($this->outputDir . '/index.md');
        $this->assertFileExists($this->outputDir . '/phalcon_sample.md');

        $this->assertStringContainsString(
            '- [Phalcon Sample](phalcon_sample.md)',
            (string) file_get_contents($this->outputDir . '/index.md')
        );
    }

    private function clean(): void
    {
        $this->remove($this->outputDir);
    }

    private function command(?Formatter $formatter = null, string $format = 'markdown'): GenerateCommand
    {
        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        $this->stdout = $stdout;

        return new GenerateCommand(
            new ReaderFactory(),
            $formatter ?? new MarkdownFormatter(),
            $format,
            $stdout
        );
    }

    private function config(string $assetsDir = '', string $templatesDir = ''): Config
    {
        return new Config(
            'zephir',
            dirname(__DIR__, 2) . '/Fixtures/zep',
            $this->outputDir,
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep',
            'Phalcon',
            $assetsDir,
            $templatesDir
        );
    }

    /**
     * Everything the last command() built wrote to its stdout.
     */
    private function emitted(): string
    {
        $stdout = $this->stdout;
        $this->assertIsResource($stdout);

        rewind($stdout);

        return (string) stream_get_contents($stdout);
    }

    /**
     * Depth first, because a run can write into nested directories now that the
     * assets destination need not be the one the documents go in.
     */
    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        foreach (glob($path . '/*') ?: [] as $child) {
            $this->remove($child);
        }

        rmdir($path);
    }
}
