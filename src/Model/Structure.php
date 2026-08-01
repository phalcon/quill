<?php

/**
 * This file is part of the Phalcon Scribe.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Scribe\Model;

/**
 * Which of PHP's four class-like declarations a definition is.
 *
 * Not a "type": a trait is never an object and an interface cannot be
 * instantiated, and the model already uses `type` for parameter, return and
 * property types.
 *
 * `ClassType` carries a suffix because PHP reserves `Class` as a constant name
 * for `::class` fetching. The other three need no such workaround.
 */
enum Structure: string
{
    case ClassType = 'class';
    case Enum      = 'enum';
    case Interface = 'interface';
    case Trait     = 'trait';
}
