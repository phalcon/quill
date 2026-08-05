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

use function implode;

class MissingTemplate extends Exception
{
    /**
     * @param list<string> $searched
     */
    public function __construct(string $name, array $searched)
    {
        parent::__construct(
            "The template '" . $name . "' was not found. Looked in: "
            . implode(', ', $searched) . '.'
            . ' The shipped set ships with quill, so a copy that cannot find'
            . ' it is incomplete rather than misconfigured.'
        );
    }
}
