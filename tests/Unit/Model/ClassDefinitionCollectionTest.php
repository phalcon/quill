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
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function iterator_to_array;

final class ClassDefinitionCollectionTest extends TestCase
{
    public function testAnEmptyCollectionSaysSo(): void
    {
        $empty = ClassDefinitionCollection::fromDefinitions([]);

        $this->assertTrue($empty->isEmpty());
        $this->assertCount(0, $empty);
        $this->assertSame([], $empty->all());
        $this->assertSame([], $empty->toArray());

        $this->assertFalse($this->collection()->isEmpty());
        $this->assertCount(2, $this->collection());
    }

    /**
     * Keying is the collection's business - a reader hands over what it read
     * and never spells the FQCN out.
     */
    public function testDefinitionsAreKeyedByTheirOwnFqcn(): void
    {
        $collection = $this->collection();

        $this->assertSame(
            ['Phalcon\\Sample\\Zulu', 'Phalcon\\Sample\\Alpha'],
            array_keys($collection->all())
        );
        $this->assertSame('Phalcon\\Sample\\Alpha', $collection->get('Phalcon\\Sample\\Alpha')?->location->fqcn);
        $this->assertNull($collection->get('Phalcon\\Sample\\Nope'));
        $this->assertTrue($collection->has('Phalcon\\Sample\\Zulu'));
        $this->assertFalse($collection->has('Phalcon\\Sample\\Nope'));
    }

    public function testFilteringSeesBothTheDefinitionAndItsKey(): void
    {
        $byKey = $this->collection()->filter(
            static fn (ClassDefinition $class, string $fqcn): bool => $fqcn === 'Phalcon\\Sample\\Zulu'
        );

        $this->assertSame(['Phalcon\\Sample\\Zulu'], array_keys($byKey->all()));

        $byDefinition = $this->collection()->filter(
            static fn (ClassDefinition $class): bool => $class->location->relPath === 'Sample/Alpha.zep'
        );

        $this->assertSame(['Phalcon\\Sample\\Alpha'], array_keys($byDefinition->all()));
    }

    public function testIterationKeepsTheKeys(): void
    {
        $this->assertSame(
            ['Phalcon\\Sample\\Zulu', 'Phalcon\\Sample\\Alpha'],
            array_keys(iterator_to_array($this->collection()))
        );
    }

    public function testSortingOrdersByFqcnAndLeavesTheOriginalAlone(): void
    {
        $collection = $this->collection();
        $sorted     = $collection->sorted();

        $this->assertSame(
            ['Phalcon\\Sample\\Alpha', 'Phalcon\\Sample\\Zulu'],
            array_keys($sorted->all())
        );

        // Immutable: the collection it came from is untouched.
        $this->assertSame(
            ['Phalcon\\Sample\\Zulu', 'Phalcon\\Sample\\Alpha'],
            array_keys($collection->all())
        );
    }

    public function testToArraySerializesEveryDefinitionAsAList(): void
    {
        $array = $this->collection()->toArray();

        $this->assertCount(2, $array);
        $this->assertSame([0, 1], array_keys($array));

        /** @var array<string, mixed> $location */
        $location = $array[0]['location'];
        $this->assertSame('Phalcon\\Sample\\Zulu', $location['fqcn']);
    }

    private function collection(): ClassDefinitionCollection
    {
        // Deliberately out of order, so sorting has something to do.
        return ClassDefinitionCollection::fromDefinitions([
            $this->definition('Zulu'),
            $this->definition('Alpha'),
        ]);
    }

    private function definition(string $name): ClassDefinition
    {
        return new ClassDefinition(
            new Location('Phalcon\\Sample\\' . $name, 'Phalcon\\Sample', 'Sample/' . $name . '.zep'),
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
