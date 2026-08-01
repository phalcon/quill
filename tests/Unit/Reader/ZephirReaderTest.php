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

    public function testDefaultsAreRenderedStrings(): void
    {
        $class = $this->sample();

        $this->assertSame('[]', $class->members->properties->all()[1]->default);
        $this->assertSame('array', $class->members->properties->all()[1]->varType);
        $this->assertSame('"strict"', $class->members->constants->all()[0]->default);
    }

    public function testStructureIsTrait(): void
    {
        $structure = $this->sample()->structure;

        $this->assertSame(Keyword::Trait, $structure->keyword);

        // Null rather than false: the modifiers do not apply to a trait.
        $this->assertNull($structure->isAbstract);
        $this->assertNull($structure->isFinal);
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

    public function testIdentityAndPathAreCarried(): void
    {
        $class = $this->sample();

        $this->assertSame('Phalcon\\Sample\\Sample', $class->location->fqcn);
        $this->assertSame('Phalcon\\Sample', $class->location->namespace);
        $this->assertSame('Sample.zep', $class->location->relPath);
    }

    public function testTraitUsageIsReadAndInverted(): void
    {
        $registry = $this->registry();
        $consumer = $registry->get('Phalcon\\Sample\\Consumer')
            ?? self::fail('Phalcon\\Sample\\Consumer was not read');
        $sample   = $registry->get('Phalcon\\Sample\\Sample')
            ?? self::fail('Phalcon\\Sample\\Sample was not read');

        // Forward: the class records the short trait name.
        $this->assertSame(['Sample'], $consumer->relations->traits);

        // Inverse: the registry resolves it and indexes the other way.
        $this->assertSame(['Phalcon\\Sample\\Consumer'], $registry->usedBy($sample));

        // Namespace imports are a different relation and stay empty here.
        $this->assertSame([], $consumer->imports->uses);
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
            'zep'
        );

        return (new ZephirReader())->read($config);
    }

    private function sample(): ClassDefinition
    {
        return $this->registry()->get('Phalcon\\Sample\\Sample')
            ?? self::fail('Phalcon\\Sample\\Sample was not read');
    }
}
