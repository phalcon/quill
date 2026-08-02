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

use Phalcon\Quill\Config;
use Phalcon\Quill\Formatter\MarkdownFormatter;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\ClassDefinitionCollection;
use Phalcon\Quill\Model\ConstantDefinition;
use Phalcon\Quill\Model\ConstantDefinitionCollection;
use Phalcon\Quill\Model\Imports;
use Phalcon\Quill\Model\Location;
use Phalcon\Quill\Model\Members;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinition;
use Phalcon\Quill\Model\PropertyDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use PHPUnit\Framework\TestCase;

use function substr_count;

final class MarkdownFormatterTest extends TestCase
{
    /**
     * A constant's description is wrapped, in that order - the wrapper first,
     * then the text, then the close.
     */
    public function testAConstantDescriptionIsWrappedInItsOwnSpan(): void
    {
        $this->assertStringContainsString(
            '<span class="desc">How many.</span>',
            $this->page()
        );
    }

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

    public function testTraitsCarryTheirOwnBadge(): void
    {
        $this->assertStringContainsString(
            '<span class="badge badge--trait">Trait</span>',
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

    public function testUsedByIsAbsentWhenNothingUsesIt(): void
    {
        // Base is a plain class; nothing pulls it in as a trait.
        $this->assertSame(1, substr_count($this->page(), '__Used by__'));
    }

    public function testUsedByListsTheClassesPullingTheTraitIn(): void
    {
        $this->assertStringContainsString(
            "\n__Used by__ [`Phalcon\\Sample\\Base`](#samplebase)\n{ .api-used-by }\n",
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
            'zep',
            'Phalcon'
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
            new Location('Phalcon\\Sample\\Base', 'Phalcon\\Sample', 'Sample/Base.zep'),
            Structure::classType(false, false),
            'The base.',
            new Imports([], []),
            new Relations([], [], ['Child']),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection()
            )
        );

        $child = new ClassDefinition(
            new Location('Phalcon\\Sample\\Child', 'Phalcon\\Sample', 'Sample/Child.zep'),
            Structure::trait(),
            'The child.',
            new Imports(
                ['Phalcon\\Sample\\Base'],
                ['Base' => 'Phalcon\\Sample\\Base']
            ),
            new Relations(['Base'], [], []),
            new Members(
                new ConstantDefinitionCollection([
                    new ConstantDefinition('LIMIT', '10', 'int', 'How many.'),
                ]),
                new PropertyDefinitionCollection([
                    new PropertyDefinition('hidden', 'private', false, null, 'mixed', 'Hidden.', []),
                    new PropertyDefinition('store', 'protected', false, '[]', 'array', 'The store.', []),
                ]),
                new MethodDefinitionCollection()
            )
        );

        return new Registry(ClassDefinitionCollection::fromDefinitions([
            $base,
            $child,
        ]));
    }
}
