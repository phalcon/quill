<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * Extends and implements names written absolutely. The leading backslash is
 * the only thing saying they are global rather than `Phalcon\Sample\...`, so
 * it has to survive into the model.
 */
class GlobalChild extends \Exception implements \Countable
{
    public function count(): int
    {
        return 0;
    }
}
