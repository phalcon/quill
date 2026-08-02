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

namespace Phalcon\Quill\Tests\Unit\Model;

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
use PHPUnit\Framework\TestCase;

use function array_keys;

final class RegistryTest extends TestCase
{
    public function testAncestorsOfARootAreEmpty(): void
    {
        $registry = $this->registry();

        $this->assertSame(
            [],
            $registry->ancestorsOf($this->definition($registry, 'Phalcon\\Base'))
        );
    }

    public function testAncestorsWalkUpwardAndAreRootFirst(): void
    {
        $registry = $this->registry();

        $this->assertSame(
            [['display' => 'Phalcon\\Base', 'fqcn' => 'Phalcon\\Base']],
            $registry->ancestorsOf($this->definition($registry, 'Phalcon\\Child'))
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
        $this->assertCount(3, $registry->definitions());
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
     * Without a configured root the third candidate is skipped rather than
     * built, so a name that only the root could have found stays unresolved.
     */
    public function testResolveSkipsTheRootCandidateWhenThereIsNoRoot(): void
    {
        $registry = new Registry(
            ClassDefinitionCollection::fromDefinitions([$this->classDefinition('Phalcon\\Rooted')])
        );

        $context = new ClassDefinition(
            new Location('Deep\\Place\\Context', 'Deep\\Place', 'rel.zep'),
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

        $this->assertNull($registry->resolve('Rooted', $context));
    }

    /**
     * Each candidate on its own.
     *
     * The other resolve tests use a registry where the alias, the namespace and
     * the root all point at the same definition, so any one of the three could
     * be deleted without a test noticing. These isolate them: each name here can
     * only be found by the candidate it is named for.
     */
    public function testResolveTriesTheAliasThenTheNamespaceThenTheRoot(): void
    {
        $registry = new Registry(
            ClassDefinitionCollection::fromDefinitions([
                // Reachable only through the alias - outside the root, and not
                // a sibling of the context.
                $this->classDefinition('Vendor\\Aliased'),
                // Reachable only as a sibling of the context's namespace.
                $this->classDefinition('Deep\\Place\\Sibling'),
                // Reachable only under the configured root namespace.
                $this->classDefinition('Phalcon\\Rooted'),
            ]),
            'Phalcon'
        );

        $context = new ClassDefinition(
            new Location('Deep\\Place\\Context', 'Deep\\Place', 'rel.zep'),
            Structure::classType(false, false),
            '',
            new Imports([], ['Shortcut' => 'Vendor\\Aliased']),
            new Relations([], [], []),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection()
            )
        );

        $this->assertSame('Vendor\\Aliased', $registry->resolve('Shortcut', $context));
        $this->assertSame('Deep\\Place\\Sibling', $registry->resolve('Sibling', $context));
        $this->assertSame('Phalcon\\Rooted', $registry->resolve('Rooted', $context));
    }

    public function testToArraySerializesEveryDefinitionInOrder(): void
    {
        $array = $this->registry()->definitions()->toArray();

        $this->assertCount(3, $array);

        $fqcns = [];
        foreach ($array as $definition) {
            /** @var array<string, mixed> $location */
            $location = $definition['location'];
            $fqcns[]  = $location['fqcn'];
        }

        $this->assertSame('Phalcon\\Base', $fqcns[0]);
        $this->assertSame('Phalcon\\Child', $fqcns[1]);
        // Keys are dropped: a document is a list here, keyed higher up.
        $this->assertSame([0, 1, 2], array_keys($array));
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

    /**
     * @param array<string, string> $usesMap
     * @param list<string>          $extends
     * @param list<string>          $traits
     */
    private function classDefinition(
        string $fqcn,
        array $extends = [],
        array $usesMap = [],
        array $traits = []
    ): ClassDefinition {
        return new ClassDefinition(
            new Location($fqcn, 'Phalcon', 'rel.zep'),
            Structure::classType(false, false),
            '',
            new Imports([], $usesMap),
            new Relations($extends, [], $traits),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection()
            )
        );
    }

    private function definition(Registry $registry, string $fqcn): ClassDefinition
    {
        return $registry->get($fqcn) ?? self::fail("missing definition {$fqcn}");
    }

    private function registry(): Registry
    {
        return new Registry(ClassDefinitionCollection::fromDefinitions([
            $this->classDefinition('Phalcon\\Base'),
            $this->classDefinition(
                'Phalcon\\Child',
                ['Base'],
                ['Base' => 'Phalcon\\Base'],
                ['Thing']
            ),
            $this->classDefinition('Phalcon\\Thing'),
        ]));
    }
}
