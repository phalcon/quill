namespace Phalcon\Sample;

/**
 * A final class, whose badge differs from every other declaration.
 */
final class Sealed
{
    const FROM_CONSTANT = PHP_EOL;

    /**
     * ```php
     * $sealed = new Sealed();
     * ```
     */
    public function opensWithAnExample()
    {
        return 1;
    }

    public function noDeclaredReturn()
    {
    }
}
