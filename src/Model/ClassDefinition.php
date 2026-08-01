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

/**
 * One class, interface, trait or enum.
 *
 * Immutable, and deliberately unaware of any other definition: resolving short
 * names to FQCNs, ancestors and children is Registry's job, which keeps this
 * object language-agnostic and readonly.
 */
final class ClassDefinition implements Definition
{
    /**
     * Bumped whenever toArray()'s shape changes. It is a published format the
     * moment anything downstream reads it.
     *
     * 2 - added `traits`.
     * 3 - `kind` renamed to `structure`; backing values unchanged.
     * 4 - `structure` nests keyword/isAbstract/isFinal, replacing the flat
     *     `structure`, `abstract` and `final` keys.
     * 5 - dropped `title`, `page` and `anchor`; they were Markdown output
     *     concerns, not facts about the declaration.
     */
    public const MODEL_VERSION = 5;

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
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $relPath,
        public readonly string $namespace,
        public readonly Structure $structure,
        public readonly string $description,
        public readonly array $uses,
        public readonly array $usesMap,
        public readonly array $extends,
        public readonly array $implements,
        public readonly array $traits,
        public readonly ConstantDefinitionCollection $constants,
        public readonly PropertyDefinitionCollection $properties,
        public readonly MethodDefinitionCollection $methods,
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
     *     relPath: string,
     *     namespace: string,
     *     structure: array{keyword: string, isAbstract: bool|null, isFinal: bool|null},
     *     description: string,
     *     uses: list<string>,
     *     usesMap: array<string, string>,
     *     extends: list<string>,
     *     implements: list<string>,
     *     traits: list<string>,
     *     constants: list<array<string, mixed>>,
     *     properties: list<array<string, mixed>>,
     *     methods: list<array<string, mixed>>
     * }
     *
     * The three member lists are declared loosely here; each entry's own
     * toArray() carries the exact shape.
     */
    public function toArray(): array
    {
        return [
            'version'     => self::MODEL_VERSION,
            'fqcn'        => $this->fqcn,
            'relPath'     => $this->relPath,
            'namespace'   => $this->namespace,
            'structure'   => $this->structure->toArray(),
            'description' => $this->description,
            'uses'        => $this->uses,
            'usesMap'     => $this->usesMap,
            'extends'     => $this->extends,
            'implements'  => $this->implements,
            'traits'      => $this->traits,
            'constants'   => $this->constants->toArray(),
            'properties'  => $this->properties->toArray(),
            'methods'     => $this->methods->toArray(),
        ];
    }
}
