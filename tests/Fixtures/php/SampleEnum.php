<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * A backed enum, whose cases the reader records as constants.
 */
enum SampleEnum: string implements Countable
{
    /**
     * The strict mode.
     */
    case Strict = 'strict';

    case Loose = 'loose';

    public function count(): int
    {
        return 1;
    }
}
