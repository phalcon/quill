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

namespace Phalcon\Scribe\Reader;

use Phalcon\Scribe\Contracts\Reader;
use Phalcon\Scribe\Exceptions\MissingDependency;
use Phalcon\Scribe\Exceptions\UnknownLanguage;
use Zephir\Parser\Parser;

use function class_exists;

/**
 * Resolves a configured `language` to its reader.
 */
final class ReaderFactory
{
    private const KNOWN = ['php', 'zephir'];

    public function create(string $language): Reader
    {
        return match ($language) {
            'php'    => new PhpReader(),
            'zephir' => $this->zephir(),
            default  => throw new UnknownLanguage($language, self::KNOWN),
        };
    }

    /**
     * phalcon/zephir is a suggest rather than a hard requirement, so a missing
     * package has to explain itself instead of surfacing as "class not found".
     */
    private function zephir(): Reader
    {
        if (!class_exists(Parser::class)) {
            throw new MissingDependency('phalcon/zephir', 'The zephir reader');
        }

        return new ZephirReader();
    }
}
