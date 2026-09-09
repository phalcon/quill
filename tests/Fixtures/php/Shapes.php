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
     * `$plain` is not promoted, so it is a parameter and nothing more. The
     * rest are both parameters and properties, one of each visibility, with
     * and without readonly, and one with no declared type at all.
     */
    public function __construct(
        string $plain,
        public readonly int $open,
        protected string $guarded = 'none',
        private ?int $hidden = null,
        public $untypedPromoted = null
    ) {
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
