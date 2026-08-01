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
