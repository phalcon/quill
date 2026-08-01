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

namespace Phalcon\Scribe\Formatter\Markdown;

use Phalcon\Scribe\Config;
use Phalcon\Scribe\Model\ClassDefinition;

use function explode;
use function preg_replace;
use function str_replace;
use function strtolower;

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
    public function anchor(ClassDefinition $class): string
    {
        return strtolower((string) preg_replace('/[^\w\s-]/', '', $this->title($class)));
    }

    public function methodAnchor(ClassDefinition $class, string $methodName): string
    {
        return $this->anchor($class) . '-' . strtolower($methodName);
    }

    /**
     * The page a definition belongs on: its top-level namespace segment.
     */
    public function pageKey(ClassDefinition $class, Config $config): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, $class->location->relPath);
        $key      = str_replace('.' . $config->extension(), '', $segments[0]);

        return 'phalcon_' . strtolower($key);
    }

    /**
     * The heading text: the FQCN without the vendor root, which the page's
     * own notice already states.
     */
    public function title(ClassDefinition $class): string
    {
        return (string) preg_replace('/^Phalcon\\\\/', '', $class->location->fqcn);
    }
}
