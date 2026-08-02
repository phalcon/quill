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

namespace Phalcon\Quill\Model;

/**
 * The keyword a declaration opens with - which of PHP's four class-like
 * declarations this is.
 *
 * Not called a "type": the model already uses `type` for parameter, return and
 * property types.
 *
 * `ClassType` carries a suffix because PHP reserves `Class` as a constant name
 * for `::class` fetching. The other three need no such workaround.
 */
enum Keyword: string
{
    case ClassType = 'class';
    case Enum      = 'enum';
    case Interface = 'interface';
    case Trait     = 'trait';
}
