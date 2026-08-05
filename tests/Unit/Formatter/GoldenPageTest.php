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

namespace Phalcon\Quill\Tests\Unit\Formatter;

use Phalcon\Quill\Config;
use Phalcon\Quill\Formatter\MarkdownFormatter;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\ClassDefinitionCollection;
use Phalcon\Quill\Model\ConstantDefinition;
use Phalcon\Quill\Model\ConstantDefinitionCollection;
use Phalcon\Quill\Model\Imports;
use Phalcon\Quill\Model\Location;
use Phalcon\Quill\Model\Members;
use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\ParameterDefinition;
use Phalcon\Quill\Model\ParameterDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinition;
use Phalcon\Quill\Model\PropertyDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use Phalcon\Quill\Selection;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_file;

/**
 * Byte equality against a committed page.
 *
 * The expectation was generated from the formatter as it stood before the
 * markup moved into templates, which is what makes it evidence rather than a
 * restatement. It then pins the whitespace convention: a template that gains
 * or loses a newline changes what mkdocs renders, and nothing else in the
 * suite would notice.
 *
 * Regenerate deliberately, never to make a red test green:
 *
 *     docker exec -e UPDATE_GOLDEN=1 quill-8.1 \
 *         vendor/bin/phpunit -c resources/phpunit.xml.dist --filter GoldenPageTest
 *
 * Then read the diff. An intentional markup change shows up there; an
 * accidental one shows up there too, which is the point.
 */
final class GoldenPageTest extends TestCase
{
    public function testThePageMatchesTheCommittedExpectation(): void
    {
        $documents = (new MarkdownFormatter())->format(
            $this->registry(),
            $this->config(),
            new Selection('', '')
        );

        $page = $documents['phalcon_sample'] ?? self::fail('phalcon_sample page missing');
        $path = dirname(__DIR__, 2) . '/Fixtures/golden/phalcon_sample.md';

        if (getenv('UPDATE_GOLDEN') !== false) {
            file_put_contents($path, $page);
        }

        if (!is_file($path)) {
            self::fail('Golden file missing. Generate it with UPDATE_GOLDEN=1.');
        }

        $this->assertSame((string) file_get_contents($path), $page);
    }

    private function config(): Config
    {
        return new Config(
            'zephir',
            '/sources',
            '/unused',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep',
            'Phalcon'
        );
    }

    /**
     * Five declarations chosen to reach every branch of the page layout: an
     * abstract class carrying the members, a final class that extends it, the
     * interface it implements, the trait it uses, and a plain class for the
     * default badge. Between them they exercise all five badges, the
     * inheritance tree with an ancestor and with a child, the implements
     * annotation, both inline lists, every member section, and each of the
     * conditional slots - a class with no description, a constant with none, a
     * property with no default, a method with no return type and a method with
     * no description.
     */
    private function registry(): Registry
    {
        $base = new ClassDefinition(
            new Location('Phalcon\\Sample\\Base', 'Phalcon\\Sample', 'Sample/Base.zep'),
            Structure::classType(true, false),
            'The abstract root.',
            new Imports([], []),
            new Relations([], [], []),
            new Members(
                new ConstantDefinitionCollection([
                    new ConstantDefinition('LIMIT', '10', 'int', 'How many.'),
                    new ConstantDefinition('MARKER', null, 'string', ''),
                ]),
                new PropertyDefinitionCollection([
                    new PropertyDefinition('label', 'protected', false, 'null', 'string|null', 'The label.', []),
                    new PropertyDefinition('secret', 'private', false, null, 'mixed', 'Hidden.', []),
                    new PropertyDefinition('store', 'public', false, null, 'array', '', []),
                ]),
                new MethodDefinitionCollection([
                    new MethodDefinition(
                        'describe',
                        ['public'],
                        'public',
                        new ParameterDefinitionCollection([
                            new ParameterDefinition('text', 'string', "'none'"),
                            new ParameterDefinition('depth', 'int', '0'),
                        ]),
                        'string',
                        'Describes the subject.'
                    ),
                    new MethodDefinition(
                        'conceal',
                        ['protected'],
                        'protected',
                        new ParameterDefinitionCollection([
                            new ParameterDefinition('flag', 'bool', null),
                        ]),
                        null,
                        ''
                    ),
                    new MethodDefinition(
                        'internal',
                        ['private'],
                        'private',
                        new ParameterDefinitionCollection([]),
                        'void',
                        'Never rendered.'
                    ),
                ])
            )
        );

        $child = new ClassDefinition(
            new Location('Phalcon\\Sample\\Child', 'Phalcon\\Sample', 'Sample/Child.zep'),
            Structure::classType(false, true),
            'The concrete leaf.',
            new Imports(
                ['Phalcon\\Sample\\Contract', 'Phalcon\\Sample\\Base'],
                ['Base' => 'Phalcon\\Sample\\Base', 'Contract' => 'Phalcon\\Sample\\Contract']
            ),
            new Relations(['Base'], ['Contract'], ['Helper']),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection()
            )
        );

        $contract = new ClassDefinition(
            new Location('Phalcon\\Sample\\Contract', 'Phalcon\\Sample', 'Sample/Contract.zep'),
            Structure::interface(),
            'What the leaf promises.',
            new Imports([], []),
            new Relations([], [], []),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection([
                    new MethodDefinition(
                        'describe',
                        ['public'],
                        'public',
                        new ParameterDefinitionCollection([
                            new ParameterDefinition('text', 'string', null),
                        ]),
                        'string',
                        'Describes the subject.'
                    ),
                ])
            )
        );

        $helper = new ClassDefinition(
            new Location('Phalcon\\Sample\\Helper', 'Phalcon\\Sample', 'Sample/Helper.zep'),
            Structure::trait(),
            'Shared behavior.',
            new Imports([], []),
            new Relations([], [], []),
            new Members(
                new ConstantDefinitionCollection(),
                new PropertyDefinitionCollection(),
                new MethodDefinitionCollection()
            )
        );

        $plain = new ClassDefinition(
            new Location('Phalcon\\Sample\\Plain', 'Phalcon\\Sample', 'Sample/Plain.zep'),
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

        return new Registry(
            ClassDefinitionCollection::fromDefinitions([
                $base,
                $child,
                $contract,
                $helper,
                $plain,
            ]),
            'Phalcon'
        );
    }
}
