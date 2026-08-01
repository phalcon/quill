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
 * One method parameter. `$default` is an already-rendered expression such as
 * `"[]"` or `"self::FOO"` - no source AST reaches the model.
 */
final class ParameterDefinition implements Definition
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $default,
    ) {
    }

    /**
     * @return array{name: string, type: string, default: string|null}
     */
    public function toArray(): array
    {
        return [
            'name'    => $this->name,
            'type'    => $this->type,
            'default' => $this->default,
        ];
    }
}
