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

namespace Phalcon\Scribe\Model;

/**
 * Anything a collection can hold. Each definition knows how to serialize
 * itself; the collection only knows how to ask.
 */
interface Definition
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
