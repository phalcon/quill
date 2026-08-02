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

namespace Phalcon\Quill\Model;

/**
 * One class, interface, trait or enum.
 *
 * Immutable, and deliberately unaware of any other definition: ancestors and
 * children are Registry's job, which keeps this object language-agnostic and
 * readonly.
 *
 * `description` stands alone because it is the only field here that is human
 * prose rather than a parsed fact.
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
     * 6 - grouped into location/imports/relations/members, mirroring the
     *     object graph.
     * 7 - properties carry isReadonly.
     */
    public const MODEL_VERSION = 7;

    public function __construct(
        public readonly Location $location,
        public readonly Structure $structure,
        public readonly string $description,
        public readonly Imports $imports,
        public readonly Relations $relations,
        public readonly Members $members,
    ) {
    }

    /**
     * The published serialization shape, mirroring the object graph. Each
     * group declares its own exact shape.
     *
     * @return array{
     *     version: int,
     *     location: array{fqcn: string, namespace: string, relPath: string},
     *     structure: array{keyword: string, isAbstract: bool|null, isFinal: bool|null},
     *     description: string,
     *     imports: array{uses: list<string>, aliases: array<string, string>},
     *     relations: array{extends: list<string>, implements: list<string>, traits: list<string>},
     *     members: array{
     *         constants: list<array<string, mixed>>,
     *         properties: list<array<string, mixed>>,
     *         methods: list<array<string, mixed>>
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'version'     => self::MODEL_VERSION,
            'location'    => $this->location->toArray(),
            'structure'   => $this->structure->toArray(),
            'description' => $this->description,
            'imports'     => $this->imports->toArray(),
            'relations'   => $this->relations->toArray(),
            'members'     => $this->members->toArray(),
        ];
    }
}
