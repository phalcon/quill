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

namespace Phalcon\Quill\Formatter\Markdown;

use function array_map;
use function array_pop;
use function count;
use function explode;
use function implode;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function sort;
use function str_replace;
use function strtolower;
use function substr;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Prose made safe for MDX.
 *
 * MDX reads a brace as the start of an expression and `<Word>` as a
 * component, so two things that are ordinary in a docblock stop the page from
 * compiling: the phpDoc inline tags (`{@see method()}`) and the generic
 * annotations (`@extends AbstractLocator<Access>`). The generics cannot be
 * removed from the sources - the static analyzer needs them.
 *
 * Code keeps its own rules. A fenced block and an inline span are put aside
 * first and put back last, so a PHP example that opens a brace stays the
 * example the docblock wrote.
 */
final class Mdx
{
    /**
     * The HTML a description may legitimately carry. Everything after a `<`
     * that is not one of these is text about a tag, not a tag.
     */
    private const TAGS = 'a|b|br|code|em|hr|i|img|li|ol|p|pre|strong|sub|sup|table|td|th|tr|ul';

    public static function safe(string $text): string
    {
        $blocks = [];

        $stashed = (string) preg_replace_callback(
            '/```.*?```|`[^`\n]+`/s',
            /** @param array<int, string> $match */
            static function (array $match) use (&$blocks): string {
                $blocks[] = $match[0];

                return "\x00" . (count($blocks) - 1) . "\x00";
            },
            $text
        );

        $stashed = (string) preg_replace(
            '#<(?!/?(?:' . self::TAGS . ')[\s/>])#i',
            '&lt;',
            $stashed
        );

        // MDX ends an element at the paragraph, so a tag opened in one and
        // never closed there is prose about the tag, not markup.
        $stashed = implode(
            "\n\n",
            array_map(
                static fn (string $paragraph): string => self::bareTags($paragraph),
                explode("\n\n", $stashed)
            )
        );

        $stashed = str_replace(['{', '}'], ['\\{', '\\}'], $stashed);

        return (string) preg_replace_callback(
            '/\x00(\d+)\x00/',
            /** @param array<int, string> $match */
            static fn (array $match): string => $blocks[(int) $match[1]],
            $stashed
        );
    }

    /**
     * One paragraph with the `<` of every unclosed opener escaped.
     *
     * A closing tag cancels the nearest opener of its name. What is left open
     * at the end of the paragraph was never markup. A self-closing tag closes
     * itself and is skipped.
     */
    private static function bareTags(string $paragraph): string
    {
        preg_match_all(
            '#<(/?)(' . self::TAGS . ')\b[^>]*?(/?)>#i',
            $paragraph,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        $open   = [];
        $orphan = [];
        foreach ($matches as $match) {
            $tag = strtolower($match[2][0]);

            if ($match[1][0] === '/') {
                if (($open[$tag] ?? []) !== []) {
                    array_pop($open[$tag]);
                } else {
                    $orphan[] = (int) $match[0][1];
                }

                continue;
            }

            if ($match[3][0] === '/') {
                continue;
            }

            $open[$tag][] = (int) $match[0][1];
        }

        $offsets = $orphan;
        foreach ($open as $positions) {
            foreach ($positions as $position) {
                $offsets[] = $position;
            }
        }

        sort($offsets);

        $out    = '';
        $cursor = 0;
        foreach ($offsets as $offset) {
            $out   .= substr($paragraph, $cursor, $offset - $cursor) . '&lt;';
            $cursor = $offset + 1;
        }

        return $out . substr($paragraph, $cursor);
    }
}
