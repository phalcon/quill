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

namespace Phalcon\Scribe\Formatter;

use Phalcon\Scribe\Config;
use Phalcon\Scribe\Contracts\Formatter;
use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\Registry;

use function json_encode;
use function ksort;
use function stripos;
use function strtolower;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Serializes the whole model to one JSON document.
 *
 * Written to be diffed: definitions are keyed by FQCN and sorted, and the
 * output is pretty-printed one value per line, so two runs over two source
 * trees can be compared with an ordinary text diff.
 */
final class JsonFormatter implements Formatter
{
    public const DOCUMENT = 'model';

    public function extension(): string
    {
        return 'json';
    }

    /**
     * The filter narrows by FQCN here rather than by page - there are no
     * pages in a JSON document.
     *
     * @return array<string, string>
     */
    public function format(Registry $registry, Config $config, string $filter = ''): array
    {
        $definitions = [];
        foreach ($registry->all() as $fqcn => $class) {
            if ($filter !== '' && stripos($fqcn, strtolower($filter)) === false) {
                continue;
            }

            $definitions[$fqcn] = $class->toArray();
        }

        ksort($definitions);

        $document = [
            'language'    => $config->language(),
            'repository'  => $config->repository(),
            'definitions' => $definitions,
        ];

        return [
            self::DOCUMENT => json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n",
        ];
    }
}
