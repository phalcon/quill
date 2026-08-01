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

use Phalcon\Quill\Reader\Php\TypeRenderer;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\TestCase;

final class TypeRendererTest extends TestCase
{
    /**
     * Anything that is not a type expression renders as nothing rather than
     * as a guess. Only a malformed tree gets here, which is why no fixture
     * can reach it.
     */
    public function testANodeThatIsNotATypeRendersAsNothing(): void
    {
        $this->assertNull((new TypeRenderer())->render(new String_('not a type')));
    }

    public function testANullableWrapsAsAUnionForParityWithZephir(): void
    {
        $rendered = (new TypeRenderer())->render(new NullableType(new Identifier('string')));

        $this->assertSame('string|null', $rendered);
    }

    public function testNoTypeRendersAsNothing(): void
    {
        $this->assertNull((new TypeRenderer())->render(null));
    }

    public function testUnionsAndIntersectionsJoinWithTheirOwnSeparator(): void
    {
        $renderer = new TypeRenderer();

        $this->assertSame(
            'int|string',
            $renderer->render(new UnionType([new Identifier('int'), new Identifier('string')]))
        );
        $this->assertSame(
            'Countable&Stringable',
            $renderer->render(new IntersectionType([new Name('Countable'), new Name('Stringable')]))
        );
    }
}
