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
 * @extends AbstractDefinitionCollection<PropertyDefinition>
 */
final class PropertyDefinitionCollection extends AbstractDefinitionCollection
{
    public function sortedByName(): self
    {
        return $this->sorted(
            static fn (PropertyDefinition $a, PropertyDefinition $b): int => $a->name <=> $b->name
        );
    }

    /**
     * The model keeps private members; hiding them is a formatter decision.
     */
    public function withoutPrivate(): self
    {
        return $this->filter(
            static fn (PropertyDefinition $property): bool => $property->visibility !== 'private'
        );
    }
}
