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

use function array_unshift;
use function ltrim;
use function str_starts_with;

/**
 * The cross-class questions a formatter needs answered, over one source tree.
 *
 * Holding the definitions is ClassDefinitionCollection's job; this is the
 * graph across them - who extends whom, who uses which trait, and what a short
 * name refers to. Both indexes are derived once, on construction.
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
     * `$rootNamespace` is the last candidate short-name resolution tries, for a
     * name that reached resolve() unqualified.
     */
    public function __construct(
        private readonly ClassDefinitionCollection $definitions,
        private readonly string $rootNamespace = '',
    ) {
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
            // One lookup answers both questions: whether the parent is in this
            // registry at all, and what to walk to next if it is.
            $parent = $fqcn === null ? null : $this->definitions->get($fqcn);

            array_unshift($chain, [
                'display' => $fqcn ?? $name,
                'fqcn'    => $parent === null ? null : $fqcn,
            ]);

            if ($parent === null || isset($seen[$fqcn])) {
                break;
            }

            $seen[$fqcn] = true;
            $name        = $parent->relations->extends[0] ?? null;
            $fqcn        = $name === null ? null : $this->resolve($name, $parent);
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

    public function definitions(): ClassDefinitionCollection
    {
        return $this->definitions;
    }

    public function get(string $fqcn): ?ClassDefinition
    {
        return $this->definitions->get($fqcn);
    }

    public function has(string $fqcn): bool
    {
        return $this->definitions->has($fqcn);
    }

    public function parentOf(ClassDefinition $class): ?string
    {
        $name = $class->relations->extends[0] ?? null;
        if ($name === null) {
            return null;
        }

        $parent = $this->resolve($name, $class);

        return $parent !== null && $this->definitions->has($parent) ? $parent : null;
    }

    /**
     * A referenced name => the FQCN it names here, or null when it names
     * nothing this registry holds.
     *
     * The readers resolve as they read, so every name arriving from one takes
     * the absolute branch. The candidate list below is the fallback for a name
     * that reached this method unresolved.
     */
    public function resolve(string $name, ClassDefinition $context): ?string
    {
        if (str_starts_with($name, '\\')) {
            $absolute = ltrim($name, '\\');

            return $this->definitions->has($absolute) ? $absolute : null;
        }

        $candidates = [
            $context->imports->aliases[$name] ?? null,
            $context->location->namespace . '\\' . $name,
            $this->rootNamespace === '' ? null : $this->rootNamespace . '\\' . $name,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $this->definitions->has($candidate)) {
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
}
