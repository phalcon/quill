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
 * A definition that answers to a name, which is what lets a collection order
 * itself without knowing what it holds.
 *
 * ClassDefinition is deliberately not one. It is identified by its Location
 * rather than a bare name, and it never lives in a collection - the Registry
 * keys it by FQCN instead.
 *
 * PHP 8.1 cannot declare a property on an interface, so the name is stated
 * for the analyzer rather than enforced by the engine. Every implementor
 * declares it as a readonly promoted property.
 *
 * @property-read string $name
 */
interface NamedDefinition extends Definition
{
}
