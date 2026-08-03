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

namespace Phalcon\Quill\Cli;

use Phalcon\Quill\Parity\Comparison;
use Phalcon\Quill\Parity\ModelDocument;

use function array_slice;
use function count;
use function fwrite;
use function sprintf;

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
        $left   = ModelDocument::fromFile($leftPath);
        $right  = ModelDocument::fromFile($rightPath);
        $report = (new Comparison())->compare($left->definitions, $right->definitions);

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
                $this->remainder(count($differing), $limit);

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

        $clean = $report['leftOnly'] === []
            && $report['rightOnly'] === []
            && $differing === [];

        return $clean ? 0 : 1;
    }

    /**
     * Says how many entries the limit held back. Silent when it held back
     * nothing, so a short list reads cleanly.
     */
    private function remainder(int $total, int $limit): void
    {
        if ($total <= $limit) {
            return;
        }

        fwrite(
            $this->stdout,
            sprintf('  ... and %d more%s', $total - $limit, PHP_EOL)
        );
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

        $this->remainder(count($names), $limit);

        fwrite($this->stdout, PHP_EOL);
    }
}
