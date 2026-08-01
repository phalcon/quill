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

namespace Phalcon\Scribe\Reader\Zephir;

use function is_array;
use function is_scalar;
use function is_string;

/**
 * One node of the parser's output, read safely.
 *
 * The parser hands back nested untyped arrays. Wrapping them keeps the
 * narrowing in one place instead of spreading `is_string($x['y'] ?? null)`
 * through the reader, and lets a node be passed around as a value rather than
 * as an array plus the key you need from it.
 */
final class AstNode
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * Wraps a list of raw nodes, dropping anything that is not an array.
     *
     * @param array<array-key, mixed> $items
     *
     * @return list<self>
     */
    public static function listFrom(array $items): array
    {
        $nodes = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $nodes[] = new self($item);
            }
        }

        return $nodes;
    }

    /**
     * True when the key holds the integer 1 - how the parser records
     * `abstract`, `final`, `void` and `collection`.
     */
    public function flag(string $key): bool
    {
        return ($this->data[$key] ?? 0) === 1;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * True when the key holds exactly this string.
     */
    public function is(string $key, string $value): bool
    {
        return ($this->data[$key] ?? null) === $value;
    }

    /**
     * A nested node, or null when absent or empty.
     */
    public function node(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) && $value !== [] ? new self($value) : null;
    }

    /**
     * A list-shaped child, wrapped.
     *
     * @return list<self>
     */
    public function section(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? self::listFrom($value) : [];
    }

    /**
     * A list-shaped child of plain strings, such as a visibility list.
     *
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * A scalar coerced to string - empty when absent or non-scalar.
     */
    public function stringValue(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A string, or null when the key is absent or holds something else.
     */
    public function text(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
