<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * Sample fixture.
 *
 * Exercises the constructs the PHP reader has to cover.
 *
 * @property string $unused
 */
final class Sample extends Base implements Countable
{
    use SampleTrait;

    public const DEFAULT_MODE = 'strict';

    protected array $store = [];

    private ?string $hidden = null;

    public function __construct(
        public readonly int $id,
        protected string $label = 'none',
    ) {
    }

    public static function toLower(string $text, bool $trim = true): ?string
    {
        return $text;
    }

    private function secret(): void
    {
    }
}
