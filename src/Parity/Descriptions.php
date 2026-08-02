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

use Phalcon\Quill\Model\Document;
use Phalcon\Quill\Model\Members;

use function array_intersect;
use function array_keys;
use function is_array;
use function is_string;
use function usort;

/**
 * Finds documentation text that two implementations disagree about.
 *
 * Only definitions and members present on both sides are considered - anything
 * on one side alone is a structural difference, which Comparison reports.
 */
final class Descriptions
{
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

            $rows = [
                ...$rows,
                ...$this->classRows($fqcn, $leftClass, $rightClass),
                ...$this->memberRows($fqcn, $leftClass, $rightClass),
            ];
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => [$a['fqcn'], $a['kind'], $a['member']]
                <=> [$b['fqcn'], $b['kind'], $b['member']]
        );

        return $rows;
    }

    /**
     * The class's own description, as nought or one row.
     *
     * @param array<array-key, mixed> $leftClass
     * @param array<array-key, mixed> $rightClass
     *
     * @return list<array{fqcn: string, kind: string, member: string, left: string, right: string}>
     */
    private function classRows(string $fqcn, array $leftClass, array $rightClass): array
    {
        $leftText  = $this->text($leftClass, Document::DESCRIPTION);
        $rightText = $this->text($rightClass, Document::DESCRIPTION);

        if ($leftText === $rightText) {
            return [];
        }

        return [[
            'fqcn'   => $fqcn,
            'kind'   => 'class',
            'member' => '',
            'left'   => $leftText,
            'right'  => $rightText,
        ]];
    }

    /**
     * One row per member the two sides word differently. A member missing
     * from either side is a structural difference, not a wording one.
     *
     * @param array<array-key, mixed> $leftClass
     * @param array<array-key, mixed> $rightClass
     *
     * @return list<array{fqcn: string, kind: string, member: string, left: string, right: string}>
     */
    private function memberRows(string $fqcn, array $leftClass, array $rightClass): array
    {
        $rows = [];

        foreach (Members::SECTIONS as $section => $kind) {
            $rightMembers = $this->members($rightClass, $section);

            foreach ($this->members($leftClass, $section) as $name => $text) {
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

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $class
     *
     * @return array<string, string> member name => description
     */
    private function members(array $class, string $section): array
    {
        $named = [];
        foreach (MemberIndex::of($class, $section) as $name => $member) {
            $named[$name] = $this->text($member, Document::DESCRIPTION);
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
