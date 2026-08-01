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

use Phalcon\Quill\Config;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\Keyword;
use Phalcon\Quill\Reader\PhpReader;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;

final class PhpReaderTest extends TestCase
{
    public function testFunctionAndConstantImportsAreExcluded(): void
    {
        // The fixture also has `use function is_string` and `use const PHP_EOL`;
        // neither is a class import and neither belongs in Uses.
        $this->assertSame(
            [
                'Phalcon\\Sample\\Support\\Helper',
                'Phalcon\\Sample\\Support\\Other',
            ],
            $this->sample()->imports->uses
        );
    }

    public function testAliasesUseTheDeclaredNameWhenGiven(): void
    {
        $this->assertSame(
            [
                'Helper'  => 'Phalcon\\Sample\\Support\\Helper',
                'Aliased' => 'Phalcon\\Sample\\Support\\Other',
            ],
            $this->sample()->imports->aliases
        );
    }

    public function testPromotedParametersBecomePropertiesToo(): void
    {
        $class = $this->sample();

        // hidden and store are declared; id and label are promoted.
        $this->assertSame(
            ['hidden', 'id', 'label', 'store'],
            array_map(static fn ($p): string => $p->name, $class->members->properties->all())
        );

        // ...and they are still parameters of the constructor.
        $constructor = $class->members->methods->all()[0];
        $this->assertSame('__construct', $constructor->name);
        $this->assertSame(
            ['id', 'label'],
            array_map(static fn ($p): string => $p->name, $constructor->parameters->all())
        );
    }

    public function testReadonlyIsCaptured(): void
    {
        $properties = $this->sample()->members->properties->all();

        $this->assertSame('id', $properties[1]->name);
        $this->assertTrue($properties[1]->isReadonly);
        $this->assertFalse($properties[0]->isReadonly);
    }

    public function testRelationsAndStructure(): void
    {
        $class = $this->sample();

        $this->assertSame(Keyword::ClassType, $class->structure->keyword);
        $this->assertFalse($class->structure->isAbstract);
        $this->assertTrue($class->structure->isFinal);
        $this->assertSame(['Base'], $class->relations->extends);
        $this->assertSame(['Countable'], $class->relations->implements);
        $this->assertSame(['SampleTrait'], $class->relations->traits);
    }

    public function testNullableReturnsRenderAsUnionsForParity(): void
    {
        $methods = $this->sample()->members->methods->all();

        $toLower = $methods[1];
        $this->assertSame('toLower', $toLower->name);
        $this->assertSame(['public', 'static'], $toLower->modifiers);

        // `?string` is rendered the way Zephir declares the same thing.
        $this->assertSame('string|null', $toLower->returnType);
        $this->assertSame('true', $toLower->parameters->all()[1]->default);
        $this->assertSame('void', $methods[2]->returnType);
    }

    public function testPrivateMembersAreCaptured(): void
    {
        $class = $this->sample();

        $this->assertSame('private', $class->members->properties->all()[0]->visibility);
        $this->assertSame('private', $class->members->methods->all()[2]->visibility);
    }

    public function testConstantsCarryTheirRenderedDefault(): void
    {
        $constant = $this->sample()->members->constants->all()[0];

        $this->assertSame('DEFAULT_MODE', $constant->name);
        $this->assertSame('"strict"', $constant->default);
        $this->assertSame('string', $constant->varType);
    }

    private function sample(): ClassDefinition
    {
        $config = new Config(
            'php',
            dirname(__DIR__, 2) . '/Fixtures/php',
            '/unused',
            'phalcon/phalcon',
            'master',
            'src',
            'php'
        );

        return (new PhpReader())->read($config)->get('Phalcon\\Sample\\Sample')
            ?? self::fail('Phalcon\\Sample\\Sample was not read');
    }
}
