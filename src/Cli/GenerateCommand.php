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

namespace Phalcon\Quill\Cli;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Formatter;
use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Reader\ReaderFactory;

use function basename;
use function file_put_contents;
use function fwrite;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const STDOUT;

/**
 * Reads a source tree and writes one document per page.
 */
final class GenerateCommand
{
    /**
     * @param resource $stdout
     */
    public function __construct(
        private readonly ReaderFactory $factory,
        private readonly Formatter $formatter,
        private $stdout = STDOUT,
    ) {
    }

    /**
     * The registry always covers every source file; `$filter` narrows only
     * what is written out.
     */
    public function execute(Config $config, string $filter = ''): int
    {
        $reader   = $this->factory->create($config->language());
        $registry = $reader->read($config);
        $pages    = $this->formatter->format($registry, $config, $filter);

        $output = $config->outputDir();
        if (!is_dir($output)) {
            mkdir($output, 0777, true);
        }

        $written = [];
        foreach ($pages as $page => $document) {
            $path = $output . DIRECTORY_SEPARATOR . $page . '.' . $this->formatter->extension();

            // Suppressed because the return value is checked: an unwritable
            // destination would otherwise report a full, successful run.
            if (@file_put_contents($path, $document) === false) {
                throw new WriteFailed($path);
            }

            $written[$path] = true;

            fwrite($this->stdout, 'Processing: ' . $page . PHP_EOL);
        }

        foreach ($this->writeAssets($config) as $path) {
            fwrite($this->stdout, 'Asset: ' . basename($path) . PHP_EOL);
        }

        // Only a complete run may prune. A filtered run is deliberately partial,
        // so what it did not write was never asked about, not stale.
        if ($filter === '') {
            foreach ($this->prune($output, $written) as $path) {
                fwrite($this->stdout, 'Removed: ' . basename($path) . PHP_EOL);
            }
        }

        fwrite($this->stdout, 'Done. Output: ' . $output . PHP_EOL);

        return 0;
    }

    /**
     * Deletes documents this run did not produce, so a source namespace that
     * disappears takes its page with it instead of leaving an orphan that the
     * index no longer links.
     *
     * Scoped to the formatter's own extension: anything else in the output
     * directory belongs to someone else.
     *
     * @param array<string, true> $written
     *
     * @return list<string>
     */
    private function prune(string $output, array $written): array
    {
        $pattern = $output . DIRECTORY_SEPARATOR . '*.' . $this->formatter->extension();

        $removed = [];
        foreach (glob($pattern) ?: [] as $path) {
            if (isset($written[$path]) || !is_file($path)) {
                continue;
            }

            if (unlink($path)) {
                $removed[] = $path;
            }
        }

        return $removed;
    }

    /**
     * Writes the formatter's static assets to the configured assets directory,
     * which defaults to the one the documents go in.
     *
     * A filtered run writes them too - they do not vary with what was selected.
     * prune() cannot reach them either way: it globs the formatter's own
     * extension, and an asset never carries it.
     *
     * @return list<string>
     */
    private function writeAssets(Config $config): array
    {
        $assets = $this->formatter->assets();
        if ($assets === []) {
            return [];
        }

        $directory = $config->assetsDir();
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $written = [];
        foreach ($assets as $name => $contents) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;

            if (@file_put_contents($path, $contents) === false) {
                throw new WriteFailed($path);
            }

            $written[] = $path;
        }

        return $written;
    }
}
