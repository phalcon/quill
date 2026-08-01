<?php

/**
 * Gate configuration. Phase 1 writes nothing into cphalcon - adoption is
 * follow-on work - so the paths are absolute rather than relative to a
 * scribe.php sitting in that repository.
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
];
