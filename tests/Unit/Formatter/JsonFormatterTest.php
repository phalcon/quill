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
use Phalcon\Scribe\Exceptions\UnknownFormat;
use Phalcon\Scribe\Formatter\FormatterFactory;
use Phalcon\Scribe\Formatter\JsonFormatter;
use Phalcon\Scribe\Formatter\MarkdownFormatter;
use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\ConstantDefinitionCollection;
use Phalcon\Scribe\Model\Imports;
use Phalcon\Scribe\Model\Location;
use Phalcon\Scribe\Model\Members;
use Phalcon\Scribe\Model\MethodDefinitionCollection;
use Phalcon\Scribe\Model\PropertyDefinitionCollection;
use Phalcon\Scribe\Model\Registry;
use Phalcon\Scribe\Model\Relations;
use Phalcon\Scribe\Model\Structure;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function json_decode;

final class JsonFormatterTest extends TestCase
{
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

        $this->assertSame('zephir', $decoded['language']);
        $this->assertSame('phalcon/cphalcon', $decoded['repository']);
    }

    public function testFactoryResolvesBothFormats(): void
    {
        $factory = new FormatterFactory();

        $this->assertInstanceOf(JsonFormatter::class, $factory->create('json'));
        $this->assertInstanceOf(MarkdownFormatter::class, $factory->create('markdown'));
    }

    public function testFactoryRejectsAnUnknownFormat(): void
    {
        $this->expectException(UnknownFormat::class);
        $this->expectExceptionMessage("Unknown format 'yaml'; known formats are: json, markdown");

        (new FormatterFactory())->create('yaml');
    }

    public function testFilterNarrowsByFqcn(): void
    {
        $pages = (new JsonFormatter())->format($this->registry(), $this->config(), 'zulu');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($pages[JsonFormatter::DOCUMENT], true);

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];
        $this->assertSame(['Phalcon\\Sample\\Zulu'], array_keys($definitions));
    }

    public function testWritesOneDocumentWithAJsonExtension(): void
    {
        $formatter = new JsonFormatter();
        $pages     = $formatter->format($this->registry(), $this->config());

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
            'zep'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $pages = (new JsonFormatter())->format($this->registry(), $this->config());

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($pages[JsonFormatter::DOCUMENT], true);

        return $decoded;
    }

    private function registry(): Registry
    {
        // Inserted out of order, so a sorted result proves the formatter sorts.
        return new Registry([
            'Phalcon\\Sample\\Zulu'  => $this->definition('Phalcon\\Sample\\Zulu'),
            'Phalcon\\Sample\\Alpha' => $this->definition('Phalcon\\Sample\\Alpha'),
        ]);
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
}
