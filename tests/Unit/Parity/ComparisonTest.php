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

namespace Phalcon\Quill\Tests\Unit\Parity;

use Phalcon\Quill\Parity\Comparison;
use PHPUnit\Framework\TestCase;

use function array_keys;

final class ComparisonTest extends TestCase
{
    /**
     * A section is reported when either side has something the other lacks,
     * not only when both do.
     */
    public function testASectionIsReportedWhenOnlyOneSideDiffers(): void
    {
        $onlyMissing = (new Comparison())->compare(
            ['A' => $this->definition(['shared', 'gone'])],
            ['A' => $this->definition(['shared'])]
        );

        $this->assertSame(['gone'], $onlyMissing['differing']['A']['methods']['left']);
        $this->assertSame([], $onlyMissing['differing']['A']['methods']['right']);

        $onlyExtra = (new Comparison())->compare(
            ['A' => $this->definition(['shared'])],
            ['A' => $this->definition(['shared', 'added'])]
        );

        $this->assertSame([], $onlyExtra['differing']['A']['methods']['left']);
        $this->assertSame(['added'], $onlyExtra['differing']['A']['methods']['right']);
    }

    public function testCountsAndExclusiveNames(): void
    {
        $report = (new Comparison())->compare(
            ['A' => $this->definition(), 'B' => $this->definition()],
            ['B' => $this->definition(), 'C' => $this->definition()]
        );

        $this->assertSame(2, $report['leftCount']);
        $this->assertSame(2, $report['rightCount']);
        $this->assertSame(1, $report['common']);
        $this->assertSame(['A'], $report['leftOnly']);
        $this->assertSame(['C'], $report['rightOnly']);
    }

    public function testDifferingClassesComeOutInSortedOrder(): void
    {
        $report = (new Comparison())->compare(
            [
                'Zulu'  => $this->definition(['gone']),
                'Alpha' => $this->definition(['gone']),
                'Mike'  => $this->definition(['gone']),
            ],
            [
                'Zulu'  => $this->definition([]),
                'Alpha' => $this->definition([]),
                'Mike'  => $this->definition([]),
            ]
        );

        $this->assertSame(['Alpha', 'Mike', 'Zulu'], array_keys($report['differing']));
    }

    /**
     * Every section that differs is reported, not just the first one found.
     */
    public function testEverySectionThatDiffersIsReported(): void
    {
        $left  = ['members' => [
            'constants'  => [['name' => 'A_CONST']],
            'properties' => [['name' => 'aProperty']],
            'methods'    => [['name' => 'aMethod']],
        ]];
        $right = ['members' => ['constants' => [], 'properties' => [], 'methods' => []]];

        $sections = (new Comparison())->compare(['A' => $left], ['A' => $right])['differing']['A'];

        $this->assertSame(['constants', 'properties', 'methods'], array_keys($sections));
        $this->assertSame(['A_CONST'], $sections['constants']['left']);
        $this->assertSame(['aProperty'], $sections['properties']['left']);
        $this->assertSame(['aMethod'], $sections['methods']['left']);
    }

    public function testIdenticalDefinitionsDoNotDiffer(): void
    {
        $report = (new Comparison())->compare(
            ['A' => $this->definition(['one', 'two'])],
            ['A' => $this->definition(['two', 'one'])]
        );

        // Order is not a difference; membership is.
        $this->assertSame([], $report['differing']);
    }

    public function testMalformedDefinitionsAreTreatedAsEmpty(): void
    {
        $report = (new Comparison())->compare(['A' => 'nonsense'], ['A' => null]);

        $this->assertSame([], $report['differing']);
    }

    public function testMemberDifferencesAreReportedPerSection(): void
    {
        $report = (new Comparison())->compare(
            ['A' => $this->definition(['one', 'two'])],
            ['A' => $this->definition(['two', 'three'])]
        );

        $this->assertSame(
            ['methods' => ['left' => ['one'], 'right' => ['three']]],
            $report['differing']['A']
        );
    }

    public function testMissingAndExtraMembersAreSortedAndReindexed(): void
    {
        $report = (new Comparison())->compare(
            ['A' => $this->definition(['zulu', 'alpha', 'shared'])],
            ['A' => $this->definition(['shared', 'yankee', 'bravo'])]
        );

        $methods = $report['differing']['A']['methods'];

        $this->assertSame(['alpha', 'zulu'], $methods['left']);
        $this->assertSame(['bravo', 'yankee'], $methods['right']);
        $this->assertSame([0, 1], array_keys($methods['left']));
        $this->assertSame([0, 1], array_keys($methods['right']));
    }

    /**
     * The report is read by eye and diffed between runs, so the one-sided
     * lists are sorted and re-keyed from zero however the definitions arrived.
     */
    public function testOneSidedListsAreSortedAndReindexed(): void
    {
        $report = (new Comparison())->compare(
            [
                'Zulu'   => $this->definition(),
                'Alpha'  => $this->definition(),
                'Shared' => $this->definition(),
            ],
            [
                'Shared' => $this->definition(),
                'Yankee' => $this->definition(),
                'Bravo'  => $this->definition(),
            ]
        );

        $this->assertSame(['Alpha', 'Zulu'], $report['leftOnly']);
        $this->assertSame(['Bravo', 'Yankee'], $report['rightOnly']);
        $this->assertSame([0, 1], array_keys($report['leftOnly']));
        $this->assertSame([0, 1], array_keys($report['rightOnly']));
        // `common` is sorted too, and it drives the order of `differing`.
        $this->assertSame(3, $report['leftCount']);
        $this->assertSame(3, $report['rightCount']);
        $this->assertSame(1, $report['common']);
    }

    /**
     * @param list<string> $methods
     *
     * @return array<string, mixed>
     */
    private function definition(array $methods = []): array
    {
        $named = [];
        foreach ($methods as $name) {
            $named[] = ['name' => $name];
        }

        return [
            'members' => [
                'constants'  => [],
                'properties' => [],
                'methods'    => $named,
            ],
        ];
    }
}
