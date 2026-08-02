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

use function str_starts_with;

/**
 * @extends AbstractVisibleCollection<MethodDefinition>
 */
final class MethodDefinitionCollection extends AbstractVisibleCollection
{
    /**
     * Reserved (`__*`) methods first, then alphabetical.
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
}
