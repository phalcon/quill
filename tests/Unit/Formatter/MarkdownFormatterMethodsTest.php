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
use Phalcon\Scribe\Model\ConstantDefinitionCollection;
use Phalcon\Scribe\Model\MethodDefinition;
use Phalcon\Scribe\Model\MethodDefinitionCollection;
use Phalcon\Scribe\Model\ParameterDefinition;
use Phalcon\Scribe\Model\ParameterDefinitionCollection;
use Phalcon\Scribe\Model\PropertyDefinitionCollection;
use Phalcon\Scribe\Model\Registry;
use Phalcon\Scribe\Model\Structure;
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
            'zep'
        );
    }

    private function format(Registry $registry): string
    {
        $pages = (new MarkdownFormatter())->format($registry, $this->config());

        return $pages['phalcon_sample'] ?? self::fail('phalcon_sample page missing');
    }

    /**
     * @return array<string, string>
     */
    private function formatted(): array
    {
        return (new MarkdownFormatter())->format($this->registry(), $this->config());
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
            'Phalcon\\Sample\\Child',
            'Sample/Child.zep',
            'Phalcon\\Sample',
            Structure::classType(false, false),
            '',
            [],
            [],
            [],
            [],
            [],
            new ConstantDefinitionCollection(),
            new PropertyDefinitionCollection(),
            new MethodDefinitionCollection($methods)
        );

        return new Registry(['Phalcon\\Sample\\Child' => $child]);
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
}
