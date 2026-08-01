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

namespace Phalcon\Scribe\Cli;

use Phalcon\Scribe\Exceptions\InvalidConfiguration;
use Phalcon\Scribe\Parity\Comparison;

use function array_slice;
use function count;
use function file_get_contents;
use function fwrite;
use function is_array;
use function is_file;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STDOUT;

/**
 * Compares two model documents and reports where the two implementations
 * diverge.
 *
 * Exits non-zero when anything differs, so it can gate a build once the two
 * are meant to be aligned.
 */
final class ParityCommand
{
    /**
     * @param resource $stdout
     */
    public function __construct(private $stdout = STDOUT)
    {
    }

    public function execute(string $leftPath, string $rightPath, int $limit = 25): int
    {
        $left   = $this->load($leftPath);
        $right  = $this->load($rightPath);
        $report = (new Comparison())->compare($left, $right);

        fwrite($this->stdout, sprintf(
            "left:   %s (%d)%sright:  %s (%d)%scommon: %d%s%s",
            $leftPath,
            $report['leftCount'],
            PHP_EOL,
            $rightPath,
            $report['rightCount'],
            PHP_EOL,
            $report['common'],
            PHP_EOL,
            PHP_EOL
        ));

        $this->section('Only on the left', $report['leftOnly'], $limit);
        $this->section('Only on the right', $report['rightOnly'], $limit);

        $differing = $report['differing'];
        fwrite($this->stdout, sprintf(
            'Differing members: %d of %d shared definitions%s',
            count($differing),
            $report['common'],
            PHP_EOL
        ));

        $shown = 0;
        foreach ($differing as $fqcn => $sections) {
            if ($shown++ >= $limit) {
                fwrite($this->stdout, sprintf('  ... and %d more%s', count($differing) - $limit, PHP_EOL));

                break;
            }

            fwrite($this->stdout, '  ' . $fqcn . PHP_EOL);
            foreach ($sections as $section => $diff) {
                fwrite($this->stdout, sprintf(
                    '    %-11s -%d +%d%s',
                    $section,
                    count($diff['left']),
                    count($diff['right']),
                    PHP_EOL
                ));
            }
        }

        $clean = $report['leftOnly'] === [] && $report['rightOnly'] === [] && $differing === [];

        return $clean ? 0 : 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidConfiguration("parity: no such model document '{$path}'");
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['definitions'] ?? null)) {
            throw new InvalidConfiguration("parity: '{$path}' is not a model document");
        }

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];

        return $definitions;
    }

    /**
     * @param list<string> $names
     */
    private function section(string $title, array $names, int $limit): void
    {
        fwrite($this->stdout, sprintf('%s: %d%s', $title, count($names), PHP_EOL));

        foreach (array_slice($names, 0, $limit) as $name) {
            fwrite($this->stdout, '  ' . $name . PHP_EOL);
        }

        if (count($names) > $limit) {
            fwrite($this->stdout, sprintf('  ... and %d more%s', count($names) - $limit, PHP_EOL));
        }

        fwrite($this->stdout, PHP_EOL);
    }
}
