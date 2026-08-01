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

namespace Phalcon\Quill\Tests\Unit\Cli;

use Phalcon\Quill\Cli\DocblocksCommand;
use Phalcon\Quill\Exceptions\IncompatibleDocument;
use Phalcon\Quill\Exceptions\MissingDocument;
use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Model\ClassDefinition;
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

    public function testABlankSideIsDecidedAlready(): void
    {
        $rows = $this->rows();

        // Row for `documented`: blank on the left, so the right wins.
        $this->assertSame('documented', $rows[1][2]);
        $this->assertSame('', $rows[1][3]);
        $this->assertSame('p', $rows[1][5]);
    }

    public function testACsvThatCannotBeOpenedIsReported(): void
    {
        $this->write();

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessage('writable');

        (new DocblocksCommand())->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/no/such/directory/out.csv'
        );
    }

    /**
     * A document from another version describes the same facts in a different
     * shape. Comparing anyway reports those moves as disagreements between the
     * two implementations, so it has to stop at the door.
     */
    public function testADocumentFromAnotherVersionIsRejected(): void
    {
        $this->write();
        file_put_contents($this->dir . '/left.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION - 1,
            'repository'  => 'phalcon/cphalcon',
            'definitions' => [],
        ]));

        $this->expectException(IncompatibleDocument::class);
        $this->expectExceptionMessage('is version ' . (ClassDefinition::MODEL_VERSION - 1));

        (new DocblocksCommand())->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );
    }

    public function testADocumentWithNoVersionIsRejected(): void
    {
        $this->write();
        file_put_contents($this->dir . '/left.json', json_encode([
            'repository'  => 'phalcon/cphalcon',
            'definitions' => [],
        ]));

        $this->expectException(IncompatibleDocument::class);
        $this->expectExceptionMessage('declares no version');

        (new DocblocksCommand())->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );
    }

    public function testAMissingDocumentIsRejected(): void
    {
        $this->expectException(MissingDocument::class);
        $this->expectExceptionMessage('no such model document');

        (new DocblocksCommand())->execute('/nowhere/a.json', '/nowhere/b.json', $this->dir . '/out.csv');
    }

    /**
     * A repository name with no owner is still a usable column heading, and
     * its first letter still selects that side.
     */
    public function testARepositoryWithNoOwnerIsItsOwnLabel(): void
    {
        file_put_contents($this->dir . '/left.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'repository'  => 'standalone',
            'definitions' => ['A' => $this->definition('Documented.', 'One wording.')],
        ]));
        file_put_contents($this->dir . '/right.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'repository'  => 'phalcon/phalcon',
            'definitions' => ['A' => $this->definition('Documented.', 'Another wording.')],
        ]));

        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        (new DocblocksCommand($stdout))->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );

        rewind($stdout);
        $this->assertStringContainsString('Put s or p', (string) stream_get_contents($stdout));
    }

    public function testASuccessfulRunReturnsZero(): void
    {
        $this->write();

        $status = (new DocblocksCommand($this->silent()))->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );

        $this->assertSame(0, $status);
    }

    /**
     * Blankness decides a row whichever side is blank, and only a row with
     * text on both sides is left for a human.
     */
    public function testEitherBlankSideDecidesItsOwnRow(): void
    {
        file_put_contents($this->dir . '/left.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'repository'  => 'phalcon/cphalcon',
            'definitions' => ['A' => $this->definition('Only on the left.', 'One wording.')],
        ]));
        file_put_contents($this->dir . '/right.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'repository'  => 'phalcon/phalcon',
            'definitions' => ['A' => $this->definition('', 'Another wording.')],
        ]));

        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        $status = (new DocblocksCommand($stdout))->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $this->dir . '/out.csv'
        );

        $this->assertSame(0, $status);

        $handle = fopen($this->dir . '/out.csv', 'rb');
        $this->assertIsResource($handle);
        fgetcsv($handle, 0, ',', '"', '');
        $row = fgetcsv($handle, 0, ',', '"', '');
        fclose($handle);

        $this->assertIsArray($row);

        // Right side blank, so the left label wins.
        $this->assertSame('documented', $row[2]);
        $this->assertSame('c', $row[5]);

        rewind($stdout);
        $output = (string) stream_get_contents($stdout);
        $this->assertStringContainsString('1 pre-filled', $output);
        $this->assertStringContainsString('1 to decide', $output);
    }

    public function testHeadersComeFromTheRepositoryNames(): void
    {
        $rows = $this->rows();

        $this->assertSame(
            ['fqcn', 'kind', 'member', 'cphalcon', 'phalcon', 'winner'],
            $rows[0]
        );
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

    public function testTwoTextsAreLeftForAHumanToDecide(): void
    {
        $rows = $this->rows();

        $this->assertSame('worded', $rows[2][2]);
        $this->assertSame('One wording.', $rows[2][3]);
        $this->assertSame('Another wording.', $rows[2][4]);
        $this->assertSame('', $rows[2][5]);
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

    /**
     * @return resource
     */
    private function silent()
    {
        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        return $stdout;
    }

    private function write(): void
    {
        file_put_contents($this->dir . '/left.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'language'    => 'zephir',
            'repository'  => 'phalcon/cphalcon',
            'definitions' => ['A' => $this->definition('', 'One wording.')],
        ]));

        file_put_contents($this->dir . '/right.json', json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'language'    => 'php',
            'repository'  => 'phalcon/phalcon',
            'definitions' => ['A' => $this->definition('Documented.', 'Another wording.')],
        ]));
    }
}
