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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

use function implode;

/**
 * Renders a default-value expression to the string the model carries.
 *
 * What a rendered default looks like belongs to Notation, which the Zephir
 * reader writes through as well - two models that render defaults differently
 * would report parity differences that are only about formatting.
 */
final class ValueRenderer
{
    public function render(?Expr $expr): ?string
    {
        if ($expr === null) {
            return null;
        }

        if ($expr instanceof String_) {
            // php-parser hands back the value, not the source text, so the
            // escapes have to go back on.
            return Notation::string($expr->value);
        }

        if ($expr instanceof Int_ || $expr instanceof Float_) {
            return (string) $expr->value;
        }

        if ($expr instanceof ConstFetch) {
            // true, false and null arrive here as constant names.
            return $expr->name->toString();
        }

        if ($expr instanceof Array_) {
            return $expr->items === [] ? Notation::ARRAY_EMPTY : Notation::ARRAY_FILLED;
        }

        if ($expr instanceof ClassConstFetch) {
            return Notation::classConstant(
                $expr->class instanceof Expr ? '' : $expr->class->toString(),
                $expr->name instanceof Expr ? '' : $expr->name->toString()
            );
        }

        if ($expr instanceof UnaryMinus) {
            return '-' . ($this->render($expr->expr) ?? '');
        }

        if ($expr instanceof BitwiseOr) {
            // The operator is associative, thus a chain reads the same with
            // no parentheses. Other operators need the precedence and stay
            // unrendered.
            $left  = $this->render($expr->left);
            $right = $this->render($expr->right);

            return $left === null || $right === null ? null : $left . ' | ' . $right;
        }

        if ($expr instanceof New_) {
            return $this->renderNew($expr);
        }

        return null;
    }

    /**
     * A part that cannot be rendered leaves the full expression unrendered.
     */
    private function renderNew(New_ $expr): ?string
    {
        if (!$expr->class instanceof Name) {
            return null;
        }

        $arguments = [];
        foreach ($expr->args as $arg) {
            if (!$arg instanceof Arg || $arg->unpack) {
                return null;
            }

            $value = $this->render($arg->value);
            if ($value === null) {
                return null;
            }

            $arguments[] = ($arg->name === null ? '' : $arg->name->toString() . ': ') . $value;
        }

        return 'new ' . $expr->class->toCodeString() . '(' . implode(', ', $arguments) . ')';
    }
}
