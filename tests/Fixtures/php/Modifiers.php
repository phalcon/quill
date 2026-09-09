<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * An abstract class with one abstract method and one final method, so the
 * reader has a declaration that carries each method modifier.
 */
abstract class Modifiers
{
    abstract public function shape(): string;

    final public function sealed(): string
    {
        return 'sealed';
    }
}
