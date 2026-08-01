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
 * Turns the model into output. Every presentation decision - what to hide,
 * how to badge it, what to link - belongs on this side of the boundary.
 */
interface Formatter
{
    /**
     * The file extension the documents are written with, without the dot.
     */
    public function extension(): string;

    /**
     * The registry always covers every source file; `$filter` narrows only
     * what gets emitted, index included.
     *
     * @return array<string, string> document key => rendered document
     */
    public function format(Registry $registry, Config $config, string $filter = ''): array;
}
