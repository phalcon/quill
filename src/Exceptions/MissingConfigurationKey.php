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

/**
 * A setting every run needs was absent or empty.
 */
class MissingConfigurationKey extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct(
            "quill configuration key '" . $key . "' is required and must be a non-empty string"
        );
    }
}
