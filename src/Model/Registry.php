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

use function array_map;
use function array_unshift;
use function array_values;
use function ltrim;
use function str_starts_with;

/**
 * Every definition read from one source tree, keyed by FQCN, plus the
 * cross-class questions a formatter needs answered.
 *
 * Resolution lives here rather than on ClassDefinition so definitions stay
 * immutable - and because none of it is language-specific, so a second reader
 * inherits the lot.
 */
final class Registry
{
    /** @var array<string, list<string>> */
    private array $children = [];

    /** @var array<string, list<string>> trait FQCN => the FQCNs pulling it in */
    private array $usedBy = [];

    /**
     * @param array<string, ClassDefinition> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
        foreach ($this->definitions as $fqcn => $definition) {
            $parent = $this->parentOf($definition);
            if ($parent !== null) {
                $this->children[$parent][] = $fqcn;
            }

            foreach ($definition->relations->traits as $name) {
                $trait = $this->resolve($name, $definition);
                if ($trait !== null) {
                    $this->usedBy[$trait][] = $fqcn;
                }
            }
        }
    }

    /**
     * @return array<string, ClassDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * The extends chain, root first. `display` is the FQCN when it resolved
     * and the raw short name when it did not; `fqcn` is null for anything
     * outside this registry, which is what stops the walk.
     *
     * @return list<array{display: string, fqcn: string|null}>
     */
    public function ancestorsOf(ClassDefinition $class): array
    {
        $chain = [];
        $seen  = [$class->location->fqcn => true];
        $name  = $class->relations->extends[0] ?? null;
        $fqcn  = $name === null ? null : $this->resolve($name, $class);

        while ($name !== null) {
            $entry = [
                'display' => $fqcn ?? $name,
                'fqcn'    => $fqcn !== null && isset($this->definitions[$fqcn]) ? $fqcn : null,
            ];
            array_unshift($chain, $entry);

            if ($entry['fqcn'] === null || isset($seen[$entry['fqcn']])) {
                break;
            }

            $seen[$entry['fqcn']] = true;

            $parent = $this->definitions[$entry['fqcn']];
            $name   = $parent->relations->extends[0] ?? null;
            $fqcn   = $name === null ? null : $this->resolve($name, $parent);
        }

        return $chain;
    }

    /**
     * Direct subclasses, in registry order. Callers that present them sort
     * first.
     *
     * @return list<string>
     */
    public function childrenOf(ClassDefinition $class): array
    {
        return $this->children[$class->location->fqcn] ?? [];
    }

    public function get(string $fqcn): ?ClassDefinition
    {
        return $this->definitions[$fqcn] ?? null;
    }

    public function has(string $fqcn): bool
    {
        return isset($this->definitions[$fqcn]);
    }

    public function parentOf(ClassDefinition $class): ?string
    {
        $name = $class->relations->extends[0] ?? null;
        if ($name === null) {
            return null;
        }

        $parent = $this->resolve($name, $class);

        return $parent !== null && isset($this->definitions[$parent]) ? $parent : null;
    }

    /**
     * Short name => FQCN, using the class's `use` map, then its own namespace,
     * then the Phalcon root. A leading backslash means absolute and skips the
     * candidate list entirely.
     */
    public function resolve(string $name, ClassDefinition $context): ?string
    {
        if (str_starts_with($name, '\\')) {
            $absolute = ltrim($name, '\\');

            return isset($this->definitions[$absolute]) ? $absolute : null;
        }

        $candidates = [
            $context->imports->aliases[$name] ?? null,
            $context->location->namespace . '\\' . $name,
            'Phalcon\\' . $name,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && isset($this->definitions[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The classes that pull this trait in - the inverse of ClassDefinition's
     * `traits`. Empty for anything that is not a used trait.
     *
     * @return list<string>
     */
    public function usedBy(ClassDefinition $class): array
    {
        return $this->usedBy[$class->location->fqcn] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (ClassDefinition $definition): array => $definition->toArray(),
            array_values($this->definitions)
        );
    }
}
