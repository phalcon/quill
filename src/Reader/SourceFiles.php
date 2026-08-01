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

namespace Phalcon\Scribe\Reader;

use FilesystemIterator;
use Phalcon\Scribe\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function sort;
use function str_replace;

use const DIRECTORY_SEPARATOR;

/**
 * Finding the files to read is the same job whatever language they are in -
 * walk the configured root, keep the configured extension, sort for a stable
 * order.
 */
final class SourceFiles
{
    /**
     * @return list<string> paths relative to the configured source root
     */
    public static function collect(Config $config): array
    {
        $prefix   = $config->sourceRoot() . DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $config->sourceRoot(),
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $files = [];
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isFile() && $file->getExtension() === $config->extension()) {
                $files[] = str_replace($prefix, '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }
}
