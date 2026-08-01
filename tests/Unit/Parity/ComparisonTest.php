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

final class ComparisonTest extends TestCase
{
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

    public function testIdenticalDefinitionsDoNotDiffer(): void
    {
        $report = (new Comparison())->compare(
            ['A' => $this->definition(['one', 'two'])],
            ['A' => $this->definition(['two', 'one'])]
        );

        // Order is not a difference; membership is.
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

    public function testMalformedDefinitionsAreTreatedAsEmpty(): void
    {
        $report = (new Comparison())->compare(['A' => 'nonsense'], ['A' => null]);

        $this->assertSame([], $report['differing']);
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
