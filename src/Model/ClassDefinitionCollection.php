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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_map;
use function array_values;
use function count;
use function ksort;

/**
 * Every definition read from one source tree, keyed by its FQCN.
 *
 * The member collections each own their own narrowing and ordering; this is
 * the same idea one level up, for the definitions themselves. Keying by FQCN
 * is this object's business alone - a reader hands over what it read and does
 * not need to know what the key is.
 *
 * Registry wraps one of these and answers the questions that need the whole
 * set at once, such as who extends whom.
 *
 * @implements IteratorAggregate<string, ClassDefinition>
 */
final class ClassDefinitionCollection implements Countable, IteratorAggregate
{
    /**
     * @param array<string, ClassDefinition> $definitions
     */
    private function __construct(private readonly array $definitions)
    {
    }

    /**
     * @param iterable<ClassDefinition> $classes
     */
    public static function fromDefinitions(iterable $classes): self
    {
        $definitions = [];
        foreach ($classes as $class) {
            $definitions[$class->location->fqcn] = $class;
        }

        return new self($definitions);
    }

    /**
     * @return array<string, ClassDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function count(): int
    {
        return count($this->definitions);
    }

    /**
     * @param callable(ClassDefinition, string): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        $kept = [];
        foreach ($this->definitions as $fqcn => $definition) {
            if ($predicate($definition, $fqcn)) {
                $kept[$fqcn] = $definition;
            }
        }

        return new self($kept);
    }

    public function get(string $fqcn): ?ClassDefinition
    {
        return $this->definitions[$fqcn] ?? null;
    }

    /**
     * @return Traversable<string, ClassDefinition>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->definitions);
    }

    public function has(string $fqcn): bool
    {
        return isset($this->definitions[$fqcn]);
    }

    public function isEmpty(): bool
    {
        return $this->definitions === [];
    }

    /**
     * Ordered by FQCN, which is what makes two runs comparable by eye.
     */
    public function sorted(): self
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return new self($definitions);
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
