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

namespace Phalcon\Quill\Reader\Php;

use Phalcon\Quill\Reader\Notation;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

use function implode;

/**
 * Renders a php-parser type node to the plain string the model carries.
 *
 * `?T` is rendered as `T|null` rather than `?T` on purpose: Zephir declares
 * the same thing as a two-entry list that already renders that way, and the
 * two models are meant to be diffable against each other.
 */
final class TypeRenderer
{
    public function render(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof NullableType) {
            $inner = $this->render($type->type);

            return $inner === null ? Notation::NULL : Notation::nullable($inner);
        }

        if ($type instanceof UnionType) {
            return implode('|', $this->parts($type));
        }

        if ($type instanceof IntersectionType) {
            return implode('&', $this->parts($type));
        }

        if ($type instanceof Identifier || $type instanceof Name) {
            return $type->toString();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parts(ComplexType $type): array
    {
        $parts = [];

        /** @var list<Identifier|Name|ComplexType> $types */
        $types = $type->types ?? [];
        foreach ($types as $part) {
            $rendered = $this->render($part);
            if ($rendered !== null) {
                $parts[] = $rendered;
            }
        }

        return $parts;
    }
}
