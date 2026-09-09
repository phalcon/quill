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

use Phalcon\CodeQuality\PhpCsFixer\ConfigFactory;

$root = dirname(__DIR__);

return ConfigFactory::create(
    [
        $root . '/src',
        // Only the test code. `tests/Fixtures` holds parse targets, not code:
        // the fixer would strip their unused imports and sort their members,
        // which is the exact shape the reader tests assert. `tests/_baseline`
        // and `tests/_output` are generated and gitignored.
        $root . '/tests/Unit',
    ],
    $root . '/tests/_output/.php-cs-fixer.cache'
);
