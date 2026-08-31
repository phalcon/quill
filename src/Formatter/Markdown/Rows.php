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

/**
 * The slot values of the four shapes that carry a member.
 *
 * Everything else on a page - the class heading, the tree, the section
 * wrappers, the index - takes the same values whichever dialect renders it,
 * so it stays in ClassPage. These four do not: mkdocs wants markup built in
 * PHP, and nimbus wants properties that a component reads. The seam is here
 * and nowhere else.
 *
 * A description arrives already made safe for the dialect; an implementation
 * decides only where to put it.
 */
interface Rows
{
    /**
     * @return array<string, string>
     */
    public function constantRow(ConstantDefinition $constant): array;

    /**
     * @return array<string, string>
     */
    public function methodBlock(MethodDefinition $method, string $anchor, string $description): array;

    /**
     * @return array<string, string>
     */
    public function propertyRow(PropertyDefinition $property): array;

    /**
     * @return array<string, string>
     */
    public function summaryRow(
        MethodDefinition $method,
        string $anchor,
        string $visibility,
        string $description
    ): array;
}
