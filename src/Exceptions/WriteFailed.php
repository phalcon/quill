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

class WriteFailed extends Exception
{
    public function __construct(string $path)
    {
        parent::__construct(
            "Could not write '" . $path . "'. Check the destination is writable"
            . ' by the user running scribe.'
        );
    }
}
