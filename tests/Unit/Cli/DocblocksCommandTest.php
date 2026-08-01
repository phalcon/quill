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

namespace Phalcon\Scribe\Tests\Unit\Cli;

use Phalcon\Scribe\Cli\DocblocksCommand;
use Phalcon\Scribe\Exceptions\InvalidConfiguration;
use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function fgetcsv;
use function file_exists;
use function file_put_contents;
use function fopen;
use function is_dir;
use function json_encode;
use function mkdir;
use function rewind;
use function stream_get_contents;
use function unlink;

final class DocblocksCommandTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = dirname(__DIR__, 2) . '/_output/docblocks';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (['left.json', 'right.json', 'out.csv'] as $name) {
            if (file_exists($this->dir . '/' . $name)) {
                unlink($this->dir . '/' . $name);
            }
        }

        parent::tearDown();
    }

    public function testHeadersComeFromTheRepositoryNames(): void
    {
        $rows = $this->rows();

        $this->assertSame(
            ['fqcn', 'kind', 'member', 'cphalcon', 'phalcon', 'winner'],
            $rows[0]
        );
    }

    public function testABlankSideIsDecidedAlready(): void
    {
        $rows = $this->rows();

        // Row for `documented`: blank on the left, so the right wins.
        $this->assertSame('documented', $rows[1][2]);
        $this->assertSame('', $rows[1][3]);
        $this->assertSame('p', $rows[1][5]);
    }

    public function testTwoTextsAreLeftForAHumanToDecide(): void
    {
        $rows = $this->rows();

        $this->assertSame('worded', $rows[2][2]);
        $this->assertSame('One wording.', $rows[2][3]);
        $this->assertSame('Another wording.', $rows[2][4]);
        $this->assertSame('', $rows[2][5]);
    }

    public function testReportsTheSplitBetweenDecidedAndUndecided(): void
    {
        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        $this->write();
        (new DocblocksCommand($stdout))->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );

        rewind($stdout);
        $output = (string) stream_get_contents($stdout);

        $this->assertStringContainsString('2 differences', $output);
        $this->assertStringContainsString('1 pre-filled', $output);
        $this->assertStringContainsString('1 to decide', $output);
        $this->assertStringContainsString('Put c or p', $output);
    }

    public function testAMissingDocumentIsRejected(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('no such model document');

        (new DocblocksCommand())->execute('/nowhere/a.json', '/nowhere/b.json', $this->dir . '/out.csv');
    }

    /**
     * @return list<list<string>>
     */
    private function rows(): array
    {
        $this->write();

        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        (new DocblocksCommand($stdout))->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );

        $handle = fopen($this->dir . '/out.csv', 'rb');
        $this->assertIsResource($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            /** @var list<string> $row */
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function write(): void
    {
        file_put_contents($this->dir . '/left.json', json_encode([
            'repository'  => 'phalcon/cphalcon',
            'definitions' => ['A' => $this->definition('', 'One wording.')],
        ]));

        file_put_contents($this->dir . '/right.json', json_encode([
            'repository'  => 'phalcon/phalcon',
            'definitions' => ['A' => $this->definition('Documented.', 'Another wording.')],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $documented, string $worded): array
    {
        return [
            'description' => 'Same class text.',
            'members'     => [
                'constants'  => [],
                'properties' => [],
                'methods'    => [
                    ['name' => 'documented', 'description' => $documented],
                    ['name' => 'worded', 'description' => $worded],
                ],
            ],
        ];
    }
}
