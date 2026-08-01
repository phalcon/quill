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
use Phalcon\Quill\Model\ConstantDefinition;
use Phalcon\Quill\Model\ConstantDefinitionCollection;
use Phalcon\Quill\Model\Document;
use Phalcon\Quill\Model\Imports;
use Phalcon\Quill\Model\Location;
use Phalcon\Quill\Model\Members;
use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\ParameterDefinition;
use Phalcon\Quill\Model\ParameterDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinition;
use Phalcon\Quill\Model\PropertyDefinitionCollection;
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use PHPUnit\Framework\TestCase;

use function array_keys;

final class ModelTest extends TestCase
{
    public function testClassToArrayNestsChildrenAndCarriesVersion(): void
    {
        $array = $this->classDefinition()->toArray();

        $this->assertSame(7, $array['version']);
        $this->assertSame(
            ['keyword' => 'trait', 'isAbstract' => null, 'isFinal' => null],
            $array['structure']
        );
        $this->assertSame(['AbstractStr'], $array['relations']['traits']);
        $this->assertSame('Phalcon\\Support\\Helper\\Str\\Lower', $array['location']['fqcn']);
        $this->assertSame('count', $array['members']['constants'][0]['name']);
        $this->assertSame('store', $array['members']['properties'][0]['name']);
        $this->assertSame('toLower', $array['members']['methods'][0]['name']);

        // Nested one level deeper than the aggregate shape declares, so go
        // through the method's own toArray() where the shape is exact.
        $method = $this->classDefinition()->members->methods->all()[0];
        $this->assertSame('text', $method->toArray()['parameters'][0]['name']);
    }

    /**
     * Document is the contract readers of a model document trust. It only
     * holds if the producer still emits those keys, so the two are pinned
     * together rather than left to drift.
     */
    public function testDocumentNamesTheKeysThatAreActuallyEmitted(): void
    {
        $array = $this->classDefinition()->toArray();

        $this->assertArrayHasKey(Document::VERSION, $array);
        $this->assertArrayHasKey(Document::DESCRIPTION, $array);
        $this->assertArrayHasKey(Document::MEMBERS, $array);

        // Every section, not a chosen one: the keys are what readers index by.
        foreach (array_keys(Members::SECTIONS) as $section) {
            $member = $array[Document::MEMBERS][$section][0];

            $this->assertArrayHasKey(Document::NAME, $member);
            $this->assertArrayHasKey(Document::DESCRIPTION, $member);
        }
    }

    /**
     * Each serialized shape is asserted whole rather than key by key. The keys
     * are a published contract, so a renamed key, a dropped one or a pair
     * swapped round has to fail here.
     */
    public function testEverySerializedShapeIsExact(): void
    {
        $this->assertSame(
            [
                'fqcn'      => 'Phalcon\\Sample\\Lower',
                'namespace' => 'Phalcon\\Sample',
                'relPath'   => 'Sample/Lower.zep',
            ],
            (new Location('Phalcon\\Sample\\Lower', 'Phalcon\\Sample', 'Sample/Lower.zep'))->toArray()
        );

        $this->assertSame(
            ['uses' => ['Phalcon\\Sample\\Base'], 'aliases' => ['Base' => 'Phalcon\\Sample\\Base']],
            (new Imports(['Phalcon\\Sample\\Base'], ['Base' => 'Phalcon\\Sample\\Base']))->toArray()
        );

        $this->assertSame(
            ['extends' => ['Base'], 'implements' => ['Countable'], 'traits' => ['Marker']],
            (new Relations(['Base'], ['Countable'], ['Marker']))->toArray()
        );

        $this->assertSame(
            ['keyword' => 'class', 'isAbstract' => true, 'isFinal' => false],
            Structure::classType(true, false)->toArray()
        );

        $this->assertSame(
            ['name' => 'count', 'default' => '1', 'varType' => 'int', 'description' => 'How many.'],
            (new ConstantDefinition('count', '1', 'int', 'How many.'))->toArray()
        );

        $this->assertSame(
            [
                'name'        => 'store',
                'visibility'  => 'protected',
                'isReadonly'  => true,
                'default'     => '[]',
                'varType'     => 'array',
                'description' => 'The store.',
                'shortcuts'   => ['get'],
            ],
            (new PropertyDefinition('store', 'protected', true, '[]', 'array', 'The store.', ['get']))->toArray()
        );

        $this->assertSame(
            ['name' => 'text', 'type' => 'string', 'default' => '""'],
            (new ParameterDefinition('text', 'string', '""'))->toArray()
        );

        $this->assertSame(
            [
                'name'        => 'toLower',
                'modifiers'   => ['public', 'static'],
                'visibility'  => 'public',
                'parameters'  => [['name' => 'text', 'type' => 'string', 'default' => null]],
                'returnType'  => 'string',
                'description' => 'Lowercases.',
            ],
            (new MethodDefinition(
                'toLower',
                ['public', 'static'],
                'public',
                new ParameterDefinitionCollection([new ParameterDefinition('text', 'string', null)]),
                'string',
                'Lowercases.'
            ))->toArray()
        );
    }

    public function testMethodKeepsModifierOrderAndDerivedVisibility(): void
    {
        $method = new MethodDefinition(
            'toLower',
            ['public', 'static'],
            'public',
            new ParameterDefinitionCollection([new ParameterDefinition('text', 'string', null)]),
            'string',
            'Lowercases a string.'
        );

        $this->assertSame(['public', 'static'], $method->modifiers);
        $this->assertSame('public', $method->visibility);
        $this->assertSame(['public', 'static'], $method->toArray()['modifiers']);
    }

    public function testParameterDefaultIsAlreadyRendered(): void
    {
        $parameter = new ParameterDefinition('options', 'array', '[]');

        $this->assertSame('[]', $parameter->default);
        $this->assertSame('[]', $parameter->toArray()['default']);
    }

    /**
     * Members::SECTIONS is what every reader of a model document trusts to
     * know which sections exist. If a section is ever added to toArray()
     * without being added there, comparisons quietly skip it instead of
     * failing, so the two are pinned together here.
     */
    public function testSectionsConstantMatchesTheSerializedSections(): void
    {
        $members = $this->classDefinition()->toArray()['members'];

        $this->assertSame(
            array_keys(Members::SECTIONS),
            array_keys($members)
        );
    }

    private function classDefinition(): ClassDefinition
    {
        return new ClassDefinition(
            new Location(
                'Phalcon\\Support\\Helper\\Str\\Lower',
                'Phalcon\\Support\\Helper\\Str',
                'Support/Helper/Str/Lower.zep'
            ),
            Structure::trait(),
            'Lowercase helper.',
            new Imports(
                ['Phalcon\\Support\\Helper\\Str\\AbstractStr'],
                ['AbstractStr' => 'Phalcon\\Support\\Helper\\Str\\AbstractStr']
            ),
            new Relations([], [], ['AbstractStr']),
            new Members(
                new ConstantDefinitionCollection([
                    new ConstantDefinition('count', '1', 'int', 'How many.'),
                ]),
                new PropertyDefinitionCollection([
                    new PropertyDefinition('store', 'protected', false, '[]', 'array', 'The store.', []),
                ]),
                new MethodDefinitionCollection([
                    new MethodDefinition(
                        'toLower',
                        ['public'],
                        'public',
                        new ParameterDefinitionCollection([
                            new ParameterDefinition('text', 'string', null),
                        ]),
                        'string',
                        'Lowercases a string.'
                    ),
                ])
            )
        );
    }
}
