<?php

declare(strict_types=1);

namespace Phalcon\Twin;

/**
 * One declaration written twice, once per language.
 *
 * Its twin in ../php says the same thing in PHP. Both readers must produce the
 * same model for it - see ReaderEquivalenceTest. Everything here is a rule the
 * two readers have disagreed about at some point: escaped strings, spaced
 * unions in a docblock, a null default standing in for a nullable type, and
 * the visibility a keyword implies.
 */
class Subject
{
    public const QUOTE = '"';

    // Typed here, documented in the twin. Zephir can type a property too, but
    // the reader takes its answer from the docblock, so the two spellings have
    // to land on one model value.
    protected string|null $label = null;

    /**
     * @var array<string, mixed>
     */
    protected array $store = [];

    // Typed with no docblock, which is where the sources are heading. Zephir
    // reports `float` as `double`, so this also pins the keyword mapping.
    protected string $plain;

    protected float $ratio;

    protected bool $enabled;

    public function describe(?string $text = null): string
    {
        // Bodies are not part of the model, so the twins are free to differ here.
        return $text ?? '';
    }

    protected function hidden(int $count): void
    {
    }

    // The parser reports `float` as `double` for both the parameter and the
    // return, so this pins the keyword mapping on all three member kinds.
    public function scale(float $factor): float
    {
        return $factor;
    }
}
