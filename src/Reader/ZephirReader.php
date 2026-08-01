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

namespace Phalcon\Quill\Reader;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Reader;
use Phalcon\Quill\Model\ClassDefinition;
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
use Phalcon\Quill\Reader\Zephir\AstNode;
use Zephir\Parser\Parser;

use function implode;
use function in_array;
use function strrchr;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * Reads `.zep` sources through Zephir's own parser.
 *
 * Ported from cphalcon's bin/generate-api-docs.php. Two things differ on
 * purpose: private members are captured rather than skipped - filtering is the
 * formatter's call - and return types and default values are normalized to
 * strings here, so no parser AST leaks into the model.
 */
final class ZephirReader implements Reader
{
    public function read(Config $config): Registry
    {
        $parser      = new Parser();
        $prefix      = $config->sourceRoot() . DIRECTORY_SEPARATOR;
        $definitions = [];

        foreach (SourceFiles::collect($config) as $relPath) {
            /** @var array<int, mixed> $ast */
            $ast   = $parser->parse($prefix . $relPath);
            $class = $this->readFile(AstNode::listFrom($ast), $relPath);

            if ($class !== null) {
                $definitions[$class->location->fqcn] = $class;
            }
        }

        return new Registry($definitions);
    }

    /**
     * @param list<string> $modifiers
     *
     * @return 'public'|'protected'|'private'
     */
    private function methodVisibility(array $modifiers): string
    {
        if (in_array('private', $modifiers, true)) {
            return 'private';
        }

        return in_array('protected', $modifiers, true) ? 'protected' : 'public';
    }

    private function parameterType(AstNode $parameter): string
    {
        $cast = $parameter->node('cast')?->text('value');
        if ($cast !== null) {
            return $cast;
        }

        $type = $parameter->text('data-type') ?? 'variable';

        return $type === 'variable' ? 'mixed' : $type;
    }

    /**
     * @param list<string> $modifiers
     *
     * @return 'public'|'protected'|'private'
     */
    private function propertyVisibility(array $modifiers): string
    {
        if (in_array('private', $modifiers, true)) {
            return 'private';
        }

        // A Zephir property declared without an explicit keyword is protected.
        return in_array('public', $modifiers, true) ? 'public' : 'protected';
    }

    /**
     * One `use` statement, which may carry several aliases.
     *
     * @return array{uses: list<string>, aliases: array<string, string>}
     */
    private function readAliases(AstNode $item): array
    {
        $uses    = [];
        $aliases = [];

        foreach ($item->section('aliases') as $alias) {
            $name = $alias->text('name');
            if ($name === null) {
                continue;
            }

            $uses[] = $name;

            // Without an explicit alias the short name is the last segment.
            $short           = $alias->text('alias')
                ?? substr((string) strrchr('\\' . $name, '\\'), 1);
            $aliases[$short] = $name;
        }

        return ['uses' => $uses, 'aliases' => $aliases];
    }

    private function readConstants(AstNode $definition): ConstantDefinitionCollection
    {
        $constants = [];
        foreach ($definition->section('constants') as $constant) {
            $name = $constant->text('name');
            if (!$constant->is('type', 'const') || $name === null) {
                continue;
            }

            $doc     = new Docblock($constant->text('docblock'));
            $default = $constant->node('default');

            $constants[] = new ConstantDefinition(
                $name,
                $this->renderDefault($default),
                $this->varType($doc, $default),
                $doc->description()
            );
        }

        return (new ConstantDefinitionCollection($constants))->sortedByName();
    }

    /**
     * A class carries at most one parent; an interface may list several.
     *
     * @return list<string>
     */
    private function readExtends(AstNode $node): array
    {
        $single = $node->text('extends');
        if ($single !== null && $single !== '') {
            return [$single];
        }

        $names = [];
        foreach ($node->section('extends') as $entry) {
            $value = $entry->text('value');
            if ($value !== null) {
                $names[] = $value;
            }
        }

        return $names;
    }

    /**
     * @param list<AstNode> $ast
     */
    private function readFile(array $ast, string $relPath): ?ClassDefinition
    {
        $header = $this->readHeader($ast);
        if ($header === null) {
            return null;
        }

        $node       = $header['node'];
        $fqcn       = $header['namespace'] . '\\' . ($node->text('name') ?? '');
        $definition = $node->node('definition') ?? new AstNode([]);

        return new ClassDefinition(
            new Location($fqcn, $header['namespace'], $relPath),
            $header['structure'],
            (new Docblock($header['comment']))->description(),
            new Imports($header['uses'], $header['aliases']),
            new Relations(
                $this->readExtends($node),
                $this->readImplements($node),
                $this->readTraits($definition)
            ),
            new Members(
                $this->readConstants($definition),
                $this->readProperties($definition),
                $this->readMethods($definition)
            )
        );
    }

