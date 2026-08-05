namespace Phalcon\Sample;

use Phalcon\Sample\Support\Helper as Aliased;

/**
 * Every default-value kind the renderer has to name, plus the property and
 * return-type shapes that only appear on a class.
 */
abstract class Shapes extends Consumer implements Countable, Stringable
{
    const A_STRING = "text";
    const A_CHAR = 'a';
    const AN_INT = 1;
    const A_DOUBLE = 1.5;
    const A_BOOL = true;
    const A_NULL = null;
    const AN_EMPTY_ARRAY = [];
    const A_FILLED_ARRAY = [1, 2];
    const A_STATIC = self::AN_INT;
    const A_NEGATIVE = -3;

    /**
     * Carries getter and setter shortcuts.
     */
    protected label = "none" { get, set };

    public counter = 0;

    private flag = true;

    private rate = 1.5;

    private items = [1, 2];

    // Typed with neither a docblock nor a default, so the declaration is the
    // only thing that can name the type.
    protected array registry;

    // A declared type with a null default, which is the only union Zephir can
    // express. The PHP twin spells the same thing `?string`.
    protected string title = null;

    public function withCast(<Consumer> item) -> <Consumer>
    {
        return item;
    }

    public function collection() -> <Consumer[]>
    {
        return [];
    }

    public function untyped(var anything) -> var
    {
        return anything;
    }

    public function nullables(string text = null, <Consumer> item = null, var loose = null) -> var
    {
        return text;
    }

    public function notNullable(string text = "none", int count = 0) -> var
    {
        return text;
    }
}
