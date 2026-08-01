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

namespace Phalcon\Scribe\Model;

use function array_map;

/**
 * One class, interface, trait or enum.
 *
 * Immutable, and deliberately unaware of any other definition: resolving short
 * names to FQCNs, ancestors and children is Registry's job, which keeps this
 * object language-agnostic and readonly.
 */
final class ClassDefinition
{
    /**
     * Bumped whenever toArray()'s shape changes. It is a published format the
     * moment anything downstream reads it.
     *
     * 2 - added `traits`.
     */
    public const MODEL_VERSION = 2;

    /**
     * `$uses` are namespace imports; `$traits` are the traits the body pulls
     * in. Different relations that happen to share a keyword - do not conflate
     * them.
     *
     * @param list<string>              $uses
     * @param array<string, string>     $usesMap    short name => FQCN
     * @param list<string>              $extends    a class uses index 0; an interface may list several
     * @param list<string>              $implements
     * @param list<string>              $traits     short names, resolved by Registry
     * @param list<ConstantDefinition>  $constants
     * @param list<PropertyDefinition>  $properties
     * @param list<MethodDefinition>    $methods
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $title,
        public readonly string $page,
        public readonly string $anchor,
        public readonly string $relPath,
        public readonly string $namespace,
        public readonly Kind $kind,
        public readonly bool $abstract,
        public readonly bool $final,
        public readonly string $description,
        public readonly array $uses,
        public readonly array $usesMap,
        public readonly array $extends,
        public readonly array $implements,
        public readonly array $traits,
        public readonly array $constants,
        public readonly array $properties,
        public readonly array $methods,
    ) {
    }

    /**
     * The published serialization shape. Declared precisely rather than as
     * `array<string, mixed>` so every consumer - and every future formatter -
     * is checked against it.
     *
     * @return array{
     *     version: int,
     *     fqcn: string,
     *     title: string,
     *     page: string,
     *     anchor: string,
     *     relPath: string,
     *     namespace: string,
     *     kind: string,
     *     abstract: bool,
     *     final: bool,
     *     description: string,
     *     uses: list<string>,
     *     usesMap: array<string, string>,
     *     extends: list<string>,
     *     implements: list<string>,
     *     traits: list<string>,
     *     constants: list<array{
     *         name: string,
     *         default: string|null,
     *         varType: string,
     *         description: string
     *     }>,
     *     properties: list<array{
     *         name: string,
     *         visibility: string,
     *         default: string|null,
     *         varType: string,
     *         description: string,
     *         shortcuts: list<string>
     *     }>,
     *     methods: list<array{
     *         name: string,
     *         modifiers: list<string>,
     *         visibility: string,
     *         parameters: list<array{name: string, type: string, default: string|null}>,
     *         returnType: string|null,
     *         description: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'version'     => self::MODEL_VERSION,
            'fqcn'        => $this->fqcn,
            'title'       => $this->title,
            'page'        => $this->page,
            'anchor'      => $this->anchor,
            'relPath'     => $this->relPath,
            'namespace'   => $this->namespace,
            'kind'        => $this->kind->value,
            'abstract'    => $this->abstract,
            'final'       => $this->final,
            'description' => $this->description,
            'uses'        => $this->uses,
            'usesMap'     => $this->usesMap,
            'extends'     => $this->extends,
            'implements'  => $this->implements,
            'traits'      => $this->traits,
            'constants'   => array_map(
                static fn (ConstantDefinition $constant): array => $constant->toArray(),
                $this->constants
            ),
            'properties'  => array_map(
                static fn (PropertyDefinition $property): array => $property->toArray(),
                $this->properties
            ),
            'methods'     => array_map(
                static fn (MethodDefinition $method): array => $method->toArray(),
                $this->methods
            ),
        ];
    }
}
