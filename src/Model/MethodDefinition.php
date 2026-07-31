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

use function array_map;

/**
 * One method, private ones included.
 *
 * `$modifiers` is the source-order list - `['public']`, `['public', 'static']`
 * - because the rendered signature joins them verbatim. `$visibility` is the
 * single word derived from it, used for grouping and filtering.
 */
final class MethodDefinition
{
    /**
     * @param list<string>                   $modifiers
     * @param 'public'|'protected'|'private' $visibility
     * @param list<ParameterDefinition>      $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly array $modifiers,
        public readonly string $visibility,
        public readonly array $parameters,
        public readonly ?string $returnType,
        public readonly string $description,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     modifiers: list<string>,
     *     visibility: string,
     *     parameters: list<array{name: string, type: string, default: string|null}>,
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
            'parameters'  => array_map(
                static fn (ParameterDefinition $parameter): array => $parameter->toArray(),
                $this->parameters
            ),
            'returnType'  => $this->returnType,
            'description' => $this->description,
        ];
    }
}
