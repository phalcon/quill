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
 * The configuration file a run was pointed at does not exist.
 */
class MissingConfiguration extends Exception
{
    public function __construct(string $path)
    {
        parent::__construct(
            "quill configuration: no such file '" . $path . "'"
        );
    }
}
