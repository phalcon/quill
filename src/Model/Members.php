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
 * Everything declared inside the body. Private members included - narrowing
 * is the formatter's decision, and the collections carry the operations for
 * it.
 */
final class Members
{
    public function __construct(
        public readonly ConstantDefinitionCollection $constants,
        public readonly PropertyDefinitionCollection $properties,
        public readonly MethodDefinitionCollection $methods,
    ) {
    }

    /**
     * @return array{
     *     constants: list<array<string, mixed>>,
     *     properties: list<array<string, mixed>>,
     *     methods: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'constants'  => $this->constants->toArray(),
            'properties' => $this->properties->toArray(),
            'methods'    => $this->methods->toArray(),
        ];
    }
}
