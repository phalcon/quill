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
 * What a definition connects to. All short names as written in the source;
 * Registry resolves them against the rest of the tree.
 */
final class Relations
{
    /**
     * @param list<string> $extends    a class uses index 0; an interface may list several
     * @param list<string> $implements
     * @param list<string> $traits     pulled in by the body, inverted by Registry::usedBy()
     */
    public function __construct(
        public readonly array $extends,
        public readonly array $implements,
        public readonly array $traits,
    ) {
    }

    /**
     * @return array{extends: list<string>, implements: list<string>, traits: list<string>}
     */
    public function toArray(): array
    {
        return [
            'extends'    => $this->extends,
            'implements' => $this->implements,
            'traits'     => $this->traits,
        ];
    }
}
