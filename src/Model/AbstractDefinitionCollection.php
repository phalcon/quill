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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function usort;

/**
 * An immutable, ordered set of definitions.
 *
 * Every operation returns a new collection, so a formatter can narrow or
 * reorder a member list without disturbing the model it came from.
 *
 * Subclasses must not widen the constructor - filter() and sorted() rebuild
 * through `new static()` to preserve the concrete type.
 *
 * @template TDefinition of Definition
 *
 * @implements IteratorAggregate<int, TDefinition>
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractDefinitionCollection implements Countable, IteratorAggregate
{
    /** @var list<TDefinition> */
    protected readonly array $items;

    /**
     * @param list<TDefinition> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @return list<TDefinition>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @param callable(TDefinition): bool $predicate
     *
     * @return static
     */
    public function filter(callable $predicate): static
    {
        return new static(array_values(array_filter($this->items, $predicate)));
    }

    /**
     * @return Traversable<int, TDefinition>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @param callable(TDefinition, TDefinition): int $comparator
     *
     * @return static
     */
    public function sorted(callable $comparator): static
    {
        $items = $this->items;
        usort($items, $comparator);

        return new static($items);
    }

    /**
     * Each entry's own toArray() declares its exact shape; the aggregate is
     * deliberately looser so the base can serialize any collection.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (Definition $definition): array => $definition->toArray(),
            $this->items
        );
    }
}
