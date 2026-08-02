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

/**
 * Something was asked for by name and the name is not one of the known set.
 *
 * Subclassed rather than thrown directly: a caller resolving a format and a
 * caller resolving a language want to catch different failures, so the two stay
 * distinct types over one message.
 */
abstract class UnknownChoice extends Exception
{
    /**
     * @param string       $noun     what was being chosen, singular and lower case
     * @param list<string> $known    every accepted value
     */
    public function __construct(string $noun, string $value, array $known)
    {
        parent::__construct(
            'Unknown ' . $noun . " '" . $value . "'; known " . $noun . 's are: '
            . implode(', ', $known)
        );
    }
}
