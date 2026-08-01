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
 * One property, private ones included. Filtering by visibility is a
 * presentation decision and belongs to the formatter.
 *
 * `$shortcuts` records Zephir's get/set/toString shortcuts. Nothing formats
 * them today; they are captured because the reader can see them cheaply.
 */
final class PropertyDefinition implements Definition
{
    /**
     * @param 'public'|'protected'|'private' $visibility
     * @param list<string>                   $shortcuts
     */
    public function __construct(
        public readonly string $name,
        public readonly string $visibility,
        public readonly bool $isReadonly,
        public readonly ?string $default,
        public readonly string $varType,
        public readonly string $description,
        public readonly array $shortcuts,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     visibility: string,
     *     isReadonly: bool,
     *     default: string|null,
     *     varType: string,
     *     description: string,
     *     shortcuts: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'visibility'  => $this->visibility,
            'isReadonly'  => $this->isReadonly,
            'default'     => $this->default,
            'varType'     => $this->varType,
            'description' => $this->description,
            'shortcuts'   => $this->shortcuts,
        ];
    }
}
