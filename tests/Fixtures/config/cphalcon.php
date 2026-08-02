<?php

/**
 * Reads cphalcon's Zephir sources, for the test that compares generated
 * Markdown against a known-good copy.
 *
 * Paths are absolute because this configuration lives here rather than in
 * that repository, and the comparison must not depend on how it is checked
 * out. See phalcon.php for the PHP implementation.
 */

declare(strict_types=1);

return [
    'language'   => 'zephir',
    'source'     => '/cphalcon/phalcon',
    'output'     => '/srv/tests/_output/gate',
    'repository' => 'phalcon/cphalcon',
    'branch'     => '5.0.x',
    'prefix'     => 'phalcon',
    'extension'  => 'zep',
    'namespace'  => 'Phalcon',
];
