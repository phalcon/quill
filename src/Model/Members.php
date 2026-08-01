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
    /**
     * The serialized section names, and the singular noun each member of that
     * section is called. Anything reading a model document should take the
     * names from here rather than spelling them again - a section added to
     * toArray() but missed elsewhere produces an empty comparison, not an
     * error.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'constants'  => 'constant',
        'properties' => 'property',
        'methods'    => 'method',
    ];

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
