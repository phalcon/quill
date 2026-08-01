namespace Phalcon\Sample;

/**
 * An interface extending two others - the only declaration whose `extends` is
 * a list rather than a single name.
 */
interface Contract extends Countable, Stringable
{
    public function describe() -> string;
}
