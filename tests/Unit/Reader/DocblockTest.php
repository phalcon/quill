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

namespace Phalcon\Quill\Tests\Unit\Reader;

use Phalcon\Quill\Reader\Docblock;
use PHPUnit\Framework\TestCase;

final class DocblockTest extends TestCase
{
    /**
     * php-parser returns the comment with its opening delimiter; without
     * stripping it, `/**` becomes the first prose line and ends up rendered
     * as the description.
     */
    public function testStripsTheOpeningDelimiterPhpParserIncludes(): void
    {
        $doc = new Docblock(
            "/**\n"
            . "     * Returns the access which the list is checking\n"
            . "     *\n"
            . "     * @return string|null\n"
            . '     */'
        );

        $this->assertSame('Returns the access which the list is checking', $doc->description());
        $this->assertNull($doc->varType());
    }

    /**
     * Zephir's parser has already removed the leading slash.
     */
    public function testStripsTheZephirFormToo(): void
    {
        $doc = new Docblock("**\n     * @var array\n     *");

        $this->assertSame('', $doc->description());
        $this->assertSame('array', $doc->varType());
    }

    public function testHandlesASingleLineComment(): void
    {
        $this->assertSame('Short one.', (new Docblock('/** Short one. */'))->description());
    }

    public function testEmptyInputStaysEmpty(): void
    {
        $this->assertSame('', (new Docblock(null))->description());
        $this->assertSame('', (new Docblock(''))->description());
    }
}
