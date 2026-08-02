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
    const QUOTE = "\"";

    /**
     * @var string | null
     */
    protected label = null;

    /**
     * @var array<string, mixed>
     */
    protected store = [];

    // Typed with no docblock, which is where the sources are heading. Zephir
    // reports `float` as `double`, so this also pins the keyword mapping.
    protected string plain;

    protected float ratio;

    public function describe(string text = null) -> string
    {
        return text;
    }

    protected function hidden(int count) -> void
    {
    }
}
