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

namespace Phalcon\Scribe\Model;

use function str_starts_with;

/**
 * @extends AbstractDefinitionCollection<MethodDefinition>
 */
final class MethodDefinitionCollection extends AbstractDefinitionCollection
{
    /**
     * Reserved (`__*`) methods first, then alphabetical - the legacy ordering.
     */
    public function ordered(): self
    {
        return $this->sorted(
            static function (MethodDefinition $a, MethodDefinition $b): int {
                $left  = str_starts_with($a->name, '__') ? 0 : 1;
                $right = str_starts_with($b->name, '__') ? 0 : 1;

                return [$left, $a->name] <=> [$right, $b->name];
            }
        );
    }

    /**
     * @param 'public'|'protected'|'private' $visibility
     */
    public function withVisibility(string $visibility): self
    {
        return $this->filter(
            static fn (MethodDefinition $method): bool => $method->visibility === $visibility
        );
    }

    /**
     * The model keeps private members; hiding them is a formatter decision.
     */
    public function withoutPrivate(): self
    {
        return $this->filter(
            static fn (MethodDefinition $method): bool => $method->visibility !== 'private'
        );
    }
}
