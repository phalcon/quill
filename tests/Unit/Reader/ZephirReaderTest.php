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
use Phalcon\Quill\Reader\ZephirReader;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;

final class ZephirReaderTest extends TestCase
{
    public function testAClassCarriesOneParentAndSeveralInterfaces(): void
    {
        $class = $this->shapes();

        $this->assertSame(Keyword::ClassType, $class->structure->keyword);
        $this->assertTrue($class->structure->isAbstract);
        $this->assertFalse($class->structure->isFinal);
        $this->assertSame(['\\Phalcon\\Sample\\Consumer'], $class->relations->extends);
        $this->assertSame(
            ['\\Phalcon\\Sample\\Countable', '\\Phalcon\\Sample\\Stringable'],
            $class->relations->implements
        );
    }

    public function testAliasesFallBackToTheLastSegment(): void
    {
        $imports = $this->shapes()->imports;

        $this->assertSame(['Phalcon\\Sample\\Support\\Helper'], $imports->uses);
        $this->assertSame(['Aliased' => 'Phalcon\\Sample\\Support\\Helper'], $imports->aliases);
    }

    public function testAnInterfaceExtendsAList(): void
    {
        $contract = $this->registry()->get('Phalcon\\Sample\\Contract')
            ?? self::fail('Phalcon\\Sample\\Contract was not read');

        $this->assertSame(Keyword::Interface, $contract->structure->keyword);
        $this->assertSame(
            ['\\Phalcon\\Sample\\Countable', '\\Phalcon\\Sample\\Stringable'],
            $contract->relations->extends
        );
    }

    /**
     * Zephir cannot write a union, so a null default is how it says "or
     * null". PHP writes the same thing as `?string`, and both must reach the
     * model as `string|null` or the two look different when they are not.
     */
    public function testANullDefaultMakesTheParameterNullable(): void
    {
        $methods = [];
        foreach ($this->shapes()->members->methods->all() as $method) {
            $methods[$method->name] = $method;
        }

        $nullable = [];
        foreach ($methods['nullables']->parameters->all() as $parameter) {
            $nullable[$parameter->name] = $parameter->type;
        }

        $this->assertSame('string|null', $nullable['text']);
        $this->assertSame('Consumer|null', $nullable['item']);
        // `var` is already mixed, which admits null on its own.
        $this->assertSame('mixed', $nullable['loose']);

        $plain = [];
        foreach ($methods['notNullable']->parameters->all() as $parameter) {
            $plain[$parameter->name] = $parameter->type;
        }

        $this->assertSame('string', $plain['text']);
        $this->assertSame('int', $plain['count']);
    }

    public function testCapturesPrivateMembers(): void
    {
        $class = $this->sample();

        $this->assertSame(
            ['hidden', 'store'],
            array_map(static fn ($property): string => $property->name, $class->members->properties->all())
        );
        $this->assertSame('private', $class->members->properties->all()[0]->visibility);
        $this->assertSame('protected', $class->members->properties->all()[1]->visibility);

        $this->assertSame(
            ['toLower', 'secret'],
            array_map(static fn ($method): string => $method->name, $class->members->methods->all())
        );
        $this->assertSame('private', $class->members->methods->all()[1]->visibility);
    }

    public function testCastsAndCollectionsRenderInReturnsAndParameters(): void
    {
        $methods = [];
        foreach ($this->shapes()->members->methods->all() as $method) {
            $methods[$method->name] = $method;
        }

        $this->assertSame('Consumer', $methods['withCast']->returnType);
        $this->assertSame('Consumer', $methods['withCast']->parameters->all()[0]->type);
        $this->assertSame('Consumer[]', $methods['collection']->returnType);
        // `var` is the Zephir spelling of no particular type.
        $this->assertSame('mixed', $methods['untyped']->returnType);
        $this->assertSame('mixed', $methods['untyped']->parameters->all()[0]->type);
    }

    public function testDefaultsAreRenderedStrings(): void
    {
        $class = $this->sample();

        $this->assertSame('[]', $class->members->properties->all()[1]->default);
        $this->assertSame('array', $class->members->properties->all()[1]->varType);
        $this->assertSame('"strict"', $class->members->constants->all()[0]->default);
    }

    public function testEveryDefaultKindIsRendered(): void
    {
        $defaults = [];
        foreach ($this->shapes()->members->constants->all() as $constant) {
            $defaults[$constant->name] = $constant->default;
        }

        $this->assertSame('"text"', $defaults['A_STRING']);
        $this->assertSame("'a'", $defaults['A_CHAR']);
        $this->assertSame('1', $defaults['AN_INT']);
        $this->assertSame('1.5', $defaults['A_DOUBLE']);
        $this->assertSame('true', $defaults['A_BOOL']);
        $this->assertSame('null', $defaults['A_NULL']);
        $this->assertSame('[]', $defaults['AN_EMPTY_ARRAY']);
        $this->assertSame('[...]', $defaults['A_FILLED_ARRAY']);
        $this->assertSame('self::AN_INT', $defaults['A_STATIC']);
        $this->assertSame('-3', $defaults['A_NEGATIVE']);
    }

