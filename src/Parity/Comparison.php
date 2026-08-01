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

namespace Phalcon\Quill\Parity;

use Phalcon\Quill\Model\Members;

use function array_diff;
use function array_intersect;
use function array_keys;
use function array_values;
use function sort;

/**
 * Compares two serialized models.
 *
 * cphalcon and phalcon are meant to expose the same surface. This reports
 * where they do not: definitions present on one side only, and per-definition
 * member differences for the ones they share.
 */
final class Comparison
{
    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array{
     *     leftCount: int,
     *     rightCount: int,
     *     common: int,
     *     leftOnly: list<string>,
     *     rightOnly: list<string>,
     *     differing: array<string, array<string, array{left: list<string>, right: list<string>}>>
     * }
     */
    public function compare(array $left, array $right): array
    {
        $leftKeys  = array_keys($left);
        $rightKeys = array_keys($right);

        $leftOnly  = array_values(array_diff($leftKeys, $rightKeys));
        $rightOnly = array_values(array_diff($rightKeys, $leftKeys));
        $common    = array_values(array_intersect($leftKeys, $rightKeys));

        sort($leftOnly);
        sort($rightOnly);
        sort($common);

        $differing = [];
        foreach ($common as $fqcn) {
            $sections = $this->compareMembers($left[$fqcn] ?? null, $right[$fqcn] ?? null);
            if ($sections !== []) {
                $differing[$fqcn] = $sections;
            }
        }

        return [
            'leftCount'  => count($leftKeys),
            'rightCount' => count($rightKeys),
            'common'     => count($common),
            'leftOnly'   => $leftOnly,
            'rightOnly'  => $rightOnly,
            'differing'  => $differing,
        ];
    }

    /**
     * @return array<string, array{left: list<string>, right: list<string>}>
     */
    private function compareMembers(mixed $left, mixed $right): array
    {
        $sections = [];

        foreach (array_keys(Members::SECTIONS) as $section) {
            $leftNames  = array_keys(MemberIndex::of($left, $section));
            $rightNames = array_keys(MemberIndex::of($right, $section));

            $missing = array_values(array_diff($leftNames, $rightNames));
            $extra   = array_values(array_diff($rightNames, $leftNames));

            if ($missing === [] && $extra === []) {
                continue;
            }

            sort($missing);
            sort($extra);

            $sections[$section] = ['left' => $missing, 'right' => $extra];
        }

        return $sections;
    }
}
