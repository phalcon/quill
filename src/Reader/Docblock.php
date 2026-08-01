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

namespace Phalcon\Scribe\Reader;

use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function substr;
use function trim;

/**
 * One raw comment, cleaned once and then asked for what it holds.
 *
 * Tags are stripped from the description rather than parsed. When
 * `@deprecated` or `@since` need surfacing, this is where they arrive.
 */
final class Docblock
{
    private readonly string $text;

    public function __construct(?string $raw)
    {
        $this->text = $this->clean($raw);
    }

    /**
     * Prose only: the tag block is removed, examples and informational tags
     * such as `@todo` are kept.
     */
    public function description(): string
    {
        $output   = [];
        $skipping = false;
        foreach (explode("\n", $this->text) as $line) {
            $trimmed = trim($line);

            // Multi-line tags are swallowed up to the next blank line.
            if ($skipping) {
                if ($trimmed === '') {
                    $skipping = false;
                }

                continue;
            }

            if (preg_match('/^@(param|return|throws|var|deprecated|phpstan-\S+|psalm-\S+)\b/', $trimmed) === 1) {
                $skipping = true;

                continue;
            }

            $output[] = $line;
        }

        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $output));

        return trim($text, "\n");
    }

    /**
     * The declared `@var` type, or null when the comment does not carry one.
     */
    public function varType(): ?string
    {
        if (preg_match('/^@var\s+(.+)$/m', $this->text, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Strips the comment decoration, leaving clean text lines.
     */
    private function clean(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        /**
         * Parsers disagree about the delimiters. php-parser returns the whole
         * comment including `/**` and the closing `*​/`; Zephir's has already
         * removed the leading slash. Strip both forms here so the line loop
         * below only ever sees content.
         */
        $raw = (string) preg_replace('#^\s*/?\*\*#', '', $raw);
        $raw = (string) preg_replace('#\*/\s*$#', '', $raw);

        $output = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '**' || $line === '*' || $line === '*/') {
                $output[] = '';

                continue;
            }
            if (str_starts_with($line, '* ')) {
                $output[] = substr($line, 2);

                continue;
            }
            if (str_starts_with($line, '*')) {
                $output[] = ltrim(substr($line, 1));

                continue;
            }

            $output[] = $line;
        }

        return trim(implode("\n", $output), "\n");
    }
}