    public function testIdentityAndPathAreCarried(): void
    {
        $class = $this->sample();

        $this->assertSame('Phalcon\\Sample\\Sample', $class->location->fqcn);
        $this->assertSame('Phalcon\\Sample', $class->location->namespace);
        $this->assertSame('Sample.zep', $class->location->relPath);
    }

    public function testMethodModifiersKeepSourceOrder(): void
    {
        $method = $this->sample()->members->methods->all()[0];

        $this->assertSame(['public', 'static'], $method->modifiers);
        $this->assertSame('public', $method->visibility);
        $this->assertSame('string', $method->returnType);
        $this->assertSame('true', $method->parameters->all()[1]->default);
        $this->assertSame('string', $method->parameters->all()[0]->type);
        $this->assertNull($method->parameters->all()[0]->default);
    }

    public function testPropertyShortcutsAreRecorded(): void
    {
        $properties = [];
        foreach ($this->shapes()->members->properties->all() as $property) {
            $properties[$property->name] = $property;
        }

        // A declared type with no docblock and no default: the declaration is
        // the only thing left to name it, which is where the sources are going.
        $this->assertSame('array', $properties['registry']->varType);

        $this->assertSame(['get', 'set'], $properties['label']->shortcuts);
        $this->assertSame([], $properties['counter']->shortcuts);
        // No keyword at all would be protected; these say so explicitly.
        $this->assertSame('protected', $properties['label']->visibility);
        $this->assertSame('public', $properties['counter']->visibility);
        $this->assertSame('private', $properties['flag']->visibility);
    }

    public function testStructureIsTrait(): void
    {
        $structure = $this->sample()->structure;

        $this->assertSame(Keyword::Trait, $structure->keyword);

        // Null rather than false: the modifiers do not apply to a trait.
        $this->assertNull($structure->isAbstract);
        $this->assertNull($structure->isFinal);
    }

    public function testTraitUsageIsReadAndInverted(): void
    {
        $registry = $this->registry();
        $consumer = $registry->get('Phalcon\\Sample\\Consumer')
            ?? self::fail('Phalcon\\Sample\\Consumer was not read');
        $sample   = $registry->get('Phalcon\\Sample\\Sample')
            ?? self::fail('Phalcon\\Sample\\Sample was not read');

        // Forward: the class records the trait, resolved against its namespace.
        $this->assertSame(['\\Phalcon\\Sample\\Sample'], $consumer->relations->traits);

        // Inverse: the registry resolves it and indexes the other way.
        $this->assertSame(['Phalcon\\Sample\\Consumer'], $registry->usedBy($sample));

        // Namespace imports are a different relation and stay empty here.
        $this->assertSame([], $consumer->imports->uses);
    }

    public function testTypesAreInferredFromTheDefaultWhenNoVarTagSaysOtherwise(): void
    {
        $types = [];
        foreach ($this->shapes()->members->constants->all() as $constant) {
            $types[$constant->name] = $constant->varType;
        }

        $this->assertSame('string', $types['A_STRING']);
        $this->assertSame('string', $types['A_CHAR']);
        $this->assertSame('int', $types['AN_INT']);
        $this->assertSame('float', $types['A_DOUBLE']);
        $this->assertSame('bool', $types['A_BOOL']);
        $this->assertSame('array', $types['AN_EMPTY_ARRAY']);
        $this->assertSame('array', $types['A_FILLED_ARRAY']);
        $this->assertSame('mixed', $types['A_NULL']);
    }

    public function testUsesAreMapped(): void
    {
        $class = $this->sample();

        $this->assertSame(['Phalcon\\Support\\Helper\\Str\\AbstractStr'], $class->imports->uses);
        $this->assertSame(
            ['AbstractStr' => 'Phalcon\\Support\\Helper\\Str\\AbstractStr'],
            $class->imports->aliases
        );
    }

    public function testVoidReturnIsRendered(): void
    {
        $this->assertSame('void', $this->sample()->members->methods->all()[1]->returnType);
    }

    private function registry(): Registry
    {
        $config = new Config(
            'zephir',
            dirname(__DIR__, 2) . '/Fixtures/zep',
            '/unused',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep',
            'Phalcon'
        );

        return (new ZephirReader())->read($config);
    }

    private function sample(): ClassDefinition
    {
        return $this->registry()->get('Phalcon\\Sample\\Sample')
            ?? self::fail('Phalcon\\Sample\\Sample was not read');
    }

    private function shapes(): ClassDefinition
    {
        return $this->registry()->get('Phalcon\\Sample\\Shapes')
            ?? self::fail('Phalcon\\Sample\\Shapes was not read');
    }
}
