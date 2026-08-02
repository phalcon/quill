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

namespace Phalcon\Quill\Formatter;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Formatter;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\Document;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Selection;

use function json_encode;
use function stripos;

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

    /**
     * None. A model document is data, and data is not styled.
     *
     * @return array<string, string>
     */
    public function assets(): array
    {
        return [];
    }

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
    public function format(Registry $registry, Config $config, Selection $selection): array
    {
        $definitions = $registry->definitions()->filter(
            static fn (ClassDefinition $class, string $fqcn): bool
                => $selection->matchesNamespace($fqcn)
                && ($selection->filter === '' || stripos($fqcn, $selection->filter) !== false)
        );

        $serialized = [];
        foreach ($definitions->sorted() as $fqcn => $class) {
            $serialized[$fqcn] = $class->toArray();
        }

        $document = Document::envelope(
            $config->language(),
            $config->repository(),
            $serialized
        );

        return [
            self::DOCUMENT => json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n",
        ];
    }
}
