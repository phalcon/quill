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
 * How a definition was declared: the keyword, plus the modifiers that qualify
 * it.
 *
 * `isAbstract` and `isFinal` are null for anything but a class, because the
 * concept does not apply - an interface is not "not abstract", the question is
 * meaningless. Storing false there would assert something untrue.
 *
 * Built only through the named constructors, so an abstract interface is
 * unrepresentable rather than merely rejected.
 */
final class Structure
{
    private function __construct(
        public readonly Keyword $keyword,
        public readonly ?bool $isAbstract,
        public readonly ?bool $isFinal,
    ) {
    }

    public static function classType(bool $isAbstract, bool $isFinal): self
    {
        return new self(Keyword::ClassType, $isAbstract, $isFinal);
    }

    public static function enum(): self
    {
        return new self(Keyword::Enum, null, null);
    }

    public static function interface(): self
    {
        return new self(Keyword::Interface, null, null);
    }

    /**
     * @return array{keyword: string, isAbstract: bool|null, isFinal: bool|null}
     */
    public function toArray(): array
    {
        return [
            'keyword'    => $this->keyword->value,
            'isAbstract' => $this->isAbstract,
            'isFinal'    => $this->isFinal,
        ];
    }

    public static function trait(): self
    {
        return new self(Keyword::Trait, null, null);
    }
}
