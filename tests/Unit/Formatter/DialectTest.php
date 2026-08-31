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

namespace Phalcon\Quill\Tests\Unit\Formatter;

use Phalcon\Quill\Formatter\Dialect;
use PHPUnit\Framework\TestCase;

/**
 * The five values that separate one Markdown output from another, asserted
 * one dialect at a time so a failure names which output moved.
 */
final class DialectTest extends TestCase
{
    public function testMarkdownCarriesTheStylesheet(): void
    {
        $this->assertSame('api.css', Dialect::markdown()->stylesheet);
    }

    public function testMarkdownLinksTheSourceFile(): void
    {
        $this->assertSame('phalcon_events.md', Dialect::markdown()->pageLink('phalcon_events'));
    }

    public function testMarkdownNamesItselfAndItsExtension(): void
    {
        $dialect = Dialect::markdown();

        $this->assertSame('markdown', $dialect->name);
        $this->assertSame('md', $dialect->extension);
    }

    public function testMarkdownProseIsUntouched(): void
    {
        $this->assertSame('{@see x()}', Dialect::markdown()->prose('{@see x()}'));
    }

    public function testNimbusHasNoStylesheet(): void
    {
        $this->assertNull(Dialect::nimbus()->stylesheet);
    }

    public function testNimbusLinksASiblingDirectory(): void
    {
        $this->assertSame('../phalcon_events/', Dialect::nimbus()->pageLink('phalcon_events'));
    }

    public function testNimbusNamesItselfAndItsExtension(): void
    {
        $dialect = Dialect::nimbus();

        $this->assertSame('nimbus', $dialect->name);
        $this->assertSame('mdx', $dialect->extension);
    }

    public function testNimbusProseIsMadeSafe(): void
    {
        $this->assertSame('\{@see x()\}', Dialect::nimbus()->prose('{@see x()}'));
    }
}
