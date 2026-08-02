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
use Phalcon\Quill\Exceptions\UnknownFormat;
use Phalcon\Quill\Formatter\FormatterFactory;
use Phalcon\Quill\Formatter\JsonFormatter;
use Phalcon\Quill\Formatter\MarkdownFormatter;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\ClassDefinitionCollection;
use Phalcon\Quill\Model\ConstantDefinitionCollection;
use Phalcon\Quill\Model\Imports;
use Phalcon\Quill\Model\Location;
use Phalcon\Quill\Model\Members;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use Phalcon\Quill\Selection;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function json_decode;

final class JsonFormatterTest extends TestCase
{
    public function testANamespaceAndAFilterCompose(): void
    {
        $registry = new Registry(ClassDefinitionCollection::fromDefinitions([
            $this->definition('Phalcon\\Sample\\Zulu'),
            $this->definition('Phalcon\\Sample\\Deep\\Charlie'),
            $this->definition('Phalcon\\Other\\Charlie'),
        ]));

        $documents = (new JsonFormatter())->format(
            $registry,
            $this->config(),
            new Selection('charlie', 'Phalcon\\Sample')
        );

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($documents[JsonFormatter::DOCUMENT], true);

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];

        // The namespace drops Other\Charlie, the filter drops Sample\Zulu.
        $this->assertSame(['Phalcon\\Sample\\Deep\\Charlie'], array_keys($definitions));
    }

    public function testANamespaceNarrowsToItsSubtree(): void
    {
        $registry = new Registry(ClassDefinitionCollection::fromDefinitions([
            $this->definition('Phalcon\\Sample\\Zulu'),
            $this->definition('Phalcon\\Sample\\Deep\\Charlie'),
            $this->definition('Phalcon\\Other\\Bravo'),
        ]));

        $documents = (new JsonFormatter())->format(
            $registry,
            $this->config(),
            new Selection('', 'Phalcon\\Sample')
        );

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($documents[JsonFormatter::DOCUMENT], true);

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];

        $this->assertSame(
            ['Phalcon\\Sample\\Deep\\Charlie', 'Phalcon\\Sample\\Zulu'],
            array_keys($definitions)
        );
    }

    /**
     * A model document is data, so there is nothing to style and nothing to
     * write beside it.
     */
    public function testAModelDocumentShipsNoAssets(): void
    {
        $this->assertSame([], (new JsonFormatter())->assets());
    }

    public function testDefinitionsAreKeyedByFqcnAndSorted(): void
    {
        /** @var array<string, mixed> $definitions */
        $definitions = $this->decode()['definitions'];

        $this->assertSame(
            ['Phalcon\\Sample\\Alpha', 'Phalcon\\Sample\\Zulu'],
            array_keys($definitions)
        );
    }

    public function testDocumentCarriesTheSourceItCameFrom(): void
    {
        $decoded = $this->decode();

        $this->assertSame(ClassDefinition::MODEL_VERSION, $decoded['version']);
        $this->assertSame('zephir', $decoded['language']);
        $this->assertSame('phalcon/cphalcon', $decoded['repository']);
    }

    public function testFactoryRejectsAnUnknownFormat(): void
    {
        $this->expectException(UnknownFormat::class);
        $this->expectExceptionMessage("Unknown format 'yaml'; known formats are: json, markdown");

        (new FormatterFactory())->create('yaml');
    }

    public function testFactoryResolvesBothFormats(): void
    {
        $factory = new FormatterFactory();

        $this->assertInstanceOf(JsonFormatter::class, $factory->create('json'));
        $this->assertInstanceOf(MarkdownFormatter::class, $factory->create('markdown'));
    }

    public function testFilterIsCaseInsensitive(): void
    {
        $pages = (new JsonFormatter())->format($this->registry(), $this->config(), new Selection('ZuLu'));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($pages[JsonFormatter::DOCUMENT], true);

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];
        $this->assertSame(['Phalcon\\Sample\\Zulu'], array_keys($definitions));
    }

    /**
     * The filter skips what does not match rather than stopping at it, so a
     * match after a miss is still found.
     */
    public function testFilterKeepsLookingPastANonMatch(): void
    {
        // The registry hands Zulu over first, so Alpha is only reachable if
        // the loop carries on past the miss.
        $pages = (new JsonFormatter())->format($this->registry(), $this->config(), new Selection('alpha'));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($pages[JsonFormatter::DOCUMENT], true);

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];
        $this->assertSame(['Phalcon\\Sample\\Alpha'], array_keys($definitions));
    }

    public function testFilterNarrowsByFqcn(): void
    {
        $pages = (new JsonFormatter())->format($this->registry(), $this->config(), new Selection('zulu'));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($pages[JsonFormatter::DOCUMENT], true);

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];
        $this->assertSame(['Phalcon\\Sample\\Zulu'], array_keys($definitions));
    }

    /**
     * The document is written to be diffed and to be read back, so the exact
     * encoding matters: indented, slashes left alone, and one trailing
     * newline so the file ends the way a text file should.
     */
    public function testTheDocumentIsPrettyPrintedWithUnescapedSlashes(): void
    {
        $documents = (new JsonFormatter())->format($this->registry(), $this->config(), Selection::none());
        $json      = $documents[JsonFormatter::DOCUMENT];

        $this->assertStringContainsString("{\n    \"version\"", $json);
        $this->assertStringContainsString('"phalcon/cphalcon"', $json);
        $this->assertStringNotContainsString('phalcon\\/cphalcon', $json);
        $this->assertStringEndsWith("}\n", $json);
    }

    public function testWritesOneDocumentWithAJsonExtension(): void
    {
        $formatter = new JsonFormatter();
        $pages     = $formatter->format($this->registry(), $this->config(), Selection::none());

        $this->assertSame('json', $formatter->extension());
        $this->assertSame([JsonFormatter::DOCUMENT], array_keys($pages));
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

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $pages = (new JsonFormatter())->format($this->registry(), $this->config(), Selection::none());

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($pages[JsonFormatter::DOCUMENT], true);

        return $decoded;
    }

    private function definition(string $fqcn): ClassDefinition
    {
        return new ClassDefinition(
            new Location($fqcn, 'Phalcon\\Sample', 'Sample/File.zep'),
            Structure::classType(false, false),
            '',
            new Imports([], []),
            new Relations([], [], []),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection()
            )
        );
    }

    private function registry(): Registry
    {
        // Inserted out of order, so a sorted result proves the formatter sorts.
        return new Registry(ClassDefinitionCollection::fromDefinitions([
            $this->definition('Phalcon\\Sample\\Zulu'),
            $this->definition('Phalcon\\Sample\\Alpha'),
        ]));
    }
}
