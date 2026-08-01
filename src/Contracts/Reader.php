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

namespace Phalcon\Quill\Contracts;

use Phalcon\Quill\Config;
use Phalcon\Quill\Model\Registry;

/**
 * Turns a source tree into the model. Everything language-specific stops
 * here - what comes out carries no trace of the syntax it was read from.
 */
interface Reader
{
    public function read(Config $config): Registry;
}
