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

namespace Phalcon\Quill\Model;

use function ltrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strstr;
use function substr;

/**
 * The file's namespace imports.
 *
 * Not to be confused with Relations::$traits - both come from a `use`
 * keyword, but importing a name and pulling in a trait are different things.
 */
final class Imports
{
    /**
     * @param list<string>          $uses    fully qualified, in source order
     * @param array<string, string> $aliases short name => FQCN, including the
     *                                       implicit alias of the last segment
     */
    public function __construct(
        public readonly array $uses,
        public readonly array $aliases,
    ) {
    }

    /**
     * A name written in this file, resolved to an absolute one.
     *
     * Both languages spell a parent three ways - `\Foo` outright, `Foo` behind
     * a `use`, or `Foo` meaning the sibling in the same namespace - and which
     * one a source picks says nothing about what it means. Recording the name
     * as written left the model carrying all three spellings, so two trees that
     * agree looked like they disagreed.
     *
     * The rules are PHP's, and Zephir follows them: a leading backslash is
     * already absolute, a first segment matching an import takes that import's
     * target, and anything else belongs to the enclosing namespace. The
     * backslash stays on the way out, marking the name as resolved rather than
     * relative to wherever it is read next.
     */
    public function qualify(string $name, string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return $name;
        }

        $head  = str_contains($name, '\\') ? (string) strstr($name, '\\', true) : $name;
        $alias = $this->aliases[$head] ?? null;

        if ($alias !== null) {
            return '\\' . ltrim($alias, '\\') . substr($name, strlen($head));
        }

        return $namespace === '' ? '\\' . $name : '\\' . $namespace . '\\' . $name;
    }

    /**
     * @return array{uses: list<string>, aliases: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'uses'    => $this->uses,
            'aliases' => $this->aliases,
        ];
    }
}
