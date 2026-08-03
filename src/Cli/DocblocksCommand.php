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

use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Parity\Descriptions;
use Phalcon\Quill\Parity\ModelDocument;

use function count;
use function fclose;
use function fopen;
use function fputcsv;
use function fwrite;
use function sprintf;

use const PHP_EOL;
use const STDOUT;

/**
 * Writes the documentation disagreements between two implementations as CSV,
 * one row per difference, with an empty column for the decision.
 *
 * Filling that column is the whole workflow; nothing here edits source.
 */
final class DocblocksCommand
{
    /**
     * @param resource $stdout
     */
    public function __construct(private $stdout = STDOUT)
    {
    }

    public function execute(string $leftPath, string $rightPath, string $csvPath): int
    {
        $left  = ModelDocument::fromFile($leftPath);
        $right = ModelDocument::fromFile($rightPath);

        $leftLabel  = $left->label();
        $rightLabel = $right->label();

        $rows = (new Descriptions())->compare($left->definitions, $right->definitions);

        $handle = @fopen($csvPath, 'wb');
        if ($handle === false) {
            throw new WriteFailed($csvPath);
        }

        // The escape parameter is explicit: its default changes in PHP 8.4,
        // and an escape character would corrupt docblocks containing one.
        fputcsv(
            $handle,
            ['fqcn', 'kind', 'member', $leftLabel, $rightLabel, 'winner'],
            ',',
            '"',
            ''
        );

        $undecided = 0;
        foreach ($rows as $row) {
            // One side blank decides itself: the side with text wins.
            $winner = match (true) {
                $row['left'] === ''  => $rightLabel[0],
                $row['right'] === '' => $leftLabel[0],
                default              => '',
            };

            if ($winner === '') {
                $undecided++;
            }

            fputcsv(
                $handle,
                [
                    $row['fqcn'],
                    $row['kind'],
                    $row['member'],
                    $row['left'],
                    $row['right'],
                    $winner,
                ],
                ',',
                '"',
                ''
            );
        }

        fclose($handle);

        fwrite($this->stdout, sprintf(
            '%d differences: %d pre-filled where one side is blank, %d to decide.%s',
            count($rows),
            count($rows) - $undecided,
            $undecided,
            PHP_EOL
        ));
        fwrite($this->stdout, sprintf(
            'Put %s or %s in the winner column. Written to %s%s',
            $leftLabel[0],
            $rightLabel[0],
            $csvPath,
            PHP_EOL
        ));

        return 0;
    }
}
