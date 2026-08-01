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

namespace Phalcon\Quill\Tests\Unit\Reader\Zephir;

use Phalcon\Quill\Reader\Zephir\AstNode;
use PHPUnit\Framework\TestCase;

/**
 * The node wraps whatever the parser handed over, so its guards are exercised
 * here directly - a well-formed source cannot produce the shapes they defend
 * against, but the parser's output is not this project's to guarantee.
 */
final class AstNodeTest extends TestCase
{
    public function testFlagIsTrueOnlyForTheIntegerOne(): void
    {
        $node = new AstNode(['abstract' => 1, 'final' => 0, 'other' => '1']);

        $this->assertTrue($node->flag('abstract'));
        $this->assertFalse($node->flag('final'));
        $this->assertFalse($node->flag('other'));
        $this->assertFalse($node->flag('absent'));
    }

    public function testHasReportsWhetherTheKeyIsPresent(): void
    {
        $node = new AstNode(['value' => 'something', 'empty' => null]);

        $this->assertTrue($node->has('value'));
        // isset() is false for a null value, and that is the intent: a key
        // holding null carries nothing to render.
        $this->assertFalse($node->has('empty'));
        $this->assertFalse($node->has('absent'));
    }

    public function testStringsKeepsOnlyTheStrings(): void
    {
        $node = new AstNode(['visibility' => ['public', 42, 'static', null]]);

        $this->assertSame(['public', 'static'], $node->strings('visibility'));
    }

    public function testStringsReturnsNothingWhenTheKeyIsNotAList(): void
    {
        $this->assertSame([], (new AstNode(['visibility' => 'public']))->strings('visibility'));
        $this->assertSame([], (new AstNode([]))->strings('visibility'));
    }
}
