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

class UnknownFormat extends UnknownChoice
{
    /**
     * @param list<string> $known
     */
    public function __construct(string $format, array $known)
    {
        parent::__construct('format', $format, $known);
    }
}
