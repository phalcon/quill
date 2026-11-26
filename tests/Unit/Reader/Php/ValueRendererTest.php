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

namespace Phalcon\Quill\Tests\Unit\Reader\Php;

use Phalcon\Quill\Reader\Php\ValueRenderer;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\TestCase;

/**
 * A `new` default that the renderer cannot write in full renders as nothing.
 * PHP does not accept a class expression or an unpacked argument in a
 * default, thus no fixture can have them. The parser accepts them.
 */
final class ValueRendererTest extends TestCase
{
    public function testANewWithAClassExpressionRendersAsNothing(): void
    {
        $expr = new New_(new Variable('class'));

        $this->assertNull((new ValueRenderer())->render($expr));
    }

    public function testANewWithAnArgumentThatDoesNotRenderRendersAsNothing(): void
    {
        $expr = new New_(
            new Name('Shapes'),
            [new Arg(new Plus(new Int_(1), new Int_(2)))]
        );

        $this->assertNull((new ValueRenderer())->render($expr));
    }

    public function testANewWithAnUnpackedArgumentRendersAsNothing(): void
    {
        $expr = new New_(
            new Name('Shapes'),
            [new Arg(new Variable('arguments'), unpack: true)]
        );

        $this->assertNull((new ValueRenderer())->render($expr));
    }
}
