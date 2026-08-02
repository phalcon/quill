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

use Phalcon\Quill\Cli\ParityCommand;
use Phalcon\Quill\Exceptions\MalformedDocument;
use Phalcon\Quill\Exceptions\MissingDocument;
use Phalcon\Quill\Model\ClassDefinition;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;
use function file_exists;
use function file_put_contents;
use function fopen;
use function is_dir;
use function json_encode;
use function mkdir;
use function rewind;
use function sprintf;
use function stream_get_contents;
use function substr_count;
use function unlink;

final class ParityCommandTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = dirname(__DIR__, 2) . '/_output/parity';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (['left.json', 'right.json'] as $name) {
            if (file_exists($this->dir . '/' . $name)) {
                unlink($this->dir . '/' . $name);
            }
        }

        parent::tearDown();
    }

    public function testAMissingDocumentIsRejected(): void
    {
        $this->expectException(MissingDocument::class);
        $this->expectExceptionMessage('no such model document');

        (new ParityCommand())->execute('/nowhere/a.json', '/nowhere/b.json');
    }

    /**
     * The version is right but the definitions are absent - the one case that
     * still reaches the shape complaint now that the version gate runs first.
     */
    public function testAVersionedDocumentWithoutDefinitionsIsRejected(): void
    {
        file_put_contents($this->dir . '/left.json', json_encode([
            'version'    => ClassDefinition::MODEL_VERSION,
            'repository' => 'phalcon/cphalcon',
        ]));

        $this->expectException(MalformedDocument::class);
        $this->expectExceptionMessage('is not a model document');

        (new ParityCommand())->execute($this->dir . '/left.json', $this->dir . '/left.json');
    }

    public function testDefinitionsOnOneSideOnlyAreListed(): void
    {
        [$status, $output] = $this->compare(
            ['A' => $this->definition([]), 'OnlyLeft' => $this->definition([])],
            ['A' => $this->definition([]), 'OnlyRight' => $this->definition([])]
        );

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Only on the left: 1', $output);
        $this->assertStringContainsString('  OnlyLeft', $output);
        $this->assertStringContainsString('Only on the right: 1', $output);
        $this->assertStringContainsString('  OnlyRight', $output);
    }

    /**
     * Any one of the three kinds of difference is enough to fail the run;
     * they are not weighed against each other.
     */
    public function testEachKindOfDifferenceOnItsOwnFailsTheRun(): void
    {
        [$leftOnly] = $this->compare(
            ['A' => $this->definition([]), 'OnlyLeft' => $this->definition([])],
            ['A' => $this->definition([])]
        );
        $this->assertSame(1, $leftOnly);

        [$rightOnly] = $this->compare(
            ['A' => $this->definition([])],
            ['A' => $this->definition([]), 'OnlyRight' => $this->definition([])]
        );
        $this->assertSame(1, $rightOnly);

        [$members] = $this->compare(
            ['A' => $this->definition(['gone'])],
            ['A' => $this->definition([])]
        );
        $this->assertSame(1, $members);
    }

    public function testJsonThatIsNotEvenAnArrayIsRejected(): void
    {
        file_put_contents($this->dir . '/left.json', json_encode('a bare string'));

        $this->expectException(MalformedDocument::class);
        $this->expectExceptionMessage('is not a model document');

        (new ParityCommand())->execute($this->dir . '/left.json', $this->dir . '/left.json');
    }

    public function testMembersAreCountedPerSectionAsMissingAndExtra(): void
    {
        [$status, $output] = $this->compare(
            ['A' => $this->definition(['shared', 'goneOnTheRight'])],
            ['A' => $this->definition(['shared', 'addedOnTheRight'])]
        );

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Differing members: 1 of 1 shared definitions', $output);
        $this->assertStringContainsString('  A', $output);
        $this->assertStringContainsString('methods     -1 +1', $output);
    }

    /**
     * Exactly at the limit nothing was held back, so the remainder line has to
     * stay silent - the boundary is `<=`, not `<`, and "and 0 more" would be
     * both wrong and noise.
     */
    public function testACountEqualToTheLimitReportsNoRemainder(): void
    {
        $left = [];
        for ($i = 1; $i <= 3; $i++) {
            $left['Left' . $i] = $this->definition([]);
        }

        $this->write('left.json', $left);
        $this->write('right.json', []);

        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        (new ParityCommand($stdout))->execute($this->dir . '/left.json', $this->dir . '/right.json', 3);

        rewind($stdout);
        $output = (string) stream_get_contents($stdout);

        $this->assertStringContainsString('Only on the left: 3', $output);
        $this->assertStringContainsString('  Left3', $output);
        $this->assertStringNotContainsString('more', $output);
    }

    /**
     * Twenty-five is the default the command promises when no limit is given.
     */
    public function testTheDefaultLimitIsTwentyFive(): void
    {
        $left = [];
        for ($i = 1; $i <= 27; $i++) {
            $left['Left' . sprintf('%02d', $i)] = $this->definition([]);
        }

        $this->write('left.json', $left);
        $this->write('right.json', []);

        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        // No limit argument, so the default decides.
        (new ParityCommand($stdout))->execute($this->dir . '/left.json', $this->dir . '/right.json');

        rewind($stdout);
        $output = (string) stream_get_contents($stdout);

        $this->assertStringContainsString('Only on the left: 27', $output);
        $this->assertStringContainsString('... and 2 more', $output);
        $this->assertStringContainsString('  Left25' . PHP_EOL, $output);
        $this->assertStringNotContainsString('  Left26', $output);
    }

    /**
     * The limit holds back both the one-sided lists and the per-class
     * difference report, and each says how much it withheld.
     */
    public function testTheLimitReportsWhatItHeldBack(): void
    {
        $left  = [];
        $right = [];
        foreach (['A', 'B', 'C'] as $fqcn) {
            $left[$fqcn]  = $this->definition(['onlyOnTheLeft']);
            $right[$fqcn] = $this->definition(['onlyOnTheRight']);
        }

        $left['LeftOne']   = $this->definition([]);
        $left['LeftTwo']   = $this->definition([]);
        $left['LeftThree'] = $this->definition([]);

        [$status, $output] = $this->compare($left, $right, 1);

        $this->assertSame(1, $status);
        // Three left-only definitions, one shown.
        $this->assertStringContainsString('... and 2 more', $output);
        // Three differing classes, one shown, so the notice appears twice.
        $this->assertSame(2, substr_count($output, '... and 2 more'));
    }

    /**
     * The limit counts what is shown, so exactly that many appear before the
     * notice - one fewer or one more would be off by one.
     */
    public function testTheLimitShowsExactlyThatMany(): void
    {
        $left  = [];
        $right = [];
        foreach (['A', 'B', 'C'] as $fqcn) {
            $left[$fqcn]  = $this->definition(['gone']);
            $right[$fqcn] = $this->definition([]);
        }

        [, $output] = $this->compare($left, $right, 2);

        $this->assertStringContainsString('  A' . PHP_EOL, $output);
        $this->assertStringContainsString('  B' . PHP_EOL, $output);
        $this->assertStringNotContainsString('  C' . PHP_EOL, $output);
        $this->assertStringContainsString('... and 1 more', $output);
    }

    public function testTwoIdenticalDocumentsAreClean(): void
    {
        [$status, $output] = $this->compare(
            ['A' => $this->definition(['toLower'])],
            ['A' => $this->definition(['toLower'])]
        );

        $this->assertSame(0, $status);
        $this->assertStringContainsString('common: 1', $output);
        $this->assertStringContainsString('Only on the left: 0', $output);
        $this->assertStringContainsString('Only on the right: 0', $output);
        $this->assertStringContainsString('Differing members: 0 of 1 shared definitions', $output);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array{0: int, 1: string}
     */
    private function compare(array $left, array $right, int $limit = 25): array
    {
        $this->write('left.json', $left);
        $this->write('right.json', $right);

        $stdout = fopen('php://memory', 'rb+');
        $this->assertIsResource($stdout);

        $status = (new ParityCommand($stdout))->execute(
            $this->dir . '/left.json',
            $this->dir . '/right.json',
            $limit
        );

        rewind($stdout);

        return [$status, (string) stream_get_contents($stdout)];
    }

    /**
     * @param list<string> $methods
     *
     * @return array<string, mixed>
     */
    private function definition(array $methods): array
    {
        return [
            'description' => 'Same text.',
            'members'     => [
                'constants'  => [],
                'properties' => [],
                'methods'    => array_map(
                    static fn (string $name): array => ['name' => $name, 'description' => ''],
                    $methods
                ),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $definitions
     */
    private function write(string $name, array $definitions): void
    {
        file_put_contents($this->dir . '/' . $name, json_encode([
            'version'     => ClassDefinition::MODEL_VERSION,
            'language'    => 'zephir',
            'repository'  => 'phalcon/cphalcon',
            'definitions' => $definitions,
        ]));
    }
}
