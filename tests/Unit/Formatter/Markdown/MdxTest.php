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

namespace Phalcon\Quill\Tests\Unit\Formatter\Markdown;

use Phalcon\Quill\Formatter\Markdown\Mdx;
use PHPUnit\Framework\TestCase;

/**
 * The three shapes a docblock carries that MDX cannot read, and the two
 * places that must keep their own rules.
 *
 * Every example is a real line from the generated API pages: the phpDoc
 * inline tags of `Phalcon\Dispatcher`, the generic annotations of
 * `Phalcon\Auth`, and the PHP samples that open a brace in a fence.
 */
final class MdxTest extends TestCase
{
    /**
     * "Produce a `<a>` tag" in Phalcon\Html\Helper\Anchor. The tag is being
     * named, not opened, and MDX asks for a closing tag it never gets.
     */
    public function testABareTagWithNoCloserBecomesText(): void
    {
        $this->assertSame('Produce a &lt;a> tag', Mdx::safe('Produce a <a> tag'));
    }

    public function testABraceInProseIsEscaped(): void
    {
        $this->assertSame(
            'See \{@see handleException()\} for the rest.',
            Mdx::safe('See {@see handleException()} for the rest.')
        );
    }

    public function testABraceInsideAFenceIsLeftAlone(): void
    {
        $text = "Example:\n\n```php\nforeach (\$roles as \$role) {\n}\n```\n";

        $this->assertSame($text, Mdx::safe($text));
    }

    public function testABraceInsideAnInlineSpanIsLeftAlone(): void
    {
        $this->assertSame('Pass `{$name}` here.', Mdx::safe('Pass `{$name}` here.'));
    }

    public function testAKnownTagSurvives(): void
    {
        $this->assertSame(
            'One<br />two <code>x</code> three',
            Mdx::safe('One<br />two <code>x</code> three')
        );
    }

    public function testALooseLessThanIsEscaped(): void
    {
        $this->assertSame('when a &lt; b', Mdx::safe('when a < b'));
    }

    /**
     * The mirror of the bare opener. Escaping one and not the other would
     * leave an orphan that fails the compile just the same.
     */
    public function testACloserWithNoOpenerBecomesText(): void
    {
        $this->assertSame('Ends with &lt;/a> alone', Mdx::safe('Ends with </a> alone'));
    }

    public function testAMatchedTagPairSurvives(): void
    {
        $this->assertSame(
            'See <a href="x">this</a> link',
            Mdx::safe('See <a href="x">this</a> link')
        );
    }

    /**
     * A closer in the next paragraph does not match: MDX ends the element at
     * the paragraph, so both halves are bare and both are escaped.
     */
    public function testATagClosedInAnotherParagraphIsStillEscaped(): void
    {
        $this->assertSame(
            "Open &lt;a> here\n\nand &lt;/a> there",
            Mdx::safe("Open <a> here\n\nand </a> there")
        );
    }

    public function testAnUnknownTagBecomesText(): void
    {
        $this->assertSame(
            '@extends AbstractLocator&lt;Access>',
            Mdx::safe('@extends AbstractLocator<Access>')
        );
    }

    public function testProseWithNothingToEscapeIsUntouched(): void
    {
        $this->assertSame('Plain prose.', Mdx::safe('Plain prose.'));
    }

    public function testTheFenceAndTheProseAroundItAreBothHandled(): void
    {
        $source = "Use {@see x()}:\n\n```php\n\$a = [\"k\" => 1];\n```\n\nAnd <T> after.";
        $safe   = "Use \\{@see x()\\}:\n\n```php\n\$a = [\"k\" => 1];\n```\n\nAnd &lt;T> after.";

        $this->assertSame($safe, Mdx::safe($source));
    }
}
