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
 * A definition declared with a visibility keyword, which is what lets a
 * collection narrow itself to what a formatter shows.
 *
 * Methods and properties are the only two. A constant carries no visibility in
 * either language the readers handle, and a parameter has none to carry - so
 * the filtering belongs here rather than on NamedDefinition, where it would
 * offer collections a question their members cannot answer.
 *
 * PHP 8.1 cannot declare a property on an interface, so the name is stated for
 * the analyzer rather than enforced by the engine, the same way NamedDefinition
 * states `$name`.
 *
 * @property-read 'public'|'protected'|'private' $visibility
 */
interface VisibleDefinition extends NamedDefinition
{
}
