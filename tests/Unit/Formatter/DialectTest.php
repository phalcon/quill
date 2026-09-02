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

    public function testMarkdownIndexLinksTheSourceFile(): void
    {
        $this->assertSame('phalcon_events.md', Dialect::markdown()->indexLink('phalcon_events'));
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

    /**
     * The index is served one segment higher than the pages it lists, so it
     * reaches a page without climbing.
     */
    public function testNimbusIndexLinksAPageWithoutClimbing(): void
    {
        $this->assertSame('phalcon_events/', Dialect::nimbus()->indexLink('phalcon_events'));
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

    public function testNimbusWithABaseUriIgnoresATrailingSlash(): void
    {
        $dialect = Dialect::nimbus('/5.20/api/');

        $this->assertSame('/5.20/api/phalcon_events/', $dialect->pageLink('phalcon_events'));
        $this->assertSame('/5.20/api/phalcon_events/', $dialect->indexLink('phalcon_events'));
    }

    /**
     * The point of the base URI: one string that is correct from a page at
     * any depth, so the index needs no form of its own.
     */
    public function testNimbusWithABaseUriIsAbsoluteFromEveryDepth(): void
    {
        $dialect = Dialect::nimbus('/5.20/api');

        $this->assertSame('/5.20/api/phalcon_events/', $dialect->pageLink('phalcon_events'));
        $this->assertSame($dialect->pageLink('phalcon_events'), $dialect->indexLink('phalcon_events'));
    }
}
