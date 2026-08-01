<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * A trait, so the reader has one of every declaration keyword to classify.
 */
trait Marker
{
    public function mark(): string
    {
        return 'marked';
    }
}
