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

class NamespaceNotFound extends Exception
{
    public function __construct(string $namespace)
    {
        parent::__construct(
            "No definitions were found in '" . $namespace . "'. Nothing was"
            . ' written; check the namespace against the source tree.'
        );
    }
}
