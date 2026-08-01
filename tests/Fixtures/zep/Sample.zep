namespace Phalcon\Sample;

use Phalcon\Support\Helper\Str\AbstractStr;

/**
 * Sample fixture.
 *
 * Exercises every branch the reader has to cover.
 *
 * @property string $unused
 */
trait Sample
{
    const DEFAULT_MODE = "strict";

    /**
     * @var array
     */
    protected store = [];

    private hidden = null;

    /**
     * Lowercases text.
     *
     * @param string text
     *
     * @return string
     */
    public static function toLower(string! text, bool trim = true) -> string
    {
        return text;
    }

    private function secret() -> void
    {
    }
}
