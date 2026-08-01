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

namespace Phalcon\Scribe\Tests\Unit\Reader;

use Phalcon\Scribe\Config;
use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\Structure;
use Phalcon\Scribe\Model\Registry;
use Phalcon\Scribe\Reader\ZephirReader;
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
            array_map(static fn ($property): string => $property->name, $class->properties)
        );
        $this->assertSame('private', $class->properties[0]->visibility);
        $this->assertSame('protected', $class->properties[1]->visibility);

        $this->assertSame(
            ['toLower', 'secret'],
            array_map(static fn ($method): string => $method->name, $class->methods)
        );
        $this->assertSame('private', $class->methods[1]->visibility);
    }

    public function testDefaultsAreRenderedStrings(): void
    {
        $class = $this->sample();

        $this->assertSame('[]', $class->properties[1]->default);
        $this->assertSame('array', $class->properties[1]->varType);
        $this->assertSame('"strict"', $class->constants[0]->default);
    }

    public function testStructureIsTrait(): void
    {
        $this->assertSame(Structure::Trait, $this->sample()->structure);
    }

    public function testMethodModifiersKeepSourceOrder(): void
    {
        $method = $this->sample()->methods[0];

        $this->assertSame(['public', 'static'], $method->modifiers);
        $this->assertSame('public', $method->visibility);
        $this->assertSame('string', $method->returnType);
        $this->assertSame('true', $method->parameters[1]->default);
        $this->assertSame('string', $method->parameters[0]->type);
        $this->assertNull($method->parameters[0]->default);
    }

    public function testPageAnchorAndPathsFollowTheLegacyRules(): void
    {
        $class = $this->sample();

        $this->assertSame('Phalcon\\Sample\\Sample', $class->fqcn);
        $this->assertSame('Sample\\Sample', $class->title);
        $this->assertSame('phalcon_sample', $class->page);
        $this->assertSame('samplesample', $class->anchor);
        $this->assertSame('Sample.zep', $class->relPath);
    }

    public function testTraitUsageIsReadAndInverted(): void
    {
        $registry = $this->registry();
        $consumer = $registry->get('Phalcon\\Sample\\Consumer')
            ?? self::fail('Phalcon\\Sample\\Consumer was not read');
        $sample   = $registry->get('Phalcon\\Sample\\Sample')
            ?? self::fail('Phalcon\\Sample\\Sample was not read');

        // Forward: the class records the short trait name.
        $this->assertSame(['Sample'], $consumer->traits);

        // Inverse: the registry resolves it and indexes the other way.
        $this->assertSame(['Phalcon\\Sample\\Consumer'], $registry->usedBy($sample));

        // Namespace imports are a different relation and stay empty here.
        $this->assertSame([], $consumer->uses);
    }

    public function testUsesAreMapped(): void
    {
        $class = $this->sample();

        $this->assertSame(['Phalcon\\Support\\Helper\\Str\\AbstractStr'], $class->uses);
        $this->assertSame(
            ['AbstractStr' => 'Phalcon\\Support\\Helper\\Str\\AbstractStr'],
            $class->usesMap
        );
    }

    public function testVoidReturnIsRendered(): void
    {
        $this->assertSame('void', $this->sample()->methods[1]->returnType);
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
