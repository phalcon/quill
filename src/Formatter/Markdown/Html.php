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

namespace Phalcon\Scribe\Formatter\Markdown;

use function htmlspecialchars;
use function preg_replace;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * The small amount of raw HTML the Markdown pages carry, in one place.
 *
 * mkdocs renders these pages, but the API layout leans on inline markup for
 * the parts Markdown cannot express - highlight spans, badges, list rows.
 */
final class Html
{
    /**
     * A default-value expression as a muted highlight span, or empty.
     */
    public function default(?string $default): string
    {
        if ($default === null) {
            return '';
        }

        return '<span class="sm"> = ' . $this->escape($default) . '</span>';
    }

    public function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE);
    }

    /**
     * Escaped text with markdown backtick spans converted to <code>.
     */
    public function inlineCode(string $text): string
    {
        return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $this->escape($text));
    }
}
