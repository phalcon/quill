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
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Reader\PhpReader;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;

final class PhpReaderTest extends TestCase
{
    public function testADeclarationOutsideAnyNamespaceKeepsItsBareName(): void
    {
        $class = $this->definition('Standalone');

        $this->assertSame('', $class->location->namespace);
        $this->assertSame('Standalone', $class->location->fqcn);
    }

    public function testAFileWithNoDeclarationIsSkipped(): void
    {
        // helpers.php holds only a function, so nothing is recorded for it.
        $this->assertNull($this->registry()->get('Phalcon\\Sample\\helper'));
        $this->assertNull($this->registry()->get('Phalcon\\Sample\\helpers'));
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

    public function testAnInterfaceExtendsAList(): void
    {
        $interface = $this->definition('Phalcon\\Sample\\SampleInterface');

        $this->assertSame(Keyword::Interface, $interface->structure->keyword);
        $this->assertSame(['Countable', 'Stringable'], $interface->relations->extends);
        // Only classes and enums implement; an interface never does.
        $this->assertSame([], $interface->relations->implements);
    }

    public function testATraitIsClassifiedAsOne(): void
    {
        $this->assertSame(Keyword::Trait, $this->definition('Phalcon\\Sample\\Marker')->structure->keyword);
    }

    public function testConstantsCarryTheirRenderedDefault(): void
    {
        $constant = $this->sample()->members->constants->all()[0];

        $this->assertSame('DEFAULT_MODE', $constant->name);
        $this->assertSame('"strict"', $constant->default);
        $this->assertSame('string', $constant->varType);
    }

    public function testEachConstantKindIsNamed(): void
    {
        $types = [];
        foreach ($this->definition('Phalcon\\Sample\\Shapes')->members->constants->all() as $constant) {
            $types[$constant->name] = $constant->varType;
        }

        $this->assertSame('string', $types['A_STRING']);
        $this->assertSame('int', $types['AN_INT']);
        $this->assertSame('float', $types['A_FLOAT']);
        $this->assertSame('array', $types['AN_ARRAY']);
        $this->assertSame('bool', $types['A_BOOL']);
        // `null` is a constant name too, but it is not a bool.
        $this->assertSame('mixed', $types['A_NULL']);
        // Anything the renderer cannot name falls back the same way.
        $this->assertSame('mixed', $types['AN_EXPRESSION']);
    }

    public function testEachConstantKindIsRendered(): void
    {
        $defaults = [];
        foreach ($this->definition('Phalcon\\Sample\\Shapes')->members->constants->all() as $constant) {
            $defaults[$constant->name] = $constant->default;
        }

        $this->assertSame('"text"', $defaults['A_STRING']);
        $this->assertSame('1', $defaults['AN_INT']);
        $this->assertSame('1.5', $defaults['A_FLOAT']);
        $this->assertSame('[]', $defaults['AN_ARRAY']);
        $this->assertSame('[...]', $defaults['A_FILLED_ARRAY']);
        $this->assertSame('true', $defaults['A_BOOL']);
        $this->assertSame('null', $defaults['A_NULL']);
        $this->assertSame('self::A_STRING', $defaults['A_CLASS_CONST']);
        $this->assertSame('-3', $defaults['A_NEGATIVE']);
        // An expression is not rendered rather than rendered wrongly.
        $this->assertNull($defaults['AN_EXPRESSION']);
    }

    public function testEnumCasesAreRecordedAsConstants(): void
    {
        $enum = $this->definition('Phalcon\\Sample\\SampleEnum');

        $this->assertSame(Keyword::Enum, $enum->structure->keyword);
        $this->assertNull($enum->structure->isAbstract);
        $this->assertSame(['Countable'], $enum->relations->implements);

        $cases = $enum->members->constants->all();
        $this->assertSame(['Loose', 'Strict'], array_map(static fn ($c): string => $c->name, $cases));
        $this->assertSame('"strict"', $cases[1]->default);
        $this->assertSame('string', $cases[1]->varType);
        $this->assertSame('The strict mode.', $cases[1]->description);
    }

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

    public function testUnionNullableAndIntersectionTypesRender(): void
    {
        $methods = [];
        foreach ($this->definition('Phalcon\\Sample\\Shapes')->members->methods->all() as $method) {
            $methods[$method->name] = $method;
        }

        $union = $methods['unionAndNullable'];
        $this->assertSame('int|string|null', $union->returnType);

        $parameters = [];
        foreach ($union->parameters->all() as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }

        $this->assertSame('int|string', $parameters['either']->type);
        $this->assertSame('Shapes|null', $parameters['maybe']->type);
        // No declared type at all.
        $this->assertSame('mixed', $parameters['untyped']->type);

        $this->assertSame(
            'Shapes&Countable',
            $methods['intersection']->parameters->all()[0]->type
        );
    }

    private function definition(string $fqcn): ClassDefinition
    {
        return $this->registry()->get($fqcn) ?? self::fail($fqcn . ' was not read');
    }

    private function registry(): Registry
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

        return (new PhpReader())->read($config);
    }

    private function sample(): ClassDefinition
    {
        return $this->definition('Phalcon\\Sample\\Sample');
    }
}
