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

namespace Phalcon\Quill\Parity;

use Phalcon\Quill\Model\Document;

use function is_array;
use function is_string;

/**
 * Reaches one section of members out of a decoded model document.
 *
 * A document read from disk is untrusted shape, so every step down to a member
 * has to be guarded. Both comparisons need the same guarded walk and differ
 * only in whether they keep the keys or the values.
 */
final class MemberIndex
{
    /**
     * Members of one section, keyed by name. Anything malformed is dropped
     * rather than reported - a document that cannot be read this far is not
     * a difference between implementations, it is a broken file.
     *
     * @return array<string, array<array-key, mixed>>
     */
    public static function of(mixed $definition, string $section): array
    {
        if (!is_array($definition) || !is_array($definition[Document::MEMBERS] ?? null)) {
            return [];
        }

        $members = $definition[Document::MEMBERS][$section] ?? null;
        if (!is_array($members)) {
            return [];
        }

        $indexed = [];
        foreach ($members as $member) {
            if (is_array($member) && is_string($member[Document::NAME] ?? null)) {
                $indexed[$member[Document::NAME]] = $member;
            }
        }

        return $indexed;
    }
}
