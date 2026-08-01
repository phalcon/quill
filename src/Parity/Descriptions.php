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

use function array_intersect;
use function array_keys;
use function is_array;
use function is_string;
use function usort;

/**
 * Finds documentation text that two implementations disagree about.
 *
 * Only definitions and members that exist on both sides are considered -
 * anything present on one side alone is a structural difference, not a
 * wording one, and belongs to a different conversation.
 */
final class Descriptions
{
    private const SECTIONS = [
        'constants'  => 'constant',
        'properties' => 'property',
        'methods'    => 'method',
    ];

    /**
     * One row per disagreement, ordered so the result is stable between runs.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return list<array{fqcn: string, kind: string, member: string, left: string, right: string}>
     */
    public function compare(array $left, array $right): array
    {
        $rows = [];

        foreach (array_intersect(array_keys($left), array_keys($right)) as $fqcn) {
            $leftClass  = $left[$fqcn];
            $rightClass = $right[$fqcn];

            if (!is_array($leftClass) || !is_array($rightClass)) {
                continue;
            }

            $leftText  = $this->text($leftClass, 'description');
            $rightText = $this->text($rightClass, 'description');

            if ($leftText !== $rightText) {
                $rows[] = [
                    'fqcn'   => $fqcn,
                    'kind'   => 'class',
                    'member' => '',
                    'left'   => $leftText,
                    'right'  => $rightText,
                ];
            }

            foreach (self::SECTIONS as $section => $kind) {
                $leftMembers  = $this->members($leftClass, $section);
                $rightMembers = $this->members($rightClass, $section);

                foreach ($leftMembers as $name => $text) {
                    if (!isset($rightMembers[$name]) || $rightMembers[$name] === $text) {
                        continue;
                    }

                    $rows[] = [
                        'fqcn'   => $fqcn,
                        'kind'   => $kind,
                        'member' => $name,
                        'left'   => $text,
                        'right'  => $rightMembers[$name],
                    ];
                }
            }
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => [$a['fqcn'], $a['kind'], $a['member']]
                <=> [$b['fqcn'], $b['kind'], $b['member']]
        );

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $class
     *
     * @return array<string, string> member name => description
     */
    private function members(array $class, string $section): array
    {
        if (!is_array($class['members'] ?? null)) {
            return [];
        }

        $members = $class['members'][$section] ?? null;
        if (!is_array($members)) {
            return [];
        }

        $named = [];
        foreach ($members as $member) {
            if (!is_array($member) || !is_string($member['name'] ?? null)) {
                continue;
            }

            $named[$member['name']] = $this->text($member, 'description');
        }

        return $named;
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private function text(array $node, string $key): string
    {
        $value = $node[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
