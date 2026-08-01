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
 * One method, private ones included.
 *
 * `$modifiers` is the source-order list - `['public']`, `['public', 'static']`
 * - because the rendered signature joins them verbatim. `$visibility` is the
 * single word derived from it, used for grouping and filtering.
 */
final class MethodDefinition implements NamedDefinition
{
    /**
     * @param list<string>                   $modifiers
     * @param 'public'|'protected'|'private' $visibility
     */
    public function __construct(
        public readonly string $name,
        public readonly array $modifiers,
        public readonly string $visibility,
        public readonly ParameterDefinitionCollection $parameters,
        public readonly ?string $returnType,
        public readonly string $description,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     modifiers: list<string>,
     *     visibility: string,
     *     parameters: list<array<string, mixed>>,
     *     returnType: string|null,
     *     description: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'modifiers'   => $this->modifiers,
            'visibility'  => $this->visibility,
            'parameters'  => $this->parameters->toArray(),
            'returnType'  => $this->returnType,
            'description' => $this->description,
        ];
    }
}
