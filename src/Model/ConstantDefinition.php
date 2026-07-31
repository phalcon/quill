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

/**
 * One class constant. `$varType` comes from the `@var` tag when present, and
 * otherwise from the default value's type.
 */
final class ConstantDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $default,
        public readonly string $varType,
        public readonly string $description,
    ) {
    }

    /**
     * @return array{name: string, default: string|null, varType: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'default'     => $this->default,
            'varType'     => $this->varType,
            'description' => $this->description,
        ];
    }
}
