<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * A backed enum, whose cases the reader records as constants.
 */
enum SampleEnum: string implements Countable
{
    case Loose = 'loose';

    /**
     * The strict mode.
     */
    case Strict = 'strict';

    public function count(): int
    {
        return 1;
    }
}
