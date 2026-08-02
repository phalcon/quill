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
use Phalcon\Quill\Exceptions\NamespaceNotFound;
use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Reader\ReaderFactory;
use Phalcon\Quill\Selection;

use function array_keys;
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
     * The registry always covers every source file; `$selection` narrows only
     * what is written out.
     */
    public function execute(Config $config, Selection $selection): int
    {
        $reader   = $this->factory->create($config->language());
        $registry = $reader->read($config);

        $this->guardNamespace($registry, $selection);

        $pages = $this->formatter->format($registry, $config, $selection);

        $output = $config->outputDir();
        if (!is_dir($output)) {
            mkdir($output, 0777, true);
        }

        $written = [];
        foreach ($pages as $page => $document) {
            $path = $output . DIRECTORY_SEPARATOR . $page . '.' . $this->formatter->extension();

            $this->write($path, $document);

            $written[$path] = true;

            fwrite($this->stdout, 'Processing: ' . $page . PHP_EOL);
        }

        foreach ($this->writeAssets($config) as $path) {
            fwrite($this->stdout, 'Asset: ' . basename($path) . PHP_EOL);
        }

        // Only an unnarrowed run may prune. A narrowed run is deliberately
        // partial, so what it did not write was never asked about, not stale.
        if (!$selection->narrows()) {
            foreach ($this->prune($output, $written) as $path) {
                fwrite($this->stdout, 'Removed: ' . basename($path) . PHP_EOL);
            }
        }

        fwrite($this->stdout, 'Done. Output: ' . $output . PHP_EOL);

        return 0;
    }

    /**
     * Checked against the registry rather than the formatter's output, because
     * a JSON run would otherwise emit a structurally valid document holding no
     * definitions - a silent success where a typo belongs.
     */
    private function guardNamespace(Registry $registry, Selection $selection): void
    {
        if ($selection->namespace === '') {
            return;
        }

        foreach (array_keys($registry->definitions()->all()) as $fqcn) {
            if ($selection->matchesNamespace($fqcn)) {
                return;
            }
        }

        throw new NamespaceNotFound($selection->namespace);
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
     * One file, or the failure that stopped it.
     *
     * The suppression is here rather than at the call sites because the return
     * value is checked: an unwritable destination would otherwise report a
     * full, successful run.
     */
    private function write(string $path, string $contents): void
    {
        if (@file_put_contents($path, $contents) === false) {
            throw new WriteFailed($path);
        }
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

            $this->write($path, $contents);

            $written[] = $path;
        }

        return $written;
    }
}
