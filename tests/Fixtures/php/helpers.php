<?php

declare(strict_types=1);

namespace Phalcon\Sample;

/**
 * A file with no declaration in it at all. The reader has to skip it rather
 * than record an empty definition.
 */
function helper(string $text): string
{
    return $text;
}
