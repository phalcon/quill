<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * Every constant kind the type guesser has to name, and every parameter shape
 * the type and value renderers have to render.
 */
class Shapes
{
    public const A_STRING = 'text';
    public const AN_INT = 1;
    public const A_FLOAT = 1.5;
    public const AN_ARRAY = [];
    public const A_FILLED_ARRAY = [1, 2];
    public const A_BOOL = true;
    public const A_NULL = null;
    public const A_CLASS_CONST = self::A_STRING;
    public const A_NEGATIVE = -3;
    public const AN_EXPRESSION = 1 + 2;

    /**
     * `$plain` is not promoted, so it is a parameter and nothing more, while
     * `$kept` is both a parameter and a property.
     */
    public function __construct(string $plain, protected int $kept = 0)
    {
    }

    public function unionAndNullable(
        int | string $either,
        ?Shapes $maybe = null,
        $untyped = 1,
        float $rate = 2.5
    ): int | string | null {
        return $either;
    }

    public function intersection(Shapes & Countable $both): void
    {
    }

    public function defaults(
        int $count = -7,
        string $mode = self::A_STRING,
        array $filled = [1],
        mixed $computed = 1 + 2
    ): void {
    }
}
