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
use Phalcon\Scribe\Model\ConstantDefinition;
use Phalcon\Scribe\Model\Structure;
use Phalcon\Scribe\Model\MethodDefinition;
use Phalcon\Scribe\Model\ParameterDefinition;
use Phalcon\Scribe\Model\PropertyDefinition;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testClassToArrayNestsChildrenAndCarriesVersion(): void
    {
        $array = $this->classDefinition()->toArray();

        $this->assertSame(3, $array['version']);
        $this->assertSame('trait', $array['structure']);
        $this->assertSame(['AbstractStr'], $array['traits']);
        $this->assertSame('Phalcon\\Support\\Helper\\Str\\Lower', $array['fqcn']);
        $this->assertSame('count', $array['constants'][0]['name']);
        $this->assertSame('store', $array['properties'][0]['name']);
        $this->assertSame('toLower', $array['methods'][0]['name']);
        $this->assertSame('text', $array['methods'][0]['parameters'][0]['name']);
    }

    public function testMethodKeepsModifierOrderAndDerivedVisibility(): void
    {
        $method = new MethodDefinition(
            'toLower',
            ['public', 'static'],
            'public',
            [new ParameterDefinition('text', 'string', null)],
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

    private function classDefinition(): ClassDefinition
    {
        return new ClassDefinition(
            'Phalcon\\Support\\Helper\\Str\\Lower',
            'Support\\Helper\\Str\\Lower',
            'phalcon_support',
            'supporthelperstrlower',
            'Support/Helper/Str/Lower.zep',
            'Phalcon\\Support\\Helper\\Str',
            Structure::Trait,
            false,
            false,
            'Lowercase helper.',
            ['Phalcon\\Support\\Helper\\Str\\AbstractStr'],
            ['AbstractStr' => 'Phalcon\\Support\\Helper\\Str\\AbstractStr'],
            [],
            [],
            ['AbstractStr'],
            [new ConstantDefinition('count', '1', 'int', 'How many.')],
            [new PropertyDefinition('store', 'protected', '[]', 'array', 'The store.', [])],
            [new MethodDefinition(
                'toLower',
                ['public'],
                'public',
                [new ParameterDefinition('text', 'string', null)],
                'string',
                'Lowercases a string.'
            )]
        );
    }
}
