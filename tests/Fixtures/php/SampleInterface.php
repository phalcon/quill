<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * An interface extending two others, which is the only declaration whose
 * `extends` is a list rather than a single name.
 */
interface SampleInterface extends Countable, Stringable
{
    public function describe(): string;
}
