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

namespace Phalcon\Quill\Model;

/**
 * Where a definition lives: what it is called and which file it came from.
 *
 * Display names, page keys and anchors are deliberately absent - those are
 * output decisions and belong to whichever formatter needs them.
 */
final class Location
{
    public function __construct(
        public readonly string $fqcn,
        public readonly string $namespace,
        public readonly string $relPath,
    ) {
    }

    /**
     * @return array{fqcn: string, namespace: string, relPath: string}
     */
    public function toArray(): array
    {
        return [
            'fqcn'      => $this->fqcn,
            'namespace' => $this->namespace,
            'relPath'   => $this->relPath,
        ];
    }
}
