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

namespace Phalcon\Quill\Reader;

use function addcslashes;
use function preg_replace;
use function trim;

/**
 * How the readers write down what they observe.
 *
 * Two readers must produce identical models for equivalent sources, and every
 * rule they both apply used to live twice - once per reader, kept in agreement
 * by comment rather than by code. They disagreed: a string default containing a
 * quote came out `"\""` from Zephir and `"""` from PHP, and parity reported it
 * as a difference between the two implementations.
 *
 * The rules live here so there is one place to change and nowhere to drift.
 */
final class Notation
{
    public const ARRAY_EMPTY  = '[]';
    public const ARRAY_FILLED = '[...]';

    public const NULL = 'null';

    public const TYPE_ARRAY  = 'array';
    public const TYPE_BOOL   = 'bool';
    public const TYPE_FLOAT  = 'float';
    public const TYPE_INT    = 'int';
    public const TYPE_MIXED  = 'mixed';
    public const TYPE_STRING = 'string';

    /**
     * A single-quoted character default, which only Zephir declares.
     */
    public static function char(string $value): string
    {
        return "'" . $value . "'";
    }

    public static function classConstant(string $class, string $name): string
    {
        return $class . '::' . $name;
    }

    /**
     * A string default whose escapes are already in the source form the model
     * carries - which is what Zephir's parser hands back, since it returns the
     * characters between the quotes rather than their value.
     */
    public static function escapedString(string $escaped): string
    {
        return '"' . $escaped . '"';
    }

    /**
     * Zephir cannot write a union, so a typed parameter defaulting to null is
     * how it says `?T`. Both readers render that as `T|null`. `mixed` already
     * admits null and is left alone.
     */
    public static function nullable(string $type): string
    {
        return $type === self::TYPE_MIXED ? $type : $type . '|null';
    }

    /**
     * A string default given as its value rather than its source text, which is
     * what php-parser hands back. The escapes go back on here so both readers
     * write the same thing.
     */
    public static function string(string $value): string
    {
        return self::escapedString(addcslashes($value, "\"\\"));
    }

    /**
     * A type as a docblock declared it, written the way the model carries one.
     *
     * A docblock is prose: `@var Foo | null` and `@var Foo|null` say the same
     * thing, and a reader that passes either through verbatim reports the
     * spacing as a difference between the two implementations. Every other type
     * in the model is built here, so a declared one is squared up to match.
     */
    public static function type(string $declared): string
    {
        return (string) preg_replace('/\s*([|&])\s*/', '$1', trim($declared));
    }

    /**
     * @return 'public'|'protected'|'private'
     */
    public static function visibility(bool $isPrivate, bool $isProtected): string
    {
        if ($isPrivate) {
            return 'private';
        }

        return $isProtected ? 'protected' : 'public';
    }
}
