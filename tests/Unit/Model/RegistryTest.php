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

namespace Phalcon\Scribe\Tests\Unit\Model;

use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\ConstantDefinitionCollection;
use Phalcon\Scribe\Model\MethodDefinitionCollection;
use Phalcon\Scribe\Model\PropertyDefinitionCollection;
use Phalcon\Scribe\Model\Registry;
use Phalcon\Scribe\Model\Structure;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testAncestorsWalkUpwardAndAreRootFirst(): void
    {
        $registry = $this->registry();

        $this->assertSame(
            [['display' => 'Phalcon\\Base', 'fqcn' => 'Phalcon\\Base']],
            $registry->ancestorsOf($this->definition($registry, 'Phalcon\\Child'))
        );
    }

    public function testAncestorsOfARootAreEmpty(): void
    {
        $registry = $this->registry();

        $this->assertSame(
            [],
            $registry->ancestorsOf($this->definition($registry, 'Phalcon\\Base'))
        );
    }

    public function testChildrenAreFoundThroughTheResolvedParent(): void
    {
        $registry = $this->registry();

        $this->assertSame(
            ['Phalcon\\Child'],
            $registry->childrenOf($this->definition($registry, 'Phalcon\\Base'))
        );
    }

    public function testHasAndGet(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('Phalcon\\Base'));
        $this->assertFalse($registry->has('Phalcon\\Nope'));
        $this->assertNull($registry->get('Phalcon\\Nope'));
        $this->assertCount(3, $registry->all());
    }

    public function testUsedByIsTheInverseOfTraitUsage(): void
    {
        $registry = $this->registry();

        $this->assertSame(
            ['Phalcon\\Child'],
            $registry->usedBy($this->definition($registry, 'Phalcon\\Thing'))
        );
        $this->assertSame(
            [],
            $registry->usedBy($this->definition($registry, 'Phalcon\\Base'))
        );
    }

    public function testPagesAreGroupedAndSorted(): void
    {
        $this->assertSame(
            [
                'phalcon_base'    => ['Phalcon\\Base', 'Phalcon\\Child'],
                'phalcon_support' => ['Phalcon\\Thing'],
            ],
            $this->registry()->pages()
        );
    }

    public function testResolveHandlesLeadingBackslash(): void
    {
        $registry = $this->registry();
        $child    = $this->definition($registry, 'Phalcon\\Child');

        $this->assertSame('Phalcon\\Base', $registry->resolve('\\Phalcon\\Base', $child));
        $this->assertNull($registry->resolve('\\Phalcon\\Nope', $child));
    }

    public function testResolvePrefersTheUseMapOverTheNamespace(): void
    {
        $registry = $this->registry();
        $child    = $this->definition($registry, 'Phalcon\\Child');

        $this->assertSame('Phalcon\\Base', $registry->resolve('Base', $child));
    }

    public function testResolveReturnsNullForUnknownNames(): void
    {
        $registry = $this->registry();
        $child    = $this->definition($registry, 'Phalcon\\Child');

        $this->assertNull($registry->resolve('NoSuchThing', $child));
    }

    /**
     * @param array<string, string> $usesMap
     * @param list<string>          $extends
     * @param list<string>          $traits
     */
    private function classDefinition(
        string $fqcn,
        string $page,
        array $extends = [],
        array $usesMap = [],
        array $traits = []
    ): ClassDefinition {
        return new ClassDefinition(
            $fqcn,
            $fqcn,
            $page,
            'anchor',
            'rel.zep',
            'Phalcon',
            Structure::classType(false, false),
            '',
            [],
            $usesMap,
            $extends,
            [],
            $traits,
            new ConstantDefinitionCollection(),
            new PropertyDefinitionCollection(),
            new MethodDefinitionCollection()
        );
    }

    private function definition(Registry $registry, string $fqcn): ClassDefinition
    {
        return $registry->get($fqcn) ?? self::fail("missing definition {$fqcn}");
    }

    private function registry(): Registry
    {
        return new Registry([
            'Phalcon\\Base'  => $this->classDefinition('Phalcon\\Base', 'phalcon_base'),
            'Phalcon\\Child' => $this->classDefinition(
                'Phalcon\\Child',
                'phalcon_base',
                ['Base'],
                ['Base' => 'Phalcon\\Base'],
                ['Thing']
            ),
            'Phalcon\\Thing' => $this->classDefinition('Phalcon\\Thing', 'phalcon_support'),
        ]);
    }
}
