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
 * A collection whose members carry a visibility, and can therefore be narrowed
 * by one.
 *
 * Both questions used to be answered twice, once per collection, in bodies that
 * differed only in a type hint. They sit here instead of on the base so that
 * constants and parameters - which have no visibility - are not offered them.
 *
 * @template TDefinition of VisibleDefinition
 *
 * @extends AbstractDefinitionCollection<TDefinition>
 */
abstract class AbstractVisibleCollection extends AbstractDefinitionCollection
{
    /**
     * The model keeps private members; hiding them is a formatter decision.
     *
     * @return static
     */
    public function withoutPrivate(): static
    {
        return $this->filter(
            static fn (VisibleDefinition $definition): bool => $definition->visibility !== 'private'
        );
    }

    /**
     * @param 'public'|'protected'|'private' $visibility
     *
     * @return static
     */
    public function withVisibility(string $visibility): static
    {
        return $this->filter(
            static fn (VisibleDefinition $definition): bool => $definition->visibility === $visibility
        );
    }
}
