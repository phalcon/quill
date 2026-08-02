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
use Phalcon\Quill\Model\ConstantDefinitionCollection;
use Phalcon\Quill\Model\Imports;
use Phalcon\Quill\Model\Location;
use Phalcon\Quill\Model\Members;
use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\ParameterDefinition;
use Phalcon\Quill\Model\ParameterDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use Phalcon\Quill\Selection;
use PHPUnit\Framework\TestCase;

use function strpos;

final class MarkdownFormatterMethodsTest extends TestCase
{
    public function testIndexPageListsEveryPage(): void
    {
        $pages = $this->formatted();

        $this->assertArrayHasKey('index', $pages);
        $this->assertStringContainsString("# API Index\n- - -\n", $pages['index']);
        $this->assertStringContainsString('- [Phalcon Sample](phalcon_sample.md)', $pages['index']);
    }

    public function testMethodGroupsAreLabelledAndCounted(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('<div class="api-group">Public · 3</div>', $page);
        $this->assertStringContainsString('<div class="api-group">Protected · 1</div>', $page);
    }

    public function testMultiParameterSignaturesBreakIntoLines(): void
    {
        $this->assertStringContainsString(
            "public static function toLower(\n    string \$text,\n    bool \$trim = true\n): string;",
            $this->page()
        );
    }

    public function testPrivateMethodsAreFilteredOut(): void
    {
        $this->assertStringNotContainsString('secret', $this->page());
    }

    public function testPrivateOnlyClassEmitsNoMethodSections(): void
    {
        $page = $this->format($this->registry(true));

        $this->assertStringNotContainsString('### Methods', $page);
        $this->assertStringNotContainsString('### Method Summary', $page);
    }

    public function testReservedMethodsSortFirst(): void
    {
        $page = $this->page();

        $this->assertLessThan(
            (int) strpos($page, '#### `toLower()`'),
            (int) strpos($page, '#### `__construct()`')
        );
    }

    public function testSingleParameterSignaturesStayInline(): void
    {
        $this->assertStringContainsString(
            'public function trim( string $text ): string;',
            $this->page()
        );
    }

    public function testSummaryLinksToTheMethodAnchor(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('### Method Summary', $page);
        $this->assertStringContainsString('<a class="api-item" href="#samplechild-tolower">', $page);
        $this->assertStringContainsString('#### `toLower()` { #samplechild-tolower }', $page);
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

    private function format(Registry $registry): string
    {
        $pages = (new MarkdownFormatter())->format($registry, $this->config(), Selection::none());

        return $pages['phalcon_sample'] ?? self::fail('phalcon_sample page missing');
    }

    /**
     * @return array<string, string>
     */
    private function formatted(): array
    {
        return (new MarkdownFormatter())->format($this->registry(), $this->config(), Selection::none());
    }

    /**
     * @param list<string>              $modifiers
     * @param list<ParameterDefinition> $parameters
     */
    private function method(
        string $name,
        array $modifiers,
        string $visibility,
        array $parameters,
        ?string $returnType
    ): MethodDefinition {
        /** @var 'public'|'protected'|'private' $visibility */
        return new MethodDefinition(
            $name,
            $modifiers,
            $visibility,
            new ParameterDefinitionCollection($parameters),
            $returnType,
            'Does a thing.'
        );
    }

    private function page(): string
    {
        return $this->format($this->registry());
    }

    private function registry(bool $privateOnly = false): Registry
    {
        $methods = $privateOnly
            ? [$this->method('secret', ['private'], 'private', [], null)]
            : [
                $this->method(
                    'toLower',
                    ['public', 'static'],
                    'public',
                    [
                        new ParameterDefinition('text', 'string', null),
                        new ParameterDefinition('trim', 'bool', 'true'),
                    ],
                    'string'
                ),
                $this->method(
                    'trim',
                    ['public'],
                    'public',
                    [new ParameterDefinition('text', 'string', null)],
                    'string'
                ),
                $this->method('__construct', ['public'], 'public', [], null),
                $this->method('helper', ['protected'], 'protected', [], 'void'),
                $this->method('secret', ['private'], 'private', [], null),
            ];

        $child = new ClassDefinition(
            new Location('Phalcon\\Sample\\Child', 'Phalcon\\Sample', 'Sample/Child.zep'),
            Structure::classType(false, false),
            '',
            new Imports([], []),
            new Relations([], [], []),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection($methods)
            )
        );

        return new Registry(ClassDefinitionCollection::fromDefinitions([$child]));
    }
}
