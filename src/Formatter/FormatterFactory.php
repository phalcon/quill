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

namespace Phalcon\Quill\Formatter;

use Phalcon\Quill\Contracts\Formatter;
use Phalcon\Quill\Exceptions\UnknownFormat;

/**
 * Resolves a requested output format to its formatter.
 *
 * Format is a run parameter rather than project configuration - the same
 * sources are legitimately rendered as pages one moment and as a model
 * document the next.
 */
final class FormatterFactory
{
    private const KNOWN = ['json', 'markdown'];

    public function create(string $format): Formatter
    {
        return match ($format) {
            'json'     => new JsonFormatter(),
            'markdown' => new MarkdownFormatter(),
            default    => throw new UnknownFormat($format, self::KNOWN),
        };
    }
}
