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

class UnknownLanguage extends Exception
{
    /**
     * @param list<string> $known
     */
    public function __construct(string $language, array $known)
    {
        parent::__construct(
            "Unknown language '" . $language . "'; known languages are: " . implode(', ', $known)
        );
    }
}
