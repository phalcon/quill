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

namespace Phalcon\Scribe\Parity;

use function array_diff;
use function array_intersect;
use function array_keys;
use function array_values;
use function is_array;
use function is_string;
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
    private const MEMBERS = ['constants', 'properties', 'methods'];

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

        foreach (self::MEMBERS as $section) {
            $leftNames  = $this->names($left, $section);
            $rightNames = $this->names($right, $section);

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

    /**
     * @return list<string>
     */
    private function names(mixed $definition, string $section): array
    {
        if (!is_array($definition) || !is_array($definition['members'] ?? null)) {
            return [];
        }

        $members = $definition['members'][$section] ?? null;
        if (!is_array($members)) {
            return [];
        }

        $names = [];
        foreach ($members as $member) {
            if (is_array($member) && is_string($member['name'] ?? null)) {
                $names[] = $member['name'];
            }
        }

        return $names;
    }
}
