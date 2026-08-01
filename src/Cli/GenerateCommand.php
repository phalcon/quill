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

namespace Phalcon\Scribe\Cli;

use Phalcon\Scribe\Config;
use Phalcon\Scribe\Contracts\Formatter;
use Phalcon\Scribe\Reader\ReaderFactory;

use function file_put_contents;
use function fwrite;
use function is_dir;
use function mkdir;

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

        foreach ($pages as $page => $document) {
            file_put_contents($output . DIRECTORY_SEPARATOR . $page . '.md', $document);

            fwrite($this->stdout, 'Processing: ' . $page . PHP_EOL);
        }

        fwrite($this->stdout, 'Done. Output: ' . $output . PHP_EOL);

        return 0;
    }
}
