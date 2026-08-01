<?php

/**
 * Reads the PHP implementation's sources, for comparing its API surface
 * against cphalcon's.
 *
 * Paths are absolute for the same reason as cphalcon.php: this configuration
 * lives here, not in that repository.
 */

declare(strict_types=1);

return [
    'language'   => 'php',
    'source'     => '/phalcon/src',
    'output'     => '/srv/tests/_output/parity',
    'repository' => 'phalcon/phalcon',
    'branch'     => 'master',
    'prefix'     => 'src',
    'extension'  => 'php',
];
