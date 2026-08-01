<?php

/**
 * This file is part of the Phalcon Scribe.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Scribe\Tests\Unit\Formatter;

use Phalcon\Scribe\Config;
use Phalcon\Scribe\Formatter\MarkdownFormatter;
use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\ConstantDefinition;
use Phalcon\Scribe\Model\Kind;
use Phalcon\Scribe\Model\PropertyDefinition;
use Phalcon\Scribe\Model\Registry;
use PHPUnit\Framework\TestCase;

final class MarkdownFormatterTest extends TestCase
{
    public function testConstantsSectionRendersTheApiList(): void
    {
        $markdown = $this->page();

        $this->assertStringContainsString("\n### Constants\n\n<div class=\"api-list\">\n", $markdown);
        $this->assertStringContainsString('<code class="ret">int</code>', $markdown);
        $this->assertStringContainsString('<span class="sc">LIMIT</span>', $markdown);
        $this->assertStringContainsString('<span class="sm"> = 10</span>', $markdown);
    }

    public function testFrontMatterAndNoticeOpenEveryPage(): void
    {
        $this->assertStringStartsWith(
            "---\nhide:\n    - navigation\n---\n\n!!! info \"NOTE\"\n\n    All classes are prefixed with `Phalcon`\n",
            $this->page()
        );
    }

    public function testPrivatePropertiesAreFilteredOut(): void
    {
        $markdown = $this->page();

        $this->assertStringContainsString('$store', $markdown);
        $this->assertStringNotContainsString('$hidden', $markdown);
    }

    public function testSourceLinkComesFromConfig(): void
    {
        $this->assertStringContainsString(
            '(https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Sample/Child.zep){ .src-btn }',
            $this->page()
        );
    }

    public function testTraitsRenderWithTheClassBadgeForNow(): void
    {
        // Deliberate parity with the legacy script, which has no trait branch
        // in its badge selection. The fix lands after the gate is green.
        $this->assertStringContainsString(
            '<span class="badge badge--class">Class</span>',
            $this->page()
        );
    }

    public function testTreeLinksResolvedAncestors(): void
    {
        $this->assertStringContainsString(
            '- [`Phalcon\\Sample\\Base`](#samplebase)',
            $this->page()
        );
    }

    public function testUsesAreListedAndSorted(): void
    {
        $this->assertStringContainsString(
            "\n__Uses__ `Phalcon\\Sample\\Base`\n{ .api-uses }\n",
            $this->page()
        );
    }

    private function config(): Config
    {
        return new Config(
            'zephir',
            '/unused',
            '/unused',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep'
        );
    }

    private function page(): string
    {
        $pages = (new MarkdownFormatter())->format($this->registry(), $this->config());

        return $pages['phalcon_sample'] ?? self::fail('phalcon_sample page missing');
    }

    private function registry(): Registry
    {
        $base = new ClassDefinition(
            'Phalcon\\Sample\\Base',
            'Sample\\Base',
            'phalcon_sample',
            'samplebase',
            'Sample/Base.zep',
            'Phalcon\\Sample',
            Kind::ClassKind,
            false,
            false,
            'The base.',
            [],
            [],
            [],
            [],
            [],
            [],
            []
        );

        $child = new ClassDefinition(
            'Phalcon\\Sample\\Child',
            'Sample\\Child',
            'phalcon_sample',
            'samplechild',
            'Sample/Child.zep',
            'Phalcon\\Sample',
            Kind::TraitKind,
            false,
            false,
            'The child.',
            ['Phalcon\\Sample\\Base'],
            ['Base' => 'Phalcon\\Sample\\Base'],
            ['Base'],
            [],
            [new ConstantDefinition('LIMIT', '10', 'int', 'How many.')],
            [
                new PropertyDefinition('hidden', 'private', null, 'mixed', 'Hidden.', []),
                new PropertyDefinition('store', 'protected', '[]', 'array', 'The store.', []),
            ],
            []
        );

        return new Registry([
            'Phalcon\\Sample\\Base'  => $base,
            'Phalcon\\Sample\\Child' => $child,
        ]);
    }
}
