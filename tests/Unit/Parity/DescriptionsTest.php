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

namespace Phalcon\Scribe\Tests\Unit\Parity;

use Phalcon\Scribe\Parity\Descriptions;
use PHPUnit\Framework\TestCase;

use function array_column;

final class DescriptionsTest extends TestCase
{
    public function testClassDescriptionsAreReportedWithNoMemberName(): void
    {
        $rows = (new Descriptions())->compare(
            ['A' => $this->definition('One thing.')],
            ['A' => $this->definition('Another thing.')]
        );

        $this->assertSame(
            [[
                'fqcn'   => 'A',
                'kind'   => 'class',
                'member' => '',
                'left'   => 'One thing.',
                'right'  => 'Another thing.',
            ]],
            $rows
        );
    }

    public function testEachSectionReportsItsOwnKind(): void
    {
        $rows = (new Descriptions())->compare(
            ['A' => $this->definition('', ['LIMIT' => 'a'], ['store' => 'a'], ['run' => 'a'])],
            ['A' => $this->definition('', ['LIMIT' => 'b'], ['store' => 'b'], ['run' => 'b'])]
        );

        $this->assertSame(['constant', 'method', 'property'], array_column($rows, 'kind'));
        $this->assertSame(['LIMIT', 'run', 'store'], array_column($rows, 'member'));
    }

    public function testIdenticalTextIsNotADifference(): void
    {
        $rows = (new Descriptions())->compare(
            ['A' => $this->definition('Same.', [], [], ['run' => 'Same.'])],
            ['A' => $this->definition('Same.', [], [], ['run' => 'Same.'])]
        );

        $this->assertSame([], $rows);
    }

    public function testMembersOnOneSideOnlyAreIgnored(): void
    {
        // Presence differences are structural, and reported elsewhere.
        $rows = (new Descriptions())->compare(
            ['A' => $this->definition('', [], [], ['only' => 'text'])],
            ['A' => $this->definition('', [], [], ['other' => 'text'])]
        );

        $this->assertSame([], $rows);
    }

    public function testDefinitionsOnOneSideOnlyAreIgnored(): void
    {
        $rows = (new Descriptions())->compare(
            ['A' => $this->definition('a')],
            ['B' => $this->definition('b')]
        );

        $this->assertSame([], $rows);
    }

    public function testABlankSideIsStillADifference(): void
    {
        $rows = (new Descriptions())->compare(
            ['A' => $this->definition('', [], [], ['run' => ''])],
            ['A' => $this->definition('', [], [], ['run' => 'Documented.'])]
        );

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['left']);
        $this->assertSame('Documented.', $rows[0]['right']);
    }

    public function testRowsAreOrderedForStableOutput(): void
    {
        $rows = (new Descriptions())->compare(
            [
                'Zulu'  => $this->definition('a'),
                'Alpha' => $this->definition('a'),
            ],
            [
                'Zulu'  => $this->definition('b'),
                'Alpha' => $this->definition('b'),
            ]
        );

        $this->assertSame(['Alpha', 'Zulu'], array_column($rows, 'fqcn'));
    }

    public function testMalformedInputIsSkipped(): void
    {
        $this->assertSame([], (new Descriptions())->compare(['A' => 'nonsense'], ['A' => null]));
    }

    /**
     * @param array<string, string> $constants
     * @param array<string, string> $properties
     * @param array<string, string> $methods
     *
     * @return array<string, mixed>
     */
    private function definition(
        string $description,
        array $constants = [],
        array $properties = [],
        array $methods = []
    ): array {
        return [
            'description' => $description,
            'members'     => [
                'constants'  => $this->named($constants),
                'properties' => $this->named($properties),
                'methods'    => $this->named($methods),
            ],
        ];
    }

    /**
     * @param array<string, string> $entries
     *
     * @return list<array{name: string, description: string}>
     */
    private function named(array $entries): array
    {
        $members = [];
        foreach ($entries as $name => $description) {
            $members[] = ['name' => $name, 'description' => $description];
        }

        return $members;
    }
}
