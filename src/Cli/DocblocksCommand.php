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

use Phalcon\Quill\Exceptions\InvalidConfiguration;
use Phalcon\Quill\Exceptions\WriteFailed;
use Phalcon\Quill\Parity\Descriptions;

use function count;
use function fclose;
use function file_get_contents;
use function fopen;
use function fputcsv;
use function fwrite;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function sprintf;
use function str_contains;
use function strrchr;
use function substr;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STDOUT;

/**
 * Writes the documentation disagreements between two implementations as CSV,
 * one row per difference, with an empty column for the decision.
 *
 * A spreadsheet is the right medium: hundreds of rows of two-sided prose where
 * a human picks a side. Filling the decision column is the whole workflow;
 * nothing here edits source.
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
        $left  = $this->load($leftPath);
        $right = $this->load($rightPath);

        $leftLabel  = $this->label($left['repository']);
        $rightLabel = $this->label($right['repository']);

        $rows = (new Descriptions())->compare($left['definitions'], $right['definitions']);

        $handle = @fopen($csvPath, 'wb');
        if ($handle === false) {
            throw new WriteFailed($csvPath);
        }

        // The escape parameter is explicit: its default changes in PHP 8.4,
        // and an escape character would corrupt docblocks containing one.
        fputcsv($handle, ['fqcn', 'kind', 'member', $leftLabel, $rightLabel, 'winner'], ',', '"', '');

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

    /**
     * The short repository name, which gives the column heading and - by its
     * first letter - the value that selects it.
     */
    private function label(string $repository): string
    {
        if (!str_contains($repository, '/')) {
            return $repository;
        }

        return substr((string) strrchr($repository, '/'), 1);
    }

    /**
     * @return array{repository: string, definitions: array<string, mixed>}
     */
    private function load(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidConfiguration("docblocks: no such model document '{$path}'");
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['definitions'] ?? null)) {
            throw new InvalidConfiguration("docblocks: '{$path}' is not a model document");
        }

        $repository = $decoded['repository'] ?? null;

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded['definitions'];

        return [
            'repository'  => is_string($repository) ? $repository : 'unknown',
            'definitions' => $definitions,
        ];
    }
}
