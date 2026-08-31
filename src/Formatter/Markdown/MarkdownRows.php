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

namespace Phalcon\Quill\Formatter\Markdown;

use Phalcon\Quill\Model\ConstantDefinition;
use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\PropertyDefinition;

use function implode;

/**
 * The member shapes as mkdocs takes them: highlight spans and list rows built
 * here, because Markdown cannot express them.
 *
 * The exact markup is load-bearing. These documents are diffed between the
 * two Phalcon implementations, so an incidental change reads as an API
 * change. Every expression below was moved from ClassPage unchanged.
 */
final class MarkdownRows implements Rows
{
    public function __construct(
        private readonly Html $html,
        private readonly Signature $signature,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function constantRow(ConstantDefinition $constant): array
    {
        return [
            'default' => $this->html->default($constant->default),
            'name'    => $this->html->escape($constant->name),
            'type'    => $this->html->escape($constant->varType),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function methodBlock(MethodDefinition $method, string $anchor, string $description): array
    {
        return [
            'anchor'      => $anchor,
            'description' => $description,
            'name'        => $method->name,
            'signature'   => implode("\n", $this->signature->lines($method)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function propertyRow(PropertyDefinition $property): array
    {
        return [
            'default'    => $this->html->default($property->default),
            'name'       => $this->html->escape($property->name),
            'type'       => $this->html->escape($property->varType),
            'visibility' => $property->visibility,
        ];
    }

    /**
     * `returnType` is absent on purpose: it is a conditional slot that
     * ClassPage wraps in its own template, not part of a row's shape.
     *
     * @return array<string, string>
     */
    public function summaryRow(
        MethodDefinition $method,
        string $anchor,
        string $visibility,
        string $description
    ): array {
        return [
            'anchor'      => $anchor,
            'description' => $description,
            'signature'   => $this->signature->inline($method),
            'visibility'  => $visibility,
        ];
    }
}
