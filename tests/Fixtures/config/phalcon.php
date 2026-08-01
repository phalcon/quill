<?php

/**
 * Parity configuration for the PHP implementation, alongside cphalcon.php.
 * Absolute paths for the same reason: Phase 2 writes nothing into phalcon
 * until parity is clean.
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
