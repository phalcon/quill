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

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * Renders a default-value expression to the string the model carries.
 *
 * The output deliberately matches the Zephir reader's: quoted strings, bare
 * numbers, `[]` for an empty array and `[...]` for a populated one. Two
 * models that render defaults differently would report parity differences
 * that are only about formatting.
 */
final class ValueRenderer
{
    public function render(?Expr $expr): ?string
    {
        if ($expr === null) {
            return null;
        }

        if ($expr instanceof String_) {
            return '"' . $expr->value . '"';
        }

        if ($expr instanceof Int_ || $expr instanceof Float_) {
            return (string) $expr->value;
        }

        if ($expr instanceof ConstFetch) {
            // true, false and null arrive here as constant names.
            return $expr->name->toString();
        }

        if ($expr instanceof Array_) {
            return $expr->items === [] ? '[]' : '[...]';
        }

        if ($expr instanceof ClassConstFetch) {
            $class = $expr->class instanceof Expr ? '' : $expr->class->toString();
            $name  = $expr->name instanceof Expr ? '' : $expr->name->toString();

            return $class . '::' . $name;
        }

        if ($expr instanceof UnaryMinus) {
            return '-' . ($this->render($expr->expr) ?? '');
        }

        return null;
    }
}
