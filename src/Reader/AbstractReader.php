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

namespace Phalcon\Quill\Reader;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Reader;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\ClassDefinitionCollection;
use Phalcon\Quill\Model\Registry;

use const DIRECTORY_SEPARATOR;

/**
 * The part of reading a source tree that has nothing to do with the language.
 *
 * Walking the configured root, skipping files that declare nothing, and
 * assembling the registry are the same job whatever the syntax. What differs is
 * one file in, one definition out - which is what a subclass supplies.
 */
abstract class AbstractReader implements Reader
{
    public function read(Config $config): Registry
    {
        $prefix      = $config->sourceRoot() . DIRECTORY_SEPARATOR;
        $definitions = [];

        foreach (SourceFiles::collect($config) as $relPath) {
            $class = $this->readSource($prefix . $relPath, $relPath);

            if ($class !== null) {
                $definitions[] = $class;
            }
        }

        return new Registry(
            ClassDefinitionCollection::fromDefinitions($definitions),
            $config->rootNamespace()
        );
    }

    /**
     * One source file, parsed and read. Null when it declares no class,
     * interface, trait or enum - an empty file is skipped, not an error.
     *
     * `$relPath` is carried through rather than derived: it is what the model
     * records as the location, and only the caller knows the root it is
     * relative to.
     */
    abstract protected function readSource(string $absolutePath, string $relPath): ?ClassDefinition;
}