    /**
     * Walks the top level until the declaration, gathering what precedes it.
     * Null when the file declares no class, interface or trait.
     *
     * @param list<AstNode> $ast
     *
     * @return array{
     *     namespace: string,
     *     uses: list<string>,
     *     aliases: array<string, string>,
     *     comment: string,
     *     node: AstNode,
     *     structure: Structure
     * }|null
     */
    private function readHeader(array $ast): ?array
    {
        $namespace = '';
        $uses      = [];
        $aliases   = [];
        $comment   = '';

        foreach ($ast as $item) {
            $type = $item->text('type') ?? '';

            if ($type === 'namespace') {
                $namespace = $item->text('name') ?? '';
                $comment   = '';

                continue;
            }

            if ($type === 'comment') {
                $comment = $item->text('value') ?? '';

                continue;
            }

            if ($type === 'use') {
                $comment   = '';
                $collected = $this->readAliases($item);
                $uses      = [...$uses, ...$collected['uses']];
                $aliases   = [...$aliases, ...$collected['aliases']];

                continue;
            }

            if ($type === 'class' || $type === 'interface' || $type === 'trait') {
                return [
                    'namespace' => $namespace,
                    'uses'      => $uses,
                    'aliases'   => $aliases,
                    'comment'   => $comment,
                    'node'      => $item,
                    // Zephir has no enum declaration, so Keyword::Enum never
                    // appears here - only PhpReader produces it.
                    'structure' => match ($type) {
                        'interface' => Structure::interface(),
                        'trait'     => Structure::trait(),
                        default     => Structure::classType(
                            $item->flag('abstract'),
                            $item->flag('final')
                        ),
                    },
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function readImplements(AstNode $node): array
    {
        $names = [];
        foreach ($node->section('implements') as $entry) {
            $value = $entry->text('value');
            if ($value !== null) {
                $names[] = $value;
            }
        }

        return $names;
    }

    /**
     * Source order is preserved - the formatter's ordering depends on it.
     */
    private function readMethods(AstNode $definition): MethodDefinitionCollection
    {
        $methods = [];
        foreach ($definition->section('methods') as $method) {
            $name = $method->text('name');
            if ($name === null) {
                continue;
            }

            $modifiers  = $method->strings('visibility');
            $parameters = [];
            foreach ($method->section('parameters') as $parameter) {
                $parameterName = $parameter->text('name');
                if (!$parameter->is('type', 'parameter') || $parameterName === null) {
                    continue;
                }

                $parameters[] = new ParameterDefinition(
                    $parameterName,
                    $this->parameterType($parameter),
                    $this->renderDefault($parameter->node('default'))
                );
            }

            $methods[] = new MethodDefinition(
                $name,
                $modifiers,
                $this->methodVisibility($modifiers),
                new ParameterDefinitionCollection($parameters),
                $this->renderReturnType($method->node('return-type')),
                (new Docblock($method->text('docblock')))->description()
            );
        }

        return new MethodDefinitionCollection($methods);
    }

    private function readProperties(AstNode $definition): PropertyDefinitionCollection
    {
        $properties = [];
        foreach ($definition->section('properties') as $property) {
            $name = $property->text('name');
            if ($name === null) {
                continue;
            }

            $shortcuts = [];
            foreach ($property->section('shortcuts') as $shortcut) {
                $shortcutName = $shortcut->text('name');
                if ($shortcutName !== null) {
                    $shortcuts[] = $shortcutName;
                }
            }

            $doc     = new Docblock($property->text('docblock'));
            $default = $property->node('default');

            $properties[] = new PropertyDefinition(
                $name,
                $this->propertyVisibility($property->strings('visibility')),
                false,
                $this->renderDefault($default),
                $this->varType($doc, $default),
                $doc->description(),
                $shortcuts
            );
        }

        return (new PropertyDefinitionCollection($properties))->sortedByName();
    }

    /**
     * Traits pulled in by the class body. The parser files these under
     * `definition.uses` as `use-trait` nodes - not to be confused with the
     * file's top-level namespace imports, which are a different relation.
     *
     * @return list<string>
     */
    private function readTraits(AstNode $definition): array
    {
        $traits = [];
        foreach ($definition->section('uses') as $use) {
            if (!$use->is('type', 'use-trait')) {
                continue;
            }

            foreach ($use->section('traits') as $trait) {
                $value = $trait->text('value');
                if ($value !== null) {
                    $traits[] = $value;
                }
            }
        }

        return $traits;
    }

    /**
     * Renders a default-value expression into the string the model carries.
     */
    private function renderDefault(?AstNode $expr): ?string
    {
        if ($expr === null) {
            return null;
        }

        $type = $expr->text('type') ?? '';

        return match ($type) {
            'string'                 => '"' . $expr->stringValue('value') . '"',
            'char'                   => "'" . $expr->stringValue('value') . "'",
            'int', 'uint', 'long',
            'double', 'bool'         => $expr->stringValue('value'),
            'null'                   => 'null',
            'empty-array'            => '[]',
            'array'                  => '[...]',
            'static-constant-access' => ($expr->node('left')?->text('value') ?? 'self')
                . '::' . ($expr->node('right')?->text('value') ?? ''),
            'constant'               => $expr->stringValue('value'),
            'minus'                  => '-' . ($this->renderDefault($expr->node('left')) ?? ''),
            default                  => $expr->has('value') ? $expr->stringValue('value') : $type,
        };
    }

    private function renderReturnType(?AstNode $returnType): ?string
    {
        if ($returnType === null) {
            return null;
        }

        if ($returnType->flag('void')) {
            return 'void';
        }

        $types = [];
        foreach ($returnType->section('list') as $entry) {
            $cast = $entry->node('cast')?->text('value');
            if ($cast !== null) {
                $types[] = $entry->flag('collection') ? $cast . '[]' : $cast;

                continue;
            }

            $dataType = $entry->text('data-type') ?? 'mixed';
            $types[]  = $dataType === 'variable' ? 'mixed' : $dataType;
        }

        return $types === [] ? null : implode('|', $types);
    }

    /**
     * The declared `@var` type, falling back to the default value's type.
     */
    private function varType(Docblock $doc, ?AstNode $default): string
    {
        $declared = $doc->varType();
        if ($declared !== null) {
            return $declared;
        }

        return match ($default?->text('type') ?? '') {
            'string', 'char'       => 'string',
            'int', 'uint', 'long'  => 'int',
            'double'               => 'float',
            'bool'                 => 'bool',
            'empty-array', 'array' => 'array',
            default                => 'mixed',
        };
    }
}
