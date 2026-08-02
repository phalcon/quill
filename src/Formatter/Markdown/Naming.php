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

namespace Phalcon\Quill\Formatter\Markdown;

use Phalcon\Quill\Config;
use Phalcon\Quill\Model\ClassDefinition;

use function explode;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * How a definition's identity maps onto Markdown identity: heading text,
 * heading anchor, and which file it lands in.
 *
 * Deliberately absent from the model: a JSON or stubs consumer has no use for
 * a heading anchor or an mkdocs page name.
 */
final class Naming
{
    /**
     * The mkdocs heading anchor: the title with everything mkdocs would strip.
     */
    public function anchor(ClassDefinition $class, Config $config): string
    {
        return strtolower((string) preg_replace('/[^\w\s-]/', '', $this->title($class, $config)));
    }

    public function methodAnchor(ClassDefinition $class, string $methodName, Config $config): string
    {
        return $this->anchor($class, $config) . '-' . strtolower($methodName);
    }

    /**
     * The page a definition belongs on: its top-level namespace segment.
     */
    public function pageKey(ClassDefinition $class, Config $config): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, $class->location->relPath);
        $key      = str_replace('.' . $config->extension(), '', $segments[0]);

        return $config->pagePrefix() . strtolower($key);
    }

    /**
     * The heading text: the FQCN without the root namespace, which the page's
     * own notice already states.
     */
    public function title(ClassDefinition $class, Config $config): string
    {
        $root = $config->rootNamespace() . '\\';

        return str_starts_with($class->location->fqcn, $root)
            ? substr($class->location->fqcn, strlen($root))
            : $class->location->fqcn;
    }
}
