<?php

/**
 * This file is part of the Phalcon Quill.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Quill\Exceptions;

class IncompatibleDocument extends Exception
{
    public function __construct(string $path, ?int $found, int $expected)
    {
        $declared = $found === null ? 'declares no version' : 'is version ' . $found;

        parent::__construct(
            "'" . $path . "' " . $declared . ', but this copy of quill reads'
            . ' version ' . $expected . '. Comparing across versions reports'
            . ' shape changes as differences between implementations, so'
            . ' regenerate the document with the quill that reads it.'
        );
    }
}
